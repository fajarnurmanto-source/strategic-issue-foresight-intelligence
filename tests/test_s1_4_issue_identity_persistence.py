import unittest
from dataclasses import replace
from datetime import datetime, timezone

from sifi.domain import (
    ObservabilityState,
    PersistenceClass,
    TrajectoryDimension,
)
from sifi.issues import (
    ExplicitIssueAssembler,
    IssueEvidenceLinks,
    IssueGeography,
    IssueIdentityProposal,
    IssueLineage,
    IssueObject,
    IssueObjectRepository,
    IssueTrajectoryState,
    IssueVersionSequenceError,
)
from sifi.persistence import SQLiteVersionStore


UTC = timezone.utc


def dt(day):
    return datetime(
        2026, 2, day, 12, 0,
        tzinfo=UTC,
    )


def trajectories(day=2):
    return tuple(
        IssueTrajectoryState(
            dimension=dimension,
            trajectory_state=(
                "CONTROLLED_TEST_STATE_"
                + dimension.value
            ),
            as_of=dt(day),
        )
        for dimension in TrajectoryDimension
    )


def issue(
    issue_id="ISSUE-1",
    title="Controlled Test Issue",
    lifecycle_state="CONTROLLED_TEST_LIFECYCLE",
    related_event_ids=("EVENT-1",),
    first_seen=None,
    last_updated=None,
    **changes,
):
    base = IssueObject(
        issue_id=issue_id,
        title=title,
        issue_type="CONTROLLED_TEST_TYPE",
        persistence_class=PersistenceClass("PC-01"),
        lifecycle_state=lifecycle_state,
        first_seen=first_seen or dt(1),
        last_updated=last_updated or dt(2),
        identity_review_state="REVIEW_PENDING",
        evidence=IssueEvidenceLinks(
            related_event_ids=related_event_ids,
            supporting_claim_ids=("CLAIM-S",),
            disputed_claim_ids=("CLAIM-D",),
            supporting_evidence_ids=("EV-S",),
            contradictory_evidence_ids=("EV-C",),
            context_only_evidence_ids=("EV-X",),
            excluded_production_evidence_ids=("EV-Z1",),
        ),
        geography=IssueGeography(
            source_geography=("SOURCE-GEO",),
            event_geography=("EVENT-GEO",),
            affected_geography=("AFFECTED-GEO",),
            policy_jurisdiction=("POLICY-JUR",),
            relevance_geography=("RELEVANCE-GEO",),
            coverage_profile_id="PROFILE-1",
        ),
        trajectories=trajectories(),
        observability_states=(
            ObservabilityState("OBS-05"),
        ),
        competent_authority_candidates=(
            "AUTHORITY-CANDIDATE",
        ),
        authority_review_state=(
            "AUTHORITY_REVIEW_REQUIRED"
        ),
        operational_capability_review_state=(
            "CAPABILITY_REVIEW_REQUIRED"
        ),
        uncertainty_notes=("UNCERTAINTY",),
        evidence_gaps=("GAP",),
        known_limitations=("LIMITATION",),
        watch_conditions=("WATCH",),
        lineage=IssueLineage(),
        review_required=True,
    )

    return replace(base, **changes)


