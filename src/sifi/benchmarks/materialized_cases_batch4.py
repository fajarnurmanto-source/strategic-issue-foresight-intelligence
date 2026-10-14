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

from .model import (
    CanonicalCaseDefinition,
    CaseObservation,
    ExecutionEvidenceReference,
)


MATERIALIZED_BATCH4_CASE_IDS = ("CB16",)


@dataclass(frozen=True, slots=True)
class Batch4ExecutorSpec:
    case_id: str
    executor_id: str
    executor_version: str
    executor_reference: str
    fixture_path: str
    manifest_path: str


BATCH4_EXECUTOR_SPECS = {
    "CB16": Batch4ExecutorSpec(
        case_id="CB16",
        executor_id="SIFI-CB16-REVIEWER-CORRECTION-EXECUTOR",
        executor_version="v0.1",
        executor_reference=(
            "sifi.benchmarks.materialized_cases_batch4:execute_cb16"
        ),
        fixture_path=(
            "04_IMPLEMENTATION/S1_6D/materialized_cases/CB16.fixture.json"
        ),
        manifest_path=(
            "04_IMPLEMENTATION/S1_6D/materialized_cases/"
            "CB16.materialization.json"
        ),
    ),
}


def _root() -> Path:
    return Path(__file__).resolve().parents[3]


def _dt(value: str) -> datetime:
    parsed = datetime.fromisoformat(value)
    if parsed.tzinfo is None or parsed.utcoffset() is None:
        raise ValueError("fixture datetime must be timezone-aware")
    return parsed


def _fixture() -> dict:
    spec = BATCH4_EXECUTOR_SPECS["CB16"]
    payload = json.loads(
        (_root() / spec.fixture_path).read_text(encoding="utf-8")
    )
    if payload.get("case_id") != "CB16":
        raise ValueError("CB16: fixture case binding mismatch")
    return payload


def _assignment(item: dict) -> AuthorityAssignment:
    return AuthorityAssignment(
        assignment_id=item["assignment_id"],
        reviewer_id_or_role_reference=item["reviewer_reference"],
        roles=(ReviewerRole(item["role"]),),
        authorized_transitions=(ProtectedTransition(item["transition"]),),
        authority_basis=item["authority_basis"],
        effective_from=_dt(item["effective_from"]),
        effective_to=(
            _dt(item["effective_to"])
            if item.get("effective_to")
            else None
        ),
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
        contradictory_evidence_refs=tuple(
            item["contradictory_evidence_refs"]
        ),
        coverage_limitations=tuple(item["coverage_limitations"]),
        rights_or_privacy_constraints=tuple(
            item["rights_or_privacy_constraints"]
        ),
        uncertainty_state=item["uncertainty_state"],
        disagreement_state=DisagreementState(item["disagreement_state"]),
        proposed_at=_dt(item["proposed_at"]),
        analytical_cutoff=_dt(item["analytical_cutoff"]),
        prior_review_ids=tuple(item.get("prior_review_ids", ())),
        supersedes_review_id=item.get("supersedes_review_id"),
        correction_reference=item.get("correction_reference"),
        affected_downstream_outputs=tuple(
            item["affected_downstream_outputs"]
        ) if item.get("affected_downstream_outputs") is not None else None,
        review_notes=tuple(item.get("review_notes", ())),
    )


