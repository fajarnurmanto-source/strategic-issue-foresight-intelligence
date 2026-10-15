from __future__ import annotations

from datetime import datetime

from sifi.domain.versioning import VersionedSnapshot, require_aware_datetime
from sifi.persistence import SQLiteVersionStore

from .model import EvidenceLinkCheck, Intervention, InterventionCategory, StudyMode, StudyObservation


OBJECT_TYPE = "CB22_STUDY_OBSERVATION"


class StudyObservationVersionError(ValueError):
    pass


def _payload(item: StudyObservation) -> dict:
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
        "completed_stage_ids": list(item.completed_stage_ids),
        "task_completed": item.task_completed,
        "completed_at": item.completed_at.isoformat() if item.completed_at else None,
        "noncompletion_reason_code": item.noncompletion_reason_code,
        "interventions": [
            {
                "category": intervention.category.value,
                "reason_code": intervention.reason_code,
                "duration_seconds": intervention.duration_seconds,
                "routine_developer_help": intervention.routine_developer_help,
            }
            for intervention in item.interventions
        ],
        "forbidden_output_observed": item.forbidden_output_observed,
        "evidence_link_check": item.evidence_link_check.value,
        "application_failure_codes": list(item.application_failure_codes),
        "blocker_codes": list(item.blocker_codes),
        "direct_identifier_collected": False,
    }


def _decode(payload: dict) -> StudyObservation:
    return StudyObservation(
        observation_id=payload["observation_id"],
        protocol_version=payload["protocol_version"],
        mode=StudyMode(payload["mode"]),
        participant_code=payload["participant_code"],
        screening_record_id=payload["screening_record_id"],
        consent_record_id=payload["consent_record_id"],
        consent_confirmed_at=datetime.fromisoformat(payload["consent_confirmed_at"]),
        governance_authorization_id=payload["governance_authorization_id"],
        workflow_session_id=payload["workflow_session_id"],
        task_package_version=payload["task_package_version"],
        representative_profile_met=payload["representative_profile_met"],
        participant_is_sifi_developer=payload["participant_is_sifi_developer"],
        started_at=datetime.fromisoformat(payload["started_at"]),
        completed_stage_ids=tuple(payload["completed_stage_ids"]),
        task_completed=payload["task_completed"],
        completed_at=datetime.fromisoformat(payload["completed_at"]) if payload["completed_at"] else None,
        noncompletion_reason_code=payload.get("noncompletion_reason_code"),
        interventions=tuple(
            Intervention(
                category=InterventionCategory(value["category"]),
                reason_code=value["reason_code"],
                duration_seconds=value["duration_seconds"],
                routine_developer_help=value["routine_developer_help"],
            ) for value in payload["interventions"]
        ),
        forbidden_output_observed=payload["forbidden_output_observed"],
        evidence_link_check=EvidenceLinkCheck(payload["evidence_link_check"]),
        application_failure_codes=tuple(payload["application_failure_codes"]),
        blocker_codes=tuple(payload["blocker_codes"]),
        direct_identifier_collected=payload["direct_identifier_collected"],
    )


class StudyObservationRepository:
    def __init__(self, store: SQLiteVersionStore) -> None:
        self._store = store

    def append(self, item: StudyObservation, *, recorded_at: datetime) -> StudyObservation:
        require_aware_datetime(recorded_at, "recorded_at")
        latest = self._store.latest(OBJECT_TYPE, item.observation_id)
        if latest is not None:
            previous = _decode(dict(latest.payload))
            immutable = (
                "observation_id", "protocol_version", "mode", "participant_code",
                "screening_record_id", "consent_record_id", "consent_confirmed_at",
                "governance_authorization_id", "workflow_session_id",
                "task_package_version", "representative_profile_met",
                "participant_is_sifi_developer", "started_at",
            )
            if any(getattr(previous, field) != getattr(item, field) for field in immutable):
                raise StudyObservationVersionError("study identity and gate fields are immutable")
            if item.completed_stage_ids[:len(previous.completed_stage_ids)] != previous.completed_stage_ids:
                raise StudyObservationVersionError("completed study stages cannot regress or change")
            if len(item.completed_stage_ids) > len(previous.completed_stage_ids) + 1:
                raise StudyObservationVersionError("only one study stage may be appended per version")
            if item.interventions[:len(previous.interventions)] != previous.interventions:
                raise StudyObservationVersionError("intervention history is append-only")
            if item.application_failure_codes[:len(previous.application_failure_codes)] != previous.application_failure_codes:
                raise StudyObservationVersionError("application failure history is append-only")
            if item.blocker_codes[:len(previous.blocker_codes)] != previous.blocker_codes:
                raise StudyObservationVersionError("blocker history is append-only")
            if previous.forbidden_output_observed and not item.forbidden_output_observed:
                raise StudyObservationVersionError("forbidden-output observation cannot be cleared")
            if previous.task_completed and not item.task_completed:
                raise StudyObservationVersionError("task completion cannot be reversed")
            if previous.completed_at is not None and item != previous:
                raise StudyObservationVersionError("ended observation is immutable")
        version = 1 if latest is None else latest.version + 1
        self._store.append(VersionedSnapshot(
            object_type=OBJECT_TYPE,
            object_id=item.observation_id,
            version=version,
            effective_at=recorded_at,
            recorded_at=recorded_at,
            payload=_payload(item),
        ))
        return item

    def latest(self, observation_id: str) -> StudyObservation | None:
        snapshot = self._store.latest(OBJECT_TYPE, observation_id)
        return _decode(dict(snapshot.payload)) if snapshot else None

    def list_latest(self, *, limit: int = 1000) -> tuple[StudyObservation, ...]:
        return tuple(_decode(dict(item.payload)) for item in self._store.list_latest(OBJECT_TYPE, limit=limit))
