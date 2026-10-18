import unittest
from datetime import datetime, timezone
from sifi.analysis.foresight import RISK_HIGH, RISK_INSUFFICIENT, RISK_LOW, RISK_ELEVATED, derive_foresight
from sifi.domain import PersistenceClass, TrajectoryDimension
from sifi.issues.model import IssueEvidenceLinks, IssueGeography, IssueObject, IssueTrajectoryState

_T0 = datetime(2026, 1, 1, tzinfo=timezone.utc)

def _make_issue(*, supporting=("EV-SUPPORT-1",), contradictory=(), trajectory_evidence=None):
    # Secara default, semua trajektori menggunakan supporting evidence
    dims = []
    for d in TrajectoryDimension:
        ev_ids = trajectory_evidence.get(d, tuple(supporting)) if trajectory_evidence else tuple(supporting)
        dims.append(IssueTrajectoryState(
            dimension=d, trajectory_state="stable", as_of=_T0, evidence_ids=ev_ids
        ))
    return IssueObject(
        issue_id="ISSUE-TEST", title="Test", issue_type="test",
        persistence_class=PersistenceClass.PC_01_INCIDENT, lifecycle_state="ACTIVE",
        first_seen=_T0, last_updated=_T0, identity_review_state=None,
        evidence=IssueEvidenceLinks(supporting_evidence_ids=tuple(supporting), contradictory_evidence_ids=tuple(contradictory)),
        geography=IssueGeography(), trajectories=tuple(dims)
    )

class TestForesightDerivation(unittest.TestCase):
    def test_per_dimension_risk_elevates_global_risk(self):
        # Global contradiction ratio is low (1 vs 3), but TR-01 has 100% contradiction
        issue = _make_issue(
            supporting=("EV-1", "EV-2", "EV-3"),
            contradictory=("EV-CONTRA",),
            trajectory_evidence={
                TrajectoryDimension.TR_01_ATTENTION_TRAJECTORY: ("EV-CONTRA",),
                TrajectoryDimension.TR_02_EVIDENCE_TRAJECTORY: ("EV-1", "EV-2", "EV-3"),
                TrajectoryDimension.TR_03_CONDITION_TRAJECTORY: ("EV-1",),
                TrajectoryDimension.TR_04_NARRATIVE_TRAJECTORY: ("EV-1",),
                TrajectoryDimension.TR_05_COVERAGE_CONTEXT: ("EV-1",),
            }
        )
        proposal = derive_foresight(issue)
        
        # Cek per-dimensi
        tr01 = next(d for d in proposal.dimension_risks if d.dimension == "TR-01")
        self.assertEqual(tr01.level, RISK_HIGH)
        
        # Risiko global harus naik jadi HIGH karena salah satu dimensi HIGH
        self.assertEqual(proposal.risk.level, RISK_HIGH)
        self.assertTrue(any("FOCUS_EVIDENCE_GATHERING" in p for p in proposal.recommendation_proposals))

    def test_no_supporting_evidence_abstains(self):
        issue = _make_issue(supporting=())
        proposal = derive_foresight(issue)
        self.assertTrue(proposal.abstained)
        self.assertEqual(proposal.risk.level, RISK_INSUFFICIENT)

if __name__ == "__main__":
    unittest.main()
