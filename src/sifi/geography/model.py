"""Orthogonal geography dimensions.

This object deliberately preserves geography dimensions separately.
It performs no inference from geographic relevance to legal authority.
"""

from dataclasses import dataclass, fields
from typing import Optional


@dataclass(frozen=True, slots=True)
class GeographyContext:
    source_geography: Optional[str] = None
    event_geography: Optional[str] = None
    affected_geography: Optional[str] = None
    policy_jurisdiction: Optional[str] = None
    relevance_geography: Optional[str] = None
    rights_jurisdiction: Optional[str] = None
    coverage_profile_id: Optional[str] = None

    def __post_init__(self) -> None:
        for field in fields(self):
            value = getattr(self, field.name)

            if value is not None and not value.strip():
                raise ValueError(
                    f"{field.name} must be None or non-blank"
                )

    def as_dict(self) -> dict[str, Optional[str]]:
        return {
            field.name: getattr(self, field.name)
            for field in fields(self)
        }
