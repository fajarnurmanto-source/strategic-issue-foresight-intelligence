from __future__ import annotations

import unittest
from dataclasses import replace
from datetime import (
    datetime,
    timezone,
)

from sifi.domain import ReviewDisposition
from sifi.review import (
    AuthorityAssignment,
    DisagreementState,
    DispositionConstraintError,
    HardBoundaryViolation,
    HumanReviewAuthorityKernel,
    ProtectedTransition,
    ReviewActorType,
    ReviewHistory,
    ReviewProposal,
    ReviewerRole,
    UnauthorizedReviewerError,
)


RD1 = ReviewDisposition("RD-01")
RD2 = ReviewDisposition("RD-02")
RD3 = ReviewDisposition("RD-03")
RD4 = ReviewDisposition("RD-04")
RD5 = ReviewDisposition("RD-05")
RD6 = ReviewDisposition("RD-06")
RD7 = ReviewDisposition("RD-07")
RD8 = ReviewDisposition("RD-08")
RD9 = ReviewDisposition("RD-09")


def dt(day: int) -> datetime:
    return datetime(
        2026,
        3,
        day,
        12,
        0,
        tzinfo=timezone.utc,
    )


def make_assignment(
    role: ReviewerRole,
    *actions: ProtectedTransition,
    reviewer: str = "REVIEWER-1",
) -> AuthorityAssignment:
    return AuthorityAssignment(
        assignment_id="AUTH-1",
        reviewer_id_or_role_reference=reviewer,
        roles=(role,),
        authorized_transitions=tuple(actions),
        authority_basis="EXPLICIT_TEST_AUTHORITY",
        effective_from=dt(1),
    )


def make_proposal(
    action: ProtectedTransition,
    **changes,
) -> ReviewProposal:

    base = ReviewProposal(
        proposal_id="PROP-1",
        reviewed_object_type="ISSUE",
        reviewed_object_id="ISSUE-1",
        reviewed_object_version=1,
        proposed_action=action,
        proposed_state=None,
        proposer_actor_type=(
            ReviewActorType.HUMAN
        ),
        proposer_reference="ANALYST-1",
        rationale="bounded analytical review",
        evidence_refs=("EV-1",),
        contradictory_evidence_refs=(),
        coverage_limitations=(),
        rights_or_privacy_constraints=(),
        uncertainty_state="KNOWN_LIMITATIONS",
        disagreement_state=(
            DisagreementState
            .NO_MATERIAL_DISAGREEMENT
        ),
        proposed_at=dt(2),
        analytical_cutoff=dt(2),
    )

    return replace(
        base,
        **changes,
    )


