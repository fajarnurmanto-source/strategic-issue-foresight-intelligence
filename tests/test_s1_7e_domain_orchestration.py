from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.domain import AdmissionZone, ObservabilityState, PersistenceClass, TrajectoryDimension
from sifi.issues import (
    IssueEvidenceLinks, IssueGeography, IssueLineage, IssueObject,
    IssueTrajectoryState,
)
from sifi.rights import AdmissionDecision, AdmissionReason
from sifi.workflow import (
    AnalystCaseContext, LocalDomainOrchestrator, LocalWorkflowRuntime,
    OrchestrationBoundaryError, WorkflowStep,
)


BASE = datetime(2026, 10, 15, 8, 0, tzinfo=timezone.utc)


def decision(zone=AdmissionZone.Z2_PRODUCTION_ADMITTED):
    eligible = zone == AdmissionZone.Z2_PRODUCTION_ADMITTED
    return AdmissionDecision(
        zone=zone,
        research_eligible=True,
        production_eligible=eligible,
        hard_block=False,
        reasons=(
            AdmissionReason.PRODUCTION_ADMITTED
            if eligible else AdmissionReason.SOURCE_RESEARCH_ONLY,
        ),
    )


def issue():
    return IssueObject(
        issue_id="ISSUE-ORCH-1",
        title="Controlled orchestration issue",
        issue_type="CONTROLLED_TEST",
        persistence_class=PersistenceClass("PC-01"),
        lifecycle_state="ACTIVE",
        first_seen=BASE - timedelta(days=1),
        last_updated=BASE,
        identity_review_state="REVIEWED",
        evidence=IssueEvidenceLinks(
            supporting_evidence_ids=("EV-1", "EV-2"),
            contradictory_evidence_ids=("EV-C",),
            context_only_evidence_ids=("EV-X",),
            excluded_production_evidence_ids=("EV-Z1",),
        ),
        geography=IssueGeography(
            source_geography=("SOURCE",), event_geography=("EVENT",),
            affected_geography=("AFFECTED",), policy_jurisdiction=("POLICY",),
            relevance_geography=("RELEVANCE",), coverage_profile_id="COVERAGE-1",
        ),
        trajectories=tuple(
            IssueTrajectoryState(
                dimension=item, trajectory_state="BOUNDED", as_of=BASE,
                evidence_ids=("EV-1",),
            )
            for item in TrajectoryDimension
        ),
        observability_states=(ObservabilityState("OBS-05"),),
        uncertainty_notes=("bounded uncertainty",),
        evidence_gaps=("one synthetic gap",),
        known_limitations=("controlled fixture",),
        lineage=IssueLineage(),
    )


def context(first=decision(), second=decision()):
    return AnalystCaseContext(
        task_id="TASK-ORCH-1", analyst_id="ANALYST-1", issue=issue(),
        issue_version=1, coverage_profile_id="COVERAGE-1",
        coverage_limitations=("synthetic coverage",),
        evidence_decisions=(("EV-1", first), ("EV-2", second)),
        analytical_cutoff=BASE,
    )


class DomainOrchestrationTests(unittest.TestCase):
    def test_001_full_controlled_case_completes_and_creates_brief(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                state = service.start_inspection(
                    session_id="SESSION-1", context=context(),
                    started_at=BASE + timedelta(hours=1),
                    recorded_at=BASE + timedelta(hours=2),
                )
                self.assertEqual(state.next_step, WorkflowStep.AUTHORIZED_CORRECTION)
                completed, brief = service.complete_case(
                    session_id="SESSION-1", context=context(),
                    recorded_at=BASE + timedelta(hours=3), correction_review=None,
                    priority_rationale="materiality based on bounded evidence",
                    attention_only_basis=False, brief_id="BRIEF-1",
                    brief_summary="Bounded evidence-linked analytical summary.",
                    brief_evidence_refs=("EV-1", "EV-2"),
                    historical_cutoff=BASE - timedelta(hours=1),
                )
                self.assertTrue(completed.is_complete)
                self.assertEqual(len(completed.audit_events), 11)
                self.assertEqual(brief.issue_id, "ISSUE-ORCH-1")
                self.assertTrue(brief.analytical_only)
                self.assertFalse(brief.production_action_authorized)

    def test_002_z1_supporting_evidence_fails_before_session_creation(self):
        z1 = decision(AdmissionZone.Z1_RESEARCH_ONLY)
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                with self.assertRaises(OrchestrationBoundaryError):
                    service.start_inspection(
                        session_id="SESSION-Z1", context=context(second=z1),
                        started_at=BASE + timedelta(hours=1),
                        recorded_at=BASE + timedelta(hours=2),
                    )
                self.assertIsNone(runtime.latest("SESSION-Z1"))

    def test_003_attention_only_priority_is_rejected_without_progress(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                state = service.start_inspection(
                    session_id="SESSION-ATTN", context=context(),
                    started_at=BASE + timedelta(hours=1),
                    recorded_at=BASE + timedelta(hours=2),
                )
                with self.assertRaises(OrchestrationBoundaryError):
                    service.complete_case(
                        session_id="SESSION-ATTN", context=context(),
                        recorded_at=BASE + timedelta(hours=3), correction_review=None,
                        priority_rationale="attention only", attention_only_basis=True,
                        brief_id="BRIEF-X", brief_summary="not created",
                        brief_evidence_refs=("EV-1",),
                        historical_cutoff=BASE - timedelta(hours=1),
                    )
                self.assertEqual(runtime.latest("SESSION-ATTN"), state)

    def test_004_brief_cannot_use_unadjudicated_evidence(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                state = service.start_inspection(
                    session_id="SESSION-BRIEF", context=context(),
                    started_at=BASE + timedelta(hours=1),
                    recorded_at=BASE + timedelta(hours=2),
                )
                with self.assertRaises(OrchestrationBoundaryError):
                    service.complete_case(
                        session_id="SESSION-BRIEF", context=context(),
                        recorded_at=BASE + timedelta(hours=3), correction_review=None,
                        priority_rationale="bounded materiality", attention_only_basis=False,
                        brief_id="BRIEF-X", brief_summary="not created",
                        brief_evidence_refs=("EV-UNKNOWN",),
                        historical_cutoff=BASE - timedelta(hours=1),
                    )
                self.assertEqual(runtime.latest("SESSION-BRIEF"), state)

    def test_005_invalid_historical_cutoff_leaves_no_partial_progress(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                state = service.start_inspection(
                    session_id="SESSION-PIT", context=context(),
                    started_at=BASE + timedelta(hours=1),
                    recorded_at=BASE + timedelta(hours=2),
                )
                with self.assertRaises(OrchestrationBoundaryError):
                    service.complete_case(
                        session_id="SESSION-PIT", context=context(),
                        recorded_at=BASE + timedelta(hours=3), correction_review=None,
                        priority_rationale="bounded materiality", attention_only_basis=False,
                        brief_id="BRIEF-X", brief_summary="not created",
                        brief_evidence_refs=("EV-1",),
                        historical_cutoff=BASE + timedelta(minutes=1),
                    )
                self.assertEqual(runtime.latest("SESSION-PIT"), state)


if __name__ == "__main__":
    unittest.main()
