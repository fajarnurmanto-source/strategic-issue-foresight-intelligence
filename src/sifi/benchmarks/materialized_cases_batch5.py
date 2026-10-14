from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Callable

from sifi.domain import ReviewDisposition
from sifi.review import (
    AuthorityAssignment,
    DisagreementState,
    HumanReviewAuthorityKernel,
    ProtectedTransition,
    ReviewActorType,
    ReviewHistory,
    ReviewProposal,
    ReviewerRole,
)

from .model import CanonicalCaseDefinition, CaseObservation, ExecutionEvidenceReference


MATERIALIZED_BATCH5_CASE_IDS = ("CB17",)


@dataclass(frozen=True, slots=True)
class Batch5ExecutorSpec:
    case_id: str
    executor_id: str
    executor_version: str
    executor_reference: str
    fixture_path: str
    manifest_path: str


BATCH5_EXECUTOR_SPECS = {
    "CB17": Batch5ExecutorSpec(
        case_id="CB17",
        executor_id="SIFI-CB17-REVIEWER-DISAGREEMENT-EXECUTOR",
        executor_version="v0.1",
        executor_reference="sifi.benchmarks.materialized_cases_batch5:execute_cb17",
        fixture_path="04_IMPLEMENTATION/S1_6D/materialized_cases/CB17.fixture.json",
        manifest_path="04_IMPLEMENTATION/S1_6D/materialized_cases/CB17.materialization.json",
    )
}


def _root() -> Path:
    return Path(__file__).resolve().parents[3]


def _dt(value: str) -> datetime:
    parsed = datetime.fromisoformat(value)
    if parsed.tzinfo is None or parsed.utcoffset() is None:
        raise ValueError("fixture datetime must be timezone-aware")
    return parsed


def _fixture() -> dict:
    spec = BATCH5_EXECUTOR_SPECS["CB17"]
    payload = json.loads((_root() / spec.fixture_path).read_text(encoding="utf-8"))
    if payload.get("case_id") != "CB17":
        raise ValueError("CB17: fixture case binding mismatch")
    return payload


def _assignment(item: dict) -> AuthorityAssignment:
    return AuthorityAssignment(
        assignment_id=item["assignment_id"],
        reviewer_id_or_role_reference=item["reviewer_reference"],
        roles=(ReviewerRole(item["role"]),),
        authorized_transitions=(ProtectedTransition(item["transition"]),),
        authority_basis=item["authority_basis"],
        effective_from=_dt(item["effective_from"]),
    )


def _proposal(item: dict) -> ReviewProposal:
    return ReviewProposal(
        proposal_id=item["proposal_id"],
        reviewed_object_type=item["reviewed_object_type"],
        reviewed_object_id=item["reviewed_object_id"],
        reviewed_object_version=item["reviewed_object_version"],
        proposed_action=ProtectedTransition(item["proposed_action"]),
        proposed_state=item["proposed_state"],
        proposer_actor_type=ReviewActorType(item["proposer_actor_type"]),
        proposer_reference=item["proposer_reference"],
        rationale=item["rationale"],
        evidence_refs=tuple(item["evidence_refs"]),
        contradictory_evidence_refs=tuple(item["contradictory_evidence_refs"]),
        coverage_limitations=tuple(item["coverage_limitations"]),
        rights_or_privacy_constraints=tuple(item["rights_or_privacy_constraints"]),
        uncertainty_state=item["uncertainty_state"],
        disagreement_state=DisagreementState(item["disagreement_state"]),
        proposed_at=_dt(item["proposed_at"]),
        analytical_cutoff=_dt(item["analytical_cutoff"]),
        prior_review_ids=tuple(item.get("prior_review_ids", ())),
        dissenting_view=item.get("dissenting_view"),
        review_notes=tuple(item.get("review_notes", ())),
    )