class AuthorityTests(unittest.TestCase):

    def setUp(self) -> None:
        self.kernel = (
            HumanReviewAuthorityKernel()
        )

    def dispose(
        self,
        proposal,
        role,
        *,
        disposition=RD1,
        actor=ReviewActorType.HUMAN,
        assignment=None,
        review_id="REV-1",
    ):
        if assignment is None:
            assignment = make_assignment(
                role,
                proposal.proposed_action,
            )

        return self.kernel.dispose(
            review_id=review_id,
            proposal=proposal,
            assignment=assignment,
            reviewer_id_or_role_reference=(
                "REVIEWER-1"
            ),
            reviewer_authority_role=role,
            reviewer_actor_type=actor,
            final_disposition=disposition,
            decided_at=dt(3),
        )

    def test_001_roles_exact(self):
        self.assertEqual(
            tuple(x.value for x in ReviewerRole),
            (
                "RR-01",
                "RR-02",
                "RR-03",
                "RR-04",
                "RR-05",
                "RR-06",
            ),
        )

    def test_002_transitions_exact(self):
        self.assertEqual(
            tuple(
                x.value
                for x in ProtectedTransition
            ),
            tuple(
                f"T-{i:02d}"
                for i in range(1, 13)
            ),
        )

    def test_003_ai_cannot_finally_dispose(self):
        p = make_proposal(
            ProtectedTransition.CLAIM_QUALIFICATION
        )

        with self.assertRaises(
            UnauthorizedReviewerError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
                actor=ReviewActorType.AI,
            )

    def test_004_rr01_cannot_execute_protected_transition(self):
        action = (
            ProtectedTransition.CLAIM_QUALIFICATION
        )

        p = make_proposal(action)

        assignment = make_assignment(
            ReviewerRole.ANALYST_REVIEWER,
            action,
        )

        with self.assertRaises(
            UnauthorizedReviewerError
        ):
            self.dispose(
                p,
                ReviewerRole.ANALYST_REVIEWER,
                assignment=assignment,
            )

    def test_005_rr02_claim_qualification(self):
        self.dispose(
            make_proposal(
                ProtectedTransition
                .CLAIM_QUALIFICATION
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

    def test_006_rr02_issue_qualification(self):
        self.dispose(
            make_proposal(
                ProtectedTransition
                .ISSUE_QUALIFICATION
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

    def test_007_rr02_persistence_change(self):
        self.dispose(
            make_proposal(
                ProtectedTransition
                .PERSISTENCE_CLASS_CHANGE,
                proposed_state="RECURRING",
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

    def test_008_rr02_lifecycle_change(self):
        self.dispose(
            make_proposal(
                ProtectedTransition
                .LIFECYCLE_CHANGE,
                proposed_state="MONITORING",
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

    def test_009_strategic_issue_role_unbound(self):
        p = make_proposal(
            ProtectedTransition
            .STRATEGIC_ISSUE_QUALIFICATION
        )

        assignment = make_assignment(
            ReviewerRole.QUALIFYING_REVIEWER,
            p.proposed_action,
        )

        with self.assertRaises(
            UnauthorizedReviewerError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
                assignment=assignment,
                disposition=RD1,
            )

    def test_010_strategic_issue_can_request_authority_review(self):
        p = make_proposal(
            ProtectedTransition
            .STRATEGIC_ISSUE_QUALIFICATION
        )

        assignment = make_assignment(
            ReviewerRole.QUALIFYING_REVIEWER,
            p.proposed_action,
        )

        r = self.dispose(
            p,
            ReviewerRole.QUALIFYING_REVIEWER,
            assignment=assignment,
            disposition=RD6,
        )

        self.assertEqual(
            r.final_disposition,
            RD6,
        )

    def test_011_invalidation_role_unbound(self):
        p = make_proposal(
            ProtectedTransition.INVALIDATION
        )

        assignment = make_assignment(
            ReviewerRole.QUALIFYING_REVIEWER,
            p.proposed_action,
        )

        with self.assertRaises(
            UnauthorizedReviewerError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
                assignment=assignment,
                disposition=RD1,
            )

    def test_012_rights_mutation_blocked(self):
        p = make_proposal(
            ProtectedTransition
            .CLAIM_QUALIFICATION,
            rights_zone_change_requested=True,
        )

        with self.assertRaises(
            HardBoundaryViolation
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_013_governance_review_state_allowed(self):
        p = make_proposal(
            ProtectedTransition
            .CLAIM_QUALIFICATION,
            governance_review_required=True,
        )

        r = self.dispose(
            p,
            ReviewerRole.QUALIFYING_REVIEWER,
            disposition=RD5,
        )

        self.assertEqual(
            r.final_disposition,
            RD5,
        )

    def test_014_empirical_truth_by_review_blocked(self):
        p = make_proposal(
            ProtectedTransition
            .CLAIM_QUALIFICATION,
            empirical_truth_claimed_by_review=True,
        )

        with self.assertRaises(
            HardBoundaryViolation
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_015_institutional_action_blocked(self):
        action = (
            ProtectedTransition
            .ANALYTICAL_ESCALATION_APPROVAL
        )

        p = make_proposal(
            action,
            escalation_reference="ESC-1",
            institutional_action_requested=True,
        )

        with self.assertRaises(
            HardBoundaryViolation
        ):
            self.dispose(
                p,
                ReviewerRole
                .ANALYTICAL_ESCALATION_REVIEWER,
            )

    def test_016_insufficient_evidence_cannot_approve(self):
        p = make_proposal(
            ProtectedTransition
            .CLAIM_QUALIFICATION,
            evidence_sufficient=False,
        )

        with self.assertRaises(
            HardBoundaryViolation
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_017_additional_evidence_state_allowed(self):
        p = make_proposal(
            ProtectedTransition
            .CLAIM_QUALIFICATION,
            evidence_sufficient=False,
        )

        r = self.dispose(
            p,
            ReviewerRole.QUALIFYING_REVIEWER,
            disposition=RD4,
        )

        self.assertEqual(
            r.final_disposition,
            RD4,
        )

    def test_018_repetition_not_structural(self):
        p = make_proposal(
            ProtectedTransition
            .PERSISTENCE_CLASS_CHANGE,
            proposed_state="STRUCTURAL",
            publication_repetition_only=True,
        )

        with self.assertRaises(
            HardBoundaryViolation
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_019_coverage_failure_not_resolution(self):
        p = make_proposal(
            ProtectedTransition.LIFECYCLE_CHANGE,
            proposed_state=(
                "RESOLVED_OR_MITIGATED"
            ),
            coverage_failure_material=True,
        )

        with self.assertRaises(
            HardBoundaryViolation
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_020_merge_requires_identity_lineage(self):
        action = ProtectedTransition.ISSUE_MERGE

        p = make_proposal(
            action,
            affected_issue_ids=(
                "ISSUE-1",
                "ISSUE-2",
            ),
            identity_rationale="same identity",
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.IDENTITY_ADJUDICATOR,
            )

    def test_021_merge_approved_with_lineage(self):
        action = ProtectedTransition.ISSUE_MERGE

        r = self.dispose(
            make_proposal(
                action,
                affected_issue_ids=(
                    "ISSUE-1",
                    "ISSUE-2",
                ),
                predecessor_issue_ids=(
                    "ISSUE-1",
                    "ISSUE-2",
                ),
                successor_issue_ids=(
                    "ISSUE-3",
                ),
                identity_rationale=(
                    "shared canonical identity"
                ),
                temporal_context="same phenomenon",
                geographic_context="same geography",
            ),
            ReviewerRole.IDENTITY_ADJUDICATOR,
        )

        self.assertEqual(
            r.predecessor_issue_ids,
            ("ISSUE-1", "ISSUE-2"),
        )

    def test_022_split_approved_with_lineage(self):
        action = ProtectedTransition.ISSUE_SPLIT

        r = self.dispose(
            make_proposal(
                action,
                affected_issue_ids=("ISSUE-1",),
                predecessor_issue_ids=("ISSUE-1",),
                successor_issue_ids=(
                    "ISSUE-2",
                    "ISSUE-3",
                ),
                identity_rationale=(
                    "distinct mechanisms"
                ),
                temporal_context="same review window",
                geographic_context="same affected geography",
            ),
            ReviewerRole.IDENTITY_ADJUDICATOR,
        )

        self.assertEqual(
            len(r.successor_issue_ids),
            2,
        )

    def test_023_correction_requires_supersession(self):
        action = (
            ProtectedTransition.MATERIAL_CORRECTION
        )

        p = make_proposal(
            action,
            correction_reference="CORR-1",
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_024_correction_valid(self):
        action = (
            ProtectedTransition.MATERIAL_CORRECTION
        )

        r = self.dispose(
            make_proposal(
                action,
                supersedes_review_id="REV-OLD",
                correction_reference="CORR-1",
                proposed_state="CORRECTED_STATE",
                affected_downstream_outputs=(),
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

        self.assertEqual(
            r.supersedes_review_id,
            "REV-OLD",
        )

    def test_025_reopen_requires_evidence(self):
        p = make_proposal(
            ProtectedTransition.REOPEN,
            evidence_refs=(),
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_026_escalation_non_binding(self):
        action = (
            ProtectedTransition
            .ANALYTICAL_ESCALATION_APPROVAL
        )

        r = self.dispose(
            make_proposal(
                action,
                escalation_reference="ESC-1",
            ),
            ReviewerRole
            .ANALYTICAL_ESCALATION_REVIEWER,
        )

        self.assertTrue(
            r.analytical_only
        )
        self.assertFalse(
            r.binding_institutional_action_authorized
        )

    def test_027_referral_non_binding(self):
        action = (
            ProtectedTransition
            .COORDINATION_OR_REFERRAL_APPROVAL
        )

        r = self.dispose(
            make_proposal(
                action,
                escalation_reference="REF-1",
            ),
            ReviewerRole
            .ANALYTICAL_ESCALATION_REVIEWER,
        )

        self.assertFalse(
            r.binding_institutional_action_authorized
        )

    def test_028_open_disagreement_visible(self):
        action = (
            ProtectedTransition.ISSUE_QUALIFICATION
        )

        r = self.dispose(
            make_proposal(
                action,
                disagreement_state=(
                    DisagreementState
                    .DISAGREEMENT_OPEN
                ),
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
            disposition=RD7,
        )

        self.assertEqual(
            r.disagreement_state,
            DisagreementState.DISAGREEMENT_OPEN,
        )

    def test_029_adjudication_preserves_dissent(self):
        action = (
            ProtectedTransition.ISSUE_QUALIFICATION
        )

        p = make_proposal(
            action,
            disagreement_state=(
                DisagreementState
                .ADJUDICATED_WITH_DISSENT_RETAINED
            ),
            prior_review_ids=(
                "REV-A",
                "REV-B",
            ),
            dissenting_view=(
                "minority interpretation retained"
            ),
        )

        assignment = make_assignment(
            ReviewerRole.REVIEW_ADJUDICATOR,
            action,
        )

        r = self.dispose(
            p,
            ReviewerRole.REVIEW_ADJUDICATOR,
            assignment=assignment,
            disposition=RD8,
        )

        self.assertEqual(
            r.final_disposition,
            RD8,
        )
        self.assertTrue(
            r.dissenting_view
        )

    def test_030_adjudication_requires_prior_reviews(self):
        action = (
            ProtectedTransition.ISSUE_QUALIFICATION
        )

        p = make_proposal(
            action,
            disagreement_state=(
                DisagreementState
                .ADJUDICATED_WITH_CONSENSUS
            ),
        )

        assignment = make_assignment(
            ReviewerRole.REVIEW_ADJUDICATOR,
            action,
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.REVIEW_ADJUDICATOR,
                assignment=assignment,
                disposition=RD8,
            )


class HistoryTests(unittest.TestCase):

    def make_record(self):
        kernel = (
            HumanReviewAuthorityKernel()
        )

        action = (
            ProtectedTransition.CLAIM_QUALIFICATION
        )

        proposal = make_proposal(action)

        return kernel.dispose(
            review_id="REV-PIT",
            proposal=proposal,
            assignment=make_assignment(
                ReviewerRole.QUALIFYING_REVIEWER,
                action,
            ),
            reviewer_id_or_role_reference=(
                "REVIEWER-1"
            ),
            reviewer_authority_role=(
                ReviewerRole.QUALIFYING_REVIEWER
            ),
            reviewer_actor_type=(
                ReviewActorType.HUMAN
            ),
            final_disposition=RD1,
            decided_at=dt(3),
        )

    def test_031_history_append_only(self):
        history = ReviewHistory()
        r1 = self.make_record()

        history.append(
            review_id="REV-PIT",
            version=1,
            recorded_at=dt(3),
            record=r1,
        )

        self.assertEqual(
            len(
                history.history("REV-PIT")
            ),
            1,
        )

    def test_032_history_sequential_versions(self):
        history = ReviewHistory()
        r1 = self.make_record()

        with self.assertRaises(ValueError):
            history.append(
                review_id="REV-PIT",
                version=2,
                recorded_at=dt(3),
                record=r1,
            )

    def test_033_pit_excludes_later_correction(self):
        history = ReviewHistory()

        r1 = self.make_record()

        r2 = replace(
            r1,
            final_disposition=RD2,
            rationale="later correction",
            correction_reference="CORR-LATER",
        )

        history.append(
            review_id="REV-PIT",
            version=1,
            recorded_at=dt(3),
            record=r1,
        )

        history.append(
            review_id="REV-PIT",
            version=2,
            recorded_at=dt(10),
            record=r2,
        )

        at_5 = history.as_of(
            "REV-PIT",
            dt(5),
        )

        at_11 = history.as_of(
            "REV-PIT",
            dt(11),
        )

        self.assertEqual(
            at_5.version,
            1,
        )

        self.assertEqual(
            at_11.version,
            2,
        )

        self.assertEqual(
            at_5.record.final_disposition,
            RD1,
        )

        self.assertEqual(
            at_11.record.final_disposition,
            RD2,
        )

    def test_034_no_delete_api(self):
        self.assertFalse(
            hasattr(
                ReviewHistory,
                "delete",
            )
        )

    def test_035_kernel_has_no_transition_apply_api(self):
        self.assertFalse(
            hasattr(
                HumanReviewAuthorityKernel,
                "apply_transition",
            )
        )

    def test_036_kernel_has_no_rights_mutation_api(self):
        self.assertFalse(
            hasattr(
                HumanReviewAuthorityKernel,
                "change_rights_state",
            )
        )



class ContractClosureR1Tests(unittest.TestCase):

    def setUp(self) -> None:
        self.kernel = HumanReviewAuthorityKernel()

    def dispose(
        self,
        proposal,
        role,
        *,
        disposition=RD1,
    ):
        return self.kernel.dispose(
            review_id="REV-R1",
            proposal=proposal,
            assignment=make_assignment(
                role,
                proposal.proposed_action,
            ),
            reviewer_id_or_role_reference=(
                "REVIEWER-1"
            ),
            reviewer_authority_role=role,
            reviewer_actor_type=(
                ReviewActorType.HUMAN
            ),
            final_disposition=disposition,
            decided_at=dt(3),
        )

    def test_037_proposed_state_is_preserved(self):
        r = self.dispose(
            make_proposal(
                ProtectedTransition
                .CLAIM_QUALIFICATION,
                proposed_state=(
                    "SUPPORTED_WITH_LIMITATIONS"
                ),
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

        self.assertEqual(
            r.proposed_state,
            "SUPPORTED_WITH_LIMITATIONS",
        )

    def test_038_merge_requires_temporal_context(self):
        p = make_proposal(
            ProtectedTransition.ISSUE_MERGE,
            affected_issue_ids=(
                "ISSUE-1",
                "ISSUE-2",
            ),
            predecessor_issue_ids=(
                "ISSUE-1",
                "ISSUE-2",
            ),
            successor_issue_ids=("ISSUE-3",),
            identity_rationale="same identity",
            temporal_context=None,
            geographic_context="Yogyakarta",
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.IDENTITY_ADJUDICATOR,
            )

    def test_039_merge_requires_geographic_context(self):
        p = make_proposal(
            ProtectedTransition.ISSUE_MERGE,
            affected_issue_ids=(
                "ISSUE-1",
                "ISSUE-2",
            ),
            predecessor_issue_ids=(
                "ISSUE-1",
                "ISSUE-2",
            ),
            successor_issue_ids=("ISSUE-3",),
            identity_rationale="same identity",
            temporal_context="same review window",
            geographic_context=None,
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.IDENTITY_ADJUDICATOR,
            )

    def test_040_split_requires_context(self):
        p = make_proposal(
            ProtectedTransition.ISSUE_SPLIT,
            affected_issue_ids=("ISSUE-1",),
            predecessor_issue_ids=("ISSUE-1",),
            successor_issue_ids=(
                "ISSUE-2",
                "ISSUE-3",
            ),
            identity_rationale="distinct mechanisms",
            temporal_context="same review window",
            geographic_context=None,
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.IDENTITY_ADJUDICATOR,
            )

    def test_041_correction_requires_downstream_assessment(self):
        p = make_proposal(
            ProtectedTransition
            .MATERIAL_CORRECTION,
            proposed_state="CORRECTED_STATE",
            supersedes_review_id="REV-OLD",
            correction_reference="CORR-1",
            affected_downstream_outputs=None,
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_042_correction_preserves_downstream_outputs(self):
        r = self.dispose(
            make_proposal(
                ProtectedTransition
                .MATERIAL_CORRECTION,
                proposed_state="CORRECTED_STATE",
                supersedes_review_id="REV-OLD",
                correction_reference="CORR-1",
                affected_downstream_outputs=(
                    "OUT-1",
                    "OUT-2",
                ),
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

        self.assertEqual(
            r.proposed_state,
            "CORRECTED_STATE",
        )

        self.assertEqual(
            r.affected_downstream_outputs,
            (
                "OUT-1",
                "OUT-2",
            ),
        )

    def test_043_foresight_approval_requires_framework(self):
        p = make_proposal(
            ProtectedTransition
            .CLAIM_QUALIFICATION,
            reviewed_object_type=(
                "STRATEGIC_HYPOTHESIS"
            ),
            evidence_refs=("EV-SUPPORT",),
            contradictory_evidence_refs=(
                "EV-CONTRA",
            ),
        )

        with self.assertRaises(
            DispositionConstraintError
        ):
            self.dispose(
                p,
                ReviewerRole.QUALIFYING_REVIEWER,
            )

    def test_044_foresight_framework_is_preserved(self):
        r = self.dispose(
            make_proposal(
                ProtectedTransition
                .CLAIM_QUALIFICATION,
                reviewed_object_type=(
                    "STRATEGIC_HYPOTHESIS"
                ),
                evidence_refs=("EV-SUPPORT",),
                contradictory_evidence_refs=(
                    "EV-CONTRA",
                ),
                foresight_assumptions=(
                    "ASSUMPTION-1",
                ),
                alternative_hypotheses=(
                    "ALT-HYPOTHESIS-1",
                ),
                falsification_conditions=(
                    "FALSIFIER-1",
                ),
            ),
            ReviewerRole.QUALIFYING_REVIEWER,
        )

        self.assertEqual(
            r.foresight_assumptions,
            ("ASSUMPTION-1",),
        )

        self.assertEqual(
            r.alternative_hypotheses,
            ("ALT-HYPOTHESIS-1",),
        )

        self.assertEqual(
            r.falsification_conditions,
            ("FALSIFIER-1",),
        )

        self.assertEqual(
            r.contradictory_evidence_refs,
            ("EV-CONTRA",),
        )

        self.assertFalse(
            r.empirical_truth_established_by_review
        )


if __name__ == "__main__":
    unittest.main()
