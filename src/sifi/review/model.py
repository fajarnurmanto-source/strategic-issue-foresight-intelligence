"""SIFI human-review authority domain model.

Controlled non-production implementation.

This module does not:
- grant source-rights authority;
- establish empirical truth;
- authorize institutional action;
- apply protected transitions automatically.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from enum import StrEnum

from sifi.domain import ReviewDisposition


def require_nonblank(name: str, value: str) -> None:
    if not isinstance(value, str) or not value.strip():
        raise ValueError(f"{name} must be nonblank")


def require_aware(name: str, value: datetime) -> None:
    if (
        value.tzinfo is None
        or value.utcoffset() is None
    ):
        raise ValueError(
            f"{name} must be timezone-aware"
        )


def require_unique(
    name: str,
    values: tuple[str, ...],
) -> None:
    if len(values) != len(set(values)):
        raise ValueError(
            f"{name} must not contain duplicates"
        )


class ReviewerRole(StrEnum):
    ANALYST_REVIEWER = "RR-01"
    QUALIFYING_REVIEWER = "RR-02"
    IDENTITY_ADJUDICATOR = "RR-03"
    GOVERNANCE_REVIEWER = "RR-04"
    ANALYTICAL_ESCALATION_REVIEWER = "RR-05"
    REVIEW_ADJUDICATOR = "RR-06"


class ProtectedTransition(StrEnum):
    CLAIM_QUALIFICATION = "T-01"
    ISSUE_QUALIFICATION = "T-02"
    STRATEGIC_ISSUE_QUALIFICATION = "T-03"
    PERSISTENCE_CLASS_CHANGE = "T-04"
    LIFECYCLE_CHANGE = "T-05"
    ISSUE_MERGE = "T-06"
    ISSUE_SPLIT = "T-07"
    MATERIAL_CORRECTION = "T-08"
    INVALIDATION = "T-09"
    REOPEN = "T-10"
    ANALYTICAL_ESCALATION_APPROVAL = "T-11"
    COORDINATION_OR_REFERRAL_APPROVAL = "T-12"


class ReviewActorType(StrEnum):
    HUMAN = "HUMAN"
    AI = "AI"


class DisagreementState(StrEnum):
    NO_MATERIAL_DISAGREEMENT = (
        "NO_MATERIAL_DISAGREEMENT"
    )
    DISAGREEMENT_OPEN = "DISAGREEMENT_OPEN"
    SECOND_REVIEW_REQUESTED = (
        "SECOND_REVIEW_REQUESTED"
    )
    ADJUDICATION_REQUIRED = (
        "ADJUDICATION_REQUIRED"
    )
    ADJUDICATED_WITH_CONSENSUS = (
        "ADJUDICATED_WITH_CONSENSUS"
    )
    ADJUDICATED_WITH_DISSENT_RETAINED = (
        "ADJUDICATED_WITH_DISSENT_RETAINED"
    )


@dataclass(frozen=True, slots=True)
class AuthorityAssignment:
    assignment_id: str
    reviewer_id_or_role_reference: str
    roles: tuple[ReviewerRole, ...]
    authorized_transitions: tuple[
        ProtectedTransition,
        ...
    ]
    authority_basis: str
    effective_from: datetime
    effective_to: datetime | None = None

    def __post_init__(self) -> None:
        require_nonblank(
            "assignment_id",
            self.assignment_id,
        )
        require_nonblank(
            "reviewer_id_or_role_reference",
            self.reviewer_id_or_role_reference,
        )
        require_nonblank(
            "authority_basis",
            self.authority_basis,
        )

        require_aware(
            "effective_from",
            self.effective_from,
        )

        if self.effective_to is not None:
            require_aware(
                "effective_to",
                self.effective_to,
            )

            if self.effective_to < self.effective_from:
                raise ValueError(
                    "effective_to precedes effective_from"
                )

        if not self.roles:
            raise ValueError(
                "at least one explicit reviewer role required"
            )

        if len(self.roles) != len(set(self.roles)):
            raise ValueError(
                "duplicate reviewer roles forbidden"
            )

        if len(
            self.authorized_transitions
        ) != len(
            set(self.authorized_transitions)
        ):
            raise ValueError(
                "duplicate authorized transitions forbidden"
            )

    def is_active(
        self,
        at: datetime,
    ) -> bool:
        require_aware("at", at)

        if at < self.effective_from:
            return False

        if (
            self.effective_to is not None
            and at > self.effective_to
        ):
            return False

        return True


@dataclass(frozen=True, slots=True)
class ReviewProposal:
    proposal_id: str

    reviewed_object_type: str
    reviewed_object_id: str
    reviewed_object_version: int

    proposed_action: ProtectedTransition
    proposed_state: str | None

    proposer_actor_type: ReviewActorType
    proposer_reference: str

    rationale: str

    evidence_refs: tuple[str, ...]
    contradictory_evidence_refs: tuple[str, ...]

    coverage_limitations: tuple[str, ...]
    rights_or_privacy_constraints: tuple[str, ...]

    uncertainty_state: str
    disagreement_state: DisagreementState

    proposed_at: datetime
    analytical_cutoff: datetime

    prior_review_ids: tuple[str, ...] = ()
    dissenting_view: str | None = None

    supersedes_review_id: str | None = None
    correction_reference: str | None = None
    escalation_reference: str | None = None

    affected_issue_ids: tuple[str, ...] = ()
    predecessor_issue_ids: tuple[str, ...] = ()
    successor_issue_ids: tuple[str, ...] = ()

    identity_rationale: str | None = None
    temporal_context: str | None = None
    geographic_context: str | None = None

    review_notes: tuple[str, ...] = ()

    affected_downstream_outputs: (
        tuple[str, ...] | None
    ) = None

    foresight_assumptions: tuple[str, ...] = ()
    alternative_hypotheses: tuple[str, ...] = ()
    falsification_conditions: tuple[str, ...] = ()

    evidence_sufficient: bool = True
    governance_review_required: bool = False
    authority_review_required: bool = False

    publication_repetition_only: bool = False
    coverage_failure_material: bool = False

    rights_zone_change_requested: bool = False
    institutional_action_requested: bool = False
    empirical_truth_claimed_by_review: bool = False

    def __post_init__(self) -> None:
        for name, value in (
            ("proposal_id", self.proposal_id),
            (
                "reviewed_object_type",
                self.reviewed_object_type,
            ),
            (
                "reviewed_object_id",
                self.reviewed_object_id,
            ),
            (
                "proposer_reference",
                self.proposer_reference,
            ),
            ("rationale", self.rationale),
            (
                "uncertainty_state",
                self.uncertainty_state,
            ),
        ):
            require_nonblank(name, value)

        if self.reviewed_object_version < 1:
            raise ValueError(
                "reviewed_object_version must be >= 1"
            )

        require_aware(
            "proposed_at",
            self.proposed_at,
        )
        require_aware(
            "analytical_cutoff",
            self.analytical_cutoff,
        )

        if self.analytical_cutoff > self.proposed_at:
            raise ValueError(
                "analytical_cutoff later than proposal"
            )

        for name, values in (
            (
                "evidence_refs",
                self.evidence_refs,
            ),
            (
                "contradictory_evidence_refs",
                self.contradictory_evidence_refs,
            ),
            (
                "prior_review_ids",
                self.prior_review_ids,
            ),
            (
                "affected_issue_ids",
                self.affected_issue_ids,
            ),
            (
                "predecessor_issue_ids",
                self.predecessor_issue_ids,
            ),
            (
                "successor_issue_ids",
                self.successor_issue_ids,
            ),
        ):
            require_unique(name, values)

        overlap = (
            set(self.evidence_refs)
            & set(self.contradictory_evidence_refs)
        )

        if overlap:
            raise ValueError(
                "supporting and contradictory evidence "
                "must remain distinct"
            )


@dataclass(frozen=True, slots=True)
class ReviewRecord:
    # S0.5D minimum Review Record Contract.
    review_id: str
    reviewed_object_type: str
    reviewed_object_id: str
    reviewed_object_version: int
    reviewer_id_or_role_reference: str
    reviewer_authority_role: ReviewerRole
    proposed_action: ProtectedTransition
    proposed_state: str | None
    final_disposition: ReviewDisposition
    authority_basis: str
    evidence_refs: tuple[str, ...]
    contradictory_evidence_refs: tuple[str, ...]
    coverage_limitations: tuple[str, ...]
    rights_or_privacy_constraints: tuple[str, ...]
    rationale: str
    uncertainty_state: str
    disagreement_state: DisagreementState
    review_started_at: datetime
    decided_at: datetime
    analytical_cutoff: datetime
    supersedes_review_id: str | None
    correction_reference: str | None
    escalation_reference: str | None
    review_notes: tuple[str, ...]

    # S0.5D correction / foresight preservation.
    affected_downstream_outputs: (
        tuple[str, ...] | None
    ) = None
    foresight_assumptions: tuple[str, ...] = ()
    alternative_hypotheses: tuple[str, ...] = ()
    falsification_conditions: tuple[str, ...] = ()

    # Additional audit-preservation fields.
    prior_review_ids: tuple[str, ...] = ()
    dissenting_view: str | None = None

    affected_issue_ids: tuple[str, ...] = ()
    predecessor_issue_ids: tuple[str, ...] = ()
    successor_issue_ids: tuple[str, ...] = ()

    identity_rationale: str | None = None
    temporal_context: str | None = None
    geographic_context: str | None = None

    analytical_only: bool = True
    rights_state_mutated: bool = False
    empirical_truth_established_by_review: bool = False
    binding_institutional_action_authorized: bool = False

    def __post_init__(self) -> None:
        for name, value in (
            ("review_id", self.review_id),
            (
                "reviewed_object_type",
                self.reviewed_object_type,
            ),
            (
                "reviewed_object_id",
                self.reviewed_object_id,
            ),
            (
                "reviewer_id_or_role_reference",
                self.reviewer_id_or_role_reference,
            ),
            (
                "authority_basis",
                self.authority_basis,
            ),
            ("rationale", self.rationale),
            (
                "uncertainty_state",
                self.uncertainty_state,
            ),
        ):
            require_nonblank(name, value)

        if self.reviewed_object_version < 1:
            raise ValueError(
                "reviewed_object_version must be >= 1"
            )

        for name, value in (
            (
                "review_started_at",
                self.review_started_at,
            ),
            ("decided_at", self.decided_at),
            (
                "analytical_cutoff",
                self.analytical_cutoff,
            ),
        ):
            require_aware(name, value)

        if self.decided_at < self.review_started_at:
            raise ValueError(
                "decided_at precedes review_started_at"
            )

        if self.analytical_cutoff > self.decided_at:
            raise ValueError(
                "analytical_cutoff later than decision"
            )

        if self.affected_downstream_outputs is not None:
            require_unique(
                "affected_downstream_outputs",
                self.affected_downstream_outputs,
            )

        for name, values in (
            (
                "foresight_assumptions",
                self.foresight_assumptions,
            ),
            (
                "alternative_hypotheses",
                self.alternative_hypotheses,
            ),
            (
                "falsification_conditions",
                self.falsification_conditions,
            ),
        ):
            require_unique(
                name,
                values,
            )

        if not self.analytical_only:
            raise ValueError(
                "S1.5B records must remain analytical-only"
            )

        if self.rights_state_mutated:
            raise ValueError(
                "review cannot mutate rights state"
            )

        if self.empirical_truth_established_by_review:
            raise ValueError(
                "review cannot establish empirical truth"
            )

        if self.binding_institutional_action_authorized:
            raise ValueError(
                "review cannot authorize binding "
                "institutional action"
            )
