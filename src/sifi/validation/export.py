from __future__ import annotations

import hashlib
import json
import os
from datetime import datetime, timezone
from pathlib import Path
from tempfile import NamedTemporaryFile

from sifi.local import LocalSIFIPaths

from .adjudication import assess_cb22
from .gate import TASK_PACKAGE_SHA256, cb22_gate_status
from .model import CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION, StudyObservation


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
    payload = {
        "record_type": "SIFI_CB22_ADJUDICATION_BUNDLE",
        "record_version": "1.0",
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "protocol_version": CB22_PROTOCOL_VERSION,
        "task_package_version": CB22_TASK_PACKAGE_VERSION,
        "task_package_sha256": TASK_PACKAGE_SHA256,
        "gate": {"ready_for_counted_sessions": gate.ready_for_counted_sessions,
                 "reasons": gate.reasons},
        "assessment": {
            "result": assessment.result.value,
            "analyzable_participants": assessment.analyzable_participants,
            "completed_participants": assessment.completed_participants,
            "routine_help_participants": assessment.routine_help_participants,
            "forbidden_output_participants": assessment.forbidden_output_participants,
            "evidence_link_failures": assessment.evidence_link_failures,
            "reasons": assessment.reasons,
        },
        "observations": [_observation(item) for item in items],
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
