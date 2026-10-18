"""Parse synthetic JSON feeds into IssueObjects."""
import json
from datetime import datetime, timezone
from sifi.domain import PersistenceClass, TrajectoryDimension
from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState

def parse_feed(file_path: str):
    with open(file_path, "r") as f:
        data = json.load(f)
    
    issues = []
    now = datetime.now(timezone.utc)
    
    for item in data.get("items", []):
        issue = IssueObject(
            issue_id=item["id"],
            title=item["title"],
            issue_type="ingested",
            persistence_class=PersistenceClass.PC_02_RECURRING,
            lifecycle_state="ACTIVE",
            first_seen=now,
            last_updated=now,
            identity_review_state=None,
            evidence=IssueEvidenceLinks(
                supporting_evidence_ids=tuple(item.get("supporting", [])),
                contradictory_evidence_ids=tuple(item.get("contradictory", []))
            ),
            geography=IssueGeography(),
            trajectories=tuple(
                IssueTrajectoryState(dimension=d, trajectory_state="stable", as_of=now, evidence_ids=("EV-FEED",))
                for d in TrajectoryDimension
            )
        )
        issues.append(issue)
    return issues