class IssueObjectModelTests(unittest.TestCase):

    def test_issue_id_required(self):
        with self.assertRaises(ValueError):
            issue(issue_id=" ")

    def test_title_required(self):
        with self.assertRaises(ValueError):
            issue(title=" ")

    def test_last_updated_cannot_precede_first_seen(self):
        with self.assertRaises(ValueError):
            issue(
                first_seen=dt(5),
                last_updated=dt(2),
            )

    def test_stable_identity_survives_title_change(self):
        old = issue()
        new = replace(
            old,
            title="Revised Issue Title",
            last_updated=dt(3),
        )

        self.assertEqual(
            old.issue_id,
            new.issue_id,
        )
        self.assertNotEqual(
            old.title,
            new.title,
        )

    def test_stable_identity_survives_lifecycle_change(self):
        old = issue()
        new = replace(
            old,
            lifecycle_state="ANOTHER_TEST_STATE",
            last_updated=dt(3),
        )

        self.assertEqual(
            old.issue_id,
            new.issue_id,
        )

    def test_support_and_contradiction_evidence_are_distinct(self):
        obj = issue()

        self.assertEqual(
            obj.evidence.supporting_evidence_ids,
            ("EV-S",),
        )
        self.assertEqual(
            obj.evidence.contradictory_evidence_ids,
            ("EV-C",),
        )

    def test_evidence_category_overlap_rejected(self):
        with self.assertRaises(ValueError):
            IssueEvidenceLinks(
                supporting_evidence_ids=("EV-1",),
                contradictory_evidence_ids=("EV-1",),
            )

    def test_supporting_and_disputed_claim_overlap_rejected(self):
        with self.assertRaises(ValueError):
            IssueEvidenceLinks(
                supporting_claim_ids=("C-1",),
                disputed_claim_ids=("C-1",),
            )

    def test_event_relationship_is_explicit(self):
        obj = issue(
            related_event_ids=(
                "EVENT-1",
                "EVENT-2",
            )
        )

        self.assertEqual(
            obj.evidence.related_event_ids,
            ("EVENT-1", "EVENT-2"),
        )

    def test_same_event_may_be_explicitly_related_to_multiple_issues(self):
        a = issue(
            issue_id="ISSUE-A",
            related_event_ids=("EVENT-X",),
        )

        b = issue(
            issue_id="ISSUE-B",
            related_event_ids=("EVENT-X",),
        )

        self.assertEqual(
            a.evidence.related_event_ids,
            b.evidence.related_event_ids,
        )
        self.assertNotEqual(
            a.issue_id,
            b.issue_id,
        )

    def test_all_trajectory_dimensions_are_required(self):
        incomplete = trajectories()[:-1]

        with self.assertRaises(ValueError):
            issue(
                trajectories=incomplete,
            )

    def test_duplicate_trajectory_dimension_rejected(self):
        values = trajectories()

        duplicate = (
            values[0],
            values[0],
            *values[2:],
        )

        with self.assertRaises(ValueError):
            issue(
                trajectories=duplicate,
            )

    def test_trajectory_dimensions_remain_separate(self):
        obj = issue()

        self.assertEqual(
            set(
                t.dimension
                for t in obj.trajectories
            ),
            set(TrajectoryDimension),
        )

    def test_geography_dimensions_remain_separate(self):
        obj = issue()

        self.assertNotEqual(
            obj.geography.event_geography,
            obj.geography.affected_geography,
        )
        self.assertNotEqual(
            obj.geography.policy_jurisdiction,
            obj.geography.relevance_geography,
        )

    def test_observability_state_is_preserved(self):
        obj = issue()

        self.assertEqual(
            obj.observability_states,
            (ObservabilityState("OBS-05"),),
        )

    def test_candidate_authority_does_not_become_established_authority(self):
        obj = issue()

        self.assertEqual(
            obj.competent_authority_candidates,
            ("AUTHORITY-CANDIDATE",),
        )

        self.assertFalse(
            hasattr(obj, "established_authority")
        )

    def test_no_universal_issue_score(self):
        obj = issue()

        for name in [
            "issue_score",
            "confidence_score",
            "urgency_score",
            "priority_score",
        ]:
            self.assertFalse(
                hasattr(obj, name)
            )

    def test_lineage_fields_are_preserved(self):
        lineage = IssueLineage(
            predecessor_issue_ids=("ISSUE-OLD",),
            successor_issue_ids=("ISSUE-NEW",),
            merged_from_issue_ids=("ISSUE-A",),
            merged_into_issue_id="ISSUE-M",
            split_from_issue_id="ISSUE-P",
            split_into_issue_ids=(
                "ISSUE-S1",
                "ISSUE-S2",
            ),
            lineage_review_state="REVIEW_PENDING",
        )

        obj = issue(
            lineage=lineage
        )

        self.assertEqual(
            obj.lineage,
            lineage,
        )

    def test_payload_roundtrip_preserves_issue(self):
        original = issue()

        recovered = IssueObject.from_payload(
            original.to_payload()
        )

        self.assertEqual(
            recovered,
            original,
        )


