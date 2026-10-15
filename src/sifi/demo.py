from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.domain import AdmissionZone, ObservabilityState, PersistenceClass, TrajectoryDimension
from sifi.issues import IssueEvidenceLinks, IssueGeography, IssueLineage, IssueObject, IssueTrajectoryState
from sifi.local import LocalSIFIPaths
from sifi.rights import AdmissionDecision, AdmissionReason
from sifi.workflow import AnalystCaseContext, LocalDomainOrchestrator, LocalWorkflowRuntime


DEMO_SESSION_ID = "DEMO-SESSION-001"
DEMO_BRIEF_ID = "DEMO-BRIEF-001"
DEMO_BASE = datetime(2026, 1, 1, 8, 0, tzinfo=timezone.utc)


class LocalDemoError(RuntimeError):
    pass


@dataclass(frozen=True, slots=True)
class LocalDemoResult:
    session_id: str
    brief_id: str
    created: bool
    synthetic: bool = True
    analytical_only: bool = True


def _decision() -> AdmissionDecision:
    return AdmissionDecision(
        zone=AdmissionZone.Z2_PRODUCTION_ADMITTED,
        research_eligible=True,
        production_eligible=True,
        hard_block=False,
        reasons=(AdmissionReason.PRODUCTION_ADMITTED,),
    )


def demo_context(analyst_id: str = "DEMO-ANALYST") -> AnalystCaseContext:
    issue = IssueObject(
        issue_id="DEMO-ISSUE-001",
        title="Contoh isu sintetis untuk eksplorasi lokal",
        issue_type="SYNTHETIC_DEMONSTRATION",
        persistence_class=PersistenceClass("PC-01"),
        lifecycle_state="ACTIVE",
        first_seen=DEMO_BASE - timedelta(days=2),
        last_updated=DEMO_BASE,
        identity_review_state="REVIEWED_SYNTHETIC",
        evidence=IssueEvidenceLinks(
            supporting_evidence_ids=("DEMO-EV-001", "DEMO-EV-002"),
            contradictory_evidence_ids=("DEMO-EV-C01",),
            context_only_evidence_ids=("DEMO-EV-X01",),
            excluded_production_evidence_ids=("DEMO-EV-Z101",),
        ),
        geography=IssueGeography(
            source_geography=("ID-SYNTHETIC",),
            event_geography=("ID-SYNTHETIC",),
            affected_geography=("ID-SYNTHETIC",),
            policy_jurisdiction=("ID-SYNTHETIC",),
            relevance_geography=("ID-SYNTHETIC",),
            coverage_profile_id="DEMO-COVERAGE-001",
        ),
        trajectories=tuple(
            IssueTrajectoryState(
                dimension=dimension,
                trajectory_state="SYNTHETIC_BOUNDED",
                as_of=DEMO_BASE,
                evidence_ids=("DEMO-EV-001",),
            )
            for dimension in TrajectoryDimension
        ),
        observability_states=(ObservabilityState("OBS-05"),),
        uncertainty_notes=("Skenario ini sepenuhnya sintetis.",),
        evidence_gaps=("Tidak mewakili cakupan sumber dunia nyata.",),
        known_limitations=("Hanya untuk demonstrasi fungsi lokal.",),
        lineage=IssueLineage(),
    )
    decision = _decision()
    return AnalystCaseContext(
        task_id="DEMO-TASK-001",
        analyst_id=analyst_id,
        issue=issue,
        issue_version=1,
        coverage_profile_id="DEMO-COVERAGE-001",
        coverage_limitations=("Cakupan sintetis; bukan monitoring produksi.",),
        evidence_decisions=(("DEMO-EV-001", decision), ("DEMO-EV-002", decision)),
        analytical_cutoff=DEMO_BASE,
    )


def seed_local_demo(root: str | Path) -> LocalDemoResult:
    paths = LocalSIFIPaths.from_root(root)
    paths.initialize()
    with LocalWorkflowRuntime(paths.database, workspace_root=paths.root) as runtime:
        session = runtime.latest(DEMO_SESSION_ID)
        brief = runtime.latest_brief(DEMO_BRIEF_ID)
        if session is not None or brief is not None:
            if session is None or brief is None or not session.is_complete:
                raise LocalDemoError("existing demo records are incomplete or inconsistent")
            return LocalDemoResult(DEMO_SESSION_ID, DEMO_BRIEF_ID, created=False)
        context = demo_context()
        orchestrator = LocalDomainOrchestrator(runtime)
        orchestrator.start_inspection(
            session_id=DEMO_SESSION_ID,
            context=context,
            started_at=DEMO_BASE + timedelta(hours=1),
            recorded_at=DEMO_BASE + timedelta(hours=2),
        )
        orchestrator.complete_case(
            session_id=DEMO_SESSION_ID,
            context=context,
            recorded_at=DEMO_BASE + timedelta(hours=3),
            correction_review=None,
            priority_rationale="Prioritas demo berdasarkan bukti sintetis terkontrol.",
            attention_only_basis=False,
            brief_id=DEMO_BRIEF_ID,
            brief_summary=(
                "Ringkasan demonstrasi analitis berbasis bukti sintetis. "
                "Tidak boleh digunakan sebagai klaim faktual atau tindakan produksi."
            ),
            brief_evidence_refs=("DEMO-EV-001", "DEMO-EV-002"),
            historical_cutoff=DEMO_BASE - timedelta(hours=1),
        )
    return LocalDemoResult(DEMO_SESSION_ID, DEMO_BRIEF_ID, created=True)
