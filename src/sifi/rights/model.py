"""Rights and evidence-admission domain primitives.

Implementation control vocabulary in this module does not replace the
frozen Stage0 contracts. Canonical Z0/Z1/Z2/Z3 values reuse
sifi.domain.AdmissionZone.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from enum import StrEnum

from sifi.domain import AdmissionZone
from sifi.domain.versioning import require_aware_datetime


class PermissionState(StrEnum):
    """Fail-closed implementation representation of a material permission."""

    ALLOWED = "ALLOWED"
    DENIED = "DENIED"
    UNKNOWN = "UNKNOWN"
    NOT_APPLICABLE = "NOT_APPLICABLE"


class PrivacyRiskClass(StrEnum):
    PRIV_0 = "PRIV-0"
    PRIV_1 = "PRIV-1"
    PRIV_2 = "PRIV-2"
    PRIV_3 = "PRIV-3"
    PRIV_4 = "PRIV-4"


class ProfilingLevel(StrEnum):
    P0 = "P0"
    P1 = "P1"
    P2 = "P2"
    P3 = "P3"
    P4 = "P4"


class TemporalRightsState(StrEnum):
    ACTIVE = "ACTIVE"
    NOT_YET_VALID = "NOT_YET_VALID"
    EXPIRED = "EXPIRED"
    REVOKED = "REVOKED"


class AdmissionReason(StrEnum):
    SOURCE_Z0_PROHIBITED = "SOURCE_Z0_PROHIBITED"
    INVALID_SOURCE_ZONE_Z3 = "INVALID_SOURCE_ZONE_Z3"

    ACCESS_DENIED = "ACCESS_DENIED"
    ACCESS_UNKNOWN = "ACCESS_UNKNOWN"

    SECURITY_BYPASS = "SECURITY_BYPASS"

    PRIVATE_COMMUNICATION_UNAUTHORIZED = (
        "PRIVATE_COMMUNICATION_UNAUTHORIZED"
    )

    PRIVILEGED_INSTITUTIONAL_ACCESS_UNAUTHORIZED = (
        "PRIVILEGED_INSTITUTIONAL_ACCESS_UNAUTHORIZED"
    )

    PRIVACY_PROHIBITED = "PRIVACY_PROHIBITED"
    PRIVACY_UNKNOWN = "PRIVACY_UNKNOWN"

    PROFILING_PROHIBITED = "PROFILING_PROHIBITED"
    CURRENT_PHASE_PROFILING_NOT_AUTHORIZED = (
        "CURRENT_PHASE_PROFILING_NOT_AUTHORIZED"
    )

    SOURCE_RESEARCH_ONLY = "SOURCE_RESEARCH_ONLY"

    COMMERCIAL_USE_DENIED = "COMMERCIAL_USE_DENIED"
    COMMERCIAL_USE_UNKNOWN = "COMMERCIAL_USE_UNKNOWN"

    RETENTION_DENIED = "RETENTION_DENIED"
    RETENTION_UNKNOWN = "RETENTION_UNKNOWN"

    AUTOMATED_ACCESS_DENIED = "AUTOMATED_ACCESS_DENIED"
    AUTOMATED_ACCESS_UNKNOWN = "AUTOMATED_ACCESS_UNKNOWN"

    PIT_RIGHTS_STATE_MISSING = "PIT_RIGHTS_STATE_MISSING"
    RIGHTS_NOT_YET_VALID = "RIGHTS_NOT_YET_VALID"
    RIGHTS_EXPIRED = "RIGHTS_EXPIRED"
    RIGHTS_REVOKED = "RIGHTS_REVOKED"

    EVIDENCE_SPECIFIC_REVIEW_REQUIRED = (
        "EVIDENCE_SPECIFIC_REVIEW_REQUIRED"
    )

    PROVENANCE_UNTRACEABLE = "PROVENANCE_UNTRACEABLE"

    PRODUCTION_ADMITTED = "PRODUCTION_ADMITTED"

    NO_SUPPORTING_EVIDENCE = "NO_SUPPORTING_EVIDENCE"
    UPSTREAM_Z0_BLOCKED = "UPSTREAM_Z0_BLOCKED"
    UPSTREAM_NOT_PRODUCTION_ADMITTED = (
        "UPSTREAM_NOT_PRODUCTION_ADMITTED"
    )
    Z1_LAUNDERING_PREVENTED = "Z1_LAUNDERING_PREVENTED"
    DERIVED_OUTPUT_ADMITTED = "DERIVED_OUTPUT_ADMITTED"


@dataclass(frozen=True, slots=True)
class RightsWindow:
    valid_from: datetime
    valid_until: datetime | None = None
    revoked_at: datetime | None = None

    def __post_init__(self) -> None:
        require_aware_datetime(
            self.valid_from,
            "valid_from",
        )

        if self.valid_until is not None:
            require_aware_datetime(
                self.valid_until,
                "valid_until",
            )

            if self.valid_until <= self.valid_from:
                raise ValueError(
                    "valid_until must be later than valid_from"
                )

        if self.revoked_at is not None:
            require_aware_datetime(
                self.revoked_at,
                "revoked_at",
            )

    def state_at(
        self,
        as_of: datetime,
    ) -> TemporalRightsState:
        require_aware_datetime(as_of, "as_of")

        if as_of < self.valid_from:
            return TemporalRightsState.NOT_YET_VALID

        if (
            self.revoked_at is not None
            and as_of >= self.revoked_at
        ):
            return TemporalRightsState.REVOKED

        if (
            self.valid_until is not None
            and as_of >= self.valid_until
        ):
            return TemporalRightsState.EXPIRED

        return TemporalRightsState.ACTIVE


@dataclass(frozen=True, slots=True)
class SourceRightsContext:
    source_id: str
    source_zone: AdmissionZone

    access_state: PermissionState

    commercial_use_state: PermissionState
    retention_state: PermissionState
    privacy_clearance_state: PermissionState

    automated_access_state: PermissionState = (
        PermissionState.NOT_APPLICABLE
    )

    automated_access_used: bool = False
    security_bypass_used: bool = False

    private_communication: bool = False
    private_communication_authorized: bool = False

    privileged_institutional_access: bool = False
    independent_authority: bool = False

    privacy_risk_class: PrivacyRiskClass = (
        PrivacyRiskClass.PRIV_0
    )

    profiling_level: ProfilingLevel = ProfilingLevel.P0

    rights_window: RightsWindow | None = None

    def __post_init__(self) -> None:
        if not self.source_id.strip():
            raise ValueError("source_id must not be blank")


@dataclass(frozen=True, slots=True)
class EvidenceAdmissionContext:
    evidence_id: str

    evidence_specific_reviewed: bool
    provenance_traceable: bool

    commercial_use_required: bool = True
    retention_required: bool = True
    privacy_material: bool = True

    def __post_init__(self) -> None:
        if not self.evidence_id.strip():
            raise ValueError(
                "evidence_id must not be blank"
            )


@dataclass(frozen=True, slots=True)
class AdmissionDecision:
    zone: AdmissionZone
    research_eligible: bool
    production_eligible: bool
    hard_block: bool
    reasons: tuple[AdmissionReason, ...]

    def has_reason(
        self,
        reason: AdmissionReason,
    ) -> bool:
        return reason in self.reasons


@dataclass(frozen=True, slots=True)
class DerivedOutputDecision:
    zone: AdmissionZone
    production_eligible: bool
    hard_block: bool
    supporting_zones: tuple[AdmissionZone, ...]
    reasons: tuple[AdmissionReason, ...]
