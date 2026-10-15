from __future__ import annotations

from datetime import datetime
from pathlib import Path

from sifi.local import LocalSIFIPaths
from sifi.persistence import SQLiteVersionStore

from .adjudication import CB22Assessment, assess_cb22
from .model import StudyObservation
from .repository import StudyObservationRepository


class CB22StudyRuntime:
    """Workspace-confined persistence and assessment boundary for CB22."""

    def __init__(self, database_path: str | Path, *, workspace_root: str | Path) -> None:
        path = LocalSIFIPaths.from_root(workspace_root).require_inside(database_path)
        path.parent.mkdir(parents=True, exist_ok=True)
        self._store = SQLiteVersionStore(path)
        self._repository = StudyObservationRepository(self._store)

    def __enter__(self) -> "CB22StudyRuntime":
        return self

    def __exit__(self, *_args) -> None:
        self._store.close()

    def record(self, item: StudyObservation, *, recorded_at: datetime) -> StudyObservation:
        return self._repository.append(item, recorded_at=recorded_at)

    def latest(self, observation_id: str) -> StudyObservation | None:
        return self._repository.latest(observation_id)

    def observations(self) -> tuple[StudyObservation, ...]:
        return self._repository.list_latest()

    def assessment(self) -> CB22Assessment:
        return assess_cb22(self.observations())
