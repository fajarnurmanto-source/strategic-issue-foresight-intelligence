"""Semantic Analysis for Claims and News Text."""
import re
from dataclasses import dataclass

@dataclass(frozen=True)
class SemanticClaimResult:
    is_negative: bool
    is_hedged: bool
    threat_score: int
    detected_entities: tuple[str, ...]
    causal_links: tuple[str, ...]

def analyze_text(text: str) -> SemanticClaimResult:
    t = text.lower()
    
    # Daftar kata negatif yang jauh lebih luas (termasuk bahasa berita)
    negatives = (
        "crisis", "collapse", "danger", "threat", "decline", "fail", "attack", "disrupt", 
        "krisis", "serangan", "gagal", "died", "dead", "death", "kill", "eruption", 
        "wildfire", "toxic", "rout", "loom", "bomb", "disaster", "emergency", 
        "evacuate", "damage", "loss", "warn", "fear", "risk", "sever", "conflict"
    )
    hedges = ("might", "could", "possibly", "reportedly", "unconfirmed", "mungkin", "diduga", "belum pasti")
    
    neg_hits = sum(1 for w in negatives if w in t)
    hedge_hits = sum(1 for w in hedges if w in t)
    
    # Beri bobot lebih tinggi jika ada kata kritis
    critical_words = ("died", "dead", "kill", "eruption", "crisis", "collapse", "attack")
    critical_hits = sum(1 for w in critical_words if w in t)
    
    # Skor maksimal 10 (kata negatif x3, kata kritis x2, hedging x1)
    score = min(10, (neg_hits * 3) + (critical_hits * 2) + hedge_hits)
    
    # Ekstraksi Entitas (Kata berawalan huruf kapital)
    entities = tuple(set(re.findall(r'\b[A-Z][a-z]+(?:\s[A-Z][a-z]+)*\b', text)))
    
    # Ekstraksi Kausalitas
    causal = tuple(set(re.findall(r'(?:due to|because of|caused by|led to)\s+([a-zA-Z\s]+)', t)))
    
    return SemanticClaimResult(
        is_negative=neg_hits > 0,
        is_hedged=hedge_hits > 0,
        threat_score=score,
        detected_entities=entities,
        causal_links=causal
    )
