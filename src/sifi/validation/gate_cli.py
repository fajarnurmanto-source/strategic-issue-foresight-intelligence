from __future__ import annotations

import argparse
import json

from .gate import cb22_gate_status, configure_cb22_gate


def main() -> int:
    parser = argparse.ArgumentParser(description="Configure the local CB22 counted-session gate")
    parser.add_argument("--root", required=True)
    parser.add_argument("--primary", required=True)
    parser.add_argument("--checker", required=True)
    parser.add_argument("--external-review", required=True,
                        choices=("SATISFIED", "NOT_REQUIRED_DOCUMENTED"))
    parser.add_argument("--conflict-review-complete", action="store_true")
    args = parser.parse_args()
    target = configure_cb22_gate(
        args.root, primary_staff_code=args.primary,
        checker_staff_code=args.checker,
        external_review_requirement=args.external_review,
        conflict_review_complete=args.conflict_review_complete,
    )
    status = cb22_gate_status(args.root)
    print(json.dumps({
        "configuration": str(target),
        "ready_for_counted_sessions": status.ready_for_counted_sessions,
        "reasons": status.reasons,
    }, indent=2))
    return 0 if status.ready_for_counted_sessions else 1


if __name__ == "__main__":
    raise SystemExit(main())
