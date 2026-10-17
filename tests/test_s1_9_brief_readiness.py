import unittest
from datetime import datetime, timezone

from sifi.workflow.brief import EvidenceLinkedBrief
from sifi.workflow.brief_readiness import (
    BriefReadinessStatus,
    assess_brief_readiness,
)


UTC = timezone.utc


def moment(day):
    return datetime(2026, 10, day, tzinfo=UTC)


def sample_brief():
    return EvidenceLinkedBrief(
        brief_id="BRIEF-TEST-001",
        issue_id="ISSUE-TEST-001",
        summary="Synthetic analytical example",
        evidence_refs=("E-001", "E-002"),
        created_at=moment(16),
        analytical_cutoff=moment(15),
    )


class BriefReadinessTests(unittest.TestCase):
    def test_complete_references_ready_for_human_review(self):
        result = assess_brief_readiness(
            sample_brief(),
            available_evidence={
                "E-001": moment(12),
                "E-002": moment(15),
            },
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.READY_FOR_HUMAN_REVIEW,
        )
        self.assertEqual(result.checked_refs, ("E-001", "E-002"))
        self.assertFalse(result.human_approval_granted)
        self.assertFalse(result.production_action_authorized)

    def test_missing_reference_requires_remediation(self):
        result = assess_brief_readiness(
            sample_brief(),
            available_evidence={"E-001": moment(12)},
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )
        self.assertEqual(result.missing_refs, ("E-002",))

    def test_future_evidence_requires_remediation(self):
        result = assess_brief_readiness(
            sample_brief(),
            available_evidence={
                "E-001": moment(12),
                "E-002": moment(16),
            },
        )
        self.assertEqual(
            result.later_than_cutoff_refs,
            ("E-002",),
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )

    def test_naive_availability_time_rejected(self):
        with self.assertRaises(ValueError):
            assess_brief_readiness(
                sample_brief(),
                available_evidence={
                    "E-001": datetime(2026, 10, 12),
                },
            )


if __name__ == "__main__":
    unittest.main()
