"""Cross-issue analysis to detect systemic patterns and shared evidence."""
from __future__ import annotations
from dataclasses import dataclass
from sifi.issues.model import IssueObject
from sifi.analysis.foresight import derive_foresight, RISK_HIGH, RISK_ELEVATED
from sifi.analysis.semantic import analyze_text

@dataclass(frozen=True, slots=True)
class SystemicAlert:
    alert_type: str
    description: str
    involved_issue_ids: tuple[str, ...]

@dataclass(frozen=True, slots=True)
class CrossIssueReport:
    issues_analyzed: int
    high_risk_count: int
    systemic_alerts: tuple[SystemicAlert, ...]

def analyze_systemic_risks(issues: list[IssueObject]) -> CrossIssueReport:
    if not issues:
        return CrossIssueReport(0, 0, ())

    alerts = []
    high_risk_issues = []
    evidence_map = {}
    entity_map = {}

    # 1. Deteksi Isu Berisiko Tinggi & Kumpulkan Bukti/Entitas
    for issue in issues:
        proposal = derive_foresight(issue)
        if proposal.risk.level in (RISK_HIGH, RISK_ELEVATED):
            high_risk_issues.append(issue.issue_id)

        # Kumpulkan bukti untuk deteksi tumpang tindih
        for ev in issue.evidence.supporting_evidence_ids + issue.evidence.contradictory_evidence_ids:
            if ev not in evidence_map:
                evidence_map[ev] = []
            evidence_map[ev].append(issue.issue_id)

        # Ekstrak entitas dari judul isu
        for entity in analyze_text(issue.title).detected_entities:
            if entity not in entity_map:
                entity_map[entity] = []
            if issue.issue_id not in entity_map[entity]:
                entity_map[entity].append(issue.issue_id)

    # 2. Peringatan Eskalasi Sistemik
    if len(high_risk_issues) >= 2:
        alerts.append(SystemicAlert(
            alert_type="SYSTEMIC_ESCALATION",
            description=f"Multiple issues ({len(high_risk_issues)}) are at elevated/high risk.",
            involved_issue_ids=tuple(high_risk_issues)
        ))

    # 3. Peringatan Bukti Tumpang Tindih
    for ev, issue_ids in evidence_map.items():
        if len(issue_ids) > 1:
            alerts.append(SystemicAlert(
                alert_type="SHARED_EVIDENCE",
                description=f"Evidence '{ev}' is shared across multiple issues.",
                involved_issue_ids=tuple(issue_ids)
            ))

    # 4. Peringatan Entitas Tumpang Tindih (Baru)
    for ent, issue_ids in entity_map.items():
        if len(issue_ids) > 1:
            alerts.append(SystemicAlert(
                alert_type="SHARED_ENTITY",
                description=f"Issues share the same entity: '{ent}'",
                involved_issue_ids=tuple(issue_ids)
            ))

    return CrossIssueReport(
        issues_analyzed=len(issues),
        high_risk_count=len(high_risk_issues),
        systemic_alerts=tuple(alerts)
    )
