import unittest
from datetime import datetime, timezone

from sifi.domain import AdmissionZone
from sifi.evidence.model import EvidenceItem
from sifi.workflow.brief import EvidenceLinkedBrief
from sifi.workflow.brief_readiness import BriefReadinessStatus
from sifi.workflow.evidence_preflight import (
    EvidencePreflightError,
    assess_brief_from_evidence_items,
)


def moment(day):
    return datetime(2026, 10, day, tzinfo=timezone.utc)


def brief():
    return EvidenceLinkedBrief(
        brief_id="BR-M3-001",
        issue_id="IS-M3-001",
        summary="Synthetic example",
        evidence_refs=("EV-1", "EV-2"),
        created_at=moment(16),
        analytical_cutoff=moment(15),
    )


def evidence(identifier, day):
    return EvidenceItem(
        evidence_id=identifier,
        source_id="SRC-SYNTHETIC",
        evidence_kind="SYNTHETIC_TEST",
        recorded_at=moment(day),
        admission_zone=AdmissionZone.Z2_PRODUCTION_ADMITTED,
        origin_key="ORIGIN-SYNTHETIC",
    )


class EvidencePreflightTests(unittest.TestCase):
    def test_existing_records_before_cutoff(self):
        result = assess_brief_from_evidence_items(
            brief(),
            evidence_items=(evidence("EV-1", 12), evidence("EV-2", 15)),
        )
        self.assertEqual(
            result.status,
            BriefReadinessStatus.READY_FOR_HUMAN_REVIEW,
        )
        self.assertFalse(result.human_approval_granted)
        self.assertFalse(result.production_action_authorized)

    def test_missing_record_requires_remediation(self):
        result = assess_brief_from_evidence_items(
            brief(),
            evidence_items=(evidence("EV-1", 12),),
        )
        self.assertEqual(result.missing_refs, ("EV-2",))

    def test_late_record_requires_remediation(self):
        result = assess_brief_from_evidence_items(
            brief(),
            evidence_items=(evidence("EV-1", 12), evidence("EV-2", 16)),
        )
        self.assertEqual(result.later_than_cutoff_refs, ("EV-2",))

    def test_duplicate_identity_fails_closed(self):
        with self.assertRaises(EvidencePreflightError):
            assess_brief_from_evidence_items(
                brief(),
                evidence_items=(evidence("EV-1", 12), evidence("EV-1", 13)),
            )

    def test_invalid_record_type_rejected(self):
        with self.assertRaises(TypeError):
            assess_brief_from_evidence_items(
                brief(),
                evidence_items=(object(),),
            )


if __name__ == "__main__":
    unittest.main()
