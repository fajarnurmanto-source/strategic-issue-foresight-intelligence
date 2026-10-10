"""Persistent Issue Object primitives.

Bounded implementation of S0.5B/S0.5C.

Persistent representation does not imply automatic issue discovery,
qualification, merge/split authority, or institutional action.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain import (
    ObservabilityState,
    PersistenceClass,
    TrajectoryDimension,
)
from sifi.domain.versioning import require_aware_datetime


def _require_nonblank(name: str, value: str) -> None:
    if not value.strip():
        raise ValueError(
            f"{name} must not be blank"
        )


def _require_unique(name: str, values: tuple[str, ...]) -> None:
    if len(values) != len(set(values)):
        raise ValueError(
            f"{name} must not contain duplicates"
        )


@dataclass(frozen=True, slots=True)
class IssueEvidenceLinks:
    related_event_ids: tuple[str, ...] = ()
    supporting_claim_ids: tuple[str, ...] = ()
    disputed_claim_ids: tuple[str, ...] = ()
    supporting_evidence_ids: tuple[str, ...] = ()
    contradictory_evidence_ids: tuple[str, ...] = ()
    context_only_evidence_ids: tuple[str, ...] = ()
    excluded_production_evidence_ids: tuple[str, ...] = ()

    def __post_init__(self) -> None:
        for name, values in [
            ("related_event_ids", self.related_event_ids),
            ("supporting_claim_ids", self.supporting_claim_ids),
            ("disputed_claim_ids", self.disputed_claim_ids),
            ("supporting_evidence_ids", self.supporting_evidence_ids),
            (
                "contradictory_evidence_ids",
                self.contradictory_evidence_ids,
            ),
            (
                "context_only_evidence_ids",
                self.context_only_evidence_ids,
            ),
            (
                "excluded_production_evidence_ids",
                self.excluded_production_evidence_ids,
            ),
        ]:
            _require_unique(name, values)

        claim_overlap = (
            set(self.supporting_claim_ids)
            & set(self.disputed_claim_ids)
        )

        if claim_overlap:
            raise ValueError(
                "a Claim cannot simultaneously be supporting "
                "and disputed in one Issue Object"
            )

        evidence_groups = {
            "supporting": set(self.supporting_evidence_ids),
            "contradictory": set(
                self.contradictory_evidence_ids
            ),
            "context_only": set(
                self.context_only_evidence_ids
            ),
            "excluded_production": set(
                self.excluded_production_evidence_ids
            ),
        }

        names = tuple(evidence_groups)

        for i, left in enumerate(names):
            for right in names[i + 1:]:
                overlap = (
                    evidence_groups[left]
                    & evidence_groups[right]
                )

                if overlap:
                    raise ValueError(
                        "Evidence Item categories must remain "
                        f"distinct: {left} vs {right}"
                    )


@dataclass(frozen=True, slots=True)
class IssueGeography:
    source_geography: tuple[str, ...] = ()
    event_geography: tuple[str, ...] = ()
    affected_geography: tuple[str, ...] = ()
    policy_jurisdiction: tuple[str, ...] = ()
    relevance_geography: tuple[str, ...] = ()
    coverage_profile_id: str | None = None


@dataclass(frozen=True, slots=True)
class IssueTrajectoryState:
    dimension: TrajectoryDimension
    trajectory_state: str
    as_of: datetime
    evidence_ids: tuple[str, ...] = ()
    limitations: tuple[str, ...] = ()
    reviewer_state: str | None = None

    def __post_init__(self) -> None:
        _require_nonblank(
            "trajectory_state",
            self.trajectory_state,
        )

        require_aware_datetime(
            self.as_of,
            "as_of",
        )

        _require_unique(
            "evidence_ids",
            self.evidence_ids,
        )


@dataclass(frozen=True, slots=True)
class IssueLineage:
    predecessor_issue_ids: tuple[str, ...] = ()
    successor_issue_ids: tuple[str, ...] = ()
    merged_from_issue_ids: tuple[str, ...] = ()
    merged_into_issue_id: str | None = None
    split_from_issue_id: str | None = None
    split_into_issue_ids: tuple[str, ...] = ()
    lineage_review_state: str | None = None


@dataclass(frozen=True, slots=True)
class IssueObject:
    issue_id: str
    title: str
    issue_type: str
    persistence_class: PersistenceClass
    lifecycle_state: str
    first_seen: datetime
    last_updated: datetime

    identity_review_state: str | None

    evidence: IssueEvidenceLinks
    geography: IssueGeography
    trajectories: tuple[IssueTrajectoryState, ...]

    observability_states: tuple[ObservabilityState, ...] = ()

    competent_authority_candidates: tuple[str, ...] = ()
    authority_review_state: str | None = None
    operational_capability_review_state: str | None = None

    uncertainty_notes: tuple[str, ...] = ()
    evidence_gaps: tuple[str, ...] = ()
    known_limitations: tuple[str, ...] = ()
    watch_conditions: tuple[str, ...] = ()

    lineage: IssueLineage = IssueLineage()

    review_required: bool = True

    def __post_init__(self) -> None:
        for name, value in [
            ("issue_id", self.issue_id),
            ("title", self.title),
            ("issue_type", self.issue_type),
            ("lifecycle_state", self.lifecycle_state),
        ]:
            _require_nonblank(name, value)

        require_aware_datetime(
            self.first_seen,
            "first_seen",
        )

        require_aware_datetime(
            self.last_updated,
            "last_updated",
        )

        if self.last_updated < self.first_seen:
            raise ValueError(
                "last_updated must not precede first_seen"
            )

        _require_unique(
            "competent_authority_candidates",
            self.competent_authority_candidates,
        )

        dimensions = tuple(
            t.dimension
            for t in self.trajectories
        )

        if len(dimensions) != len(set(dimensions)):
            raise ValueError(
                "trajectory dimensions must be unique"
            )

        canonical_dimensions = set(
            TrajectoryDimension
        )

        if set(dimensions) != canonical_dimensions:
            raise ValueError(
                "Issue Object must preserve all canonical "
                "trajectory dimensions separately"
            )

    def to_payload(self) -> dict:
        return {
            "issue_id": self.issue_id,
            "title": self.title,
            "issue_type": self.issue_type,
            "persistence_class": self.persistence_class.value,
            "lifecycle_state": self.lifecycle_state,
            "first_seen": self.first_seen.isoformat(),
            "last_updated": self.last_updated.isoformat(),
            "identity_review_state": (
                self.identity_review_state
            ),
            "evidence": {
                "related_event_ids": list(
                    self.evidence.related_event_ids
                ),
                "supporting_claim_ids": list(
                    self.evidence.supporting_claim_ids
                ),
                "disputed_claim_ids": list(
                    self.evidence.disputed_claim_ids
                ),
                "supporting_evidence_ids": list(
                    self.evidence.supporting_evidence_ids
                ),
                "contradictory_evidence_ids": list(
                    self.evidence.contradictory_evidence_ids
                ),
                "context_only_evidence_ids": list(
                    self.evidence.context_only_evidence_ids
                ),
                "excluded_production_evidence_ids": list(
                    self.evidence.excluded_production_evidence_ids
                ),
            },
            "geography": {
                "source_geography": list(
                    self.geography.source_geography
                ),
                "event_geography": list(
                    self.geography.event_geography
                ),
                "affected_geography": list(
                    self.geography.affected_geography
                ),
                "policy_jurisdiction": list(
                    self.geography.policy_jurisdiction
                ),
                "relevance_geography": list(
                    self.geography.relevance_geography
                ),
                "coverage_profile_id": (
                    self.geography.coverage_profile_id
                ),
            },
            "trajectories": [
                {
                    "dimension": t.dimension.value,
                    "trajectory_state": t.trajectory_state,
                    "as_of": t.as_of.isoformat(),
                    "evidence_ids": list(t.evidence_ids),
                    "limitations": list(t.limitations),
                    "reviewer_state": t.reviewer_state,
                }
                for t in self.trajectories
            ],
            "observability_states": [
                x.value
                for x in self.observability_states
            ],
            "competent_authority_candidates": list(
                self.competent_authority_candidates
            ),
            "authority_review_state": (
                self.authority_review_state
            ),
            "operational_capability_review_state": (
                self.operational_capability_review_state
            ),
            "uncertainty_notes": list(
                self.uncertainty_notes
            ),
            "evidence_gaps": list(
                self.evidence_gaps
            ),
            "known_limitations": list(
                self.known_limitations
            ),
            "watch_conditions": list(
                self.watch_conditions
            ),
            "lineage": {
                "predecessor_issue_ids": list(
                    self.lineage.predecessor_issue_ids
                ),
                "successor_issue_ids": list(
                    self.lineage.successor_issue_ids
                ),
                "merged_from_issue_ids": list(
                    self.lineage.merged_from_issue_ids
                ),
                "merged_into_issue_id": (
                    self.lineage.merged_into_issue_id
                ),
                "split_from_issue_id": (
                    self.lineage.split_from_issue_id
                ),
                "split_into_issue_ids": list(
                    self.lineage.split_into_issue_ids
                ),
                "lineage_review_state": (
                    self.lineage.lineage_review_state
                ),
            },
            "review_required": self.review_required,
        }

    @classmethod
    def from_payload(cls, payload: dict) -> "IssueObject":
        evidence = payload["evidence"]
        geography = payload["geography"]
        lineage = payload["lineage"]

        return cls(
            issue_id=payload["issue_id"],
            title=payload["title"],
            issue_type=payload["issue_type"],
            persistence_class=PersistenceClass(
                payload["persistence_class"]
            ),
            lifecycle_state=payload["lifecycle_state"],
            first_seen=datetime.fromisoformat(
                payload["first_seen"]
            ),
            last_updated=datetime.fromisoformat(
                payload["last_updated"]
            ),
            identity_review_state=payload[
                "identity_review_state"
            ],
            evidence=IssueEvidenceLinks(
                related_event_ids=tuple(
                    evidence["related_event_ids"]
                ),
                supporting_claim_ids=tuple(
                    evidence["supporting_claim_ids"]
                ),
                disputed_claim_ids=tuple(
                    evidence["disputed_claim_ids"]
                ),
                supporting_evidence_ids=tuple(
                    evidence["supporting_evidence_ids"]
                ),
                contradictory_evidence_ids=tuple(
                    evidence[
                        "contradictory_evidence_ids"
                    ]
                ),
                context_only_evidence_ids=tuple(
                    evidence[
                        "context_only_evidence_ids"
                    ]
                ),
                excluded_production_evidence_ids=tuple(
                    evidence[
                        "excluded_production_evidence_ids"
                    ]
                ),
            ),
            geography=IssueGeography(
                source_geography=tuple(
                    geography["source_geography"]
                ),
                event_geography=tuple(
                    geography["event_geography"]
                ),
                affected_geography=tuple(
                    geography["affected_geography"]
                ),
                policy_jurisdiction=tuple(
                    geography["policy_jurisdiction"]
                ),
                relevance_geography=tuple(
                    geography["relevance_geography"]
                ),
                coverage_profile_id=geography[
                    "coverage_profile_id"
                ],
            ),
            trajectories=tuple(
                IssueTrajectoryState(
                    dimension=TrajectoryDimension(
                        item["dimension"]
                    ),
                    trajectory_state=item[
                        "trajectory_state"
                    ],
                    as_of=datetime.fromisoformat(
                        item["as_of"]
                    ),
                    evidence_ids=tuple(
                        item["evidence_ids"]
                    ),
                    limitations=tuple(
                        item["limitations"]
                    ),
                    reviewer_state=item[
                        "reviewer_state"
                    ],
                )
                for item in payload["trajectories"]
            ),
            observability_states=tuple(
                ObservabilityState(x)
                for x in payload[
                    "observability_states"
                ]
            ),
            competent_authority_candidates=tuple(
                payload[
                    "competent_authority_candidates"
                ]
            ),
            authority_review_state=payload[
                "authority_review_state"
            ],
            operational_capability_review_state=payload[
                "operational_capability_review_state"
            ],
            uncertainty_notes=tuple(
                payload["uncertainty_notes"]
            ),
            evidence_gaps=tuple(
                payload["evidence_gaps"]
            ),
            known_limitations=tuple(
                payload["known_limitations"]
            ),
            watch_conditions=tuple(
                payload["watch_conditions"]
            ),
            lineage=IssueLineage(
                predecessor_issue_ids=tuple(
                    lineage["predecessor_issue_ids"]
                ),
                successor_issue_ids=tuple(
                    lineage["successor_issue_ids"]
                ),
                merged_from_issue_ids=tuple(
                    lineage["merged_from_issue_ids"]
                ),
                merged_into_issue_id=lineage[
                    "merged_into_issue_id"
                ],
                split_from_issue_id=lineage[
                    "split_from_issue_id"
                ],
                split_into_issue_ids=tuple(
                    lineage["split_into_issue_ids"]
                ),
                lineage_review_state=lineage[
                    "lineage_review_state"
                ],
            ),
            review_required=payload[
                "review_required"
            ],
        )
