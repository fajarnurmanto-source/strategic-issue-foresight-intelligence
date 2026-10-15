from __future__ import annotations

import tempfile
import unittest
import json
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.validation import (
    CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION, CB22Result,
    CB22StudyRuntime, EvidenceLinkCheck, Intervention, InterventionCategory,
    LocalCB22Workflow, StudyMode, StudyObservation, assess_cb22, cb22_gate_status,
)


NOW = datetime(2026, 10, 15, 9, 0, tzinfo=timezone.utc)


def observation(number: int = 1, **changes) -> StudyObservation:
    values = dict(
        observation_id=f"OBS-{number:03d}", protocol_version=CB22_PROTOCOL_VERSION,
        mode=StudyMode.COUNTED, participant_code=f"P-{number:03d}",
        screening_record_id=f"SCR-{number:03d}", consent_record_id=f"CON-{number:03d}",
        consent_confirmed_at=NOW, governance_authorization_id="GOV-001",
        workflow_session_id=f"SES-{number:03d}", task_package_version=CB22_TASK_PACKAGE_VERSION,
        representative_profile_met=True, participant_is_sifi_developer=False,
        started_at=NOW + timedelta(minutes=1), completed_stage_ids=(), task_completed=False,
    )
    values.update(changes)
    return StudyObservation(**values)


