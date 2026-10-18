"""Semantic Analysis for Claims."""
from dataclasses import dataclass

@dataclass(frozen=True)
class SemanticClaimResult:
    is_negative: bool
    is_hedged: bool
    detected_entities: tuple[str, ...]

def analyze_text(text: str) -> SemanticClaimResult:
    """Sederhana: deteksi kata negatif dan hedging (ketidakpastian)."""
    t = text.lower()
    negatives = ("crisis", "collapse", "danger", "threat", "decline", "fail")
    hedges = ("might", "could", "possibly", "reportedly", "unconfirmed")
    
    return SemanticClaimResult(
        is_negative=any(w in t for w in negatives),
        is_hedged=any(w in t for w in hedges),
        detected_entities=()
    )
