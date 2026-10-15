from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from uuid import uuid4

from sifi.demo import DEMO_BASE, demo_context
from sifi.local import LocalSIFIPaths
from sifi.workflow import EvidenceLinkedBrief, LocalWorkflowRuntime, WorkflowBoundaryError, WorkflowSession, WorkflowStep, WorkflowStepSubmission
from sifi.workflow.service import GEOGRAPHY_DIMENSIONS


class DemoWorkflowError(ValueError):
    pass


@dataclass(frozen=True, slots=True)
class DemoWorkflowState:
    session: WorkflowSession
    brief: EvidenceLinkedBrief | None = None
    synthetic: bool = True


class LocalDemoWorkflow:
    def __init__(self, root: str | Path) -> None:
        self.paths = LocalSIFIPaths.from_root(root)
        self.paths.initialize()

    def start(self, *, analyst_id: str, now: datetime | None = None) -> DemoWorkflowState:
        analyst = analyst_id.strip()
        if not analyst or len(analyst) > 80:
            raise DemoWorkflowError("analyst_id must contain 1 to 80 characters")
        recorded = now or datetime.now(timezone.utc)
        session_id = "LOCAL-DEMO-" + uuid4().hex.upper()
        context = demo_context(analyst)
        with LocalWorkflowRuntime(
            self.paths.database, workspace_root=self.paths.root
        ) as runtime:
            try:
                session = runtime.start_session(
                    session_id=session_id,
                    task_id=context.task_id,
                    analyst_id=analyst,
                    issue_id=context.issue.issue_id,
                    analytical_cutoff=context.analytical_cutoff,
                    started_at=recorded,
                )
            except WorkflowBoundaryError as exc:
                raise DemoWorkflowError(str(exc)) from exc
        return DemoWorkflowState(session)

    def advance(
        self,
        *,
        session_id: str,
        analyst_id: str,
        note: str = "",
        now: datetime | None = None,
    ) -> DemoWorkflowState:
        note = note.strip()
        if len(note) > 1000:
            raise DemoWorkflowError("note cannot exceed 1000 characters")
        recorded = now or datetime.now(timezone.utc)
        with LocalWorkflowRuntime(
            self.paths.database, workspace_root=self.paths.root
        ) as runtime:
            session = runtime.latest(session_id)
            if session is None:
                raise DemoWorkflowError("workflow session not found")
            if session.analyst_id != analyst_id:
                raise DemoWorkflowError("analyst does not own this workflow session")
            if session.is_complete or session.next_step is None:
                raise DemoWorkflowError("workflow session is already complete")
            context = demo_context(session.analyst_id)
            submission = self._submission(session.next_step, note)
            try:
                session = runtime.submit_step(
                    session_id,
                    submission,
                    analyst_id=session.analyst_id,
                    recorded_at=recorded,
                )
            except WorkflowBoundaryError as exc:
                raise DemoWorkflowError(str(exc)) from exc
            brief = None
            if session.is_complete:
                brief = EvidenceLinkedBrief(
                    brief_id="BRIEF-" + session.session_id,
                    issue_id=context.issue.issue_id,
                    summary=(
                        "Hasil latihan analitis lokal berbasis skenario sintetis. "
                        "Bukan klaim faktual dan bukan otorisasi tindakan produksi."
                    ),
                    evidence_refs=context.issue.evidence.supporting_evidence_ids,
                    created_at=recorded,
                    analytical_cutoff=context.analytical_cutoff,
                )
                runtime.record_brief(brief, recorded_at=recorded)
        return DemoWorkflowState(session, brief)

    @staticmethod
    def _submission(step: WorkflowStep, note: str) -> WorkflowStepSubmission:
        suffix = ("operator_note=" + note,) if note else ()
        common = {"step": step}
        if step == WorkflowStep.COVERAGE_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("synthetic coverage limitation inspected", *suffix))
        if step == WorkflowStep.ADMITTED_SIGNAL_REVIEW:
            return WorkflowStepSubmission(
                **common, findings=("synthetic admitted evidence inspected", *suffix),
                evidence_refs=("DEMO-EV-001", "DEMO-EV-002"),
                production_evidence_eligible=True,
            )
        if step == WorkflowStep.PERSISTENT_ISSUE_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("synthetic issue identity inspected", *suffix))
        if step == WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION:
            return WorkflowStepSubmission(
                **common, findings=("separate evidence categories inspected", *suffix),
                evidence_refs=("DEMO-EV-001", "DEMO-EV-002", "DEMO-EV-C01", "DEMO-EV-X01", "DEMO-EV-Z101"),
            )
        if step == WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION:
            return WorkflowStepSubmission(
                **common, findings=("all geography dimensions inspected", *suffix),
                inspected_dimensions=tuple(sorted(GEOGRAPHY_DIMENSIONS)),
            )
        if step == WorkflowStep.UNCERTAINTY_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("synthetic uncertainty and gaps inspected", *suffix))
        if step == WorkflowStep.TRAJECTORY_INSPECTION:
            from sifi.domain import TrajectoryDimension
            return WorkflowStepSubmission(
                **common, findings=("all trajectory dimensions inspected", *suffix),
                inspected_dimensions=tuple(item.value for item in TrajectoryDimension),
            )
        if step == WorkflowStep.AUTHORIZED_CORRECTION:
            return WorkflowStepSubmission(
                **common, findings=("no correction requested in synthetic exercise", *suffix),
                correction_requested=False, correction_authority_verified=True,
            )
        if step == WorkflowStep.CANDIDATE_PRIORITIZATION:
            return WorkflowStepSubmission(**common, findings=("bounded synthetic materiality considered", *suffix))
        if step == WorkflowStep.EVIDENCE_LINKED_BRIEF:
            return WorkflowStepSubmission(
                **common, findings=("synthetic brief assertions reviewed", *suffix),
                evidence_refs=("DEMO-EV-001", "DEMO-EV-002"),
                brief_assertions_supported=True,
            )
        if step == WorkflowStep.HISTORICAL_RETRIEVAL:
            return WorkflowStepSubmission(
                **common, findings=("point-in-time state inspected", *suffix),
                historical_cutoff=DEMO_BASE,
            )
        raise DemoWorkflowError("unsupported workflow step")