class ExplicitIdentityTests(unittest.TestCase):

    def test_explicit_issue_id_required(self):
        with self.assertRaises(ValueError):
            IssueIdentityProposal(
                issue_id=" ",
                rationale="reason",
            )

    def test_identity_rationale_required(self):
        with self.assertRaises(ValueError):
            IssueIdentityProposal(
                issue_id="ISSUE-1",
                rationale=" ",
            )

    def test_duplicate_event_relationship_rejected(self):
        with self.assertRaises(ValueError):
            IssueIdentityProposal(
                issue_id="ISSUE-1",
                rationale="reason",
                related_event_ids=(
                    "EVENT-1",
                    "EVENT-1",
                ),
            )

    def test_explicit_identity_can_be_verified(self):
        obj = issue()

        proposal = IssueIdentityProposal(
            issue_id="ISSUE-1",
            rationale="Controlled identity rationale",
            related_event_ids=("EVENT-1",),
            identity_review_state="REVIEW_PENDING",
        )

        result = ExplicitIssueAssembler.verify_identity(
            proposal,
            obj,
        )

        self.assertIs(
            result,
            obj,
        )

    def test_identity_mismatch_rejected(self):
        obj = issue()

        proposal = IssueIdentityProposal(
            issue_id="OTHER-ISSUE",
            rationale="Controlled identity rationale",
            related_event_ids=("EVENT-1",),
            identity_review_state="REVIEW_PENDING",
        )

        with self.assertRaises(ValueError):
            ExplicitIssueAssembler.verify_identity(
                proposal,
                obj,
            )

    def test_event_relationship_mismatch_rejected(self):
        obj = issue()

        proposal = IssueIdentityProposal(
            issue_id="ISSUE-1",
            rationale="Controlled identity rationale",
            related_event_ids=("OTHER-EVENT",),
            identity_review_state="REVIEW_PENDING",
        )

        with self.assertRaises(ValueError):
            ExplicitIssueAssembler.verify_identity(
                proposal,
                obj,
            )

    def test_no_automatic_issue_identity_inference(self):
        assembler = ExplicitIssueAssembler()

        self.assertFalse(
            hasattr(
                assembler,
                "infer_issue_id",
            )
        )

        self.assertFalse(
            hasattr(
                assembler,
                "cluster_into_issue",
            )
        )


