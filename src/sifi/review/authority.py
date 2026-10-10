"""Deterministic SIFI human-review authority kernel."""

from __future__ import annotations

from datetime import datetime

from sifi.domain import ReviewDisposition

from .model import (
    AuthorityAssignment,
    DisagreementState,
    ProtectedTransition,
    ReviewActorType,
    ReviewProposal,
    ReviewRecord,
    ReviewerRole,
)


RD_APPROVED = ReviewDisposition("RD-01")
RD_REJECTED = ReviewDisposition("RD-02")
RD_DEFERRED = ReviewDisposition("RD-03")
RD_ADDITIONAL_EVIDENCE_REQUIRED = ReviewDisposition(
    "RD-04"
)
RD_GOVERNANCE_REVIEW_REQUIRED = ReviewDisposition(
    "RD-05"
)
RD_AUTHORITY_REVIEW_REQUIRED = ReviewDisposition(
    "RD-06"
)
RD_DISAGREEMENT_OPEN = ReviewDisposition("RD-07")
RD_ADJUDICATED = ReviewDisposition("RD-08")
RD_ABSTAINED = ReviewDisposition("RD-09")


class ReviewAuthorityError(Exception):
    pass


class UnauthorizedReviewerError(
    ReviewAuthorityError
):
    pass


class HardBoundaryViolation(
    ReviewAuthorityError
):
    pass


class DispositionConstraintError(
    ReviewAuthorityError
):
    pass


# Only bindings explicitly supported by recovered S0.5D role text.
#
# T-03 STRATEGIC_ISSUE_QUALIFICATION and T-09 INVALIDATION
# deliberately remain unbound here. S1.5B refuses final approval for
# those transitions until explicit governance assigns authority.
EXPLICIT_TRANSITION_ROLE = {
    ProtectedTransition.CLAIM_QUALIFICATION:
        ReviewerRole.QUALIFYING_REVIEWER,
    ProtectedTransition.ISSUE_QUALIFICATION:
        ReviewerRole.QUALIFYING_REVIEWER,
    ProtectedTransition.PERSISTENCE_CLASS_CHANGE:
        ReviewerRole.QUALIFYING_REVIEWER,
    ProtectedTransition.LIFECYCLE_CHANGE:
        ReviewerRole.QUALIFYING_REVIEWER,
    ProtectedTransition.ISSUE_MERGE:
        ReviewerRole.IDENTITY_ADJUDICATOR,
    ProtectedTransition.ISSUE_SPLIT:
        ReviewerRole.IDENTITY_ADJUDICATOR,
    ProtectedTransition.MATERIAL_CORRECTION:
        ReviewerRole.QUALIFYING_REVIEWER,
    ProtectedTransition.REOPEN:
        ReviewerRole.QUALIFYING_REVIEWER,
    ProtectedTransition.ANALYTICAL_ESCALATION_APPROVAL:
        ReviewerRole.ANALYTICAL_ESCALATION_REVIEWER,
    ProtectedTransition.COORDINATION_OR_REFERRAL_APPROVAL:
        ReviewerRole.ANALYTICAL_ESCALATION_REVIEWER,
}


UNBOUND_STAGE0_ROLE_TRANSITIONS = {
    ProtectedTransition.STRATEGIC_ISSUE_QUALIFICATION,
    ProtectedTransition.INVALIDATION,
}


OPEN_DISAGREEMENT = {
    DisagreementState.DISAGREEMENT_OPEN,
    DisagreementState.SECOND_REVIEW_REQUESTED,
    DisagreementState.ADJUDICATION_REQUIRED,
}


ADJUDICATED_DISAGREEMENT = {
    DisagreementState.ADJUDICATED_WITH_CONSENSUS,
    DisagreementState.ADJUDICATED_WITH_DISSENT_RETAINED,
}


