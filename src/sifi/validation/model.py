from __future__ import annotations

import re
from dataclasses import dataclass
from datetime import datetime
from enum import Enum

from sifi.domain.versioning import require_aware_datetime


CB22_PROTOCOL_VERSION = "CB22-v1.0"
CB22_TASK_PACKAGE_VERSION = "CB22-TASK-v1.0"
WORKFLOW_STAGES = tuple(f"WF-{number:02d}" for number in range(1, 12))
_CODE = re.compile(r"^[A-Z0-9][A-Z0-9_-]{2,63}$")


class StudyMode(str, Enum):
    REHEARSAL = "REHEARSAL"
    COUNTED = "COUNTED"


class InterventionCategory(str, Enum):
    SAFETY = "SAFETY"
    TECHNICAL_FAILURE = "TECHNICAL_FAILURE"
    NAVIGATION = "NAVIGATION"
    ANALYTICAL = "ANALYTICAL"
    OTHER = "OTHER"


class EvidenceLinkCheck(str, Enum):
    NOT_ASSESSED = "NOT_ASSESSED"
    PASS = "PASS"
    FAIL = "FAIL"


def _require_code(value: str, field: str) -> None:
    if not _CODE.fullmatch(value):
        raise ValueError(f"{field} must be a 3-64 character pseudonymous code")


@dataclass(frozen=True, slots=True)
class Intervention:
    category: InterventionCategory
    reason_code: str
    duration_seconds: int
    routine_developer_help: bool

    def __post_init__(self) -> None:
        _require_code(self.reason_code, "reason_code")
        if self.duration_seconds < 0 or self.duration_seconds > 14_400:
            raise ValueError("duration_seconds must be between 0 and 14400")


@dataclass(frozen=True, slots=True)
class StudyObservation:
    observation_id: str
    protocol_version: str
    mode: StudyMode
    participant_code: str
    screening_record_id: str
    consent_record_id: str
    consent_confirmed_at: datetime
    governance_authorization_id: str
    workflow_session_id: str
    task_package_version: str
    representative_profile_met: bool
    participant_is_sifi_developer: bool
    started_at: datetime
    completed_stage_ids: tuple[str, ...] = ()
    task_completed: bool = False
    completed_at: datetime | None = None
    noncompletion_reason_code: str | None = None
    interventions: tuple[Intervention, ...] = ()
    forbidden_output_observed: bool = False
    evidence_link_check: EvidenceLinkCheck = EvidenceLinkCheck.NOT_ASSESSED
    application_failure_codes: tuple[str, ...] = ()
    blocker_codes: tuple[str, ...] = ()
    direct_identifier_collected: bool = False

    def __post_init__(self) -> None:
        for field in (
            "observation_id", "participant_code", "screening_record_id",
            "consent_record_id", "governance_authorization_id",
            "workflow_session_id",
        ):
            _require_code(getattr(self, field), field)
        require_aware_datetime(self.consent_confirmed_at, "consent_confirmed_at")
        require_aware_datetime(self.started_at, "started_at")
        if self.completed_at is not None:
            require_aware_datetime(self.completed_at, "completed_at")
        if self.direct_identifier_collected:
            raise ValueError("direct identifiers must not be stored in SIFI")
        if self.consent_confirmed_at > self.started_at:
            raise ValueError("consent must be confirmed before the study starts")
        if self.mode is StudyMode.COUNTED:
            if self.protocol_version != CB22_PROTOCOL_VERSION:
                raise ValueError("counted sessions require the frozen CB22 protocol")
            if self.task_package_version != CB22_TASK_PACKAGE_VERSION:
                raise ValueError("counted sessions require the frozen task package")
            if not self.representative_profile_met:
                raise ValueError("counted participant must meet the representative profile")
            if self.participant_is_sifi_developer:
                raise ValueError("SIFI developers cannot enter counted sessions")
        stages = self.completed_stage_ids
        if len(stages) != len(set(stages)) or any(s not in WORKFLOW_STAGES for s in stages):
            raise ValueError("completed stages must be unique WF-01 through WF-11")
        if stages != WORKFLOW_STAGES[: len(stages)]:
            raise ValueError("completed stages must be recorded in workflow order")
        if self.task_completed:
            if stages != WORKFLOW_STAGES or self.completed_at is None:
                raise ValueError("completed task requires all stages and completed_at")
            if self.noncompletion_reason_code is not None:
                raise ValueError("completed task cannot have noncompletion_reason_code")
            if self.evidence_link_check is EvidenceLinkCheck.NOT_ASSESSED:
                raise ValueError("completed task requires an evidence-link assessment")
        elif self.completed_at is not None:
            if self.noncompletion_reason_code is None:
                raise ValueError("ended incomplete task requires noncompletion_reason_code")
            _require_code(self.noncompletion_reason_code, "noncompletion_reason_code")
        elif self.noncompletion_reason_code is not None:
            raise ValueError("active task cannot have noncompletion_reason_code")
        if self.completed_at is not None and self.completed_at < self.started_at:
            raise ValueError("completed_at cannot precede started_at")
        for code in self.application_failure_codes + self.blocker_codes:
            _require_code(code, "failure/blocker code")

    @property
    def routine_help_received(self) -> bool:
        return any(item.routine_developer_help for item in self.interventions)
