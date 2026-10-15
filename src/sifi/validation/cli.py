from __future__ import annotations

import argparse
import json
from pathlib import Path

from sifi.local import LocalSIFIPaths

from .gate import cb22_gate_status
from .runtime import CB22StudyRuntime


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
        assessment_payload = {
            "result": assessment.result.value,
            "analyzable_participants": assessment.analyzable_participants,
            "completed_participants": assessment.completed_participants,
            "routine_help_participants": assessment.routine_help_participants,
            "forbidden_output_participants": assessment.forbidden_output_participants,
            "evidence_link_failures": assessment.evidence_link_failures,
            "reasons": assessment.reasons,
        }
    else:
        assessment_payload = {"result": "NOT_READY", "analyzable_participants": 0,
                              "reasons": ("NO_COUNTED_PARTICIPANTS",)}
    return {
        "gate": {"ready_for_counted_sessions": gate.ready_for_counted_sessions,
                 "reasons": gate.reasons},
        "assessment": assessment_payload,
        "claim_allowed": assessment_payload["result"] == "PASS",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="SIFI CB22 local study operations")
    parser.add_argument("command", choices=("prepare", "status"))
    parser.add_argument("--root", default=str(Path.cwd()))
    args = parser.parse_args()
    payload = prepare(Path(args.root)) if args.command == "prepare" else status(Path(args.root))
    print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
