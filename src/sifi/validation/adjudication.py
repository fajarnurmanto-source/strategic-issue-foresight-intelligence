from __future__ import annotations

from dataclasses import dataclass
from enum import Enum

from .model import EvidenceLinkCheck, StudyMode, StudyObservation


MINIMUM_ANALYZABLE_PARTICIPANTS = 5
MINIMUM_COMPLETION_RATE = 0.80
MAXIMUM_ROUTINE_HELP_RATE = 0.20


class CB22Result(str, Enum):
    NOT_READY = "NOT_READY"
    IN_PROGRESS = "IN_PROGRESS"
    PASS = "PASS"
    FAIL = "FAIL"
    INCONCLUSIVE = "INCONCLUSIVE"


@dataclass(frozen=True, slots=True)
class CB22Assessment:
    result: CB22Result
    analyzable_participants: int
    completed_participants: int
    routine_help_participants: int
    forbidden_output_participants: int
    evidence_link_failures: int
    reasons: tuple[str, ...]


def assess_cb22(items: tuple[StudyObservation, ...]) -> CB22Assessment:
    started = tuple(item for item in items if item.mode is StudyMode.COUNTED)
    participant_codes = tuple(item.participant_code for item in started)
    if len(participant_codes) != len(set(participant_codes)):
        return CB22Assessment(
            result=CB22Result.INCONCLUSIVE,
            analyzable_participants=0,
            completed_participants=0,
            routine_help_participants=0,
            forbidden_output_participants=0,
            evidence_link_failures=0,
            reasons=("DUPLICATE_COUNTED_PARTICIPANT_CODE",),
        )
    counted = tuple(item for item in started if item.completed_at is not None)
    completed = sum(item.task_completed for item in counted)
    helped = sum(item.routine_help_received for item in counted)
    forbidden = sum(item.forbidden_output_observed for item in counted)
    evidence_failures = sum(
        item.task_completed and item.evidence_link_check is not EvidenceLinkCheck.PASS
        for item in counted
    )
    reasons: list[str] = []
    if not started:
        result = CB22Result.NOT_READY
        reasons.append("NO_COUNTED_PARTICIPANTS")
    elif len(counted) < len(started):
        result = CB22Result.IN_PROGRESS
        reasons.append("COUNTED_SESSIONS_STILL_ACTIVE")
    elif len(counted) < MINIMUM_ANALYZABLE_PARTICIPANTS:
        result = CB22Result.IN_PROGRESS
        reasons.append("MINIMUM_SAMPLE_NOT_REACHED")
    else:
        completion_rate = completed / len(counted)
        help_rate = helped / len(counted)
        if forbidden:
            reasons.append("FORBIDDEN_OUTPUT_OBSERVED")
        if evidence_failures:
            reasons.append("EVIDENCE_LINK_FAILURE")
        if completion_rate < MINIMUM_COMPLETION_RATE:
            reasons.append("COMPLETION_RATE_BELOW_THRESHOLD")
        if help_rate > MAXIMUM_ROUTINE_HELP_RATE:
            reasons.append("ROUTINE_HELP_RATE_ABOVE_THRESHOLD")
        result = CB22Result.FAIL if reasons else CB22Result.PASS
    return CB22Assessment(
        result=result,
        analyzable_participants=len(counted),
        completed_participants=completed,
        routine_help_participants=helped,
        forbidden_output_participants=forbidden,
        evidence_link_failures=evidence_failures,
        reasons=tuple(reasons),
    )
