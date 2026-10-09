import unittest
from dataclasses import replace
from datetime import datetime, timezone

from sifi.domain import AdmissionZone
from sifi.rights import (
    AdmissionReason,
    EvidenceAdmissionContext,
    PermissionState,
    PrivacyRiskClass,
    ProfilingLevel,
    RightsPolicyEvaluator,
    RightsWindow,
    SourceRightsContext,
    TemporalRightsState,
)


UTC = timezone.utc


def dt(day: int) -> datetime:
    return datetime(
        2026, 1, day, 12, 0, tzinfo=UTC
    )


def good_source(**changes):
    base = SourceRightsContext(
        source_id="SRC-TEST",
        source_zone=(
            AdmissionZone.Z2_PRODUCTION_ADMITTED
        ),
        access_state=PermissionState.ALLOWED,
        commercial_use_state=PermissionState.ALLOWED,
        retention_state=PermissionState.ALLOWED,
        privacy_clearance_state=PermissionState.ALLOWED,
        rights_window=RightsWindow(
            valid_from=dt(1),
        ),
    )
    return replace(base, **changes)


def good_evidence(**changes):
    base = EvidenceAdmissionContext(
        evidence_id="EVID-TEST",
        evidence_specific_reviewed=True,
        provenance_traceable=True,
    )
    return replace(base, **changes)


class CanonicalRightsVocabularyTests(unittest.TestCase):

    def test_privacy_classes_are_preserved(self):
        self.assertEqual(
            [x.value for x in PrivacyRiskClass],
            [
                "PRIV-0",
                "PRIV-1",
                "PRIV-2",
                "PRIV-3",
                "PRIV-4",
            ],
        )

    def test_profiling_levels_are_preserved(self):
        self.assertEqual(
            [x.value for x in ProfilingLevel],
            ["P0", "P1", "P2", "P3", "P4"],
        )

    def test_permission_unknown_is_explicit(self):
        self.assertIn(
            PermissionState.UNKNOWN,
            tuple(PermissionState),
        )