class IssuePersistenceTests(unittest.TestCase):

    def setUp(self):
        self.store = SQLiteVersionStore(":memory:")
        self.repo = IssueObjectRepository(
            self.store
        )

    def tearDown(self):
        self.store.close()

    def test_first_version_must_be_one(self):
        with self.assertRaises(
            IssueVersionSequenceError
        ):
            self.repo.append(
                issue(),
                version=2,
                effective_at=dt(2),
                recorded_at=dt(2),
            )

    def test_append_version_one(self):
        self.repo.append(
            issue(),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        saved = self.repo.get(
            "ISSUE-1",
            1,
        )

        self.assertIsNotNone(saved)
        self.assertEqual(
            saved.version,
            1,
        )

    def test_sequential_version_two(self):
        self.repo.append(
            issue(),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        updated = replace(
            issue(),
            title="Version Two",
            last_updated=dt(4),
        )

        self.repo.append(
            updated,
            version=2,
            effective_at=dt(4),
            recorded_at=dt(4),
        )

        self.assertEqual(
            self.repo.latest(
                "ISSUE-1"
            ).version,
            2,
        )

    def test_non_sequential_version_rejected(self):
        self.repo.append(
            issue(),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        with self.assertRaises(
            IssueVersionSequenceError
        ):
            self.repo.append(
                issue(),
                version=3,
                effective_at=dt(4),
                recorded_at=dt(4),
            )

    def test_duplicate_version_rejected_by_sequence_guard(self):
        self.repo.append(
            issue(),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        with self.assertRaises(
            IssueVersionSequenceError
        ):
            self.repo.append(
                issue(),
                version=1,
                effective_at=dt(3),
                recorded_at=dt(3),
            )

    def test_history_is_append_only(self):
        first = issue()

        second = replace(
            first,
            title="Updated title",
            last_updated=dt(4),
        )

        self.repo.append(
            first,
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        self.repo.append(
            second,
            version=2,
            effective_at=dt(4),
            recorded_at=dt(4),
        )

        history = self.repo.history(
            "ISSUE-1"
        )

        self.assertEqual(
            tuple(x.version for x in history),
            (1, 2),
        )

        self.assertEqual(
            history[0].issue.title,
            "Controlled Test Issue",
        )

        self.assertEqual(
            history[1].issue.title,
            "Updated title",
        )

    def test_as_of_excludes_later_version(self):
        first = issue()

        second = replace(
            first,
            title="Later knowledge",
            last_updated=dt(10),
        )

        self.repo.append(
            first,
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        self.repo.append(
            second,
            version=2,
            effective_at=dt(10),
            recorded_at=dt(10),
        )

        historical = self.repo.as_of(
            "ISSUE-1",
            dt(5),
        )

        self.assertEqual(
            historical.version,
            1,
        )

        self.assertEqual(
            historical.issue.title,
            "Controlled Test Issue",
        )

    def test_later_recorded_correction_does_not_leak_backward(self):
        first = issue()

        correction = replace(
            first,
            title="Corrected issue",
            last_updated=dt(3),
        )

        self.repo.append(
            first,
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        self.repo.append(
            correction,
            version=2,
            effective_at=dt(3),
            recorded_at=dt(10),
        )

        before_recording = self.repo.as_of(
            "ISSUE-1",
            dt(5),
        )

        after_recording = self.repo.as_of(
            "ISSUE-1",
            dt(11),
        )

        self.assertEqual(
            before_recording.version,
            1,
        )

        self.assertEqual(
            after_recording.version,
            2,
        )

    def test_persisted_evidence_categories_roundtrip(self):
        self.repo.append(
            issue(),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        saved = self.repo.latest(
            "ISSUE-1"
        ).issue

        self.assertEqual(
            saved.evidence.supporting_evidence_ids,
            ("EV-S",),
        )

        self.assertEqual(
            saved.evidence.excluded_production_evidence_ids,
            ("EV-Z1",),
        )

    def test_persisted_trajectory_dimensions_roundtrip(self):
        self.repo.append(
            issue(),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        saved = self.repo.latest(
            "ISSUE-1"
        ).issue

        self.assertEqual(
            set(
                x.dimension
                for x in saved.trajectories
            ),
            set(TrajectoryDimension),
        )

    def test_different_issue_histories_are_independent(self):
        self.repo.append(
            issue(
                issue_id="ISSUE-A"
            ),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        self.repo.append(
            issue(
                issue_id="ISSUE-B"
            ),
            version=1,
            effective_at=dt(2),
            recorded_at=dt(2),
        )

        self.assertEqual(
            self.repo.latest(
                "ISSUE-A"
            ).issue.issue_id,
            "ISSUE-A",
        )

        self.assertEqual(
            self.repo.latest(
                "ISSUE-B"
            ).issue.issue_id,
            "ISSUE-B",
        )


if __name__ == "__main__":
    unittest.main()
