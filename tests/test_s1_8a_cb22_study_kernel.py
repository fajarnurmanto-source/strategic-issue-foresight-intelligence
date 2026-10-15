from __future__ import annotations

import tempfile
import unittest
import json
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.validation import (
    CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION, CB22Result,
    AdjudicationRole, CB22AdjudicationRuntime, CB22StudyRuntime,
    EvidenceLinkCheck, Intervention, InterventionCategory,
    LocalCB22Workflow, StudyMode, StudyObservation, assess_cb22, cb22_gate_status,
    export_adjudication_bundle,
    configure_cb22_gate,
    StudyObservationVersionError,
)
from sifi.validation.study_task import STUDY_STEP_SPECS
from sifi.validation.cli import status as cb22_status


NOW = datetime(2026, 10, 15, 9, 0, tzinfo=timezone.utc)


def install_frozen_materials(root: Path) -> None:
    destination = root / "05_VALIDATION" / "CB22_PREP"
    destination.mkdir(parents=True)
    source = Path.cwd() / "05_VALIDATION" / "CB22_PREP"
    for name in (
        "CB22_TASK_PACKAGE_v1_0.json",
        "CB22_7_FROZEN_PROTOCOL_v1_0.md",
        "CB22_10_OBSERVATION_RECORD_SCHEMA_v1_0.json",
    ):
        (destination / name).write_bytes((source / name).read_bytes())