class EvidenceAdmissionTests(unittest.TestCase):

    def setUp(self):
        self.policy = RightsPolicyEvaluator()

    def test_z0_is_hard_blocked(self):
        result = self.policy.evaluate_evidence(
            good_source(
                source_zone=AdmissionZone.Z0_PROHIBITED
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertFalse(result.research_eligible)
        self.assertFalse(result.production_eligible)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z0_PROHIBITED,
        )

    def test_z1_remains_research_only(self):
        result = self.policy.evaluate_evidence(
            good_source(
                source_zone=(
                    AdmissionZone.Z1_RESEARCH_ONLY
                )
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.hard_block)
        self.assertTrue(result.research_eligible)
        self.assertFalse(result.production_eligible)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z1_RESEARCH_ONLY,
        )

    def test_source_z2_does_not_auto_promote_evidence(self):
        result = self.policy.evaluate_evidence(
            good_source(),
            good_evidence(
                evidence_specific_reviewed=False
            ),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z1_RESEARCH_ONLY,
        )
        self.assertTrue(
            result.has_reason(
                AdmissionReason
                .EVIDENCE_SPECIFIC_REVIEW_REQUIRED
            )
        )

    def test_fully_qualified_z2_evidence_is_admitted(self):
        result = self.policy.evaluate_evidence(
            good_source(),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.hard_block)
        self.assertTrue(result.research_eligible)
        self.assertTrue(result.production_eligible)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z2_PRODUCTION_ADMITTED,
        )

    def test_unknown_commercial_rights_fail_closed(self):
        result = self.policy.evaluate_evidence(
            good_source(
                commercial_use_state=(
                    PermissionState.UNKNOWN
                )
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z1_RESEARCH_ONLY,
        )
        self.assertTrue(
            result.has_reason(
                AdmissionReason.COMMERCIAL_USE_UNKNOWN
            )
        )

    def test_denied_retention_blocks_production(self):
        result = self.policy.evaluate_evidence(
            good_source(
                retention_state=PermissionState.DENIED
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertTrue(result.research_eligible)
        self.assertTrue(
            result.has_reason(
                AdmissionReason.RETENTION_DENIED
            )
        )

    def test_unknown_privacy_clearance_blocks_production(self):
        result = self.policy.evaluate_evidence(
            good_source(
                privacy_clearance_state=(
                    PermissionState.UNKNOWN
                )
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertTrue(
            result.has_reason(
                AdmissionReason.PRIVACY_UNKNOWN
            )
        )

    def test_denied_privacy_is_hard_block(self):
        result = self.policy.evaluate_evidence(
            good_source(
                privacy_clearance_state=(
                    PermissionState.DENIED
                )
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z0_PROHIBITED,
        )

    def test_priv4_is_hard_block(self):
        result = self.policy.evaluate_evidence(
            good_source(
                privacy_risk_class=(
                    PrivacyRiskClass.PRIV_4
                )
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertTrue(
            result.has_reason(
                AdmissionReason.PRIVACY_PROHIBITED
            )
        )

    def test_security_bypass_is_hard_block(self):
        result = self.policy.evaluate_evidence(
            good_source(
                security_bypass_used=True
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertTrue(
            result.has_reason(
                AdmissionReason.SECURITY_BYPASS
            )
        )

    def test_unknown_lawful_access_is_hard_block(self):
        result = self.policy.evaluate_evidence(
            good_source(
                access_state=PermissionState.UNKNOWN
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertFalse(result.research_eligible)

    def test_private_communication_requires_authority(self):
        result = self.policy.evaluate_evidence(
            good_source(
                private_communication=True,
                private_communication_authorized=False,
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertTrue(
            result.has_reason(
                AdmissionReason
                .PRIVATE_COMMUNICATION_UNAUTHORIZED
            )
        )

    def test_privileged_institutional_access_requires_authority(self):
        result = self.policy.evaluate_evidence(
            good_source(
                privileged_institutional_access=True,
                independent_authority=False,
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertTrue(
            result.has_reason(
                AdmissionReason
                .PRIVILEGED_INSTITUTIONAL_ACCESS_UNAUTHORIZED
            )
        )

    def test_current_phase_keeps_non_p0_profiling_out(self):
        result = self.policy.evaluate_evidence(
            good_source(
                profiling_level=ProfilingLevel.P1
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertEqual(
            result.zone,
            AdmissionZone.Z1_RESEARCH_ONLY,
        )
        self.assertTrue(
            result.has_reason(
                AdmissionReason
                .CURRENT_PHASE_PROFILING_NOT_AUTHORIZED
            )
        )

    def test_p4_is_hard_block(self):
        result = self.policy.evaluate_evidence(
            good_source(
                profiling_level=ProfilingLevel.P4
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(result.hard_block)
        self.assertTrue(
            result.has_reason(
                AdmissionReason.PROFILING_PROHIBITED
            )
        )

    def test_untraceable_evidence_cannot_be_production(self):
        result = self.policy.evaluate_evidence(
            good_source(),
            good_evidence(
                provenance_traceable=False
            ),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertTrue(
            result.has_reason(
                AdmissionReason.PROVENANCE_UNTRACEABLE
            )
        )

    def test_missing_pit_rights_state_blocks_production(self):
        result = self.policy.evaluate_evidence(
            good_source(
                rights_window=None
            ),
            good_evidence(),
            as_of=dt(5),
        )

        self.assertFalse(result.production_eligible)
        self.assertTrue(
            result.has_reason(
                AdmissionReason
                .PIT_RIGHTS_STATE_MISSING
            )
        )


class PointInTimeRightsTests(unittest.TestCase):

    def setUp(self):
        self.policy = RightsPolicyEvaluator()

    def test_rights_window_temporal_states(self):
        window = RightsWindow(
            valid_from=dt(2),
            valid_until=dt(20),
            revoked_at=dt(10),
        )

        self.assertEqual(
            window.state_at(dt(1)),
            TemporalRightsState.NOT_YET_VALID,
        )
        self.assertEqual(
            window.state_at(dt(5)),
            TemporalRightsState.ACTIVE,
        )
        self.assertEqual(
            window.state_at(dt(10)),
            TemporalRightsState.REVOKED,
        )

    def test_historical_cutoff_before_revocation_remains_eligible(self):
        source = good_source(
            rights_window=RightsWindow(
                valid_from=dt(1),
                revoked_at=dt(10),
            )
        )

        before = self.policy.evaluate_evidence(
            source,
            good_evidence(),
            as_of=dt(5),
        )

        self.assertTrue(before.production_eligible)

    def test_cutoff_after_revocation_is_not_production_eligible(self):
        source = good_source(
            rights_window=RightsWindow(
                valid_from=dt(1),
                revoked_at=dt(10),
            )
        )

        after = self.policy.evaluate_evidence(
            source,
            good_evidence(),
            as_of=dt(11),
        )

        self.assertFalse(after.production_eligible)
        self.assertTrue(
            after.has_reason(
                AdmissionReason.RIGHTS_REVOKED
            )
        )


class DerivedOutputTests(unittest.TestCase):

    def setUp(self):
        self.policy = RightsPolicyEvaluator()

    def admitted(self):
        return self.policy.evaluate_evidence(
            good_source(),
            good_evidence(),
            as_of=dt(5),
        )

    def test_z3_from_only_admitted_z2_can_be_production_eligible(self):
        result = self.policy.evaluate_derived_output(
            (self.admitted(),)
        )

        self.assertEqual(
            result.zone,
            AdmissionZone.Z3_DERIVED_OUTPUT,
        )
        self.assertTrue(result.production_eligible)
        self.assertFalse(result.hard_block)

    def test_z1_cannot_be_laundered_through_z3(self):
        z1 = self.policy.evaluate_evidence(
            good_source(
                source_zone=(
                    AdmissionZone.Z1_RESEARCH_ONLY
                )
            ),
            good_evidence(),
            as_of=dt(5),
        )

        result = self.policy.evaluate_derived_output(
            (self.admitted(), z1)
        )

        self.assertFalse(result.production_eligible)
        self.assertTrue(
            AdmissionReason.Z1_LAUNDERING_PREVENTED
            in result.reasons
        )

    def test_hard_blocked_upstream_prevents_derived_production(self):
        blocked = self.policy.evaluate_evidence(
            good_source(
                security_bypass_used=True
            ),
            good_evidence(),
            as_of=dt(5),
        )

        result = self.policy.evaluate_derived_output(
            (self.admitted(), blocked)
        )

        self.assertFalse(result.production_eligible)
        self.assertTrue(result.hard_block)

    def test_empty_support_cannot_create_production_output(self):
        result = self.policy.evaluate_derived_output(())

        self.assertFalse(result.production_eligible)
        self.assertTrue(
            AdmissionReason.NO_SUPPORTING_EVIDENCE
            in result.reasons
        )


if __name__ == "__main__":
    unittest.main()
