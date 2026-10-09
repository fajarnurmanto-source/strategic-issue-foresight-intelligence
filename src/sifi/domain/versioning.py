"""Non-destructive version primitives for controlled implementation."""

from dataclasses import dataclass
from datetime import datetime
from typing import Any, Mapping


def require_aware_datetime(value: datetime, field_name: str) -> None:
    if value.tzinfo is None or value.utcoffset() is None:
        raise ValueError(
            f"{field_name} must be timezone-aware"
        )


@dataclass(frozen=True, slots=True)
class VersionedSnapshot:
    object_type: str
    object_id: str
    version: int
    effective_at: datetime
    recorded_at: datetime
    payload: Mapping[str, Any]

    def __post_init__(self) -> None:
        if not self.object_type.strip():
            raise ValueError("object_type must not be blank")

        if not self.object_id.strip():
            raise ValueError("object_id must not be blank")

        if self.version < 1:
            raise ValueError("version must be >= 1")

        require_aware_datetime(
            self.effective_at,
            "effective_at"
        )

        require_aware_datetime(
            self.recorded_at,
            "recorded_at"
        )

        if not isinstance(self.payload, Mapping):
            raise TypeError("payload must be a mapping")
