from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

from .model import StudyMode
from .study_task import STUDY_STEP_SPECS
from .workflow import LocalCB22Workflow


def run_rehearsal(root: str | Path) -> dict:
    workflow = LocalCB22Workflow(root)
    started = datetime.now(timezone.utc)
    state = workflow.start(
        participant_code="REHEARSAL-AUTOMATED",
        screening_record_id="SCR-REHEARSAL-AUTOMATED",
        consent_record_id="CON-REHEARSAL-AUTOMATED",
        consent_confirmed_at=started,
        governance_authorization_id="GOV-REHEARSAL-AUTOMATED",
        representative_profile_met=False,
        participant_is_sifi_developer=True,
        mode=StudyMode.REHEARSAL,
        now=started,
    )
    while state.session.next_step is not None:
        state = workflow.advance(
            observation_id=state.observation.observation_id,
            participant_code=state.observation.participant_code,
            answer_code=STUDY_STEP_SPECS[state.session.next_step].accepted_code,
        )
    return {
        "status": "PASS",
        "mode": state.observation.mode.value,
        "counted": False,
        "observation_id": state.observation.observation_id,
        "workflow_session_id": state.observation.workflow_session_id,
        "brief_id": state.brief.brief_id if state.brief else None,
        "completed_stages": len(state.observation.completed_stage_ids),
        "cb22_result_effect": "NONE_REHEARSAL_EXCLUDED",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Run a non-counted CB22 local rehearsal")
    parser.add_argument("--root", default=str(Path.cwd()))
    args = parser.parse_args()
    print(json.dumps(run_rehearsal(args.root), indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
