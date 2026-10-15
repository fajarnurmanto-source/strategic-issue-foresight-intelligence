from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain import ReviewDisposition
from sifi.domain.versioning import require_aware_datetime
from sifi.issues import IssueObject
from sifi.review import ProtectedTransition, ReviewRecord
from sifi.rights import AdmissionDecision, RightsPolicyEvaluator

from .brief import EvidenceLinkedBrief
from .runtime import LocalWorkflowRuntime
from .service import (
    GEOGRAPHY_DIMENSIONS,
    WorkflowSession,
    WorkflowStep,
    WorkflowStepSubmission,
)


class OrchestrationBoundaryError(ValueError):
    pass


@dataclass(frozen=True, slots=True)
class AnalystCaseContext:
    task_id: str
    analyst_id: str
    issue: IssueObject
    issue_version: int
    coverage_profile_id: str
    coverage_limitations: tuple[str, ...]
    evidence_decisions: tuple[tuple[str, AdmissionDecision], ...]
    analytical_cutoff: datetime

    def __post_init__(self) -> None:
        require_aware_datetime(self.analytical_cutoff, "analytical_cutoff")
        if self.issue_version < 1:
            raise OrchestrationBoundaryError("issue_version must be positive")
        for name in ("task_id", "analyst_id", "coverage_profile_id"):
            if not getattr(self, name).strip():
                raise OrchestrationBoundaryError(f"{name} must be nonblank")
        ids = tuple(item[0] for item in self.evidence_decisions)
        if len(ids) != len(set(ids)):
            raise OrchestrationBoundaryError("evidence decisions must be unique")

    @property
    def decision_map(self) -> dict[str, AdmissionDecision]:
        return dict(self.evidence_decisions)


