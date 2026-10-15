from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path


class LocalPathBoundaryError(ValueError):
    pass


@dataclass(frozen=True, slots=True)
class LocalSIFIPaths:
    root: Path
    runtime: Path
    data: Path
    logs: Path
    backups: Path
    config: Path
    database: Path

    @classmethod
    def from_root(cls, root: str | Path) -> "LocalSIFIPaths":
        resolved = Path(root).resolve()
        if not (resolved / "src" / "sifi").is_dir():
            raise LocalPathBoundaryError("root is not a SIFI workspace")
        runtime = resolved / "runtime"
        return cls(
            root=resolved,
            runtime=runtime,
            data=runtime / "data",
            logs=runtime / "logs",
            backups=runtime / "backups",
            config=runtime / "config",
            database=runtime / "data" / "sifi.sqlite",
        )

    def initialize(self) -> None:
        for directory in (
            self.runtime, self.data, self.logs, self.backups, self.config
        ):
            self.require_inside(directory)
            directory.mkdir(parents=True, exist_ok=True)

    def require_inside(self, path: str | Path) -> Path:
        candidate = Path(path).resolve()
        if not candidate.is_relative_to(self.root):
            raise LocalPathBoundaryError(
                f"path must remain inside SIFI workspace: {candidate}"
            )
        return candidate
