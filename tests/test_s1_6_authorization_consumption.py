from __future__ import annotations

import tempfile
import unittest
from dataclasses import replace
from datetime import datetime, timezone
from pathlib import Path

from sifi.benchmarks.authorization import (
    CaseExecutionAuthorization,
)
from sifi.benchmarks.authorization_consumption import (
    AuthorizationAlreadyConsumedError,
    AuthorizationConsumptionError,
    AuthorizationConsumptionRepository,
)
from sifi.persistence import SQLiteVersionStore


UTC = timezone.utc


def moment(minute: int) -> datetime:
    return datetime(2026, 10, 14, 8, minute, tzinfo=UTC)


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


class AuthorizationConsumptionRepositoryTests(unittest.TestCase):
    def setUp(self):
        self.store = SQLiteVersionStore(":memory:")
        self.repository = AuthorizationConsumptionRepository(
            self.store
        )

    def tearDown(self):
        self.store.close()

    def consume(self, item=None, execution_id="EXECUTION-001"):
        return self.repository.consume(
            item or authorization(),
            execution_id=execution_id,
            consumed_at=moment(1),
            recorded_at=moment(2),
        )

    def test_001_consumption_is_durable_record(self):
        saved = self.consume()

        self.assertEqual(
            saved.consumption.consumption_state,
            "CONSUMED",
        )
        self.assertTrue(
            self.repository.is_consumed("AUTH-CB01-001")
        )

    def test_002_same_authorization_cannot_be_consumed_twice(self):
        self.consume()

        with self.assertRaises(
            AuthorizationAlreadyConsumedError
        ):
            self.consume(execution_id="EXECUTION-002")

    def test_003_duplicate_id_with_changed_binding_is_rejected(self):
        self.consume()
        changed = replace(authorization(), case_id="CB07")

        with self.assertRaises(
            AuthorizationAlreadyConsumedError
        ):
            self.consume(changed)

    def test_004_consumption_survives_repository_restart(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "consumption.sqlite"

            with SQLiteVersionStore(database) as store:
                repository = AuthorizationConsumptionRepository(store)
                repository.consume(
                    authorization(),
                    execution_id="EXECUTION-001",
                    consumed_at=moment(1),
                    recorded_at=moment(2),
                )

            with SQLiteVersionStore(database) as store:
                repository = AuthorizationConsumptionRepository(store)

                recovered = repository.get("AUTH-CB01-001")
                self.assertIsNotNone(recovered)
                self.assertEqual(
                    recovered.consumption.execution_id,
                    "EXECUTION-001",
                )

                with self.assertRaises(
                    AuthorizationAlreadyConsumedError
                ):
                    repository.consume(
                        authorization(),
                        execution_id="EXECUTION-002",
                        consumed_at=moment(3),
                        recorded_at=moment(4),
                    )

    def test_005_naive_consumption_time_is_rejected(self):
        with self.assertRaises(ValueError):
            self.repository.consume(
                authorization(),
                execution_id="EXECUTION-001",
                consumed_at=datetime(2026, 10, 14, 8, 1),
                recorded_at=moment(2),
            )

    def test_006_empty_execution_identity_is_rejected(self):
        with self.assertRaises(AuthorizationConsumptionError):
            self.consume(execution_id=" ")


if __name__ == "__main__":
    unittest.main()
