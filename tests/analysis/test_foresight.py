"""Tests for sifi.analysis.foresight.

Each test builds a minimal IssueObject using the real constructor surface.
No file I/O, no runtime, no canonical DB.
"""

from __future__ import annotations

import unittest
from datetime import datetime, timezone

from sifi.analysis.foresight import (
    RISK_HIGH,
    RISK_INSUFFICIENT,
    RISK_LOW,
    derive_foresight,
)
from sifi.domain import PersistenceClass, TrajectoryDimension
from sifi.issues.model import (
    IssueEvidenceLinks,
    IssueGeography,
    IssueObject,
    IssueTrajectoryState,
)


_T0 = datetime(2026, 1, 1, tzinfo=timezone.utc)


def _make_issue(
    *,
    supporting=("EV-SUPPORT-1",),
    contradictory=(),
    context_only=(),
    uncertainty_notes=(),
    evidence_gaps=(),
    trajectory_state="stable",
):
    dims = tuple(
        IssueTrajectoryState(
            dimension=d,
            trajectory_state=trajectory_state,
            as_of=_T0,
            evidence_ids=("EV-TRAJ",),
        )
        for d in TrajectoryDimension
    )
    return IssueObject(
        issue_id="ISSUE-FORESIGHT-TEST",
        title="Foresight derivation test issue",
        issue_type="test",
        persistence_class=PersistenceClass.PC_01_INCIDENT,
        lifecycle_state="ACTIVE",
        first_seen=_T0,
        last_updated=_T0,
        identity_review_state=None,
        evidence=IssueEvidenceLinks(
            supporting_evidence_ids=tuple(supporting),
            contradictory_evidence_ids=tuple(contradictory),
            context_only_evidence_ids=tuple(context_only),
        ),
        geography=IssueGeography(),
        trajectories=dims,
        uncertainty_notes=tuple(uncertainty_notes),
        evidence_gaps=tuple(evidence_gaps),
    )


class TestForesightDerivation(unittest.TestCase):
    def test_populated_issue_yields_low_risk_and_watch_conditions(self):
        issue = _make_issue()
        proposal = derive_foresight(issue)
        self.assertFalse(proposal.abstained)
        self.assertEqual(proposal.risk.level, RISK_LOW)
        self.assertEqual(
            len(proposal.watch_conditions), len(tuple(TrajectoryDimension))
        )
        self.assertTrue(any(
            "MAINTAIN_CURRENT_WATCH" in p
            for p in proposal.recommendation_proposals
        ))

    def test_no_supporting_evidence_abstains(self):
        issue = _make_issue(supporting=(), evidence_gaps=("gap-1",))
        proposal = derive_foresight(issue)
        self.assertTrue(proposal.abstained)
        self.assertEqual(proposal.risk.level, RISK_INSUFFICIENT)
        self.assertGreater(len(proposal.abstention_reasons), 0)
        self.assertEqual(proposal.watch_conditions, ())

    def test_high_contradiction_yields_high_risk(self):
        issue = _make_issue(
            supporting=("EV-1", "EV-2"),
            contradictory=("EV-3", "EV-4"),
        )
        proposal = derive_foresight(issue)
        self.assertFalse(proposal.abstained)
        self.assertEqual(proposal.risk.level, RISK_HIGH)
        self.assertTrue(any(
            "ESCALATE_FOR_HUMAN_REVIEW" in p
            for p in proposal.recommendation_proposals
        ))

    def test_escalating_trajectory_shifts_direction(self):
        issue = _make_issue(trajectory_state="escalating sharply")
        proposal = derive_foresight(issue)
        self.assertEqual(proposal.risk.direction, "ESCALATING")
        self.assertTrue(any(
            "INCREASE_MONITORING_FREQUENCY" in p
            for p in proposal.recommendation_proposals
        ))

    def test_all_trajectory_dimensions_produce_watch_conditions(self):
        issue = _make_issue()
        proposal = derive_foresight(issue)
        self.assertEqual(
            len(proposal.watch_conditions), len(issue.trajectories)
        )
        for t, wc in zip(issue.trajectories, proposal.watch_conditions):
            self.assertIn(t.dimension.value, wc.condition)


if __name__ == "__main__":
    unittest.main()
