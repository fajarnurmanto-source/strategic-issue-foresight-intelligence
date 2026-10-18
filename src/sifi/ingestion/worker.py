"""Background Worker for continuous synthetic ingestion."""
import threading
import time
import random
from datetime import datetime, timezone
from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState
from sifi.domain import PersistenceClass, TrajectoryDimension
from sifi.issues.repository import IssueObjectRepository
from sifi.persistence import SQLiteVersionStore

class IngestionWorker(threading.Thread):
    def __init__(self, db_path, interval=15):
        super().__init__(daemon=True)
        self.db_path = db_path
        self.interval = interval
        self._stop_event = threading.Event()

    def run(self):
        while not self._stop_event.is_set():
            if self._stop_event.wait(self.interval): break
            self._generate_mock_issue()

    def _generate_mock_issue(self):
        try:
            store = SQLiteVersionStore(self.db_path)
            repo = IssueObjectRepository(store)
            now = datetime.now(timezone.utc)
            issue_id = f"AUTO-{int(now.timestamp())}"
            
            titles = [
                "Cyber attack detected on critical infrastructure",
                "Supply chain collapse reported in Jakarta",
                "Market stabilization observed after crisis",
                "Political unrest escalates in the region",
                "Natural disaster disrupts logistics"
            ]
            title = random.choice(titles)

            issue = IssueObject(
                issue_id=issue_id,
                title=title,
                issue_type="auto_ingest",
                persistence_class=PersistenceClass.PC_01_INCIDENT,
                lifecycle_state="ACTIVE",
                first_seen=now,
                last_updated=now,
                identity_review_state=None,
                evidence=IssueEvidenceLinks(
                    supporting_evidence_ids=(f"EV-{issue_id}",), 
                    contradictory_evidence_ids=()
                ),
                geography=IssueGeography(),
                trajectories=tuple(
                    IssueTrajectoryState(dimension=d, trajectory_state="stable", as_of=now, evidence_ids=(f"EV-{issue_id}",))
                    for d in TrajectoryDimension
                )
            )
            repo.append(issue, version=1, effective_at=now, recorded_at=now)
            if hasattr(store, 'close'):
                store.close()
            print(f"[WORKER] Ingested new issue: {issue_id} - {title}")
        except Exception as e:
            print(f"[WORKER] Error: {e}")

    def stop(self):
        self._stop_event.set()
