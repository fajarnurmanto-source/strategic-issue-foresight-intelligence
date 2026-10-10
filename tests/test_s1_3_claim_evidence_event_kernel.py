import unittest
from dataclasses import replace
from datetime import datetime, timezone

from sifi.domain import (
    AdmissionZone,
    ClaimType,
    ObservabilityState,
)
from sifi.evidence import (
    Claim,
    EvidenceItem,
    PublicationContentItem,
    assess_claim,
    evidence_as_of,
    production_evidence_eligible,
    publication_origin_metrics,
)
from sifi.events import (
    EventCandidateBuilder,
    EventConstructionError,
    EventContradictionState,
)


UTC = timezone.utc


def dt(day):
    return datetime(
        2026, 1, day, 12, 0,
        tzinfo=UTC,
    )


def publication(
    publication_id="PUB-1",
    origin_key="ORIGIN-1",
    collected_at=None,
    **changes,
):
    base = PublicationContentItem(
        publication_id=publication_id,
        source_id="SRC-1",
        collected_at=collected_at or dt(2),
        content_fingerprint=publication_id + "-HASH",
        admission_zone=AdmissionZone("Z2"),
        origin_key=origin_key,
        title="Example",
    )
    return replace(base, **changes)


def claim(
    claim_id="CL-1",
    claim_type="CT-03",
    **changes,
):
    base = Claim(
        claim_id=claim_id,
        source_id="SRC-1",
        publication_id="PUB-1",
        claim_type=ClaimType(claim_type),
        claim_text="A bounded proposition",
        recorded_at=dt(2),
    )
    return replace(base, **changes)


def evidence(
    evidence_id="EV-1",
    supported=("CL-1",),
    contradicted=(),
    origin_key="ORIGIN-1",
    zone="Z2",
    recorded_at=None,
    observability_state=None,
    publication_id="PUB-1",
    **changes,
):
    base = EvidenceItem(
        evidence_id=evidence_id,
        source_id="SRC-1",
        publication_id=publication_id,
        evidence_kind="CONTROLLED_TEST_EVIDENCE",
        recorded_at=recorded_at or dt(2),
        admission_zone=AdmissionZone(zone),
        origin_key=origin_key,
        supported_claim_ids=supported,
        contradicted_claim_ids=contradicted,
        observability_state=(
            ObservabilityState(
                observability_state
            )
            if observability_state
            else None
        ),
    )
    return replace(base, **changes)


class ObjectBoundaryTests(unittest.TestCase):

    def test_publication_and_claim_are_distinct_objects(self):
        p = publication()
        c = claim()

        self.assertNotEqual(
            type(p),
            type(c),
        )
        self.assertEqual(
            c.publication_id,
            p.publication_id,
        )

    def test_claim_type_is_canonical_enum(self):
        c = claim(
            claim_type="CT-02"
        )
        self.assertEqual(
            c.claim_type.value,
            "CT-02",
        )

    def test_evidence_and_claim_are_distinct_objects(self):
        c = claim()
        e = evidence()

        self.assertNotEqual(
            type(c),
            type(e),
        )

    def test_evidence_cannot_support_and_contradict_same_claim(self):
        with self.assertRaises(ValueError):
            evidence(
                supported=("CL-1",),
                contradicted=("CL-1",),
            )

    def test_claim_correction_is_non_destructive_lineage(self):
        old = claim(
            claim_id="CL-OLD"
        )

        new = claim(
            claim_id="CL-NEW",
            supersedes_claim_id="CL-OLD",
        )

        self.assertEqual(
            old.claim_id,
            "CL-OLD",
        )
        self.assertEqual(
            new.supersedes_claim_id,
            old.claim_id,
        )

    def test_evidence_correction_is_non_destructive_lineage(self):
        old = evidence(
            evidence_id="EV-OLD"
        )

        new = evidence(
            evidence_id="EV-NEW",
            supersedes_evidence_id="EV-OLD",
        )

        self.assertEqual(
            old.evidence_id,
            "EV-OLD",
        )
        self.assertEqual(
            new.supersedes_evidence_id,
            old.evidence_id,
        )


