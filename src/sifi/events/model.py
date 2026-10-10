"""Event-candidate representation.

This is intentionally not an Issue Object.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from enum import StrEnum

from sifi.domain.versioning import require_aware_datetime


class EventContradictionState(StrEnum):
    NONE_OBSERVED = "NONE_OBSERVED"
    CONTRADICTION_PRESENT = "CONTRADICTION_PRESENT"


@dataclass(frozen=True, slots=True)
class EventCandidate:
    event_id: str

    event_type: str | None
    event_start: datetime | None
    event_end: datetime | None

    event_geography: tuple[str, ...]
    involved_actor_classes: tuple[str, ...]
    affected_systems: tuple[str, ...]
    material_circumstances: tuple[str, ...]

    supporting_claim_ids: tuple[str, ...]
    supporting_evidence_ids: tuple[str, ...]
    contradicting_evidence_ids: tuple[str, ...]
    production_support_evidence_ids: tuple[str, ...]

    contradiction_state: EventContradictionState
    review_state: str | None = None

    candidate_only: bool = True

    def __post_init__(self) -> None:
        if not self.event_id.strip():
            raise ValueError(
                "event_id must not be blank"
            )

        for name, value in [
            ("event_start", self.event_start),
            ("event_end", self.event_end),
        ]:
            if value is not None:
                require_aware_datetime(
                    value,
                    name,
                )

        if (
            self.event_start is not None
            and self.event_end is not None
            and self.event_end < self.event_start
        ):
            raise ValueError(
                "event_end must not precede event_start"
            )

    @property
    def production_support_present(self) -> bool:
        return bool(
            self.production_support_evidence_ids
        )
