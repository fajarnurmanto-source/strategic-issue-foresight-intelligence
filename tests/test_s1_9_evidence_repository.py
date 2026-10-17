import unittest
from dataclasses import replace
from datetime import datetime, timezone

from sifi.domain import AdmissionZone
from sifi.evidence.model import EvidenceItem
from sifi.evidence.repository import (
    EVIDENCE_OBJECT_TYPE,
    EvidenceItemRepository,
    EvidenceRepositoryError,
)
from sifi.persistence import SQLiteVersionStore


def moment(day):
    return datetime(2026, 10, day, tzinfo=timezone.utc)


def evidence(recorded_day=10):
    return EvidenceItem(
        evidence_id="EV-PERSIST-001",
        source_id="SOURCE-001",
        evidence_kind="SYNTHETIC_TEST",
        recorded_at=moment(recorded_day),
        admission_zone=AdmissionZone.Z2_PRODUCTION_ADMITTED,
        origin_key="TEST-ORIGIN",
        supported_claim_ids=("CLAIM-001",),
        observed_at=moment(9),
        human_review_state="UNREVIEWED",
    )


class EvidenceRepositoryTests(unittest.TestCase):
    def setUp(self):
        self.store = SQLiteVersionStore(":memory:")
        self.repo = EvidenceItemRepository(self.store)

    def tearDown(self):
        self.store.close()

    def save(self, item, version=1, effective=10, recorded=10):
        return self.repo.append(
            item,
            version=version,
            effective_at=moment(effective),
            recorded_at=moment(recorded),
        )

    def test_roundtrip_and_identity(self):
        item = evidence()
        self.save(item)
        restored = self.repo.latest(item.evidence_id)

        self.assertEqual(restored.version, 1)
        self.assertEqual(restored.evidence, item)
        self.assertEqual(
            self.repo.get(item.evidence_id, 1).evidence,
            item,
        )

    def test_version_sequence_rejected(self):
        self.save(evidence())

        with self.assertRaises(EvidenceRepositoryError):
            self.save(evidence(), version=3, recorded=11)

        self.assertEqual(len(self.repo.history("EV-PERSIST-001")), 1)

    def test_bitemporal_historical_reconstruction(self):
        original = evidence()
        revised = replace(
            original,
            evidence_kind="CORRECTED_SYNTHETIC_TEST",
            recorded_at=moment(16),
        )

        self.save(original, version=1, effective=10, recorded=10)
        self.save(revised, version=2, effective=12, recorded=17)

        self.assertEqual(
            self.repo.as_of("EV-PERSIST-001", moment(15)).version,
            1,
        )
        self.assertEqual(
            self.repo.as_of("EV-PERSIST-001", moment(17)).version,
            2,
        )
        self.assertEqual(len(self.repo.history("EV-PERSIST-001")), 2)

    def test_future_record_not_visible(self):
        self.save(evidence(), effective=10, recorded=16)

        self.assertIsNone(
            self.repo.as_of("EV-PERSIST-001", moment(15))
        )

    def test_invalid_record_chronology_rejected(self):
        with self.assertRaises(EvidenceRepositoryError):
            self.save(evidence(recorded_day=16), recorded=15)

        self.assertIsNone(self.repo.latest("EV-PERSIST-001"))

    def test_missing_evidence_returns_none(self):
        self.assertIsNone(self.repo.latest("UNKNOWN"))
        self.assertIsNone(self.repo.as_of("UNKNOWN", moment(15)))

    def test_distinct_object_type(self):
        self.save(evidence())
        snapshot = self.store.latest(
            EVIDENCE_OBJECT_TYPE,
            "EV-PERSIST-001",
        )
        self.assertIsNotNone(snapshot)
        self.assertEqual(snapshot.object_type, "EVIDENCE_ITEM")


if __name__ == "__main__":
    unittest.main()