class IndependenceAndQualificationTests(unittest.TestCase):

    def test_syndicated_publications_do_not_inflate_origins(self):
        pubs = tuple(
            publication(
                publication_id=f"PUB-{i}",
                origin_key="ONE-ORIGIN",
            )
            for i in range(20)
        )

        count, origins = publication_origin_metrics(
            pubs,
            cutoff=dt(5),
        )

        self.assertEqual(count, 20)
        self.assertEqual(origins, 1)

    def test_independent_origins_remain_separate(self):
        pubs = (
            publication(
                "PUB-1",
                "ORIGIN-A",
            ),
            publication(
                "PUB-2",
                "ORIGIN-B",
            ),
        )

        count, origins = publication_origin_metrics(
            pubs,
            cutoff=dt(5),
        )

        self.assertEqual(count, 2)
        self.assertEqual(origins, 2)

    def test_support_is_explicit(self):
        result = assess_claim(
            claim(),
            (evidence(),),
            (publication(),),
            cutoff=dt(5),
        )

        self.assertEqual(
            result.supporting_evidence_ids,
            ("EV-1",),
        )

    def test_contradiction_remains_explicit(self):
        result = assess_claim(
            claim(),
            (
                evidence(
                    evidence_id="EV-SUPPORT"
                ),
                evidence(
                    evidence_id="EV-CONTRA",
                    supported=(),
                    contradicted=("CL-1",),
                    origin_key="ORIGIN-2",
                ),
            ),
            (
                publication(),
            ),
            cutoff=dt(5),
        )

        self.assertTrue(
            result.contradiction_present
        )
        self.assertEqual(
            result.contradicting_evidence_ids,
            ("EV-CONTRA",),
        )

    def test_z1_is_not_production_support(self):
        result = assess_claim(
            claim(),
            (
                evidence(
                    zone="Z1"
                ),
            ),
            (publication(),),
            cutoff=dt(5),
        )

        self.assertFalse(
            result.production_support_present
        )

    def test_z2_is_only_admission_not_truth_score(self):
        e = evidence(
            zone="Z2"
        )

        self.assertTrue(
            production_evidence_eligible(e)
        )

        self.assertFalse(
            hasattr(e, "truth_score")
        )

    def test_no_universal_confidence_score_exists(self):
        result = assess_claim(
            claim(),
            (evidence(),),
            (publication(),),
            cutoff=dt(5),
        )

        self.assertFalse(
            hasattr(result, "confidence_score")
        )
        self.assertFalse(
            hasattr(result, "urgency_score")
        )

    def test_collection_failure_is_preserved(self):
        result = assess_claim(
            claim(),
            (
                evidence(
                    observability_state="OBS-03"
                ),
            ),
            (publication(),),
            cutoff=dt(5),
        )

        self.assertIn(
            ObservabilityState("OBS-03"),
            result.observability_states,
        )

    def test_insufficient_coverage_is_preserved(self):
        result = assess_claim(
            claim(),
            (
                evidence(
                    observability_state="OBS-02"
                ),
            ),
            (publication(),),
            cutoff=dt(5),
        )

        self.assertIn(
            ObservabilityState("OBS-02"),
            result.observability_states,
        )

    def test_pit_excludes_later_evidence(self):
        early = evidence(
            evidence_id="EV-EARLY",
            recorded_at=dt(2),
        )

        later = evidence(
            evidence_id="EV-LATE",
            recorded_at=dt(10),
        )

        visible = evidence_as_of(
            (early, later),
            cutoff=dt(5),
        )

        self.assertEqual(
            tuple(
                e.evidence_id
                for e in visible
            ),
            ("EV-EARLY",),
        )

    def test_later_correction_does_not_leak_into_historical_cutoff(self):
        original = evidence(
            evidence_id="EV-ORIGINAL",
            recorded_at=dt(2),
        )

        correction = evidence(
            evidence_id="EV-CORRECTION",
            recorded_at=dt(10),
            supersedes_evidence_id="EV-ORIGINAL",
        )

        historical = evidence_as_of(
            (original, correction),
            cutoff=dt(5),
        )

        self.assertEqual(
            tuple(
                e.evidence_id
                for e in historical
            ),
            ("EV-ORIGINAL",),
        )