class HumanReviewAuthorityKernel:
    """Create an authorized analytical ReviewRecord.

    Important:
    This does not mutate Claim, Issue, lifecycle, rights,
    institutional action, or any production object.
    """

    def dispose(
        self,
        *,
        review_id: str,
        proposal: ReviewProposal,
        assignment: AuthorityAssignment,
        reviewer_id_or_role_reference: str,
        reviewer_authority_role: ReviewerRole,
        reviewer_actor_type: ReviewActorType,
        final_disposition: ReviewDisposition,
        decided_at: datetime,
    ) -> ReviewRecord:

        self._check_explicit_authority(
            proposal=proposal,
            assignment=assignment,
            reviewer_reference=(
                reviewer_id_or_role_reference
            ),
            reviewer_role=reviewer_authority_role,
            reviewer_actor_type=reviewer_actor_type,
            final_disposition=final_disposition,
            decided_at=decided_at,
        )

        self._check_hard_boundaries(
            proposal=proposal,
            final_disposition=final_disposition,
        )

        self._check_disagreement(
            proposal=proposal,
            reviewer_role=reviewer_authority_role,
            final_disposition=final_disposition,
        )

        self._check_foresight_preservation(
            proposal=proposal,
            final_disposition=final_disposition,
        )

        self._check_transition_specific(
            proposal=proposal,
            final_disposition=final_disposition,
        )

        return ReviewRecord(
            review_id=review_id,
            reviewed_object_type=(
                proposal.reviewed_object_type
            ),
            reviewed_object_id=(
                proposal.reviewed_object_id
            ),
            reviewed_object_version=(
                proposal.reviewed_object_version
            ),
            reviewer_id_or_role_reference=(
                reviewer_id_or_role_reference
            ),
            reviewer_authority_role=(
                reviewer_authority_role
            ),
            proposed_action=proposal.proposed_action,
            proposed_state=proposal.proposed_state,
            final_disposition=final_disposition,
            authority_basis=assignment.authority_basis,
            evidence_refs=proposal.evidence_refs,
            contradictory_evidence_refs=(
                proposal.contradictory_evidence_refs
            ),
            coverage_limitations=(
                proposal.coverage_limitations
            ),
            rights_or_privacy_constraints=(
                proposal.rights_or_privacy_constraints
            ),
            rationale=proposal.rationale,
            uncertainty_state=proposal.uncertainty_state,
            disagreement_state=(
                proposal.disagreement_state
            ),
            review_started_at=proposal.proposed_at,
            decided_at=decided_at,
            analytical_cutoff=proposal.analytical_cutoff,
            supersedes_review_id=(
                proposal.supersedes_review_id
            ),
            correction_reference=(
                proposal.correction_reference
            ),
            escalation_reference=(
                proposal.escalation_reference
            ),
            review_notes=proposal.review_notes,
            affected_downstream_outputs=(
                proposal.affected_downstream_outputs
            ),
            foresight_assumptions=(
                proposal.foresight_assumptions
            ),
            alternative_hypotheses=(
                proposal.alternative_hypotheses
            ),
            falsification_conditions=(
                proposal.falsification_conditions
            ),
            prior_review_ids=proposal.prior_review_ids,
            dissenting_view=proposal.dissenting_view,
            affected_issue_ids=(
                proposal.affected_issue_ids
            ),
            predecessor_issue_ids=(
                proposal.predecessor_issue_ids
            ),
            successor_issue_ids=(
                proposal.successor_issue_ids
            ),
            identity_rationale=(
                proposal.identity_rationale
            ),
            temporal_context=proposal.temporal_context,
            geographic_context=proposal.geographic_context,
            analytical_only=True,
            rights_state_mutated=False,
            empirical_truth_established_by_review=False,
            binding_institutional_action_authorized=False,
        )

    @staticmethod
    def _check_explicit_authority(
        *,
        proposal: ReviewProposal,
        assignment: AuthorityAssignment,
        reviewer_reference: str,
        reviewer_role: ReviewerRole,
        reviewer_actor_type: ReviewActorType,
        final_disposition: ReviewDisposition,
        decided_at: datetime,
    ) -> None:

        if reviewer_actor_type != ReviewActorType.HUMAN:
            raise UnauthorizedReviewerError(
                "AI may propose but cannot issue a final "
                "protected disposition"
            )

        if (
            assignment.reviewer_id_or_role_reference
            != reviewer_reference
        ):
            raise UnauthorizedReviewerError(
                "authority assignment does not belong "
                "to reviewer"
            )

        if not assignment.is_active(decided_at):
            raise UnauthorizedReviewerError(
                "authority assignment inactive at decision"
            )

        if reviewer_role not in assignment.roles:
            raise UnauthorizedReviewerError(
                "reviewer role was not explicitly assigned"
            )

        if (
            proposal.proposed_action
            not in assignment.authorized_transitions
        ):
            raise UnauthorizedReviewerError(
                "protected transition was not explicitly "
                "assigned"
            )

        if final_disposition == RD_ADJUDICATED:
            if (
                reviewer_role
                != ReviewerRole.REVIEW_ADJUDICATOR
            ):
                raise UnauthorizedReviewerError(
                    "RD-08 requires RR-06"
                )
            return

        action = proposal.proposed_action

        if action in UNBOUND_STAGE0_ROLE_TRANSITIONS:
            if final_disposition not in {
                RD_REJECTED,
                RD_DEFERRED,
                RD_AUTHORITY_REVIEW_REQUIRED,
                RD_ABSTAINED,
            }:
                raise UnauthorizedReviewerError(
                    f"{action.value} has no explicit Stage0 "
                    "role binding; final approval is held "
                    "pending authority governance"
                )
            return

        required_role = EXPLICIT_TRANSITION_ROLE[
            action
        ]

        if reviewer_role != required_role:
            raise UnauthorizedReviewerError(
                f"{action.value} requires "
                f"{required_role.value}"
            )

    @staticmethod
    def _check_hard_boundaries(
        *,
        proposal: ReviewProposal,
        final_disposition: ReviewDisposition,
    ) -> None:

        approval_like = {
            RD_APPROVED,
            RD_ADJUDICATED,
        }

        if (
            proposal.rights_zone_change_requested
            and final_disposition in approval_like
        ):
            raise HardBoundaryViolation(
                "review cannot convert or mutate "
                "Z0/Z1/Z2/Z3 rights state"
            )

        if (
            proposal.institutional_action_requested
            and final_disposition in approval_like
        ):
            raise HardBoundaryViolation(
                "analytical review cannot authorize "
                "binding institutional action"
            )

        if (
            proposal.empirical_truth_claimed_by_review
            and final_disposition in approval_like
        ):
            raise HardBoundaryViolation(
                "human review is not empirical validation"
            )

        if proposal.governance_review_required:
            permitted = {
                RD_REJECTED,
                RD_DEFERRED,
                RD_GOVERNANCE_REVIEW_REQUIRED,
                RD_ABSTAINED,
            }

            if final_disposition not in permitted:
                raise HardBoundaryViolation(
                    "rights/privacy/admission matter requires "
                    "governance review"
                )

        if proposal.authority_review_required:
            permitted = {
                RD_REJECTED,
                RD_DEFERRED,
                RD_AUTHORITY_REVIEW_REQUIRED,
                RD_ABSTAINED,
            }

            if final_disposition not in permitted:
                raise HardBoundaryViolation(
                    "unresolved legal/institutional authority "
                    "requires authority review"
                )

        if (
            not proposal.evidence_sufficient
            and final_disposition == RD_APPROVED
        ):
            raise HardBoundaryViolation(
                "insufficient evidence cannot support approval"
            )

    @staticmethod
    def _check_disagreement(
        *,
        proposal: ReviewProposal,
        reviewer_role: ReviewerRole,
        final_disposition: ReviewDisposition,
    ) -> None:

        if final_disposition == RD_DISAGREEMENT_OPEN:
            if (
                proposal.disagreement_state
                not in OPEN_DISAGREEMENT
            ):
                raise DispositionConstraintError(
                    "RD-07 requires explicit unresolved "
                    "disagreement"
                )

        if final_disposition == RD_ADJUDICATED:
            if (
                reviewer_role
                != ReviewerRole.REVIEW_ADJUDICATOR
            ):
                raise UnauthorizedReviewerError(
                    "adjudication requires RR-06"
                )

            if (
                proposal.disagreement_state
                not in ADJUDICATED_DISAGREEMENT
            ):
                raise DispositionConstraintError(
                    "RD-08 requires adjudicated "
                    "disagreement state"
                )

            if not proposal.prior_review_ids:
                raise DispositionConstraintError(
                    "adjudication requires prior review IDs"
                )

        if (
            proposal.disagreement_state
            == (
                DisagreementState
                .ADJUDICATED_WITH_DISSENT_RETAINED
            )
        ):
            if (
                proposal.dissenting_view is None
                or not proposal.dissenting_view.strip()
            ):
                raise DispositionConstraintError(
                    "material dissent must remain recorded"
                )

    @staticmethod
    def _check_foresight_preservation(
        *,
        proposal: ReviewProposal,
        final_disposition: ReviewDisposition,
    ) -> None:

        normalized_type = (
            proposal.reviewed_object_type
            .strip()
            .upper()
            .replace("-", "_")
            .replace(" ", "_")
        )

        if normalized_type not in {
            "STRATEGIC_HYPOTHESIS",
            "OT05",
            "OT_05",
        }:
            return

        if final_disposition not in {
            RD_APPROVED,
            RD_ADJUDICATED,
        }:
            return

        if not proposal.foresight_assumptions:
            raise DispositionConstraintError(
                "Strategic Hypothesis review must "
                "preserve assumptions"
            )

        if not proposal.alternative_hypotheses:
            raise DispositionConstraintError(
                "Strategic Hypothesis review must "
                "preserve alternatives"
            )

        if not proposal.falsification_conditions:
            raise DispositionConstraintError(
                "Strategic Hypothesis review must "
                "preserve falsification conditions"
            )

    @staticmethod
    def _check_transition_specific(
        *,
        proposal: ReviewProposal,
        final_disposition: ReviewDisposition,
    ) -> None:

        if final_disposition != RD_APPROVED:
            return

        action = proposal.proposed_action
        state = (
            proposal.proposed_state or ""
        ).upper()

        if (
            action
            == ProtectedTransition.PERSISTENCE_CLASS_CHANGE
            and state in {"PC-03", "STRUCTURAL"}
            and proposal.publication_repetition_only
        ):
            raise HardBoundaryViolation(
                "publication repetition alone cannot "
                "establish STRUCTURAL persistence"
            )

        if (
            action == ProtectedTransition.LIFECYCLE_CHANGE
            and "RESOLVED" in state
            and proposal.coverage_failure_material
        ):
            raise HardBoundaryViolation(
                "coverage failure cannot justify "
                "Issue resolution"
            )

        if action == ProtectedTransition.ISSUE_MERGE:
            if len(proposal.affected_issue_ids) < 2:
                raise DispositionConstraintError(
                    "merge requires >= 2 affected Issue IDs"
                )

            if not proposal.identity_rationale:
                raise DispositionConstraintError(
                    "merge requires identity rationale"
                )

            if not proposal.evidence_refs:
                raise DispositionConstraintError(
                    "merge requires supporting Evidence Items"
                )

            if not proposal.predecessor_issue_ids:
                raise DispositionConstraintError(
                    "merge requires predecessor lineage"
                )

            if not proposal.successor_issue_ids:
                raise DispositionConstraintError(
                    "merge requires successor lineage"
                )

            if (
                proposal.temporal_context is None
                or not proposal.temporal_context.strip()
            ):
                raise DispositionConstraintError(
                    "merge requires temporal context"
                )

            if (
                proposal.geographic_context is None
                or not proposal.geographic_context.strip()
            ):
                raise DispositionConstraintError(
                    "merge requires geographic context"
                )

        if action == ProtectedTransition.ISSUE_SPLIT:
            if not proposal.affected_issue_ids:
                raise DispositionConstraintError(
                    "split requires affected Issue IDs"
                )

            if not proposal.identity_rationale:
                raise DispositionConstraintError(
                    "split requires identity rationale"
                )

            if not proposal.evidence_refs:
                raise DispositionConstraintError(
                    "split requires supporting Evidence Items"
                )

            if not proposal.predecessor_issue_ids:
                raise DispositionConstraintError(
                    "split requires predecessor lineage"
                )

            if len(proposal.successor_issue_ids) < 2:
                raise DispositionConstraintError(
                    "split requires >= 2 successors"
                )

            if (
                proposal.temporal_context is None
                or not proposal.temporal_context.strip()
            ):
                raise DispositionConstraintError(
                    "split requires temporal context"
                )

            if (
                proposal.geographic_context is None
                or not proposal.geographic_context.strip()
            ):
                raise DispositionConstraintError(
                    "split requires geographic context"
                )

        if (
            action
            == ProtectedTransition.MATERIAL_CORRECTION
        ):
            if not proposal.supersedes_review_id:
                raise DispositionConstraintError(
                    "correction requires superseded review ID"
                )

            if not proposal.correction_reference:
                raise DispositionConstraintError(
                    "correction requires correction reference"
                )

            if (
                proposal.proposed_state is None
                or not proposal.proposed_state.strip()
            ):
                raise DispositionConstraintError(
                    "correction requires corrected state"
                )

            if (
                proposal.affected_downstream_outputs
                is None
            ):
                raise DispositionConstraintError(
                    "correction requires explicit assessment "
                    "of affected downstream outputs"
                )

        if action == ProtectedTransition.REOPEN:
            if not proposal.evidence_refs:
                raise DispositionConstraintError(
                    "reopen requires material evidence"
                )

        if action in {
            (
                ProtectedTransition
                .ANALYTICAL_ESCALATION_APPROVAL
            ),
            (
                ProtectedTransition
                .COORDINATION_OR_REFERRAL_APPROVAL
            ),
        }:
            if not proposal.escalation_reference:
                raise DispositionConstraintError(
                    "escalation/referral requires reference"
                )
