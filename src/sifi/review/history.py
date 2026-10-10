"""Minimal append-only review history for S1.5B component verification.

This is not production persistence. It exists to verify:
- non-destructive review history;
- sequential versions;
- point-in-time reconstruction semantics.

Integration with persistent storage remains separately adjudicable.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from .model import (
    ReviewRecord,
    require_aware,
)


@dataclass(frozen=True, slots=True)
class ReviewVersion:
    version: int
    recorded_at: datetime
    record: ReviewRecord

    def __post_init__(self) -> None:
        if self.version < 1:
            raise ValueError(
                "version must be >= 1"
            )

        require_aware(
            "recorded_at",
            self.recorded_at,
        )


class ReviewHistory:
    """Append-only in-memory component history."""

    def __init__(self) -> None:
        self._data: dict[
            str,
            list[ReviewVersion],
        ] = {}

    def append(
        self,
        *,
        review_id: str,
        version: int,
        recorded_at: datetime,
        record: ReviewRecord,
    ) -> None:

        if review_id != record.review_id:
            raise ValueError(
                "review_id must match ReviewRecord"
            )

        existing = self._data.setdefault(
            review_id,
            [],
        )

        expected = len(existing) + 1

        if version != expected:
            raise ValueError(
                f"expected version {expected}, "
                f"received {version}"
            )

        item = ReviewVersion(
            version=version,
            recorded_at=recorded_at,
            record=record,
        )

        existing.append(item)

    def history(
        self,
        review_id: str,
    ) -> tuple[ReviewVersion, ...]:
        return tuple(
            self._data.get(
                review_id,
                (),
            )
        )

    def latest(
        self,
        review_id: str,
    ) -> ReviewVersion | None:
        items = self._data.get(review_id)

        if not items:
            return None

        return items[-1]

    def as_of(
        self,
        review_id: str,
        cutoff: datetime,
    ) -> ReviewVersion | None:

        require_aware(
            "cutoff",
            cutoff,
        )

        eligible = [
            item
            for item in self._data.get(
                review_id,
                ()
            )
            if item.recorded_at <= cutoff
        ]

        if not eligible:
            return None

        return eligible[-1]
