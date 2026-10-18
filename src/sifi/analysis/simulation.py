"""What-If Simulation for Foresight Proposals."""
from dataclasses import replace
from sifi.issues.model import IssueObject
from sifi.analysis.foresight import derive_foresight

def simulate_remove_contradiction(issue: IssueObject) -> dict:
    """Menghitung ulang risiko jika bukti kontradiksi dihapus/dianggap selesai."""
    current_proposal = derive_foresight(issue)
    
    # Buat salinan isu tanpa bukti kontradiksi
    clean_evidence = replace(issue.evidence, contradictory_evidence_ids=())
    clean_issue = replace(issue, evidence=clean_evidence)
    
    simulated_proposal = derive_foresight(clean_issue)
    
    return {
        "current_risk": current_proposal.risk.level,
        "simulated_risk": simulated_proposal.risk.level,
        "current_direction": current_proposal.risk.direction,
        "simulated_direction": simulated_proposal.risk.direction,
        "risk_reduced": current_proposal.risk.level != simulated_proposal.risk.level
    }
