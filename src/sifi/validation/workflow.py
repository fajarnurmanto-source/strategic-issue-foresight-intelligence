from __future__ import annotations

from dataclasses import dataclass, replace
from datetime import datetime, timezone
from pathlib import Path
from uuid import uuid4

from sifi.domain import TrajectoryDimension
from sifi.local import LocalSIFIPaths
from sifi.domain.versioning import VersionedSnapshot
from sifi.workflow import EvidenceLinkedBrief, LocalWorkflowRuntime, WorkflowStep, WorkflowStepSubmission
from sifi.workflow.service import GEOGRAPHY_DIMENSIONS, WorkflowBoundaryError

from .model import (
    CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION, EvidenceLinkCheck,
    Intervention, StudyMode, StudyObservation,
)
from .repository import StudyObservationRepository
from .gate import (
    GATE_LOCK_OBJECT_ID, GATE_LOCK_OBJECT_TYPE, cb22_gate_status,
    current_gate_configuration,
)
from .study_task import require_accepted_answer


STUDY_CUTOFF = datetime(2026, 1, 1, 8, 0, tzinfo=timezone.utc)
STUDY_ISSUE_ID = "CB22-ISSUE-001"
EVIDENCE_REFS = ("CB22-EV-001", "CB22-EV-002")


class CB22WorkflowError(ValueError):
    pass


@dataclass(frozen=True, slots=True)
class CB22WorkflowState:
    observation: StudyObservation
    session: object
    brief: EvidenceLinkedBrief | None = None