class LocalDomainOrchestrator:
    """Bind local domain state into the bounded analyst workflow."""

    def __init__(self, runtime: LocalWorkflowRuntime) -> None:
        self._runtime = runtime

    def start_inspection(
        self,
        *,
        session_id: str,
        context: AnalystCaseContext,
        started_at: datetime,
        recorded_at: datetime,
    ) -> WorkflowSession:
        require_aware_datetime(recorded_at, "recorded_at")
        issue = context.issue
        if issue.geography.coverage_profile_id != context.coverage_profile_id:
            raise OrchestrationBoundaryError("coverage profile does not match Issue")

        decisions = context.decision_map
        supporting = issue.evidence.supporting_evidence_ids
        missing = tuple(item for item in supporting if item not in decisions)
        if missing:
            raise OrchestrationBoundaryError("supporting evidence lacks rights decision")
        admitted = tuple(
            item for item in supporting if decisions[item].production_eligible
        )
        if set(admitted) != set(supporting):
            raise OrchestrationBoundaryError(
                "all supporting evidence must be production eligible"
            )

        evidence_categories = (
            issue.evidence.supporting_evidence_ids
            + issue.evidence.contradictory_evidence_ids
            + issue.evidence.context_only_evidence_ids
            + issue.evidence.excluded_production_evidence_ids
        )
        submissions = (
            WorkflowStepSubmission(
                step=WorkflowStep.COVERAGE_INSPECTION,
                findings=(
                    "coverage_profile=" + context.coverage_profile_id,
                    *("limitation=" + item for item in context.coverage_limitations),
                ),
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.ADMITTED_SIGNAL_REVIEW,
                findings=("production-eligible evidence inspected",),
                evidence_refs=admitted,
                production_evidence_eligible=True,
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.PERSISTENT_ISSUE_INSPECTION,
                findings=(f"issue_version={context.issue_version}",),
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION,
                findings=("separate evidence categories inspected",),
                evidence_refs=evidence_categories,
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION,
                findings=("geography dimensions preserved",),
                inspected_dimensions=tuple(sorted(GEOGRAPHY_DIMENSIONS)),
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.UNCERTAINTY_INSPECTION,
                findings=(
                    *("uncertainty=" + item for item in issue.uncertainty_notes),
                    *("gap=" + item for item in issue.evidence_gaps),
                    *("limitation=" + item for item in issue.known_limitations),
                ) or ("no uncertainty or gap declared",),
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.TRAJECTORY_INSPECTION,
                findings=("trajectory dimensions preserved",),
                inspected_dimensions=tuple(item.dimension.value for item in issue.trajectories),
            ),
        )
        with self._runtime.transaction():
            session = self._runtime.start_session(
                session_id=session_id,
                task_id=context.task_id,
                analyst_id=context.analyst_id,
                issue_id=issue.issue_id,
                analytical_cutoff=context.analytical_cutoff,
                started_at=started_at,
            )
            for submission in submissions:
                session = self._runtime.submit_step(
                    session_id,
                    submission,
                    analyst_id=context.analyst_id,
                    recorded_at=recorded_at,
                )
        return session

    def complete_case(
        self,
        *,
        session_id: str,
        context: AnalystCaseContext,
        recorded_at: datetime,
        correction_review: ReviewRecord | None,
        priority_rationale: str,
        attention_only_basis: bool,
        brief_id: str,
        brief_summary: str,
        brief_evidence_refs: tuple[str, ...],
        historical_cutoff: datetime,
    ) -> tuple[WorkflowSession, EvidenceLinkedBrief]:
        require_aware_datetime(historical_cutoff, "historical_cutoff")
        if historical_cutoff > context.analytical_cutoff:
            raise OrchestrationBoundaryError(
                "historical cutoff cannot exceed analytical cutoff"
            )
        if attention_only_basis:
            raise OrchestrationBoundaryError("attention alone cannot establish priority")
        if not priority_rationale.strip():
            raise OrchestrationBoundaryError("priority rationale must be explicit")
        if self._runtime.latest_brief(brief_id) is not None:
            raise OrchestrationBoundaryError("brief_id already exists")

        correction_requested = correction_review is not None
        correction_authorized = not correction_requested
        if correction_review is not None:
            correction_authorized = (
                correction_review.proposed_action
                == ProtectedTransition.MATERIAL_CORRECTION
                and correction_review.final_disposition == ReviewDisposition("RD-01")
                and correction_review.analytical_only
                and not correction_review.rights_state_mutated
            )
            if not correction_authorized:
                raise OrchestrationBoundaryError(
                    "correction review lacks approved analytical authority"
                )

        decisions = context.decision_map
        try:
            supporting_decisions = tuple(decisions[item] for item in brief_evidence_refs)
        except KeyError as exc:
            raise OrchestrationBoundaryError("brief evidence lacks rights decision") from exc
        output = RightsPolicyEvaluator().evaluate_derived_output(supporting_decisions)
        if not output.production_eligible:
            raise OrchestrationBoundaryError("brief cannot launder ineligible evidence")

        steps = (
            WorkflowStepSubmission(
                step=WorkflowStep.AUTHORIZED_CORRECTION,
                findings=("correction boundary evaluated",),
                correction_requested=correction_requested,
                correction_authority_verified=correction_authorized,
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.CANDIDATE_PRIORITIZATION,
                findings=(priority_rationale,),
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.EVIDENCE_LINKED_BRIEF,
                findings=("evidence-linked analytical brief created",),
                evidence_refs=brief_evidence_refs,
                brief_assertions_supported=True,
            ),
            WorkflowStepSubmission(
                step=WorkflowStep.HISTORICAL_RETRIEVAL,
                findings=("historical state retrieved",),
                historical_cutoff=historical_cutoff,
            ),
        )
        session = self._runtime.latest(session_id)
        if session is None or session.issue_id != context.issue.issue_id:
            raise OrchestrationBoundaryError("workflow session and case do not match")
        with self._runtime.transaction():
            for submission in steps:
                session = self._runtime.submit_step(
                    session_id, submission, analyst_id=context.analyst_id,
                    recorded_at=recorded_at,
                )
            brief = EvidenceLinkedBrief(
                brief_id=brief_id,
                issue_id=context.issue.issue_id,
                summary=brief_summary,
                evidence_refs=brief_evidence_refs,
                created_at=recorded_at,
                analytical_cutoff=context.analytical_cutoff,
            )
            self._runtime.record_brief(brief, recorded_at=recorded_at)
        return session, brief
