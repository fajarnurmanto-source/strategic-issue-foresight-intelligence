import unittest
from datetime import datetime, timezone

from sifi.domain import AdmissionZone
from sifi.evidence.model import EvidenceItem
from sifi.evidence.repository import EvidenceItemRepository
from sifi.persistence import SQLiteVersionStore
from sifi.workflow import BriefReadinessStatus
from sifi.workflow.brief import EvidenceLinkedBrief
from sifi.workflow.repository_preflight import assess_brief_from_repository


def at(day):
    return datetime(2026, 10, day, tzinfo=timezone.utc)


class AdmissionReadinessTests(unittest.TestCase):
    def setUp(self):
        self.store = SQLiteVersionStore(":memory:")
        self.repo = EvidenceItemRepository(self.store)
        self.brief = EvidenceLinkedBrief(
            brief_id="BR-ADMISSION",
            issue_id="IS-ADMISSION",
            summary="Synthetic example",
            evidence_refs=("EV-A",),
            created_at=at(16),
            analytical_cutoff=at(15),
        )

    def tearDown(self):
        self.store.close()

    def save(self, zone):
        self.repo.append(
            EvidenceItem(
                evidence_id="EV-A",
                source_id="SOURCE-TEST",
                evidence_kind="SYNTHETIC",
                recorded_at=at(12),
                admission_zone=zone,
                origin_key="ORIGIN-TEST",
            ),
            version=1,
            effective_at=at(12),
            recorded_at=at(12),
        )

    def test_non_z2_is_not_ready(self):
        restricted = next(
            zone for zone in AdmissionZone
            if zone != AdmissionZone.Z2_PRODUCTION_ADMITTED
        )
        self.save(restricted)
        result = assess_brief_from_repository(
            self.brief, repository=self.repo
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )
        self.assertEqual(result.restricted_refs, ("EV-A",))
        self.assertFalse(result.human_approval_granted)
        self.assertFalse(result.production_action_authorized)

    def test_z2_has_no_automatic_approval(self):
        self.save(AdmissionZone.Z2_PRODUCTION_ADMITTED)
        result = assess_brief_from_repository(
            self.brief, repository=self.repo
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.READY_FOR_HUMAN_REVIEW,
        )
        self.assertEqual(result.restricted_refs, ())
        self.assertFalse(result.human_approval_granted)
        self.assertFalse(result.production_action_authorized)

    def test_missing_evidence_stays_blocked(self):
        result = assess_brief_from_repository(
            self.brief, repository=self.repo
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )
        self.assertEqual(result.missing_refs, ("EV-A",))


if __name__ == "__main__":
    unittest.main()
