"""Persistent Issue Object repository over the S1.1 version store."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain.versioning import (
    VersionedSnapshot,
    require_aware_datetime,
)
from sifi.persistence import SQLiteVersionStore

from .model import IssueObject


ISSUE_OBJECT_TYPE = "ISSUE"


class IssueRepositoryError(Exception):
    pass


class IssueVersionSequenceError(IssueRepositoryError):
    pass


@dataclass(frozen=True, slots=True)
class PersistedIssueVersion:
    version: int
    effective_at: datetime
    recorded_at: datetime
    issue: IssueObject


class IssueObjectRepository:
    def __init__(
        self,
        store: SQLiteVersionStore,
    ) -> None:
        self._store = store

    def append(
        self,
        issue: IssueObject,
        *,
        version: int,
        effective_at: datetime,
        recorded_at: datetime,
    ) -> None:

        require_aware_datetime(
            effective_at,
            "effective_at",
        )
        require_aware_datetime(
            recorded_at,
            "recorded_at",
        )

        latest = self._store.latest(
            ISSUE_OBJECT_TYPE,
            issue.issue_id,
        )

        expected = (
            1
            if latest is None
            else latest.version + 1
        )

        if version != expected:
            raise IssueVersionSequenceError(
                f"expected Issue version {expected}, "
                f"received {version}"
            )

        self._store.append(
            VersionedSnapshot(
                object_type=ISSUE_OBJECT_TYPE,
                object_id=issue.issue_id,
                version=version,
                effective_at=effective_at,
                recorded_at=recorded_at,
                payload=issue.to_payload(),
            )
        )

    def get(
        self,
        issue_id: str,
        version: int,
    ) -> PersistedIssueVersion | None:

        snapshot = self._store.get(
            ISSUE_OBJECT_TYPE,
            issue_id,
            version,
        )

        return (
            self._decode(snapshot)
            if snapshot is not None
            else None
        )

    def latest(
        self,
        issue_id: str,
    ) -> PersistedIssueVersion | None:

        snapshot = self._store.latest(
            ISSUE_OBJECT_TYPE,
            issue_id,
        )

        return (
            self._decode(snapshot)
            if snapshot is not None
            else None
        )

    def as_of(
        self,
        issue_id: str,
        cutoff: datetime,
    ) -> PersistedIssueVersion | None:

        snapshot = self._store.as_of(
            ISSUE_OBJECT_TYPE,
            issue_id,
            cutoff,
        )

        return (
            self._decode(snapshot)
            if snapshot is not None
            else None
        )

    def history(
        self,
        issue_id: str,
    ) -> tuple[PersistedIssueVersion, ...]:

        return tuple(
            self._decode(snapshot)
            for snapshot in self._store.history(
                ISSUE_OBJECT_TYPE,
                issue_id,
            )
        )

    @staticmethod
    def _decode(
        snapshot: VersionedSnapshot,
    ) -> PersistedIssueVersion:

        return PersistedIssueVersion(
            version=snapshot.version,
            effective_at=snapshot.effective_at,
            recorded_at=snapshot.recorded_at,
            issue=IssueObject.from_payload(
                dict(snapshot.payload)
            ),
        )