class LocalCB22Workflow:
    """Counted/rehearsal workflow linked atomically to an observation record."""

    def __init__(self, root: str | Path) -> None:
        self.paths = LocalSIFIPaths.from_root(root)
        self.paths.initialize()

    def start(
        self, *, participant_code: str, screening_record_id: str,
        consent_record_id: str, consent_confirmed_at: datetime,
        governance_authorization_id: str, representative_profile_met: bool,
        participant_is_sifi_developer: bool, mode: StudyMode,
        now: datetime | None = None,
    ) -> CB22WorkflowState:
        recorded = now or datetime.now(timezone.utc)
        if mode is StudyMode.COUNTED:
            gate = cb22_gate_status(self.paths.root)
            if not gate.ready_for_counted_sessions:
                raise CB22WorkflowError(
                    "counted study gate is closed: " + ", ".join(gate.reasons)
                )
            gate_configuration = current_gate_configuration(self.paths.root)
        else:
            gate_configuration = None
        suffix = uuid4().hex.upper()
        session_id = "CB22-SES-" + suffix
        observation = StudyObservation(
            observation_id="CB22-OBS-" + suffix,
            protocol_version=CB22_PROTOCOL_VERSION,
            mode=mode,
            participant_code=participant_code,
            screening_record_id=screening_record_id,
            consent_record_id=consent_record_id,
            consent_confirmed_at=consent_confirmed_at,
            governance_authorization_id=governance_authorization_id,
            workflow_session_id=session_id,
            task_package_version=CB22_TASK_PACKAGE_VERSION,
            representative_profile_met=representative_profile_met,
            participant_is_sifi_developer=participant_is_sifi_developer,
            started_at=recorded,
        )
        with LocalWorkflowRuntime(self.paths.database, workspace_root=self.paths.root) as runtime:
            repository = StudyObservationRepository(runtime.version_store)
            with runtime.transaction():
                if mode is StudyMode.COUNTED and any(
                    item.mode is StudyMode.COUNTED
                    and item.participant_code == participant_code
                    for item in repository.list_latest()
                ):
                    raise CB22WorkflowError(
                        "participant_code already has a counted CB22 session"
                    )
                if gate_configuration is not None:
                    lock = runtime.version_store.latest(
                        GATE_LOCK_OBJECT_TYPE, GATE_LOCK_OBJECT_ID
                    )
                    if lock is None:
                        runtime.version_store.append(VersionedSnapshot(
                            object_type=GATE_LOCK_OBJECT_TYPE,
                            object_id=GATE_LOCK_OBJECT_ID, version=1,
                            effective_at=recorded, recorded_at=recorded,
                            payload=gate_configuration,
                        ))
                    elif lock.payload.get("configuration_sha256") != gate_configuration["configuration_sha256"]:
                        raise CB22WorkflowError(
                            "current gate configuration differs from the counted-study lock"
                        )
                session = runtime.start_session(
                    session_id=session_id,
                    task_id=CB22_TASK_PACKAGE_VERSION,
                    analyst_id=participant_code,
                    issue_id=STUDY_ISSUE_ID,
                    analytical_cutoff=STUDY_CUTOFF,
                    started_at=recorded,
                )
                repository.append(observation, recorded_at=recorded)
        return CB22WorkflowState(observation, session)

    def advance(self, *, observation_id: str, participant_code: str,
                answer_code: str,
                now: datetime | None = None) -> CB22WorkflowState:
        recorded = now or datetime.now(timezone.utc)
        with LocalWorkflowRuntime(self.paths.database, workspace_root=self.paths.root) as runtime:
            repository = StudyObservationRepository(runtime.version_store)
            observation = repository.latest(observation_id)
            if observation is None:
                raise CB22WorkflowError("study observation not found")
            if observation.participant_code != participant_code:
                raise CB22WorkflowError("participant does not own this study session")
            if observation.completed_at is not None:
                raise CB22WorkflowError("study observation is already ended")
            if observation.mode is StudyMode.COUNTED:
                gate = cb22_gate_status(self.paths.root)
                if not gate.ready_for_counted_sessions:
                    raise CB22WorkflowError(
                        "counted study gate closed during session: "
                        + ", ".join(gate.reasons)
                    )
                configuration = current_gate_configuration(self.paths.root)
                lock = runtime.version_store.latest(
                    GATE_LOCK_OBJECT_TYPE, GATE_LOCK_OBJECT_ID
                )
                if (
                    lock is None
                    or lock.payload.get("configuration_sha256")
                    != configuration["configuration_sha256"]
                ):
                    raise CB22WorkflowError(
                        "counted-study gate lock is missing or inconsistent"
                    )
            session = runtime.latest(observation.workflow_session_id)
            if session is None or session.next_step is None:
                raise CB22WorkflowError("study workflow is missing or already complete")
            try:
                answer = require_accepted_answer(session.next_step, answer_code)
            except ValueError as exc:
                retried = replace(
                    observation,
                    blocker_codes=observation.blocker_codes
                    + (f"BOUNDARY-RETRY-{session.next_step.value}",),
                )
                repository.append(retried, recorded_at=recorded)
                raise CB22WorkflowError(str(exc)) from exc
            try:
                with runtime.transaction():
                    session = runtime.submit_step(
                        session.session_id, self._submission(session.next_step, answer),
                        analyst_id=participant_code, recorded_at=recorded,
                    )
                    brief = None
                    updated = replace(
                        observation,
                        completed_stage_ids=tuple(step.value for step in session.completed_steps),
                    )
                    if session.is_complete:
                        brief = EvidenceLinkedBrief(
                            brief_id="BRIEF-" + session.session_id,
                            issue_id=STUDY_ISSUE_ID,
                            summary=("Bounded CB22 synthetic-case analytical brief. "
                                     "Not a real-world factual claim or production authorization."),
                            evidence_refs=EVIDENCE_REFS,
                            created_at=recorded,
                            analytical_cutoff=STUDY_CUTOFF,
                        )
                        runtime.record_brief(brief, recorded_at=recorded)
                        updated = replace(
                            updated, task_completed=True, completed_at=recorded,
                            evidence_link_check=EvidenceLinkCheck.PASS,
                        )
                    repository.append(updated, recorded_at=recorded)
            except WorkflowBoundaryError as exc:
                raise CB22WorkflowError(str(exc)) from exc
        return CB22WorkflowState(updated, session, brief)

    def record_intervention(self, *, observation_id: str, intervention: Intervention,
                            recorded_at: datetime | None = None) -> StudyObservation:
        when = recorded_at or datetime.now(timezone.utc)
        with LocalWorkflowRuntime(self.paths.database, workspace_root=self.paths.root) as runtime:
            repository = StudyObservationRepository(runtime.version_store)
            current = repository.latest(observation_id)
            if current is None:
                raise CB22WorkflowError("study observation not found")
            if current.completed_at is not None:
                raise CB22WorkflowError("study observation is already ended")
            updated = replace(current, interventions=current.interventions + (intervention,))
            repository.append(updated, recorded_at=when)
            return updated

    def stop_incomplete(self, *, observation_id: str, participant_code: str,
                        reason_code: str, recorded_at: datetime | None = None) -> StudyObservation:
        when = recorded_at or datetime.now(timezone.utc)
        with LocalWorkflowRuntime(self.paths.database, workspace_root=self.paths.root) as runtime:
            repository = StudyObservationRepository(runtime.version_store)
            current = repository.latest(observation_id)
            if current is None or current.participant_code != participant_code:
                raise CB22WorkflowError("study observation not found for participant")
            if current.completed_at is not None:
                raise CB22WorkflowError("study observation is already ended")
            updated = replace(current, completed_at=when, noncompletion_reason_code=reason_code)
            repository.append(updated, recorded_at=when)
            return updated

    def record_incident(self, *, observation_id: str, code: str,
                        kind: str, recorded_at: datetime | None = None) -> StudyObservation:
        when = recorded_at or datetime.now(timezone.utc)
        with LocalWorkflowRuntime(self.paths.database, workspace_root=self.paths.root) as runtime:
            repository = StudyObservationRepository(runtime.version_store)
            current = repository.latest(observation_id)
            if current is None:
                raise CB22WorkflowError("study observation not found")
            if current.completed_at is not None:
                raise CB22WorkflowError("study observation is already ended")
            normalized = kind.strip().upper()
            if normalized == "FORBIDDEN_OUTPUT":
                updated = replace(current, forbidden_output_observed=True)
            elif normalized == "APPLICATION_FAILURE":
                updated = replace(current, application_failure_codes=current.application_failure_codes + (code,))
            elif normalized == "BLOCKER":
                updated = replace(current, blocker_codes=current.blocker_codes + (code,))
            else:
                raise CB22WorkflowError("unsupported incident kind")
            repository.append(updated, recorded_at=when)
            return updated

    @staticmethod
    def _submission(step: WorkflowStep, answer_code: str) -> WorkflowStepSubmission:
        common = {"step": step}
        answer_finding = f"participant_selection={answer_code}"
        if step == WorkflowStep.COVERAGE_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("fixed synthetic coverage inspected", answer_finding))
        if step == WorkflowStep.ADMITTED_SIGNAL_REVIEW:
            return WorkflowStepSubmission(**common, findings=("admitted evidence inspected", answer_finding), evidence_refs=EVIDENCE_REFS, production_evidence_eligible=True)
        if step == WorkflowStep.PERSISTENT_ISSUE_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("fixed issue identity inspected", answer_finding))
        if step == WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("evidence classes inspected", answer_finding), evidence_refs=EVIDENCE_REFS + ("CB22-EV-C01", "CB22-EV-X01", "CB22-EV-Z101"))
        if step == WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("geography dimensions inspected", answer_finding), inspected_dimensions=tuple(sorted(GEOGRAPHY_DIMENSIONS)))
        if step == WorkflowStep.UNCERTAINTY_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("uncertainty and gaps inspected", answer_finding))
        if step == WorkflowStep.TRAJECTORY_INSPECTION:
            return WorkflowStepSubmission(**common, findings=("trajectory dimensions inspected", answer_finding), inspected_dimensions=tuple(item.value for item in TrajectoryDimension))
        if step == WorkflowStep.AUTHORIZED_CORRECTION:
            return WorkflowStepSubmission(**common, findings=("correction boundary inspected", answer_finding), correction_requested=False, correction_authority_verified=True)
        if step == WorkflowStep.EVIDENCE_LINKED_BRIEF:
            return WorkflowStepSubmission(**common, findings=("evidence-linked assertions inspected", answer_finding), evidence_refs=EVIDENCE_REFS, brief_assertions_supported=True)
        if step == WorkflowStep.HISTORICAL_RETRIEVAL:
            return WorkflowStepSubmission(**common, findings=("point-in-time state inspected", answer_finding), historical_cutoff=STUDY_CUTOFF)
        if step == WorkflowStep.CANDIDATE_PRIORITIZATION:
            return WorkflowStepSubmission(**common, findings=("bounded materiality inspected", answer_finding))
        raise CB22WorkflowError("unsupported workflow step")