class EventConstructionTests(unittest.TestCase):

    def setUp(self):
        self.builder = EventCandidateBuilder()

    def test_allegation_cannot_create_event_candidate(self):
        with self.assertRaises(
            EventConstructionError
        ):
            self.builder.build(
                event_id="EVENT-1",
                claims=(
                    claim(
                        claim_type="CT-02"
                    ),
                ),
                evidence_items=(
                    evidence(),
                ),
            )

    def test_attention_claim_cannot_create_event_candidate(self):
        with self.assertRaises(
            EventConstructionError
        ):
            self.builder.build(
                event_id="EVENT-1",
                claims=(
                    claim(
                        claim_type="CT-01"
                    ),
                ),
                evidence_items=(
                    evidence(),
                ),
            )

    def test_narrative_claim_cannot_create_event_candidate(self):
        with self.assertRaises(
            EventConstructionError
        ):
            self.builder.build(
                event_id="EVENT-1",
                claims=(
                    claim(
                        claim_type="CT-07"
                    ),
                ),
                evidence_items=(
                    evidence(),
                ),
            )

    def test_event_occurrence_claim_can_create_candidate(self):
        result = self.builder.build(
            event_id="EVENT-1",
            event_type="CONTROLLED_TEST_EVENT",
            event_start=dt(2),
            event_geography=("CITY-A",),
            claims=(claim(),),
            evidence_items=(evidence(),),
        )

        self.assertTrue(
            result.candidate_only
        )
        self.assertEqual(
            result.event_id,
            "EVENT-1",
        )

    def test_z1_support_does_not_become_production_support(self):
        result = self.builder.build(
            event_id="EVENT-1",
            claims=(claim(),),
            evidence_items=(
                evidence(
                    zone="Z1"
                ),
            ),
        )

        self.assertFalse(
            result.production_support_present
        )

    def test_z2_support_is_visible_as_production_support(self):
        result = self.builder.build(
            event_id="EVENT-1",
            claims=(claim(),),
            evidence_items=(
                evidence(
                    zone="Z2"
                ),
            ),
        )

        self.assertTrue(
            result.production_support_present
        )

    def test_event_contradiction_is_explicit(self):
        result = self.builder.build(
            event_id="EVENT-1",
            claims=(claim(),),
            evidence_items=(
                evidence(
                    evidence_id="EV-S"
                ),
                evidence(
                    evidence_id="EV-C",
                    supported=(),
                    contradicted=("CL-1",),
                    origin_key="ORIGIN-C",
                ),
            ),
        )

        self.assertEqual(
            result.contradiction_state,
            EventContradictionState
            .CONTRADICTION_PRESENT,
        )

        self.assertEqual(
            result.contradicting_evidence_ids,
            ("EV-C",),
        )

    def test_multiple_publications_can_support_one_event(self):
        c1 = claim(
            claim_id="CL-1",
            publication_id="PUB-1",
        )

        c2 = claim(
            claim_id="CL-2",
            publication_id="PUB-2",
        )

        e1 = evidence(
            evidence_id="EV-1",
            supported=("CL-1",),
            publication_id="PUB-1",
            origin_key="ORIGIN-1",
        )

        e2 = evidence(
            evidence_id="EV-2",
            supported=("CL-2",),
            publication_id="PUB-2",
            origin_key="ORIGIN-2",
        )

        result = self.builder.build(
            event_id="EVENT-1",
            claims=(c1, c2),
            evidence_items=(e1, e2),
        )

        self.assertEqual(
            set(result.supporting_claim_ids),
            {"CL-1", "CL-2"},
        )

    def test_one_publication_can_feed_multiple_explicit_events(self):
        c = claim(
            claim_id="CL-1",
            publication_id="PUB-1",
        )

        e = evidence(
            publication_id="PUB-1",
        )

        event_a = self.builder.build(
            event_id="EVENT-A",
            claims=(c,),
            evidence_items=(e,),
        )

        event_b = self.builder.build(
            event_id="EVENT-B",
            claims=(c,),
            evidence_items=(e,),
        )

        self.assertNotEqual(
            event_a.event_id,
            event_b.event_id,
        )

    def test_event_candidate_has_no_issue_identity(self):
        result = self.builder.build(
            event_id="EVENT-1",
            claims=(claim(),),
            evidence_items=(evidence(),),
        )

        self.assertFalse(
            hasattr(result, "issue_id")
        )

    def test_causal_claim_cannot_silently_create_event(self):
        with self.assertRaises(
            EventConstructionError
        ):
            self.builder.build(
                event_id="EVENT-1",
                claims=(
                    claim(
                        claim_type="CT-10"
                    ),
                ),
                evidence_items=(
                    evidence(),
                ),
            )

    def test_invalid_event_interval_is_rejected(self):
        with self.assertRaises(ValueError):
            self.builder.build(
                event_id="EVENT-1",
                event_start=dt(5),
                event_end=dt(2),
                claims=(claim(),),
                evidence_items=(evidence(),),
            )


if __name__ == "__main__":
    unittest.main()
