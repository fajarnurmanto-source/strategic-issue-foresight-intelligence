from __future__ import annotations

import json
import sqlite3
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path

from .local import LocalPathBoundaryError, LocalSIFIPaths
from .workflow import LocalWorkflowRuntime


class LocalOperationsError(RuntimeError):
    pass


@dataclass(frozen=True, slots=True)
class LocalStatus:
    root: str
    database: str
    database_exists: bool
    database_size_bytes: int
    integrity: str
    local_only: bool = True

    def to_json(self) -> str:
        return json.dumps(asdict(self), indent=2, sort_keys=True)


class LocalOperations:
    def __init__(self, root: str | Path) -> None:
        self.paths = LocalSIFIPaths.from_root(root)

    def initialize(self) -> LocalStatus:
        self.paths.initialize()
        with LocalWorkflowRuntime(
            self.paths.database, workspace_root=self.paths.root
        ):
            pass
        return self.status()

    def status(self) -> LocalStatus:
        exists = self.paths.database.is_file()
        size = self.paths.database.stat().st_size if exists else 0
        integrity = "NOT_INITIALIZED"
        if exists:
            with sqlite3.connect(self.paths.database) as connection:
                integrity = str(
                    connection.execute("PRAGMA integrity_check").fetchone()[0]
                ).upper()
        return LocalStatus(
            root=str(self.paths.root),
            database=str(self.paths.database),
            database_exists=exists,
            database_size_bytes=size,
            integrity=integrity,
        )

    def backup(self, *, label: str | None = None) -> Path:
        if not self.paths.database.is_file():
            raise LocalOperationsError("local database is not initialized")
        self.paths.initialize()
        safe_label = label or datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        if not safe_label.replace("-", "").replace("_", "").isalnum():
            raise LocalOperationsError("backup label contains unsafe characters")
        destination = self.paths.require_inside(
            self.paths.backups / f"sifi-{safe_label}.sqlite"
        )
        if destination.exists():
            raise LocalOperationsError("backup destination already exists")
        with sqlite3.connect(self.paths.database) as source:
            with sqlite3.connect(destination) as target:
                source.backup(target)
        return destination

    def restore(self, backup_path: str | Path) -> Path | None:
        source = self.paths.require_inside(backup_path)
        if not source.is_file() or source.parent != self.paths.backups:
            raise LocalOperationsError("restore source must be a local SIFI backup")
        with sqlite3.connect(source) as candidate:
            result = str(candidate.execute("PRAGMA integrity_check").fetchone()[0])
            if result.lower() != "ok":
                raise LocalOperationsError("backup integrity check failed")

        safety = None
        if self.paths.database.is_file():
            stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
            safety = self.backup(label="pre_restore_" + stamp)
        self.paths.initialize()
        with sqlite3.connect(source) as backup_connection:
            with sqlite3.connect(self.paths.database) as target:
                backup_connection.backup(target)
        return safety