def install_ready_gate(root: Path) -> None:
    config = root / "runtime" / "config"
    config.mkdir(parents=True, exist_ok=True)
    (config / "cb22-execution-gate.json").write_text(json.dumps({
        "protocol_version":"CB22-v1.0", "task_package_version":"CB22-TASK-v1.0",
        "primary_adjudicator_staff_code":"ADJ-001",
        "independent_checker_staff_code":"CHK-001",
        "conflict_review_complete":True,
        "external_review_requirement":"SATISFIED",
    }), encoding="utf-8")


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
        self.assertEqual(assess_cb22(items).completion_rate, 1.0)
        self.assertEqual(assess_cb22(items).median_duration_seconds, 3540.0)

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
            install_frozen_materials(root)
            install_ready_gate(root)
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
                    participant_code="P-101",
                    answer_code=STUDY_STEP_SPECS[state.session.next_step].accepted_code,
                    now=NOW + timedelta(minutes=minute),
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
            install_frozen_materials(root)
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

    def test_016_adjudication_export_is_confined_and_pseudonymous(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            destination = export_adjudication_bundle(root, (observation(),))
            self.assertTrue(destination.is_relative_to(root))
            payload = json.loads(destination.read_text(encoding="utf-8"))
            self.assertEqual(payload["observations"][0]["participant_code"], "P-001")
            self.assertFalse(payload["observations"][0]["direct_identifier_collected"])
            self.assertNotIn("name", json.dumps(payload).lower())

    def test_017_two_distinct_assigned_roles_are_required_for_final_pass(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory)
            (root / "src" / "sifi").mkdir(parents=True)
            install_frozen_materials(root); install_ready_gate(root)
            completed = []
            workflow = LocalCB22Workflow(root)
            for n in range(1, 6):
                state = workflow.start(
                    participant_code=f"P-{n:03d}", screening_record_id=f"SCR-{n:03d}",
                    consent_record_id=f"CON-{n:03d}", consent_confirmed_at=NOW,
                    governance_authorization_id="GOV-001", representative_profile_met=True,
                    participant_is_sifi_developer=False, mode=StudyMode.COUNTED,
                    now=NOW + timedelta(minutes=n),
                )
                for offset in range(11):
                    state = workflow.advance(
                        observation_id=state.observation.observation_id,
                        participant_code=state.observation.participant_code,
                        answer_code=STUDY_STEP_SPECS[state.session.next_step].accepted_code,
                        now=NOW + timedelta(minutes=10 + n * 20 + offset),
                    )
                completed.append(state.observation)
            items = tuple(completed)
            bundle = export_adjudication_bundle(root, items)
            with CB22AdjudicationRuntime(root) as runtime:
                receipt = runtime.record_vote(
                    bundle_path=bundle, role=AdjudicationRole.PRIMARY_ADJUDICATOR,
                    staff_code="ADJ-001", decision=CB22Result.PASS,
                    reviewed_at=NOW + timedelta(hours=2), conflict_free=True,
                )
                self.assertFalse(receipt.claim_allowed)
                receipt = runtime.record_vote(
                    bundle_path=bundle, role=AdjudicationRole.INDEPENDENT_CHECKER,
                    staff_code="CHK-001", decision=CB22Result.PASS,
                    reviewed_at=NOW + timedelta(hours=3), conflict_free=True,
                )
                self.assertTrue(receipt.claim_allowed)
                self.assertEqual(receipt.status, "FINAL")
                final_path = runtime.export_final_receipt(bundle)
                self.assertTrue(final_path.is_file())

    def test_018_study_workflow_rejects_boundary_breaking_answer(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            workflow = LocalCB22Workflow(root)
            state = workflow.start(
                participant_code="P-301", screening_record_id="SCR-301",
                consent_record_id="CON-301", consent_confirmed_at=NOW,
                governance_authorization_id="GOV-001", representative_profile_met=True,
                participant_is_sifi_developer=False, mode=StudyMode.REHEARSAL,
                now=NOW + timedelta(minutes=1),
            )
            with self.assertRaisesRegex(ValueError, "does not preserve"):
                workflow.advance(
                    observation_id=state.observation.observation_id,
                    participant_code="P-301", answer_code="COVERAGE-GLOBAL",
                    now=NOW + timedelta(minutes=2),
                )
            with CB22StudyRuntime(
                root / "runtime" / "data" / "sifi.sqlite", workspace_root=root
            ) as runtime:
                stored = runtime.latest(state.observation.observation_id)
            self.assertIn("BOUNDARY-RETRY-WF-01", stored.blocker_codes)

    def test_019_gate_configuration_rejects_same_person_in_both_roles(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            with self.assertRaisesRegex(ValueError, "distinct"):
                configure_cb22_gate(
                    root, primary_staff_code="STAFF-1", checker_staff_code="STAFF-1",
                    external_review_requirement="SATISFIED",
                    conflict_review_complete=True,
                )

    def test_020_gate_configuration_freezes_after_first_counted_session(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            database = root / "runtime" / "data" / "sifi.sqlite"
            with CB22StudyRuntime(database, workspace_root=root) as runtime:
                runtime.record(observation(), recorded_at=NOW + timedelta(minutes=2))
            with self.assertRaisesRegex(ValueError, "frozen"):
                configure_cb22_gate(
                    root, primary_staff_code="ADJ-002", checker_staff_code="CHK-002",
                    external_review_requirement="SATISFIED",
                    conflict_review_complete=True,
                )

    def test_021_manual_gate_change_after_counted_start_is_detected(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            install_frozen_materials(root); install_ready_gate(root)
            LocalCB22Workflow(root).start(
                participant_code="P-401", screening_record_id="SCR-401",
                consent_record_id="CON-401", consent_confirmed_at=NOW,
                governance_authorization_id="GOV-001", representative_profile_met=True,
                participant_is_sifi_developer=False, mode=StudyMode.COUNTED,
                now=NOW + timedelta(minutes=1),
            )
            gate_file = root / "runtime" / "config" / "cb22-execution-gate.json"
            changed = json.loads(gate_file.read_text(encoding="utf-8"))
            changed["independent_checker_staff_code"] = "CHK-999"
            gate_file.write_text(json.dumps(changed), encoding="utf-8")
            self.assertIn(
                "CURRENT_CONFIGURATION_DIFFERS_FROM_COUNTED_STUDY_LOCK",
                cb22_gate_status(root).reasons,
            )

    def test_022_observation_identity_and_history_are_append_only(self):
        from dataclasses import replace
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            database = root / "runtime" / "data" / "sifi.sqlite"
            original = observation()
            with CB22StudyRuntime(database, workspace_root=root) as runtime:
                runtime.record(original, recorded_at=NOW + timedelta(minutes=2))
                with self.assertRaises(StudyObservationVersionError):
                    runtime.record(
                        replace(original, participant_code="P-999"),
                        recorded_at=NOW + timedelta(minutes=3),
                    )

    def test_023_automated_pass_never_sets_claim_allowed(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            stages = tuple(f"WF-{n:02d}" for n in range(1, 12))
            database = root / "runtime" / "data" / "sifi.sqlite"
            with CB22StudyRuntime(database, workspace_root=root) as runtime:
                for n in range(1, 6):
                    runtime.record(observation(
                        n, completed_stage_ids=stages, task_completed=True,
                        completed_at=NOW + timedelta(hours=1),
                        evidence_link_check=EvidenceLinkCheck.PASS,
                    ), recorded_at=NOW + timedelta(hours=2))
            result = cb22_status(root)
            self.assertTrue(result["automated_pass_candidate"])
            self.assertFalse(result["claim_allowed"])

    def test_024_export_demotes_pass_when_workflow_evidence_is_missing(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            stages = tuple(f"WF-{n:02d}" for n in range(1, 12))
            items = tuple(observation(
                n, completed_stage_ids=stages, task_completed=True,
                completed_at=NOW + timedelta(hours=1),
                evidence_link_check=EvidenceLinkCheck.PASS,
            ) for n in range(1, 6))
            bundle = export_adjudication_bundle(root, items)
            payload = json.loads(bundle.read_text(encoding="utf-8"))
            self.assertEqual(payload["assessment"]["result"], "INCONCLUSIVE")
            self.assertEqual(len(payload["workflow_linkage_failures"]), 5)

    def test_025_counted_session_stops_if_frozen_task_changes_mid_session(self):
        with tempfile.TemporaryDirectory(dir=Path.cwd()) as directory:
            root = Path(directory); (root / "src" / "sifi").mkdir(parents=True)
            install_frozen_materials(root); install_ready_gate(root)
            workflow = LocalCB22Workflow(root)
            state = workflow.start(
                participant_code="P-501", screening_record_id="SCR-501",
                consent_record_id="CON-501", consent_confirmed_at=NOW,
                governance_authorization_id="GOV-001", representative_profile_met=True,
                participant_is_sifi_developer=False, mode=StudyMode.COUNTED,
                now=NOW + timedelta(minutes=1),
            )
            task = root / "05_VALIDATION" / "CB22_PREP" / "CB22_TASK_PACKAGE_v1_0.json"
            task.write_text("{}", encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "closed during session"):
                workflow.advance(
                    observation_id=state.observation.observation_id,
                    participant_code="P-501", answer_code="COVERAGE-SYNTHETIC",
                    now=NOW + timedelta(minutes=2),
                )


if __name__ == "__main__":
    unittest.main()
