"""Fail-closed rights and evidence admission evaluator."""

from __future__ import annotations

from datetime import datetime

from sifi.domain import AdmissionZone
from sifi.domain.versioning import require_aware_datetime

from .model import (
    AdmissionDecision,
    AdmissionReason,
    DerivedOutputDecision,
    EvidenceAdmissionContext,
    PermissionState,
    PrivacyRiskClass,
    ProfilingLevel,
    SourceRightsContext,
    TemporalRightsState,
)


class RightsPolicyEvaluator:
    """Evaluate admission without granting production authority.

    This evaluator is deterministic and fail-closed.

    It does not crawl, fetch, acquire, deploy, or independently create
    source rights. It only evaluates supplied rights/evidence state.
    """

    def evaluate_evidence(
        self,
        source: SourceRightsContext,
        evidence: EvidenceAdmissionContext,
        *,
        as_of: datetime,
    ) -> AdmissionDecision:

        require_aware_datetime(as_of, "as_of")

        hard = self._hard_block_reason(
            source,
            evidence,
        )

        if hard is not None:
            return AdmissionDecision(
                zone=AdmissionZone.Z0_PROHIBITED,
                research_eligible=False,
                production_eligible=False,
                hard_block=True,
                reasons=(hard,),
            )

        # Z1 is explicitly research-only and cannot become Z2 merely
        # because evidence-specific review later succeeds.
        if source.source_zone == AdmissionZone.Z1_RESEARCH_ONLY:
            return AdmissionDecision(
                zone=AdmissionZone.Z1_RESEARCH_ONLY,
                research_eligible=True,
                production_eligible=False,
                hard_block=False,
                reasons=(
                    AdmissionReason.SOURCE_RESEARCH_ONLY,
                ),
            )

        reasons: list[AdmissionReason] = []

        if source.source_zone != AdmissionZone.Z2_PRODUCTION_ADMITTED:
            return AdmissionDecision(
                zone=AdmissionZone.Z0_PROHIBITED,
                research_eligible=False,
                production_eligible=False,
                hard_block=True,
                reasons=(
                    AdmissionReason.SOURCE_Z0_PROHIBITED,
                ),
            )

        if source.rights_window is None:
            reasons.append(
                AdmissionReason.PIT_RIGHTS_STATE_MISSING
            )
        else:
            temporal = source.rights_window.state_at(as_of)

            if temporal == TemporalRightsState.NOT_YET_VALID:
                reasons.append(
                    AdmissionReason.RIGHTS_NOT_YET_VALID
                )
            elif temporal == TemporalRightsState.EXPIRED:
                reasons.append(
                    AdmissionReason.RIGHTS_EXPIRED
                )
            elif temporal == TemporalRightsState.REVOKED:
                reasons.append(
                    AdmissionReason.RIGHTS_REVOKED
                )

        if evidence.commercial_use_required:
            if (
                source.commercial_use_state
                == PermissionState.UNKNOWN
            ):
                reasons.append(
                    AdmissionReason.COMMERCIAL_USE_UNKNOWN
                )
            elif (
                source.commercial_use_state
                != PermissionState.ALLOWED
            ):
                reasons.append(
                    AdmissionReason.COMMERCIAL_USE_DENIED
                )

        if evidence.retention_required:
            if (
                source.retention_state
                == PermissionState.UNKNOWN
            ):
                reasons.append(
                    AdmissionReason.RETENTION_UNKNOWN
                )
            elif (
                source.retention_state
                != PermissionState.ALLOWED
            ):
                reasons.append(
                    AdmissionReason.RETENTION_DENIED
                )

        if evidence.privacy_material:
            if (
                source.privacy_clearance_state
                == PermissionState.UNKNOWN
            ):
                reasons.append(
                    AdmissionReason.PRIVACY_UNKNOWN
                )
            elif (
                source.privacy_clearance_state
                != PermissionState.ALLOWED
            ):
                # DENIED is handled as a hard block above.
                reasons.append(
                    AdmissionReason.PRIVACY_PROHIBITED
                )

        if source.automated_access_used:
            if (
                source.automated_access_state
                == PermissionState.UNKNOWN
            ):
                reasons.append(
                    AdmissionReason.AUTOMATED_ACCESS_UNKNOWN
                )
            elif (
                source.automated_access_state
                != PermissionState.ALLOWED
            ):
                reasons.append(
                    AdmissionReason.AUTOMATED_ACCESS_DENIED
                )

        # Current controlled implementation authority does not activate
        # individual profiling. P0 remains the executable default.
        if source.profiling_level != ProfilingLevel.P0:
            reasons.append(
                AdmissionReason.CURRENT_PHASE_PROFILING_NOT_AUTHORIZED
            )

        if not evidence.provenance_traceable:
            reasons.append(
                AdmissionReason.PROVENANCE_UNTRACEABLE
            )

        if not evidence.evidence_specific_reviewed:
            reasons.append(
                AdmissionReason.EVIDENCE_SPECIFIC_REVIEW_REQUIRED
            )

        if reasons:
            return AdmissionDecision(
                zone=AdmissionZone.Z1_RESEARCH_ONLY,
                research_eligible=True,
                production_eligible=False,
                hard_block=False,
                reasons=tuple(dict.fromkeys(reasons)),
            )

        return AdmissionDecision(
            zone=AdmissionZone.Z2_PRODUCTION_ADMITTED,
            research_eligible=True,
            production_eligible=True,
            hard_block=False,
            reasons=(
                AdmissionReason.PRODUCTION_ADMITTED,
            ),
        )

    def evaluate_derived_output(
        self,
        supporting_evidence: tuple[
            AdmissionDecision, ...
        ],
    ) -> DerivedOutputDecision:

        if not supporting_evidence:
            return DerivedOutputDecision(
                zone=AdmissionZone.Z3_DERIVED_OUTPUT,
                production_eligible=False,
                hard_block=False,
                supporting_zones=(),
                reasons=(
                    AdmissionReason.NO_SUPPORTING_EVIDENCE,
                ),
            )

        zones = tuple(
            decision.zone
            for decision in supporting_evidence
        )

        if any(
            decision.hard_block
            or decision.zone
            == AdmissionZone.Z0_PROHIBITED
            for decision in supporting_evidence
        ):
            return DerivedOutputDecision(
                zone=AdmissionZone.Z3_DERIVED_OUTPUT,
                production_eligible=False,
                hard_block=True,
                supporting_zones=zones,
                reasons=(
                    AdmissionReason.UPSTREAM_Z0_BLOCKED,
                ),
            )

        if any(
            decision.zone
            == AdmissionZone.Z1_RESEARCH_ONLY
            for decision in supporting_evidence
        ):
            return DerivedOutputDecision(
                zone=AdmissionZone.Z3_DERIVED_OUTPUT,
                production_eligible=False,
                hard_block=False,
                supporting_zones=zones,
                reasons=(
                    AdmissionReason.Z1_LAUNDERING_PREVENTED,
                ),
            )

        if any(
            not decision.production_eligible
            or decision.zone
            != AdmissionZone.Z2_PRODUCTION_ADMITTED
            for decision in supporting_evidence
        ):
            return DerivedOutputDecision(
                zone=AdmissionZone.Z3_DERIVED_OUTPUT,
                production_eligible=False,
                hard_block=False,
                supporting_zones=zones,
                reasons=(
                    AdmissionReason.UPSTREAM_NOT_PRODUCTION_ADMITTED,
                ),
            )

        return DerivedOutputDecision(
            zone=AdmissionZone.Z3_DERIVED_OUTPUT,
            production_eligible=True,
            hard_block=False,
            supporting_zones=zones,
            reasons=(
                AdmissionReason.DERIVED_OUTPUT_ADMITTED,
            ),
        )

    @staticmethod
    def _hard_block_reason(
        source: SourceRightsContext,
        evidence: EvidenceAdmissionContext,
    ) -> AdmissionReason | None:

        if source.source_zone == AdmissionZone.Z0_PROHIBITED:
            return AdmissionReason.SOURCE_Z0_PROHIBITED

        if source.source_zone == AdmissionZone.Z3_DERIVED_OUTPUT:
            return AdmissionReason.INVALID_SOURCE_ZONE_Z3

        if source.security_bypass_used:
            return AdmissionReason.SECURITY_BYPASS

        if source.access_state == PermissionState.DENIED:
            return AdmissionReason.ACCESS_DENIED

        if source.access_state != PermissionState.ALLOWED:
            # Z1 still requires lawful accessibility.
            return AdmissionReason.ACCESS_UNKNOWN

        if (
            source.private_communication
            and not source.private_communication_authorized
        ):
            return (
                AdmissionReason
                .PRIVATE_COMMUNICATION_UNAUTHORIZED
            )

        if (
            source.privileged_institutional_access
            and not source.independent_authority
        ):
            return (
                AdmissionReason
                .PRIVILEGED_INSTITUTIONAL_ACCESS_UNAUTHORIZED
            )

        if (
            source.privacy_risk_class
            == PrivacyRiskClass.PRIV_4
        ):
            return AdmissionReason.PRIVACY_PROHIBITED

        if source.profiling_level == ProfilingLevel.P4:
            return AdmissionReason.PROFILING_PROHIBITED

        if (
            evidence.privacy_material
            and source.privacy_clearance_state
            == PermissionState.DENIED
        ):
            return AdmissionReason.PRIVACY_PROHIBITED

        return None
