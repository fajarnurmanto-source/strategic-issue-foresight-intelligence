from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Callable

from sifi.domain import AdmissionZone, ClaimType
from sifi.evidence import (
    Claim,
    EvidenceItem,
    PublicationContentItem,
    assess_claim,
)
from sifi.rights import (
    EvidenceAdmissionContext,
    PermissionState,
    RightsPolicyEvaluator,
    RightsWindow,
    SourceRightsContext,
)

from .model import (
    CanonicalCaseDefinition,
    CaseObservation,
    ExecutionEvidenceReference,
)


MATERIALIZED_BATCH3_CASE_IDS = ("CB08",)


@dataclass(frozen=True, slots=True)
class Batch3ExecutorSpec:
    case_id: str
    executor_id: str
    executor_version: str
    executor_reference: str
    fixture_path: str
    manifest_path: str


BATCH3_EXECUTOR_SPECS = {
    "CB08": Batch3ExecutorSpec(
        case_id="CB08",
        executor_id="SIFI-CB08-INDEPENDENT-CORROBORATION-EXECUTOR",
        executor_version="v0.1",
        executor_reference=(
            "sifi.benchmarks.materialized_cases_batch3:execute_cb08"
        ),
        fixture_path=(
            "04_IMPLEMENTATION/S1_6D/materialized_cases/CB08.fixture.json"
        ),
        manifest_path=(
            "04_IMPLEMENTATION/S1_6D/materialized_cases/"
            "CB08.materialization.json"
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
    spec = BATCH3_EXECUTOR_SPECS["CB08"]
    payload = json.loads(
        (_root() / spec.fixture_path).read_text(encoding="utf-8")
    )
    if payload.get("case_id") != "CB08":
        raise ValueError("CB08: fixture case binding mismatch")
    return payload


def _source(item: dict) -> SourceRightsContext:
    return SourceRightsContext(
        source_id=item["source_id"],
        source_zone=AdmissionZone(item["source_zone"]),
        access_state=PermissionState(item["access_state"]),
        commercial_use_state=PermissionState(
            item["commercial_use_state"]
        ),
        retention_state=PermissionState(item["retention_state"]),
        privacy_clearance_state=PermissionState(
            item["privacy_clearance_state"]
        ),
        rights_window=RightsWindow(valid_from=_dt(item["valid_from"])),
    )


def _publication(item: dict) -> PublicationContentItem:
    return PublicationContentItem(
        publication_id=item["publication_id"],
        source_id=item["source_id"],
        collected_at=_dt(item["collected_at"]),
        content_fingerprint=item["content_fingerprint"],
        admission_zone=AdmissionZone(item["source_zone"]),
        origin_key=item["origin_key"],
    )


def _claim(item: dict) -> Claim:
    return Claim(
        claim_id=item["claim_id"],
        source_id=item["source_id"],
        publication_id=item.get("publication_id"),
        claim_type=ClaimType(item["claim_type"]),
        claim_text=item["claim_text"],
        recorded_at=_dt(item["recorded_at"]),
    )


def execute_cb08(definition: CanonicalCaseDefinition) -> CaseObservation:
    if getattr(definition, "case_id", None) != "CB08":
        raise ValueError("CB08: executor received wrong case definition")

    fixture = _fixture()
    cutoff = _dt(fixture["analytical_cutoff"])
    policy = RightsPolicyEvaluator()
    sources = {
        item["source_id"]: _source(item)
        for item in fixture["sources"]
    }
    publications = tuple(
        _publication(item) for item in fixture["publications"]
    )
    claims = {
        item["claim_id"]: _claim(item) for item in fixture["claims"]
    }

    evidence_items = []
    admission_decisions = []
    for item in fixture["evidence"]:
        decision = policy.evaluate_evidence(
            sources[item["source_id"]],
            EvidenceAdmissionContext(
                evidence_id=item["evidence_id"],
                evidence_specific_reviewed=(
                    item["evidence_specific_reviewed"]
                ),
                provenance_traceable=item["provenance_traceable"],
            ),
            as_of=cutoff,
        )
        admission_decisions.append(decision)
        evidence_items.append(
            EvidenceItem(
                evidence_id=item["evidence_id"],
                source_id=item["source_id"],
                publication_id=item["publication_id"],
                evidence_kind=item["evidence_kind"],
                recorded_at=_dt(item["recorded_at"]),
                admission_zone=decision.zone,
                origin_key=item["origin_key"],
                supported_claim_ids=tuple(item["supported_claim_ids"]),
            )
        )

    supported = assess_claim(
        claims["CB08-CL-CONDITION"],
        tuple(evidence_items),
        publications,
        cutoff=cutoff,
    )
    unsupported = assess_claim(
        claims["CB08-CL-SEVERITY"],
        tuple(evidence_items),
        publications,
        cutoff=cutoff,
    )

    all_admitted = all(
        decision.zone == AdmissionZone.Z2_PRODUCTION_ADMITTED
        and decision.production_eligible
        for decision in admission_decisions
    )
    expected = (
        all_admitted
        and supported.independent_origin_count
        == fixture["expected_independent_origin_count"]
        and len(supported.production_support_evidence_ids)
        == fixture["expected_support_count"]
    )
    forbidden = bool(unsupported.supporting_evidence_ids)
    regression = (
        supported.publication_count == len(publications)
        and supported.independent_origin_count < len(evidence_items)
        and not unsupported.production_support_present
    )

    spec = BATCH3_EXECUTOR_SPECS["CB08"]
    fixture_path = _root() / spec.fixture_path
    evidence_ref = ExecutionEvidenceReference(
        evidence_id="CB08-MATERIALIZED-FIXTURE",
        evidence_kind="CANONICAL_FIXTURE_MATERIALIZATION",
        artifact_path=spec.fixture_path,
        sha256=hashlib.sha256(fixture_path.read_bytes()).hexdigest(),
        producer_executor_id=spec.executor_id,
        producer_executor_version=spec.executor_version,
    )

    return CaseObservation(
        expected_conditions_satisfied=expected,
        forbidden_output_observed=forbidden,
        regression_dependencies_satisfied=regression,
        rights_constraints_satisfied=all_admitted,
        evidence_refs=(evidence_ref,),
        notes=(
            "rights_policy_z2_count=" + str(len(admission_decisions)),
            "support_count="
            + str(len(supported.production_support_evidence_ids)),
            "independent_origin_count="
            + str(supported.independent_origin_count),
            "unsupported_severity_support_count="
            + str(len(unsupported.supporting_evidence_ids)),
        ),
        analytical_cutoff=fixture["analytical_cutoff"],
    )


BATCH3_EXECUTORS: dict[
    str,
    Callable[[CanonicalCaseDefinition], CaseObservation],
] = {"CB08": execute_cb08}
