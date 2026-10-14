from __future__ import annotations

import unittest
from datetime import datetime, timezone

from sifi.domain import TrajectoryDimension
from sifi.workflow import (
    BoundedAnalystWorkflow, WorkflowBoundaryError, WorkflowOrderError,
    WorkflowStep, WorkflowStepSubmission,
)
from sifi.workflow.service import GEOGRAPHY_DIMENSIONS


UTC = timezone.utc


def at(hour: int) -> datetime:
    return datetime(2026, 10, 15, hour, 0, tzinfo=UTC)


class BoundedWorkflowTests(unittest.TestCase):
    def setUp(self):
        self.workflow = BoundedAnalystWorkflow()
        self.session = self.workflow.start(
            session_id="SESSION-1", task_id="TASK-1", analyst_id="ANALYST-1",
            issue_id="ISSUE-1", analytical_cutoff=at(8), started_at=at(9),
        )

    def submit(self, session, submission, hour=10):
        return self.workflow.submit(
            session, submission, analyst_id="ANALYST-1", recorded_at=at(hour)
        )

    def valid_submission(self, step):
        kwargs = {"step": step, "findings": (f"completed {step.value}",)}
        if step == WorkflowStep.ADMITTED_SIGNAL_REVIEW:
            kwargs.update(production_evidence_eligible=True, evidence_refs=("EV-1",))
        elif step == WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION:
            kwargs["evidence_refs"] = ("EV-1", "EV-2")
        elif step == WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION:
            kwargs["inspected_dimensions"] = tuple(sorted(GEOGRAPHY_DIMENSIONS))
        elif step == WorkflowStep.TRAJECTORY_INSPECTION:
            kwargs["inspected_dimensions"] = tuple(item.value for item in TrajectoryDimension)
        elif step == WorkflowStep.AUTHORIZED_CORRECTION:
            kwargs.update(correction_requested=True, correction_authority_verified=True)
        elif step == WorkflowStep.EVIDENCE_LINKED_BRIEF:
            kwargs.update(brief_assertions_supported=True, evidence_refs=("EV-1",))
        elif step == WorkflowStep.HISTORICAL_RETRIEVAL:
            kwargs["historical_cutoff"] = at(7)
        return WorkflowStepSubmission(**kwargs)

    def advance_to(self, target):
        session = self.session
        for step in WorkflowStep:
            if step == target:
                return session
            session = self.submit(session, self.valid_submission(step))
        raise AssertionError("target not found")

    def test_001_all_steps_complete_in_order_with_append_only_audit(self):
        session = self.session
        for step in WorkflowStep:
            session = self.submit(session, self.valid_submission(step))
        self.assertTrue(session.is_complete)
        self.assertEqual(session.completed_steps, tuple(WorkflowStep))
        self.assertEqual(tuple(event.sequence for event in session.audit_events), tuple(range(1, 12)))
        self.assertTrue(session.analytical_only)
        self.assertFalse(session.representative_user_validation_performed)
        self.assertFalse(session.production_action_authorized)

    def test_002_out_of_order_step_is_rejected(self):
        with self.assertRaises(WorkflowOrderError):
            self.submit(self.session, self.valid_submission(WorkflowStep.PERSISTENT_ISSUE_INSPECTION))

    def test_003_noneligible_evidence_fails_closed(self):
        session = self.advance_to(WorkflowStep.ADMITTED_SIGNAL_REVIEW)
        bad = WorkflowStepSubmission(
            step=WorkflowStep.ADMITTED_SIGNAL_REVIEW, findings=("review",),
            evidence_refs=("EV-Z1",), production_evidence_eligible=False,
        )
        with self.assertRaises(WorkflowBoundaryError):
            self.submit(session, bad)

    def test_004_geography_cannot_be_collapsed(self):
        session = self.advance_to(WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION)
        bad = WorkflowStepSubmission(
            step=WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION,
            findings=("collapsed",), inspected_dimensions=("relevance_geography",),
        )
        with self.assertRaises(WorkflowBoundaryError):
            self.submit(session, bad)

    def test_005_correction_requires_authority(self):
        session = self.advance_to(WorkflowStep.AUTHORIZED_CORRECTION)
        bad = WorkflowStepSubmission(
            step=WorkflowStep.AUTHORIZED_CORRECTION, findings=("correction",),
            correction_requested=True, correction_authority_verified=False,
        )
        with self.assertRaises(WorkflowBoundaryError):
            self.submit(session, bad)

    def test_006_brief_requires_evidence_and_supported_assertions(self):
        session = self.advance_to(WorkflowStep.EVIDENCE_LINKED_BRIEF)
        bad = WorkflowStepSubmission(
            step=WorkflowStep.EVIDENCE_LINKED_BRIEF, findings=("brief",),
            brief_assertions_supported=False,
        )
        with self.assertRaises(WorkflowBoundaryError):
            self.submit(session, bad)

    def test_007_historical_cutoff_cannot_exceed_analytical_cutoff(self):
        session = self.advance_to(WorkflowStep.HISTORICAL_RETRIEVAL)
        bad = WorkflowStepSubmission(
            step=WorkflowStep.HISTORICAL_RETRIEVAL, findings=("history",),
            historical_cutoff=at(9),
        )
        with self.assertRaises(WorkflowBoundaryError):
            self.submit(session, bad)

    def test_008_completed_session_is_immutable(self):
        session = self.session
        for step in WorkflowStep:
            session = self.submit(session, self.valid_submission(step))
        with self.assertRaises(WorkflowBoundaryError):
            self.submit(session, self.valid_submission(WorkflowStep.COVERAGE_INSPECTION))


if __name__ == "__main__":
    unittest.main()