class CB22StudyKernelTests(unittest.TestCase):
    def test_001_counted_session_fails_closed_without_frozen_protocol(self):
        with self.assertRaisesRegex(ValueError, "frozen CB22 protocol"):
            observation(protocol_version="DRAFT")

    def test_002_developer_cannot_be_counted(self):
        with self.assertRaisesRegex(ValueError, "developers"):
            observation(participant_is_sifi_developer=True)

    def test_003_consent_must_precede_start(self):
        with self.assertRaisesRegex(ValueError, "before"):
            observation(consent_confirmed_at=NOW + timedelta(minutes=2))

    def test_004_direct_identifier_is_rejected(self):
        with self.assertRaisesRegex(ValueError, "direct identifiers"):
            observation(direct_identifier_collected=True)

    def test_005_no_or_small_sample_cannot_pass(self):
        self.assertEqual(assess_cb22(()).result, CB22Result.NOT_READY)
        self.assertEqual(assess_cb22((observation(),)).result, CB22Result.IN_PROGRESS)

    def test_006_five_clean_completed_sessions_pass(self):
        stages = tuple(f"WF-{n:02d}" for n in range(1, 12))
        items = tuple(observation(n, completed_stage_ids=stages, task_completed=True,
                                  completed_at=NOW + timedelta(hours=1),
                                  evidence_link_check=EvidenceLinkCheck.PASS) for n in range(1, 6))
        self.assertEqual(assess_cb22(items).result, CB22Result.PASS)

    def test_007_forbidden_output_forces_failure(self):
        stages = tuple(f"WF-{n:02d}" for n in range(1, 12))
        items = tuple(observation(n, completed_stage_ids=stages, task_completed=True,
                                  completed_at=NOW + timedelta(hours=1),
                                  evidence_link_check=EvidenceLinkCheck.PASS,
                                  forbidden_output_observed=n == 1) for n in range(1, 6))
        self.assertEqual(assess_cb22(items).result, CB22Result.FAIL)

    def test_008_repository_round_trip_stays_inside_workspace(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            database = root / "runtime" / "data" / "sifi.sqlite"
            with CB22StudyRuntime(database, workspace_root=root) as runtime:
                item = observation(interventions=(Intervention(
                    InterventionCategory.NAVIGATION, "MENU-HELP", 30, True),))
                runtime.record(item, recorded_at=NOW + timedelta(minutes=2))
                self.assertEqual(runtime.latest(item.observation_id), item)
                self.assertEqual(runtime.assessment().result, CB22Result.IN_PROGRESS)

    def test_009_linked_study_workflow_completes_and_updates_observation(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            workflow = LocalCB22Workflow(root)
            (root / "05_VALIDATION" / "CB22_PREP").mkdir(parents=True)
            source = Path.cwd() / "05_VALIDATION" / "CB22_PREP" / "CB22_TASK_PACKAGE_v1_0.json"
            (root / "05_VALIDATION" / "CB22_PREP" / source.name).write_bytes(source.read_bytes())
            (root / "runtime" / "config").mkdir(parents=True, exist_ok=True)
            (root / "runtime" / "config" / "cb22-execution-gate.json").write_text(json.dumps({
                "protocol_version": "CB22-v1.0", "task_package_version": "CB22-TASK-v1.0",
                "primary_adjudicator_staff_code": "ADJ-001",
                "independent_checker_staff_code": "CHK-001",
                "conflict_review_complete": True,
                "external_review_requirement": "NOT_REQUIRED_DOCUMENTED",
            }), encoding="utf-8")
            state = workflow.start(
                participant_code="P-101", screening_record_id="SCR-101",
                consent_record_id="CON-101", consent_confirmed_at=NOW,
                governance_authorization_id="GOV-001",
                representative_profile_met=True,
                participant_is_sifi_developer=False, mode=StudyMode.COUNTED,
                now=NOW + timedelta(minutes=1),
            )
            for minute in range(2, 13):
                state = workflow.advance(
                    observation_id=state.observation.observation_id,
                    participant_code="P-101", now=NOW + timedelta(minutes=minute),
                )
            self.assertTrue(state.observation.task_completed)
            self.assertEqual(state.observation.evidence_link_check, EvidenceLinkCheck.PASS)
            self.assertIsNotNone(state.brief)

    def test_010_counted_workflow_stays_blocked_without_execution_gate(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            with self.assertRaisesRegex(ValueError, "gate is closed"):
                LocalCB22Workflow(root).start(
                    participant_code="P-101", screening_record_id="SCR-101",
                    consent_record_id="CON-101", consent_confirmed_at=NOW,
                    governance_authorization_id="GOV-001",
                    representative_profile_met=True,
                    participant_is_sifi_developer=False, mode=StudyMode.COUNTED,
                    now=NOW + timedelta(minutes=1),
                )

    def test_011_five_active_sessions_cannot_fail_or_pass_prematurely(self):
        assessment = assess_cb22(tuple(observation(n) for n in range(1, 6)))
        self.assertEqual(assessment.result, CB22Result.IN_PROGRESS)
        self.assertEqual(assessment.analyzable_participants, 0)

    def test_012_incomplete_ended_session_requires_reason_code(self):
        with self.assertRaisesRegex(ValueError, "noncompletion_reason_code"):
            observation(completed_at=NOW + timedelta(hours=1))

    def test_013_gate_detects_changed_task_package(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            package_dir = root / "05_VALIDATION" / "CB22_PREP"
            package_dir.mkdir(parents=True)
            (package_dir / "CB22_TASK_PACKAGE_v1_0.json").write_text("{}", encoding="utf-8")
            reasons = cb22_gate_status(root).reasons
            self.assertIn("TASK_PACKAGE_MISSING_OR_CHANGED", reasons)

    def test_014_distinct_adjudicators_are_mandatory(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            package_dir = root / "05_VALIDATION" / "CB22_PREP"
            package_dir.mkdir(parents=True)
            source = Path.cwd() / package_dir.relative_to(root) / "CB22_TASK_PACKAGE_v1_0.json"
            (package_dir / source.name).write_bytes(source.read_bytes())
            config_dir = root / "runtime" / "config"; config_dir.mkdir(parents=True)
            (config_dir / "cb22-execution-gate.json").write_text(json.dumps({
                "protocol_version":"CB22-v1.0", "task_package_version":"CB22-TASK-v1.0",
                "primary_adjudicator_staff_code":"STAFF-1",
                "independent_checker_staff_code":"STAFF-1",
                "conflict_review_complete":True,
                "external_review_requirement":"SATISFIED",
            }), encoding="utf-8")
            self.assertIn("ADJUDICATORS_MUST_BE_DISTINCT", cb22_gate_status(root).reasons)

    def test_015_duplicate_counted_participant_cannot_pass(self):
        first = observation(1)
        second = observation(2, participant_code=first.participant_code)
        assessment = assess_cb22((first, second))
        self.assertEqual(assessment.result, CB22Result.INCONCLUSIVE)
        self.assertIn("DUPLICATE_COUNTED_PARTICIPANT_CODE", assessment.reasons)


if __name__ == "__main__":
    unittest.main()