def _dispose(kernel: HumanReviewAuthorityKernel, item: dict):
    assignment = _assignment(item["assignment"])
    return kernel.dispose(
        review_id=item["review_id"],
        proposal=_proposal(item["proposal"]),
        assignment=assignment,
        reviewer_id_or_role_reference=item["assignment"]["reviewer_reference"],
        reviewer_authority_role=ReviewerRole(item["assignment"]["role"]),
        reviewer_actor_type=ReviewActorType.HUMAN,
        final_disposition=ReviewDisposition(item["final_disposition"]),
        decided_at=_dt(item["decided_at"]),
    )


def execute_cb17(definition: CanonicalCaseDefinition) -> CaseObservation:
    if getattr(definition, "case_id", None) != "CB17":
        raise ValueError("CB17: executor received wrong case definition")

    fixture = _fixture()
    kernel = HumanReviewAuthorityKernel()
    history = ReviewHistory()
    prior_records = []
    for item in fixture["reviewer_disagreements"]:
        record = _dispose(kernel, item)
        history.append(
            review_id=record.review_id,
            version=1,
            recorded_at=_dt(item["recorded_at"]),
            record=record,
        )
        prior_records.append(record)

    adjudication_item = fixture["adjudication"]
    adjudication = _dispose(kernel, adjudication_item)
    history.append(
        review_id=adjudication.review_id,
        version=1,
        recorded_at=_dt(adjudication_item["recorded_at"]),
        record=adjudication,
    )

    prior_ids = tuple(record.review_id for record in prior_records)
    disagreement_explicit = (
        len(prior_records) == 2
        and all(
            record.disagreement_state == DisagreementState.DISAGREEMENT_OPEN
            and record.final_disposition == ReviewDisposition("RD-07")
            for record in prior_records
        )
        and prior_records[0].proposed_state != prior_records[1].proposed_state
    )
    both_reviews_preserved = all(
        history.latest(review_id) is not None for review_id in prior_ids
    )
    adjudication_preserves_disagreement = (
        adjudication.final_disposition == ReviewDisposition("RD-08")
        and adjudication.prior_review_ids == prior_ids
        and adjudication.disagreement_state
        == DisagreementState.ADJUDICATED_WITH_DISSENT_RETAINED
        and bool(adjudication.dissenting_view)
    )
    no_first_or_average_selection = (
        adjudication.proposed_state == fixture["expected_adjudicated_state"]
        and adjudication.proposed_state != prior_records[0].proposed_state
        and adjudication.proposed_state != fixture["forbidden_averaged_state"]
    )
    boundaries_preserved = all(
        record.analytical_only
        and not record.rights_state_mutated
        and not record.binding_institutional_action_authorized
        for record in (*prior_records, adjudication)
    )

    spec = BATCH5_EXECUTOR_SPECS["CB17"]
    fixture_path = _root() / spec.fixture_path
    evidence_ref = ExecutionEvidenceReference(
        evidence_id="CB17-MATERIALIZED-FIXTURE",
        evidence_kind="CANONICAL_FIXTURE_MATERIALIZATION",
        artifact_path=spec.fixture_path,
        sha256=hashlib.sha256(fixture_path.read_bytes()).hexdigest(),
        producer_executor_id=spec.executor_id,
        producer_executor_version=spec.executor_version,
    )
    return CaseObservation(
        expected_conditions_satisfied=(
            disagreement_explicit
            and both_reviews_preserved
            and adjudication_preserves_disagreement
        ),
        forbidden_output_observed=not no_first_or_average_selection,
        regression_dependencies_satisfied=boundaries_preserved,
        rights_constraints_satisfied=True,
        evidence_refs=(evidence_ref,),
        notes=(
            "material_disagreement_explicit=" + str(disagreement_explicit).lower(),
            "both_prior_reviews_preserved=" + str(both_reviews_preserved).lower(),
            "adjudication_with_dissent_retained="
            + str(adjudication_preserves_disagreement).lower(),
            "first_or_average_selected="
            + str(not no_first_or_average_selection).lower(),
            "analytical_boundary_preserved=" + str(boundaries_preserved).lower(),
        ),
        analytical_cutoff=fixture["analytical_cutoff"],
    )


BATCH5_EXECUTORS: dict[str, Callable[[CanonicalCaseDefinition], CaseObservation]] = {
    "CB17": execute_cb17
}
