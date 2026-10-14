from __future__ import annotations

from dataclasses import dataclass, replace
from datetime import datetime
from enum import StrEnum

from sifi.domain import TrajectoryDimension
from sifi.domain.versioning import require_aware_datetime


class WorkflowBoundaryError(ValueError):
    pass


class WorkflowOrderError(WorkflowBoundaryError):
    pass


class WorkflowStep(StrEnum):
    COVERAGE_INSPECTION = "WF-01"
    ADMITTED_SIGNAL_REVIEW = "WF-02"
    PERSISTENT_ISSUE_INSPECTION = "WF-03"
    EVIDENCE_CONTRADICTION_INSPECTION = "WF-04"
    GEOGRAPHIC_RELEVANCE_INSPECTION = "WF-05"
    UNCERTAINTY_INSPECTION = "WF-06"
    TRAJECTORY_INSPECTION = "WF-07"
    AUTHORIZED_CORRECTION = "WF-08"
    CANDIDATE_PRIORITIZATION = "WF-09"
    EVIDENCE_LINKED_BRIEF = "WF-10"
    HISTORICAL_RETRIEVAL = "WF-11"


WORKFLOW_ORDER = tuple(WorkflowStep)
GEOGRAPHY_DIMENSIONS = {
    "source_geography",
    "event_geography",
    "affected_geography",
    "policy_jurisdiction",
    "relevance_geography",
}
TRAJECTORY_DIMENSIONS = {item.value for item in TrajectoryDimension}


@dataclass(frozen=True, slots=True)
class WorkflowStepSubmission:
    step: WorkflowStep
    findings: tuple[str, ...]
    evidence_refs: tuple[str, ...] = ()
    production_evidence_eligible: bool | None = None
    inspected_dimensions: tuple[str, ...] = ()
    correction_requested: bool = False
    correction_authority_verified: bool | None = None
    brief_assertions_supported: bool | None = None
    historical_cutoff: datetime | None = None

    def __post_init__(self) -> None:
        if not self.findings or any(not item.strip() for item in self.findings):
            raise WorkflowBoundaryError("step findings must be explicit and nonblank")
        if len(self.evidence_refs) != len(set(self.evidence_refs)):
            raise WorkflowBoundaryError("evidence references must be unique")
        if len(self.inspected_dimensions) != len(set(self.inspected_dimensions)):
            raise WorkflowBoundaryError("inspected dimensions must be unique")
        if self.historical_cutoff is not None:
            require_aware_datetime(self.historical_cutoff, "historical_cutoff")


@dataclass(frozen=True, slots=True)
class WorkflowAuditEvent:
    sequence: int
    step: WorkflowStep
    analyst_id: str
    recorded_at: datetime
    findings: tuple[str, ...]
    evidence_refs: tuple[str, ...]


@dataclass(frozen=True, slots=True)
class WorkflowSession:
    session_id: str
    task_id: str
    analyst_id: str
    issue_id: str
    analytical_cutoff: datetime
    started_at: datetime
    completed_steps: tuple[WorkflowStep, ...] = ()
    audit_events: tuple[WorkflowAuditEvent, ...] = ()
    completed_at: datetime | None = None
    analytical_only: bool = True
    representative_user_validation_performed: bool = False
    production_action_authorized: bool = False

    @property
    def next_step(self) -> WorkflowStep | None:
        if len(self.completed_steps) == len(WORKFLOW_ORDER):
            return None
        return WORKFLOW_ORDER[len(self.completed_steps)]

    @property
    def is_complete(self) -> bool:
        return self.completed_at is not None


class BoundedAnalystWorkflow:
    """Ordered analytical workflow mechanics; not a user-validation claim."""

    @staticmethod
    def start(
        *, session_id: str, task_id: str, analyst_id: str, issue_id: str,
        analytical_cutoff: datetime, started_at: datetime,
    ) -> WorkflowSession:
        for name, value in (
            ("session_id", session_id), ("task_id", task_id),
            ("analyst_id", analyst_id), ("issue_id", issue_id),
        ):
            if not isinstance(value, str) or not value.strip():
                raise WorkflowBoundaryError(f"{name} must be nonblank")
        require_aware_datetime(analytical_cutoff, "analytical_cutoff")
        require_aware_datetime(started_at, "started_at")
        if analytical_cutoff > started_at:
            raise WorkflowBoundaryError("analytical cutoff cannot follow session start")
        return WorkflowSession(
            session_id=session_id, task_id=task_id, analyst_id=analyst_id,
            issue_id=issue_id, analytical_cutoff=analytical_cutoff,
            started_at=started_at,
        )

    def submit(
        self, session: WorkflowSession, submission: WorkflowStepSubmission,
        *, analyst_id: str, recorded_at: datetime,
    ) -> WorkflowSession:
        require_aware_datetime(recorded_at, "recorded_at")
        if session.is_complete:
            raise WorkflowBoundaryError("completed session is immutable")
        if analyst_id != session.analyst_id:
            raise WorkflowBoundaryError("only the assigned analyst may submit")
        if recorded_at < session.started_at:
            raise WorkflowBoundaryError("audit event cannot precede session")
        if submission.step != session.next_step:
            raise WorkflowOrderError(
                f"expected {session.next_step.value}, received {submission.step.value}"
            )

        self._check_step(submission, session)
        event = WorkflowAuditEvent(
            sequence=len(session.audit_events) + 1,
            step=submission.step,
            analyst_id=analyst_id,
            recorded_at=recorded_at,
            findings=submission.findings,
            evidence_refs=submission.evidence_refs,
        )
        steps = session.completed_steps + (submission.step,)
        events = session.audit_events + (event,)
        return replace(
            session,
            completed_steps=steps,
            audit_events=events,
            completed_at=recorded_at if len(steps) == len(WORKFLOW_ORDER) else None,
        )

    @staticmethod
    def _check_step(submission: WorkflowStepSubmission, session: WorkflowSession) -> None:
        step = submission.step
        if step == WorkflowStep.ADMITTED_SIGNAL_REVIEW:
            if submission.production_evidence_eligible is not True:
                raise WorkflowBoundaryError("non-eligible evidence cannot enter admitted review")
            if not submission.evidence_refs:
                raise WorkflowBoundaryError("admitted review requires evidence references")
        elif step == WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION:
            if not submission.evidence_refs:
                raise WorkflowBoundaryError("evidence inspection requires references")
        elif step == WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION:
            if set(submission.inspected_dimensions) != GEOGRAPHY_DIMENSIONS:
                raise WorkflowBoundaryError("all geography dimensions must remain explicit")
        elif step == WorkflowStep.TRAJECTORY_INSPECTION:
            if set(submission.inspected_dimensions) != TRAJECTORY_DIMENSIONS:
                raise WorkflowBoundaryError("all trajectory dimensions must remain explicit")
        elif step == WorkflowStep.AUTHORIZED_CORRECTION:
            if submission.correction_requested and submission.correction_authority_verified is not True:
                raise WorkflowBoundaryError("correction requires verified human authority")
        elif step == WorkflowStep.EVIDENCE_LINKED_BRIEF:
            if submission.brief_assertions_supported is not True or not submission.evidence_refs:
                raise WorkflowBoundaryError("brief requires supported evidence-linked assertions")
        elif step == WorkflowStep.HISTORICAL_RETRIEVAL:
            if submission.historical_cutoff is None:
                raise WorkflowBoundaryError("historical retrieval requires explicit cutoff")
            if submission.historical_cutoff > session.analytical_cutoff:
                raise WorkflowBoundaryError("historical retrieval cannot exceed analytical cutoff")
