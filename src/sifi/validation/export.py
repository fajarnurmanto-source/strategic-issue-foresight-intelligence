from __future__ import annotations

import hashlib
import json
import os
from datetime import datetime, timezone
from pathlib import Path
from tempfile import NamedTemporaryFile

from sifi.local import LocalSIFIPaths
from sifi.persistence import SQLiteVersionStore
from sifi.workflow import LocalWorkflowRuntime

from .adjudication import assess_cb22
from .gate import (
    GATE_LOCK_OBJECT_ID, GATE_LOCK_OBJECT_TYPE, TASK_PACKAGE_SHA256,
    cb22_gate_status,
)
from .model import CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION, StudyObservation
from .workflow import EVIDENCE_REFS, STUDY_ISSUE_ID


def _observation(item: StudyObservation) -> dict:
    return {
        "observation_id": item.observation_id,
        "protocol_version": item.protocol_version,
        "mode": item.mode.value,
        "participant_code": item.participant_code,
        "screening_record_id": item.screening_record_id,
        "consent_record_id": item.consent_record_id,
        "consent_confirmed_at": item.consent_confirmed_at.isoformat(),
        "governance_authorization_id": item.governance_authorization_id,
        "workflow_session_id": item.workflow_session_id,
        "task_package_version": item.task_package_version,
        "representative_profile_met": item.representative_profile_met,
        "participant_is_sifi_developer": item.participant_is_sifi_developer,
        "started_at": item.started_at.isoformat(),
        "completed_at": item.completed_at.isoformat() if item.completed_at else None,
        "completed_stage_ids": item.completed_stage_ids,
        "task_completed": item.task_completed,
        "noncompletion_reason_code": item.noncompletion_reason_code,
        "interventions": [
            {"category": value.category.value, "reason_code": value.reason_code,
             "duration_seconds": value.duration_seconds,
             "routine_developer_help": value.routine_developer_help}
            for value in item.interventions
        ],
        "forbidden_output_observed": item.forbidden_output_observed,
        "evidence_link_check": item.evidence_link_check.value,
        "application_failure_codes": item.application_failure_codes,
        "blocker_codes": item.blocker_codes,
        "direct_identifier_collected": False,
    }


def export_adjudication_bundle(root: str | Path, items: tuple[StudyObservation, ...]) -> Path:
    paths = LocalSIFIPaths.from_root(root)
    paths.initialize()
    assessment = assess_cb22(items)
    gate = cb22_gate_status(paths.root)
    gate_lock = None
    linkage_failures: list[str] = []
    workflow_evidence: list[dict] = []
    if paths.database.is_file():
        with SQLiteVersionStore(paths.database) as store:
            snapshot = store.latest(GATE_LOCK_OBJECT_TYPE, GATE_LOCK_OBJECT_ID)
            if snapshot is not None:
                gate_lock = dict(snapshot.payload)
        with LocalWorkflowRuntime(paths.database, workspace_root=paths.root) as runtime:
            for item in items:
                session = runtime.latest(item.workflow_session_id)
                if session is None:
                    linkage_failures.append(f"{item.observation_id}:MISSING_WORKFLOW_SESSION")
                    continue
                failures = []
                if session.task_id != CB22_TASK_PACKAGE_VERSION:
                    failures.append("TASK_VERSION_MISMATCH")
                if session.analyst_id != item.participant_code:
                    failures.append("PARTICIPANT_MISMATCH")
                if session.issue_id != STUDY_ISSUE_ID:
                    failures.append("ISSUE_MISMATCH")
                if tuple(step.value for step in session.completed_steps) != item.completed_stage_ids:
                    failures.append("STAGE_MISMATCH")
                brief = runtime.latest_brief("BRIEF-" + session.session_id)
                if item.task_completed:
                    if not session.is_complete:
                        failures.append("WORKFLOW_NOT_COMPLETE")
                    if brief is None:
                        failures.append("MISSING_BRIEF")
                    elif tuple(brief.evidence_refs) != EVIDENCE_REFS:
                        failures.append("BRIEF_EVIDENCE_MISMATCH")
                if failures:
                    linkage_failures.extend(f"{item.observation_id}:{value}" for value in failures)
                workflow_evidence.append({
                    "observation_id": item.observation_id,
                    "session_id": session.session_id,
                    "task_id": session.task_id,
                    "participant_code": session.analyst_id,
                    "issue_id": session.issue_id,
                    "completed_steps": tuple(step.value for step in session.completed_steps),
                    "audit_events": [
                        {"sequence": event.sequence, "step": event.step.value,
                         "recorded_at": event.recorded_at.isoformat(),
                         "findings": event.findings, "evidence_refs": event.evidence_refs}
                        for event in session.audit_events
                    ],
                    "brief": None if brief is None else {
                        "brief_id": brief.brief_id, "evidence_refs": brief.evidence_refs,
                        "analytical_only": brief.analytical_only,
                        "production_action_authorized": brief.production_action_authorized,
                    },
                    "linkage_failures": failures,
                })
    elif items:
        linkage_failures.extend(
            f"{item.observation_id}:MISSING_WORKFLOW_DATABASE" for item in items
        )
    assessment_result = assessment.result.value
    assessment_reasons = list(assessment.reasons)
    if linkage_failures and assessment_result in {"PASS", "FAIL"}:
        assessment_result = "INCONCLUSIVE"
        assessment_reasons.append("WORKFLOW_LINKAGE_INTEGRITY_FAILURE")
    payload = {
        "record_type": "SIFI_CB22_ADJUDICATION_BUNDLE",
        "record_version": "1.0",
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "protocol_version": CB22_PROTOCOL_VERSION,
        "task_package_version": CB22_TASK_PACKAGE_VERSION,
        "task_package_sha256": TASK_PACKAGE_SHA256,
        "gate": {"ready_for_counted_sessions": gate.ready_for_counted_sessions,
                 "reasons": gate.reasons},
        "counted_study_gate_lock": gate_lock,
        "assessment": {
            "result": assessment_result,
            "analyzable_participants": assessment.analyzable_participants,
            "completed_participants": assessment.completed_participants,
            "routine_help_participants": assessment.routine_help_participants,
            "forbidden_output_participants": assessment.forbidden_output_participants,
            "evidence_link_failures": assessment.evidence_link_failures,
            "reasons": assessment_reasons,
            "completion_rate": assessment.completion_rate,
            "routine_help_rate": assessment.routine_help_rate,
            "median_duration_seconds": assessment.median_duration_seconds,
        },
        "observations": [_observation(item) for item in items],
        "workflow_evidence": workflow_evidence,
        "workflow_linkage_failures": linkage_failures,
        "claim_boundary": "AUTOMATED_RESULT_IS_PROVISIONAL_UNTIL_TWO_ROLE_ADJUDICATION",
    }
    encoded = (json.dumps(payload, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    digest = hashlib.sha256(encoded).hexdigest().upper()
    destination = paths.require_inside(paths.exports / f"cb22-adjudication-{digest[:12]}.json")
    with NamedTemporaryFile(dir=paths.exports, prefix=".cb22-", suffix=".tmp", delete=False) as handle:
        temporary = Path(handle.name)
        handle.write(encoded)
        handle.flush()
        os.fsync(handle.fileno())
    temporary.replace(destination)
    return destination
