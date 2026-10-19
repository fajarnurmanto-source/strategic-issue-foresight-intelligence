#!/usr/bin/env python3
"""CB22 Phase C — record adjudicators, ethics, participants; validate; open gate."""
import json, sys, hashlib
from datetime import datetime, timezone
from pathlib import Path

REPO = Path(__file__).resolve().parent
CB = REPO / "05_VALIDATION" / "CB22_PREP"
J5 = CB / "CB22_5_EXECUTION_GATE_v0_1.json"
J9 = CB / "CB22_9_ADJUDICATOR_ASSIGNMENT_TEMPLATE_v1_0.json"
J13 = CB / "CB22_13_PSEUDONYMOUS_ROSTER_TEMPLATE_v1_0.json"
J11 = CB / "CB22_11_TASK_PACKAGE_INTEGRITY_v1_0.json"

def load(p): return json.loads(p.read_text(encoding="utf-8"))
def save(p, d):
    t = p.with_suffix(p.suffix + ".tmp")
    t.write_text(json.dumps(d, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    t.replace(p)
def now(): return datetime.now(timezone.utc).isoformat()
def csha(p):
    d = load(p)
    return hashlib.sha256(json.dumps(d, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()).hexdigest().upper()
def tsha(p): return hashlib.sha256(p.read_bytes().replace(b"\r\n", b"\n")).hexdigest().upper()

def check_frozen():
    errs = []
    integ = load(J11)
    for f, exp in [("CB22_TASK_PACKAGE_v1_0.json", "B222F121927292C802C7D8CF57939720BD628FBD0B964EEC595687E08FB1460B"),
                   ("CB22_10_OBSERVATION_RECORD_SCHEMA_v1_0.json", "F4985C84AFBAF88E6408D211AE1797E5B5F629CBBB01BA7530C99FF86413C3E2")]:
        p = CB / f
        if not p.exists(): errs.append(f"missing {f}"); continue
        if csha(p) != exp: errs.append(f"HASH_MISMATCH {f}")
    pr = CB / "CB22_7_FROZEN_PROTOCOL_v1_0.md"
    if not pr.exists(): errs.append("missing protocol")
    elif tsha(pr) != integ["protocol_sha256"]: errs.append("HASH_MISMATCH protocol")
    return errs

def validate():
    print("=== CB22 Phase C Validation ===\n")
    ok = True
    print("[1] Frozen integrity:")
    errs = check_frozen()
    if errs:
        for e in errs: print("    ❌ " + e)
        ok = False
    else: print("    ✅ all intact")
    print("\n[2] Adjudicators (CB22_9):")
    d9 = load(J9); pa = d9.get("primary_adjudicator_staff_code"); ic = d9.get("independent_checker_staff_code")
    if pa and ic and pa != ic and d9.get("conflict_review_complete"):
        print(f"    ✅ Primary={pa}  Checker={ic}")
    else:
        print(f"    ❌ primary={pa} checker={ic} coi={d9.get('conflict_review_complete')}")
        ok = False
    print("\n[3] Ethics (CB22_5):")
    d5 = load(J5); det = d5.get("ethics_determination"); basis = d5.get("ethics_basis", "")
    if det in ("NOT_REQUIRED", "REQUIRED") and len(basis) >= 100:
        if det == "REQUIRED" and not d5.get("ethics_approval_record_id"):
            print("    ❌ REQUIRED but no approval id")
            ok = False
        else: print(f"    ✅ {det} (basis {len(basis)} chars)")
    else:
        print(f"    ❌ det={det} basis_len={len(basis)}")
        ok = False
    print("\n[4] Participants (CB22_13):")
    d13 = load(J13); ents = [e for e in d13.get("entries", []) if e.get("participant_code") != "P-EXAMPLE"]
    ready = [e for e in ents if e.get("consent_record_id") and e.get("status") == "CONSENTED_NOT_STARTED"]
    if len(ready) >= 5: print(f"    ✅ {len(ready)} consented (≥5)")
    else:
        print(f"    ❌ only {len(ready)} consented (need ≥5)")
        ok = False
    print("\n=== Overall:", "✅ READY to open gate" if ok else "❌ BLOCKED", "===")
    return ok

def assign(pa, ic):
    if pa == ic: sys.exit("Primary and Checker must differ.")
    d = load(J9)
    d["primary_adjudicator_staff_code"] = pa
    d["independent_checker_staff_code"] = ic
    d["conflict_review_complete"] = True
    d["assigned_at"] = now()
    d["status"] = "ASSIGNED_PENDING_ETHICS_AND_PARTICIPANTS"
    save(J9, d); print(f"✅ CB22_9: primary={pa}, checker={ic}")

def ethics(det, basis, approval=None):
    if det not in ("NOT_REQUIRED", "REQUIRED"): sys.exit("det must be NOT_REQUIRED or REQUIRED")
    if len(basis.strip()) < 100: sys.exit("basis must be ≥100 chars")
    if det == "REQUIRED" and not approval: sys.exit("REQUIRED needs --approval <id>")
    d = load(J5)
    d["ethics_determination"] = det
    d["ethics_basis"] = basis.strip()
    d["ethics_recorded_at"] = now()
    if approval: d["ethics_approval_record_id"] = approval
    save(J5, d); print(f"✅ CB22_5: ethics={det}")

def participant(code, scr, con):
    d = load(J13)
    ents = [e for e in d.get("entries", []) if e.get("participant_code") != "P-EXAMPLE"]
    for e in ents:
        if e["participant_code"] == code: sys.exit(f"{code} exists")
    ents.append({"participant_code": code, "screening_record_id": scr, "consent_record_id": con, "status": "CONSENTED_NOT_STARTED"})
    d["entries"] = ents
    save(J13, d); print(f"✅ CB22_13: {len(ents)} participant(s)")

def open_gate():
    if not validate(): sys.exit("❌ Cannot open gate — fix items above.")
    print("\n⚠️ Type OPEN to confirm gate opening:")
    if input().strip() != "OPEN": sys.exit("aborted.")
    d = load(J5)
    d["status"] = "READY_FOR_COUNTED_EXECUTION"
    d["unmet_execution_requirements"] = []
    d["gate_opened_at"] = now()
    d["next_action"] = "EXECUTE_FIVE_COUNTED_SESSIONS_THEN_ADJUDICATE"
    save(J5, d)
    print("✅ CB22_5 status: READY_FOR_COUNTED_EXECUTION")

if __name__ == "__main__":
    a = sys.argv[1:]
    if not a: sys.exit("usage: phase_c.py <validate|assign-adjudicators|record-ethics|add-participant|open-gate>")
    c = a[0]
    if c == "validate": validate()
    elif c == "assign-adjudicators":
        pa = a[a.index("--primary")+1] if "--primary" in a else None
        ic = a[a.index("--checker")+1] if "--checker" in a else None
        if not (pa and ic): sys.exit("need --primary CODE --checker CODE")
        assign(pa, ic)
    elif c == "record-ethics":
        det = a[a.index("--determination")+1] if "--determination" in a else None
        basis = a[a.index("--basis")+1] if "--basis" in a else ""
        app = a[a.index("--approval")+1] if "--approval" in a else None
        if not det: sys.exit("need --determination")
        ethics(det, basis, app)
    elif c == "add-participant":
        def val(k): return a[a.index(k)+1] if k in a else None
        code, scr, con = val("--participant"), val("--screening"), val("--consent")
        if not all([code, scr, con]): sys.exit("need --participant --screening --consent")
        participant(code, scr, con)
    elif c == "open-gate": open_gate()
    else: sys.exit("unknown command")
