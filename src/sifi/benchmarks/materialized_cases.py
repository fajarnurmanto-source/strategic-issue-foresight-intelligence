from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Callable

from sifi.domain import (
    AdmissionZone,
    ClaimType,
)
from sifi.evidence import (
    Claim,
    EvidenceItem,
    PublicationContentItem,
    assess_claim,
    publication_origin_metrics,
)
from sifi.events import (
    EventCandidateBuilder,
    EventConstructionError,
)

from .model import (
    CanonicalCaseDefinition,
    CaseObservation,
    ExecutionEvidenceReference,
)


MATERIALIZED_CASE_IDS = (
    "CB01",
    "CB07",
    "CB20",
)


@dataclass(frozen=True, slots=True)
class MaterializedExecutorSpec:
    case_id: str
    executor_id: str
    executor_version: str
    executor_reference: str
    fixture_path: str
    manifest_path: str


MATERIALIZED_EXECUTOR_SPECS = {
    "CB01": MaterializedExecutorSpec(
        case_id="CB01",
        executor_id=(
            "SIFI-CB01-SOURCE-LINEAGE-EXECUTOR"
        ),
        executor_version="v0.1",
        executor_reference=(
            "sifi.benchmarks.materialized_cases:"
            "execute_cb01"
        ),
        fixture_path=(
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/CB01.fixture.json"
        ),
        manifest_path=(
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/"
            "CB01.materialization.json"
        ),
    ),
    "CB07": MaterializedExecutorSpec(
        case_id="CB07",
        executor_id=(
            "SIFI-CB07-ALLEGATION-GUARD-EXECUTOR"
        ),
        executor_version="v0.1",
        executor_reference=(
            "sifi.benchmarks.materialized_cases:"
            "execute_cb07"
        ),
        fixture_path=(
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/CB07.fixture.json"
        ),
        manifest_path=(
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/"
            "CB07.materialization.json"
        ),
    ),
    "CB20": MaterializedExecutorSpec(
        case_id="CB20",
        executor_id=(
            "SIFI-CB20-CLAIM-TYPE-EXECUTOR"
        ),
        executor_version="v0.1",
        executor_reference=(
            "sifi.benchmarks.materialized_cases:"
            "execute_cb20"
        ),
        fixture_path=(
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/CB20.fixture.json"
        ),
        manifest_path=(
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/"
            "CB20.materialization.json"
        ),
    ),
}


def _repo_root() -> Path:
    return (
        Path(__file__)
        .resolve()
        .parents[3]
    )


def _sha256(
    path: Path,
) -> str:
    return hashlib.sha256(
        path.read_bytes()
    ).hexdigest()


def _load_fixture(
    case_id: str,
) -> dict:
    spec = MATERIALIZED_EXECUTOR_SPECS[
        case_id
    ]

    path = (
        _repo_root()
        / spec.fixture_path
    )

    payload = json.loads(
        path.read_text(
            encoding="utf-8"
        )
    )

    if payload.get("case_id") != case_id:
        raise ValueError(
            case_id
            + ": fixture case binding mismatch"
        )

    return payload


def _parse_dt(
    value: str,
) -> datetime:
    parsed = datetime.fromisoformat(
        value
    )

    if (
        parsed.tzinfo is None
        or parsed.utcoffset() is None
    ):
        raise ValueError(
            "fixture datetime must be "
            "timezone-aware"
        )

    return parsed


def _publication(
    item: dict,
) -> PublicationContentItem:
    return PublicationContentItem(
        publication_id=(
            item["publication_id"]
        ),
        source_id=item["source_id"],
        collected_at=_parse_dt(
            item["collected_at"]
        ),
        content_fingerprint=(
            item["content_fingerprint"]
        ),
        admission_zone=AdmissionZone(
            item["admission_zone"]
        ),
        origin_key=item["origin_key"],
        title=item.get("title"),
    )


def _claim(
    item: dict,
) -> Claim:
    return Claim(
        claim_id=item["claim_id"],
        source_id=item["source_id"],
        publication_id=item.get(
            "publication_id"
        ),
        claim_type=ClaimType(
            item["claim_type"]
        ),
        claim_text=item["claim_text"],
        recorded_at=_parse_dt(
            item["recorded_at"]
        ),
        attribution=item.get(
            "attribution"
        ),
    )


