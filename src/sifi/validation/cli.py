from __future__ import annotations

import argparse
import json
from pathlib import Path

from sifi.local import LocalSIFIPaths

from .gate import cb22_gate_status
from .runtime import CB22StudyRuntime
from .export import export_adjudication_bundle


def prepare(root: Path) -> dict:
    paths = LocalSIFIPaths.from_root(root)
    paths.initialize()
    target = paths.config / "cb22-execution-gate.json"
    created = False
    if not target.exists():
        target.write_text(json.dumps({
            "protocol_version": "CB22-v1.0",
            "task_package_version": "CB22-TASK-v1.0",
            "primary_adjudicator_staff_code": None,
            "independent_checker_staff_code": None,
            "conflict_review_complete": False,
            "external_review_requirement": "UNRESOLVED",
        }, indent=2) + "\n", encoding="utf-8")
        created = True
    gate = cb22_gate_status(paths.root)
    return {"configuration": str(target.relative_to(paths.root)), "created": created,
            "gate": {"ready_for_counted_sessions": gate.ready_for_counted_sessions,
                     "reasons": gate.reasons}}


def status(root: Path) -> dict:
    paths = LocalSIFIPaths.from_root(root)
    gate = cb22_gate_status(paths.root)
    if paths.database.exists():
        with CB22StudyRuntime(paths.database, workspace_root=paths.root) as runtime:
            assessment = runtime.assessment()
            observations = runtime.observations()
        assessment_payload = {
            "result": assessment.result.value,
            "analyzable_participants": assessment.analyzable_participants,
            "completed_participants": assessment.completed_participants,
            "routine_help_participants": assessment.routine_help_participants,
            "forbidden_output_participants": assessment.forbidden_output_participants,
            "evidence_link_failures": assessment.evidence_link_failures,
            "reasons": assessment.reasons,
            "completion_rate": assessment.completion_rate,
            "routine_help_rate": assessment.routine_help_rate,
            "median_duration_seconds": assessment.median_duration_seconds,
        }
    else:
        observations = ()
        assessment_payload = {"result": "NOT_READY", "analyzable_participants": 0,
                              "reasons": ("NO_COUNTED_PARTICIPANTS",)}
    bundles = sorted(
        (item for item in paths.exports.glob("cb22-adjudication-*.json")
         if not item.name.startswith("cb22-adjudication-receipt-")),
        key=lambda item: item.stat().st_mtime,
    )
    receipts = sorted(paths.exports.glob("cb22-adjudication-receipt-*.json"), key=lambda item: item.stat().st_mtime)
    return {
        "gate": {"ready_for_counted_sessions": gate.ready_for_counted_sessions,
                 "reasons": gate.reasons},
        "assessment": assessment_payload,
        "execution_summary": {
            "observation_count": len(observations),
            "rehearsal_count": sum(item.mode.value == "REHEARSAL" for item in observations),
            "counted_count": sum(item.mode.value == "COUNTED" for item in observations),
            "active_count": sum(item.completed_at is None for item in observations),
            "latest_bundle": str(bundles[-1].relative_to(paths.root)) if bundles else None,
            "latest_final_receipt": str(receipts[-1].relative_to(paths.root)) if receipts else None,
        },
        "automated_pass_candidate": assessment_payload["result"] == "PASS",
        "claim_allowed": False,
        "claim_hold": "TWO_ROLE_FINAL_ADJUDICATION_RECEIPT_REQUIRED",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="SIFI CB22 local study operations")
    parser.add_argument("command", choices=("prepare", "status", "export"))
    parser.add_argument("--root", default=str(Path.cwd()))
    args = parser.parse_args()
    if args.command == "prepare":
        payload = prepare(Path(args.root))
    elif args.command == "status":
        payload = status(Path(args.root))
    else:
        paths = LocalSIFIPaths.from_root(Path(args.root))
        if paths.database.exists():
            with CB22StudyRuntime(paths.database, workspace_root=paths.root) as runtime:
                items = runtime.observations()
        else:
            items = ()
        destination = export_adjudication_bundle(paths.root, items)
        payload = {"export": str(destination.relative_to(paths.root))}
    print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
