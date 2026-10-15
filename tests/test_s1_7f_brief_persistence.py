from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.persistence import SQLiteVersionStore
from sifi.workflow import (
    BriefBoundaryError, BriefRepositoryError, EvidenceBriefRepository,
    EvidenceLinkedBrief,
)


BASE = datetime(2026, 10, 15, 8, 0, tzinfo=timezone.utc)


def brief(summary="Summary"):
    return EvidenceLinkedBrief(
        brief_id="BRIEF-PERSIST-1", issue_id="ISSUE-1", summary=summary,
        evidence_refs=("EV-1",), created_at=BASE + timedelta(hours=1),
        analytical_cutoff=BASE,
    )


class BriefPersistenceTests(unittest.TestCase):
    def test_001_brief_survives_file_reopen(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "sifi.sqlite"
            with SQLiteVersionStore(database) as store:
                EvidenceBriefRepository(store).append(
                    brief(), version=1, effective_at=BASE, recorded_at=BASE
                )
            with SQLiteVersionStore(database) as reopened:
                recovered = EvidenceBriefRepository(reopened).latest("BRIEF-PERSIST-1")
                self.assertEqual(recovered.brief, brief())

    def test_002_history_is_append_only(self):
        with SQLiteVersionStore(":memory:") as store:
            repo = EvidenceBriefRepository(store)
            repo.append(brief(), version=1, effective_at=BASE, recorded_at=BASE)
            repo.append(
                brief("Revised summary"), version=2,
                effective_at=BASE + timedelta(hours=2),
                recorded_at=BASE + timedelta(hours=2),
            )
            self.assertEqual(tuple(item.version for item in repo.history("BRIEF-PERSIST-1")), (1, 2))
            self.assertEqual(repo.history("BRIEF-PERSIST-1")[0].brief.summary, "Summary")

    def test_003_version_gap_is_rejected(self):
        with SQLiteVersionStore(":memory:") as store:
            with self.assertRaises(BriefRepositoryError):
                EvidenceBriefRepository(store).append(
                    brief(), version=2, effective_at=BASE, recorded_at=BASE
                )

    def test_004_brief_cannot_authorize_production_action(self):
        with self.assertRaises(BriefBoundaryError):
            EvidenceLinkedBrief(
                brief_id="BRIEF-X", issue_id="ISSUE-1", summary="Summary",
                evidence_refs=("EV-1",), created_at=BASE,
                analytical_cutoff=BASE, production_action_authorized=True,
            )


if __name__ == "__main__":
    unittest.main()
