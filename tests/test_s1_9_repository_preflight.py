import unittest
from dataclasses import replace
from datetime import datetime, timezone

from sifi.domain import AdmissionZone
from sifi.evidence.model import EvidenceItem
from sifi.evidence.repository import EvidenceItemRepository
from sifi.persistence import SQLiteVersionStore
from sifi.workflow.brief import EvidenceLinkedBrief
from sifi.workflow.brief_readiness import BriefReadinessStatus
from sifi.workflow.repository_preflight import (
    assess_brief_from_repository,
)


def moment(day):
    return datetime(2026, 10, day, tzinfo=timezone.utc)


def evidence(identifier, day=10):
    return EvidenceItem(
        evidence_id=identifier,
        source_id="SYNTHETIC-SOURCE",
        evidence_kind="SYNTHETIC_TEST",
        recorded_at=moment(day),
        admission_zone=AdmissionZone.Z2_PRODUCTION_ADMITTED,
        origin_key="SYNTHETIC-ORIGIN",
    )


def brief():
    return EvidenceLinkedBrief(
        brief_id="BR-REPO-001",
        issue_id="IS-REPO-001",
        summary="Synthetic historical example",
        evidence_refs=("EV-1", "EV-2"),
        created_at=moment(16),
        analytical_cutoff=moment(15),
    )


class RepositoryPreflightTests(unittest.TestCase):
    def setUp(self):
        self.store = SQLiteVersionStore(":memory:")
        self.repo = EvidenceItemRepository(self.store)

    def tearDown(self):
        self.store.close()

    def save(
        self, item, *,
        version=1, effective=10, recorded=10,
    ):
        return self.repo.append(
            item,
            version=version,
            effective_at=moment(effective),
            recorded_at=moment(recorded),
        )

    def test_both_records_visible_at_cutoff(self):
        self.save(evidence("EV-1"))
        self.save(evidence("EV-2", 12), effective=12, recorded=12)

        result = assess_brief_from_repository(
            brief(), repository=self.repo,
        )

        self.assertEqual(
            result.status,
            BriefReadinessStatus.READY_FOR_HUMAN_REVIEW,
        )
        self.assertEqual(
            result.checked_refs,
            ("EV-1", "EV-2"),
        )
        self.assertFalse(result.human_approval_granted)
        self.assertFalse(result.production_action_authorized)

    def test_unknown_reference_requires_remediation(self):
        self.save(evidence("EV-1"))

        result = assess_brief_from_repository(
            brief(), repository=self.repo,
        )

        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )
        self.assertEqual(result.missing_refs, ("EV-2",))

    def test_recorded_after_cutoff_is_not_visible(self):
        self.save(evidence("EV-1"))
        self.save(
            evidence("EV-2", 16),
            effective=12,
            recorded=17,
        )

        result = assess_brief_from_repository(
            brief(), repository=self.repo,
        )

        self.assertEqual(result.missing_refs, ("EV-2",))
        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )

    def test_effective_after_cutoff_is_not_visible(self):
        self.save(evidence("EV-1"))
        self.save(
            evidence("EV-2", 12),
            effective=17,
            recorded=12,
        )

        result = assess_brief_from_repository(
            brief(), repository=self.repo,
        )

        self.assertEqual(result.missing_refs, ("EV-2",))

    def test_historical_version_used_not_later_revision(self):
        original = evidence("EV-1")
        revision = replace(
            original,
            evidence_kind="REVISED_SYNTHETIC_TEST",
            recorded_at=moment(17),
        )
        self.save(original)
        self.save(
            revision,
            version=2,
            effective=12,
            recorded=17,
        )
        self.save(evidence("EV-2", 12), effective=12, recorded=12)

        result = assess_brief_from_repository(
            brief(), repository=self.repo,
        )

        self.assertEqual(
            result.status,
            BriefReadinessStatus.READY_FOR_HUMAN_REVIEW,
        )
        self.assertEqual(result.missing_refs, ())


if __name__ == "__main__":
    unittest.main()