def _evidence(
    item: dict,
) -> EvidenceItem:
    return EvidenceItem(
        evidence_id=item["evidence_id"],
        source_id=item["source_id"],
        publication_id=item.get(
            "publication_id"
        ),
        evidence_kind=(
            item["evidence_kind"]
        ),
        recorded_at=_parse_dt(
            item["recorded_at"]
        ),
        admission_zone=AdmissionZone(
            item["admission_zone"]
        ),
        origin_key=item["origin_key"],
        supported_claim_ids=tuple(
            item.get(
                "supported_claim_ids",
                (),
            )
        ),
        contradicted_claim_ids=tuple(
            item.get(
                "contradicted_claim_ids",
                (),
            )
        ),
    )


def _require_definition(
    definition: CanonicalCaseDefinition,
    case_id: str,
) -> None:
    if (
        getattr(
            definition,
            "case_id",
            None,
        )
        != case_id
    ):
        raise ValueError(
            case_id
            + ": executor received wrong "
            "case definition"
        )


def _fixture_reference(
    case_id: str,
) -> ExecutionEvidenceReference:
    spec = MATERIALIZED_EXECUTOR_SPECS[
        case_id
    ]

    path = (
        _repo_root()
        / spec.fixture_path
    )

    return ExecutionEvidenceReference(
        evidence_id=(
            case_id
            + "-MATERIALIZED-FIXTURE"
        ),
        evidence_kind=(
            "CANONICAL_FIXTURE_MATERIALIZATION"
        ),
        artifact_path=spec.fixture_path,
        sha256=_sha256(path),
        producer_executor_id=(
            spec.executor_id
        ),
        producer_executor_version=(
            spec.executor_version
        ),
    )


def execute_cb01(
    definition: CanonicalCaseDefinition,
) -> CaseObservation:
    """CB01 materialized executor.

    Component self-tests may call this function directly.
    A canonical execution still requires harness registration,
    adjudication, and explicit execution authorization.
    """

    _require_definition(
        definition,
        "CB01",
    )

    fixture = _load_fixture(
        "CB01"
    )

    publications = tuple(
        _publication(item)
        for item in fixture[
            "publications"
        ]
    )

    publication_count, origin_count = (
        publication_origin_metrics(
            publications,
            cutoff=_parse_dt(
                fixture[
                    "analytical_cutoff"
                ]
            ),
        )
    )

    expected = (
        publication_count
        == fixture[
            "expected_publication_count"
        ]
        and origin_count
        == fixture[
            "expected_independent_origin_count"
        ]
    )

    forbidden = (
        publication_count > 1
        and origin_count
        == publication_count
    )

    regression = (
        all(
            p.origin_key
            == fixture[
                "expected_origin_key"
            ]
            for p in publications
        )
        and len(
            {
                p.publication_id
                for p in publications
            }
        )
        == fixture[
            "expected_publication_count"
        ]
    )

    rights = (
        fixture["rights_basis"]
        == "SYNTHETIC_NO_EXTERNAL_RIGHTS"
    )

    return CaseObservation(
        expected_conditions_satisfied=(
            expected
        ),
        forbidden_output_observed=(
            forbidden
        ),
        regression_dependencies_satisfied=(
            regression
        ),
        rights_constraints_satisfied=rights,
        evidence_refs=(
            _fixture_reference(
                "CB01"
            ),
        ),
        notes=(
            (
                "publication_count="
                + str(publication_count)
            ),
            (
                "independent_origin_count="
                + str(origin_count)
            ),
            (
                "synthetic_fixture_no_external_rights="
                + str(rights).upper()
            ),
        ),
    )


