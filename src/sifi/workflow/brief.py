from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain.versioning import require_aware_datetime


class BriefBoundaryError(ValueError):
    pass


@dataclass(frozen=True, slots=True)
class EvidenceLinkedBrief:
    brief_id: str
    issue_id: str
    summary: str
    evidence_refs: tuple[str, ...]
    created_at: datetime
    analytical_cutoff: datetime
    analytical_only: bool = True
    production_action_authorized: bool = False

    def __post_init__(self) -> None:
        for name in ("brief_id", "issue_id", "summary"):
            if not getattr(self, name).strip():
                raise BriefBoundaryError(f"{name} must be nonblank")
        if not self.evidence_refs:
            raise BriefBoundaryError("brief requires evidence references")
        if len(self.evidence_refs) != len(set(self.evidence_refs)):
            raise BriefBoundaryError("brief evidence references must be unique")
        require_aware_datetime(self.created_at, "created_at")
        require_aware_datetime(self.analytical_cutoff, "analytical_cutoff")
        if not self.analytical_only:
            raise BriefBoundaryError("S1.7 brief must remain analytical-only")
        if self.production_action_authorized:
            raise BriefBoundaryError("brief cannot authorize production action")
