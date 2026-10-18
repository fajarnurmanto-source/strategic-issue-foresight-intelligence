"""Append-only SQLite version store for controlled non-production use."""

from __future__ import annotations

import json
import sqlite3
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from sifi.domain.versioning import (
    VersionedSnapshot,
    require_aware_datetime,
)


class PersistenceError(Exception):
    """Base persistence error."""


class DuplicateVersionError(PersistenceError):
    """Raised when an existing object version would be overwritten."""


class UnsupportedSchemaVersionError(PersistenceError):
    """Raised when a database is newer than this SIFI runtime."""


CURRENT_SCHEMA_VERSION = 1


def _utc_iso(value: datetime) -> str:
    require_aware_datetime(value, "datetime")
    return value.astimezone(timezone.utc).isoformat()


def _from_iso(value: str) -> datetime:
    parsed = datetime.fromisoformat(value)
    require_aware_datetime(parsed, "stored datetime")
    return parsed


class SQLiteVersionStore:
    """Small append-only version store.

    This is a local controlled-development adapter.
    It is not a production database or production deployment authority.
    """

    def __init__(self, database: str | Path = ":memory:") -> None:
        self._database = str(database)
        self._connection = sqlite3.connect(self._database, timeout=30.0, check_same_thread=False)
        self._connection.row_factory = sqlite3.Row
        self._transaction_depth = 0
        self._initialize()

    def _initialize(self) -> None:
        self._connection.execute("PRAGMA journal_mode=WAL;")
        self._connection.execute("PRAGMA synchronous=NORMAL;")
        stored_version = int(
            self._connection.execute("PRAGMA user_version").fetchone()[0]
        )
        if stored_version > CURRENT_SCHEMA_VERSION:
            self._connection.close()
            raise UnsupportedSchemaVersionError(
                f"database schema {stored_version} is newer than supported "
                f"schema {CURRENT_SCHEMA_VERSION}"
            )
        self._connection.executescript(
            """
            CREATE TABLE IF NOT EXISTS object_versions (
                object_type TEXT NOT NULL,
                object_id TEXT NOT NULL,
                version INTEGER NOT NULL CHECK (version >= 1),
                effective_at TEXT NOT NULL,
                recorded_at TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                PRIMARY KEY (object_type, object_id, version)
            );

            CREATE INDEX IF NOT EXISTS
                idx_object_versions_latest
            ON object_versions (
                object_type,
                object_id,
                version DESC
            );

            CREATE INDEX IF NOT EXISTS
                idx_object_versions_pit
            ON object_versions (
                object_type,
                object_id,
                effective_at,
                recorded_at
            );
            """
        )
        if stored_version < CURRENT_SCHEMA_VERSION:
            self._connection.execute(f"PRAGMA user_version = {CURRENT_SCHEMA_VERSION}")
        self._connection.commit()

    def append(self, snapshot: VersionedSnapshot) -> None:
        payload_json = json.dumps(
            dict(snapshot.payload),
            ensure_ascii=False,
            sort_keys=True,
            separators=(",", ":"),
        )

        try:
            self._connection.execute(
                """
                INSERT INTO object_versions (
                    object_type,
                    object_id,
                    version,
                    effective_at,
                    recorded_at,
                    payload_json
                )
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                (
                    snapshot.object_type,
                    snapshot.object_id,
                    snapshot.version,
                    _utc_iso(snapshot.effective_at),
                    _utc_iso(snapshot.recorded_at),
                    payload_json,
                ),
            )
            if self._transaction_depth == 0:
                self._connection.commit()

        except sqlite3.IntegrityError as exc:
            raise DuplicateVersionError(
                "existing object version cannot be overwritten"
            ) from exc

    @contextmanager
    def transaction(self):
        outermost = self._transaction_depth == 0
        if outermost:
            self._connection.execute("BEGIN IMMEDIATE")
        self._transaction_depth += 1
        try:
            yield self
        except Exception:
            self._transaction_depth -= 1
            if outermost:
                self._connection.rollback()
            raise
        else:
            self._transaction_depth -= 1
            if outermost:
                try:
                    self._connection.commit()
                except Exception:
                    self._connection.rollback()
                    raise

    def get(
        self,
        object_type: str,
        object_id: str,
        version: int,
    ) -> VersionedSnapshot | None:

        row = self._connection.execute(
            """
            SELECT *
            FROM object_versions
            WHERE object_type = ?
              AND object_id = ?
              AND version = ?
            """,
            (object_type, object_id, version),
        ).fetchone()

        return self._row_to_snapshot(row) if row else None

    def latest(
        self,
        object_type: str,
        object_id: str,
    ) -> VersionedSnapshot | None:

        row = self._connection.execute(
            """
            SELECT *
            FROM object_versions
            WHERE object_type = ?
              AND object_id = ?
            ORDER BY version DESC
            LIMIT 1
            """,
            (object_type, object_id),
        ).fetchone()

        return self._row_to_snapshot(row) if row else None

    def as_of(
        self,
        object_type: str,
        object_id: str,
        cutoff: datetime,
    ) -> VersionedSnapshot | None:

        require_aware_datetime(cutoff, "cutoff")
        cutoff_iso = _utc_iso(cutoff)

        row = self._connection.execute(
            """
            SELECT *
            FROM object_versions
            WHERE object_type = ?
              AND object_id = ?
              AND effective_at <= ?
              AND recorded_at <= ?
            ORDER BY
                effective_at DESC,
                recorded_at DESC,
                version DESC
            LIMIT 1
            """,
            (
                object_type,
                object_id,
                cutoff_iso,
                cutoff_iso,
            ),
        ).fetchone()

        return self._row_to_snapshot(row) if row else None

    def history(
        self,
        object_type: str,
        object_id: str,
    ) -> tuple[VersionedSnapshot, ...]:

        rows = self._connection.execute(
            """
            SELECT *
            FROM object_versions
            WHERE object_type = ?
              AND object_id = ?
            ORDER BY version ASC
            """,
            (object_type, object_id),
        ).fetchall()

        return tuple(
            self._row_to_snapshot(row)
            for row in rows
        )

    def list_latest(
        self,
        object_type: str,
        *,
        limit: int = 100,
    ) -> tuple[VersionedSnapshot, ...]:
        if not object_type.strip():
            raise ValueError("object_type must be nonblank")
        if limit < 1 or limit > 1000:
            raise ValueError("limit must be between 1 and 1000")
        rows = self._connection.execute(
            """
            SELECT current.*
            FROM object_versions AS current
            INNER JOIN (
                SELECT object_id, MAX(version) AS latest_version
                FROM object_versions
                WHERE object_type = ?
                GROUP BY object_id
            ) AS latest
              ON current.object_id = latest.object_id
             AND current.version = latest.latest_version
            WHERE current.object_type = ?
            ORDER BY current.recorded_at DESC, current.object_id ASC
            LIMIT ?
            """,
            (object_type, object_type, limit),
        ).fetchall()
        return tuple(self._row_to_snapshot(row) for row in rows)

    @staticmethod
    def _row_to_snapshot(
        row: sqlite3.Row,
    ) -> VersionedSnapshot:

        payload: dict[str, Any] = json.loads(
            row["payload_json"]
        )

        return VersionedSnapshot(
            object_type=row["object_type"],
            object_id=row["object_id"],
            version=row["version"],
            effective_at=_from_iso(row["effective_at"]),
            recorded_at=_from_iso(row["recorded_at"]),
            payload=payload,
        )

    def close(self) -> None:
        self._connection.close()

    def __enter__(self) -> "SQLiteVersionStore":
        return self

    def __exit__(self, exc_type, exc, tb) -> None:
        self.close()
