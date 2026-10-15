from __future__ import annotations

import argparse
import json
from datetime import datetime
from pathlib import Path

from .adjudication import CB22Result
from .decision import AdjudicationRole, CB22AdjudicationRuntime


def main() -> int:
    parser = argparse.ArgumentParser(description="Record an assigned CB22 adjudication vote")
    parser.add_argument("--root", required=True)
    parser.add_argument("--bundle", required=True)
    parser.add_argument("--role", required=True, choices=tuple(item.value for item in AdjudicationRole))
    parser.add_argument("--staff-code", required=True)
    parser.add_argument("--decision", required=True, choices=("PASS", "FAIL", "INCONCLUSIVE"))
    parser.add_argument("--reviewed-at", required=True)
    parser.add_argument("--conflict-free", action="store_true")
    args = parser.parse_args()
    reviewed_at = datetime.fromisoformat(args.reviewed_at)
    root = Path(args.root)
    bundle = Path(args.bundle)
    if not bundle.is_absolute():
        bundle = root / bundle
    with CB22AdjudicationRuntime(root) as runtime:
        receipt = runtime.record_vote(
            bundle_path=bundle, role=AdjudicationRole(args.role),
            staff_code=args.staff_code, decision=CB22Result(args.decision),
            reviewed_at=reviewed_at, conflict_free=args.conflict_free,
        )
        output = {
            "status": receipt.status,
            "automated_result": receipt.automated_result.value,
            "final_result": receipt.final_result.value if receipt.final_result else None,
            "claim_allowed": receipt.claim_allowed,
            "vote_count": len(receipt.votes),
        }
        if receipt.status == "FINAL":
            output["receipt"] = str(runtime.export_final_receipt(bundle).relative_to(runtime.paths.root))
    print(json.dumps(output, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
