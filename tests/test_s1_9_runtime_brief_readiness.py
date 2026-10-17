import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path

from sifi.domain import AdmissionZone
from sifi.evidence.model import EvidenceItem
from sifi.evidence.repository import EvidenceItemRepository
from sifi.workflow.brief import EvidenceLinkedBrief
from sifi.workflow.brief_readiness import BriefReadinessStatus
from sifi.workflow.runtime import LocalWorkflowRuntime


def moment(day):
    return datetime(2026, 10, day, tzinfo=timezone.utc)


def brief():
    return EvidenceLinkedBrief(
        brief_id="BR-RUNTIME-001",
        issue_id="IS-RUNTIME-001",
        summary="Synthetic test brief",
        evidence_refs=("EV-1", "EV-2"),
        created_at=moment(16),
        analytical_cutoff=moment(15),
    )


def evidence(identifier, day):
    return EvidenceItem(
        evidence_id=identifier,
        source_id="SYNTHETIC-SOURCE",
        evidence_kind="SYNTHETIC_TEST",
        recorded_at=moment(day),
        admission_zone=AdmissionZone.Z2_PRODUCTION_ADMITTED,
        origin_key="SYNTHETIC-ORIGIN",
    )


class RuntimeBriefReadinessTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        database = Path(self.temp.name) / "sifi-test.sqlite"
        self.runtime = LocalWorkflowRuntime(database)
        self.evidence_repo = EvidenceItemRepository(
            self.runtime.version_store
        )

    def tearDown(self):
        self.runtime.close()
        self.temp.cleanup()

    def record_evidence(self, identifier, day, recorded=None):
        item = evidence(identifier, day)
        stored_at = day if recorded is None else recorded
        self.evidence_repo.append(
            item,
            version=1,
            effective_at=moment(day),
            recorded_at=moment(stored_at),
        )

    def test_complete_historical_references(self):
        self.runtime.record_brief(brief(), recorded_at=moment(16))
        self.record_evidence("EV-1", 12)
        self.record_evidence("EV-2", 15)

        result = self.runtime.assess_brief_readiness(
            "BR-RUNTIME-001"
        )

        self.assertEqual(
            result.status,
            BriefReadinessStatus.READY_FOR_HUMAN_REVIEW,
        )
        self.assertEqual(result.checked_refs, ("EV-1", "EV-2"))
        self.assertFalse(result.human_approval_granted)
        self.assertFalse(result.production_action_authorized)

    def test_missing_historical_evidence(self):
        self.runtime.record_brief(brief(), recorded_at=moment(16))
        self.record_evidence("EV-1", 12)

        result = self.runtime.assess_brief_readiness(
            "BR-RUNTIME-001"
        )

        self.assertEqual(
            result.status,
            BriefReadinessStatus.NEEDS_REMEDIATION,
        )
        self.assertEqual(result.missing_refs, ("EV-2",))

    def test_later_persistence_does_not_backfill_past(self):
        self.runtime.record_brief(brief(), recorded_at=moment(16))
        self.record_evidence("EV-1", 12)
        self.record_evidence("EV-2", 12, recorded=17)

        result = self.runtime.assess_brief_readiness(
            "BR-RUNTIME-001"
        )

        self.assertEqual(result.missing_refs, ("EV-2",))

    def test_unknown_brief_fails_closed(self):
        with self.assertRaisesRegex(ValueError, "brief not found"):
            self.runtime.assess_brief_readiness("UNKNOWN")

    def test_assessment_does_not_append_versions(self):
        self.runtime.record_brief(brief(), recorded_at=moment(16))
        self.record_evidence("EV-1", 12)
        self.record_evidence("EV-2", 15)

        before = self.runtime.version_store.history(
            "EVIDENCE_LINKED_ANALYTICAL_BRIEF",
            "BR-RUNTIME-001",
        )
        evidence_before = self.evidence_repo.history("EV-1")

        self.runtime.assess_brief_readiness("BR-RUNTIME-001")

        self.assertEqual(
            self.runtime.version_store.history(
                "EVIDENCE_LINKED_ANALYTICAL_BRIEF",
                "BR-RUNTIME-001",
            ),
            before,
        )
        self.assertEqual(
            self.evidence_repo.history("EV-1"),
            evidence_before,
        )


if __name__ == "__main__":
    unittest.main()
