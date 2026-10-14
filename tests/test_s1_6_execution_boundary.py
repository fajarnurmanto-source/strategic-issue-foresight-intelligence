from __future__ import annotations

import unittest
from datetime import datetime, timezone

from sifi.benchmarks.authorization import CaseExecutionAuthorization
from sifi.benchmarks.authorization_consumption import (
    AuthorizationAlreadyConsumedError,
    AuthorizationConsumptionRepository,
)
from sifi.benchmarks.execution_boundary import (
    CanonicalExecutionCoordinator,
    ExecutionBoundaryError,
)
from sifi.benchmarks.model import (
    CaseExecutionRecord,
    ExecutionEvidenceReference,
    HarnessExecutionState,
    HarnessResultState,
)
from sifi.persistence import SQLiteVersionStore


UTC = timezone.utc


def at(minute: int) -> datetime:
    return datetime(2026, 10, 14, 9, minute, tzinfo=UTC)


def authorization() -> CaseExecutionAuthorization:
    return CaseExecutionAuthorization(
        authorization_id="AUTH-CB01-001",
        case_id="CB01",
        executor_id="EXEC-CB01",
        executor_version="v0.1",
        executor_reference="module::executor",
        materialization_reference="materialization.json",
        materialization_sha256="a" * 64,
        adjudication_reference="adjudication.json",
        adjudication_sha256="b" * 64,
        authorization_reference="authorization.json",
        authorization_sha256="c" * 64,
        contract_fingerprint="d" * 64,
    )


class StubHarness:
    def __init__(self):
        self.authorized = None
        self.run_count = 0

    def authorize_execution(self, item):
        self.authorized = item

    def run_case(self, case_id):
        self.run_count += 1
        return CaseExecutionRecord(
            case_id=case_id,
            execution_state=HarnessExecutionState.EXECUTED,
            result_state=HarnessResultState.NO_RESULT,
            rights_basis="SYNTHETIC",
            analytical_cutoff_rule="CASE_DEFINED",
            reference_methodology="COMPONENT_TEST",
            executor_id="EXEC-CB01",
            executor_version="v0.1",
            executor_reference="module::executor",
            authorization_id=self.authorized.authorization_id,
            contract_fingerprint=self.authorized.contract_fingerprint,
            expected_conditions_satisfied=True,
            forbidden_output_observed=False,
            regression_dependencies_satisfied=True,
            rights_constraints_satisfied=True,
            evidence_refs=(
                ExecutionEvidenceReference(
                    evidence_id="EVIDENCE-001",
                    evidence_kind="COMPONENT_TEST",
                    artifact_path="synthetic-evidence.txt",
                    sha256="e" * 64,
                    producer_executor_id="EXEC-CB01",
                    producer_executor_version="v0.1",
                ),
            ),
        )


class SequenceClock:
    def __init__(self, *values):
        self._values = iter(values)

    def __call__(self):
        return next(self._values)


class CanonicalExecutionCoordinatorTests(unittest.TestCase):
    def setUp(self):
        self.store = SQLiteVersionStore(":memory:")
        self.repository = AuthorizationConsumptionRepository(
            self.store
        )

    def tearDown(self):
        self.store.close()

    def coordinator(self, harness=None):
        return CanonicalExecutionCoordinator(
            harness or StubHarness(),
            self.repository,
            clock=SequenceClock(at(2), at(3)),
        )

    def test_001_attempt_binds_identity_authorization_and_time(self):
        attempt = self.coordinator().execute(
            authorization(),
            execution_id="EXECUTION-001",
            requested_at=at(1),
        )

        self.assertEqual(attempt.execution_id, "EXECUTION-001")
        self.assertEqual(attempt.authorization_id, "AUTH-CB01-001")
        self.assertEqual(attempt.case_id, "CB01")
        self.assertEqual(attempt.started_at, at(2))
        self.assertEqual(attempt.completed_at, at(3))

    def test_002_authorization_is_consumed_before_executor_runs(self):
        harness = StubHarness()
        self.coordinator(harness).execute(
            authorization(),
            execution_id="EXECUTION-001",
            requested_at=at(1),
        )

        self.assertTrue(
            self.repository.is_consumed("AUTH-CB01-001")
        )
        self.assertEqual(harness.run_count, 1)

    def test_003_consumed_authorization_cannot_run_again(self):
        self.coordinator().execute(
            authorization(),
            execution_id="EXECUTION-001",
            requested_at=at(1),
        )

        second_harness = StubHarness()
        with self.assertRaises(AuthorizationAlreadyConsumedError):
            self.coordinator(second_harness).execute(
                authorization(),
                execution_id="EXECUTION-002",
                requested_at=at(1),
            )

        self.assertEqual(second_harness.run_count, 0)

    def test_004_naive_requested_time_is_rejected_before_authorization(self):
        harness = StubHarness()

        with self.assertRaises(ValueError):
            self.coordinator(harness).execute(
                authorization(),
                execution_id="EXECUTION-001",
                requested_at=datetime(2026, 10, 14, 9, 1),
            )

        self.assertIsNone(harness.authorized)

    def test_005_start_before_request_is_rejected_before_consumption(self):
        harness = StubHarness()
        coordinator = CanonicalExecutionCoordinator(
            harness,
            self.repository,
            clock=SequenceClock(at(0)),
        )

        with self.assertRaises(ExecutionBoundaryError):
            coordinator.execute(
                authorization(),
                execution_id="EXECUTION-001",
                requested_at=at(1),
            )

        self.assertFalse(
            self.repository.is_consumed("AUTH-CB01-001")
        )
        self.assertIsNone(harness.authorized)
        self.assertEqual(harness.run_count, 0)


if __name__ == "__main__":
    unittest.main()