def execute_cb16(definition: CanonicalCaseDefinition) -> CaseObservation:
    if getattr(definition, "case_id", None) != "CB16":
        raise ValueError("CB16: executor received wrong case definition")

    fixture = _fixture()
    kernel = HumanReviewAuthorityKernel()
    history = ReviewHistory()

    prior = fixture["prior_review"]
    prior_record = kernel.dispose(
        review_id=prior["review_id"],
        proposal=_proposal(prior["proposal"]),
        assignment=_assignment(prior["assignment"]),
        reviewer_id_or_role_reference=prior["assignment"]["reviewer_reference"],
        reviewer_authority_role=ReviewerRole(prior["assignment"]["role"]),
        reviewer_actor_type=ReviewActorType.HUMAN,
        final_disposition=ReviewDisposition(prior["final_disposition"]),
        decided_at=_dt(prior["decided_at"]),
    )
    history.append(
        review_id=prior_record.review_id,
        version=1,
        recorded_at=_dt(prior["recorded_at"]),
        record=prior_record,
    )

    correction = fixture["correction_review"]
    correction_record = kernel.dispose(
        review_id=correction["review_id"],
        proposal=_proposal(correction["proposal"]),
        assignment=_assignment(correction["assignment"]),
        reviewer_id_or_role_reference=(
            correction["assignment"]["reviewer_reference"]
        ),
        reviewer_authority_role=ReviewerRole(
            correction["assignment"]["role"]
        ),
        reviewer_actor_type=ReviewActorType.HUMAN,
        final_disposition=ReviewDisposition(correction["final_disposition"]),
        decided_at=_dt(correction["decided_at"]),
    )
    history.append(
        review_id=correction_record.review_id,
        version=1,
        recorded_at=_dt(correction["recorded_at"]),
        record=correction_record,
    )

    preserved_prior = history.latest(prior_record.review_id)
    preserved_correction = history.latest(correction_record.review_id)
    lineage_preserved = (
        preserved_prior is not None
        and preserved_correction is not None
        and correction_record.supersedes_review_id == prior_record.review_id
        and correction_record.prior_review_ids == (prior_record.review_id,)
    )
    prior_state_preserved = (
        preserved_prior is not None
        and preserved_prior.record.proposed_state
        == fixture["expected_prior_state"]
    )
    correction_fields_preserved = (
        correction_record.proposed_state == fixture["expected_corrected_state"]
        and correction_record.reviewer_id_or_role_reference
        == correction["assignment"]["reviewer_reference"]
        and correction_record.rationale == correction["proposal"]["rationale"]
        and correction_record.correction_reference
        == correction["proposal"]["correction_reference"]
    )
    no_silent_overwrite = (
        len(history.history(prior_record.review_id)) == 1
        and len(history.history(correction_record.review_id)) == 1
        and prior_record.review_id != correction_record.review_id
    )
    boundaries_preserved = (
        prior_record.analytical_only
        and correction_record.analytical_only
        and not prior_record.rights_state_mutated
        and not correction_record.rights_state_mutated
    )

    spec = BATCH4_EXECUTOR_SPECS["CB16"]
    fixture_path = _root() / spec.fixture_path
    evidence_ref = ExecutionEvidenceReference(
        evidence_id="CB16-MATERIALIZED-FIXTURE",
        evidence_kind="CANONICAL_FIXTURE_MATERIALIZATION",
        artifact_path=spec.fixture_path,
        sha256=hashlib.sha256(fixture_path.read_bytes()).hexdigest(),
        producer_executor_id=spec.executor_id,
        producer_executor_version=spec.executor_version,
    )

    return CaseObservation(
        expected_conditions_satisfied=(
            lineage_preserved
            and prior_state_preserved
            and correction_fields_preserved
        ),
        forbidden_output_observed=not no_silent_overwrite,
        regression_dependencies_satisfied=boundaries_preserved,
        rights_constraints_satisfied=True,
        evidence_refs=(evidence_ref,),
        notes=(
            "correction_lineage_preserved=" + str(lineage_preserved).lower(),
            "prior_state_preserved=" + str(prior_state_preserved).lower(),
            "reviewer_and_rationale_preserved="
            + str(correction_fields_preserved).lower(),
            "silent_overwrite_observed="
            + str(not no_silent_overwrite).lower(),
            "analytical_boundary_preserved="
            + str(boundaries_preserved).lower(),
        ),
        analytical_cutoff=fixture["analytical_cutoff"],
    )


BATCH4_EXECUTORS: dict[
    str,
    Callable[[CanonicalCaseDefinition], CaseObservation],
] = {"CB16": execute_cb16}