def execute_cb07(
    definition: CanonicalCaseDefinition,
) -> CaseObservation:
    """CB07 materialized executor."""

    _require_definition(
        definition,
        "CB07",
    )

    fixture = _load_fixture(
        "CB07"
    )

    publication = _publication(
        fixture["publication"]
    )

    claim = _claim(
        fixture["claim"]
    )

    evidence = _evidence(
        fixture["evidence"]
    )

    attribution_visible = bool(
        claim.attribution
        and claim.attribution.strip()
        and claim.publication_id
        == publication.publication_id
    )

    event_constructed = False

    try:
        EventCandidateBuilder().build(
            event_id=(
                "CB07-FORBIDDEN-EVENT"
            ),
            claims=(
                claim,
            ),
            evidence_items=(
                evidence,
            ),
        )
    except EventConstructionError:
        event_rejected = True
    else:
        event_rejected = False
        event_constructed = True

    regression = (
        claim.claim_type
        == ClaimType(
            "CT-02"
        )
        and event_rejected
    )

    rights = (
        fixture["rights_basis"]
        == "SYNTHETIC_NO_EXTERNAL_RIGHTS"
    )

    return CaseObservation(
        expected_conditions_satisfied=(
            attribution_visible
        ),
        forbidden_output_observed=(
            event_constructed
        ),
        regression_dependencies_satisfied=(
            regression
        ),
        rights_constraints_satisfied=rights,
        evidence_refs=(
            _fixture_reference(
                "CB07"
            ),
        ),
        notes=(
            (
                "attribution_visible="
                + str(
                    attribution_visible
                ).upper()
            ),
            (
                "allegation_event_construction_rejected="
                + str(
                    event_rejected
                ).upper()
            ),
            (
                "claim_type="
                + claim.claim_type.value
            ),
        ),
        analytical_cutoff=fixture[
            "analytical_cutoff"
        ],
    )


def execute_cb20(
    definition: CanonicalCaseDefinition,
) -> CaseObservation:
    """CB20 materialized executor."""

    _require_definition(
        definition,
        "CB20",
    )

    fixture = _load_fixture(
        "CB20"
    )

    publications = tuple(
        _publication(item)
        for item in fixture[
            "publications"
        ]
    )

    claims = tuple(
        _claim(item)
        for item in fixture[
            "claims"
        ]
    )

    evidence_items = tuple(
        _evidence(item)
        for item in fixture[
            "evidence"
        ]
    )

    by_type = {
        claim.claim_type: claim
        for claim in claims
    }

    attention_claim = by_type[
        ClaimType("CT-01")
    ]

    prevalence_claim = by_type[
        ClaimType("CT-05")
    ]

    cutoff = _parse_dt(
        fixture[
            "analytical_cutoff"
        ]
    )

    attention = assess_claim(
        attention_claim,
        evidence_items,
        publications,
        cutoff=cutoff,
    )

    prevalence = assess_claim(
        prevalence_claim,
        evidence_items,
        publications,
        cutoff=cutoff,
    )

    expected = (
        attention.production_support_present
        and len(
            attention
            .production_support_evidence_ids
        )
        == len(
            evidence_items
        )
    )

    forbidden = bool(
        prevalence.supporting_evidence_ids
        or prevalence
        .production_support_evidence_ids
    )

    regression = (
        attention.publication_count
        == len(
            publications
        )
        and not prevalence
        .production_support_present
    )

    rights = (
        fixture["rights_basis"]
        == "SYNTHETIC_NO_EXTERNAL_RIGHTS"
        and all(
            item.admission_zone
            == AdmissionZone(
                "Z2"
            )
            for item in evidence_items
        )
    )

    return CaseObservation(
        expected_conditions_satisfied=(
            expected
        ),
        forbidden_output_observed=(
            forbidden
        ),
        regression_dependencies_satisfied=(
            regression
        ),
        rights_constraints_satisfied=rights,
        evidence_refs=(
            _fixture_reference(
                "CB20"
            ),
        ),
        notes=(
            (
                "attention_production_support="
                + str(
                    attention
                    .production_support_present
                ).upper()
            ),
            (
                "attention_support_count="
                + str(
                    len(
                        attention
                        .production_support_evidence_ids
                    )
                )
            ),
            (
                "prevalence_support_count="
                + str(
                    len(
                        prevalence
                        .supporting_evidence_ids
                    )
                )
            ),
        ),
        analytical_cutoff=fixture[
            "analytical_cutoff"
        ],
    )


MATERIALIZED_EXECUTORS: dict[
    str,
    Callable[
        [CanonicalCaseDefinition],
        CaseObservation,
    ],
] = {
    "CB01": execute_cb01,
    "CB07": execute_cb07,
    "CB20": execute_cb20,
}
