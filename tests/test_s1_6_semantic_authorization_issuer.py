from __future__ import annotations

import hashlib
import json
import os
import subprocess
import tempfile
import unittest
from contextlib import contextmanager
from pathlib import Path

from sifi.benchmarks.authorization import (
    CaseExecutionAuthorization,
)
from sifi.benchmarks.harness import (
    CanonicalBenchmarkHarness,
)
from sifi.benchmarks.materialized_cases import (
    MATERIALIZED_EXECUTORS,
    MATERIALIZED_EXECUTOR_SPECS,
)
from sifi.benchmarks.semantic_authorization import (
    SemanticAuthorizationError,
    SemanticAuthorizationIssuer,
)


ROOT = Path(
    __file__
).resolve().parents[1]

CASE_ID = "CB01"


def sha256(
    path: Path,
) -> str:
    return hashlib.sha256(
        path.read_bytes()
    ).hexdigest()


def make_harness():
    return (
        CanonicalBenchmarkHarness
        .from_stage0(
            ROOT
        )
    )


def latest_commit_for(
    reference: str,
) -> str:
    result = subprocess.run(
        [
            "git",
            "log",
            "-1",
            "--format=%H",
            "--",
            reference,
        ],
        cwd=ROOT,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        check=True,
    )

    return result.stdout.strip()


def authorization_payload(
    harness,
    case_id=CASE_ID,
):
    spec = (
        MATERIALIZED_EXECUTOR_SPECS[
            case_id
        ]
    )

    adjudication_reference = (
        "04_IMPLEMENTATION/S1_6D/"
        "materialized_cases/"
        + case_id
        + ".adjudication.json"
    )

    adjudication_path = (
        ROOT
        / adjudication_reference
    )

    adjudication = json.loads(
        adjudication_path.read_text(
            encoding="utf-8"
        )
    )

    authorization_id = (
        "TEST-SEMANTIC-AUTH-"
        + case_id
    )

    return {
        "schema_version": "0.1",
        "authorization_id": (
            authorization_id
        ),
        "execution_intent_id": (
            authorization_id
        ),
        "case_id": case_id,
        "authorization_scope": (
            "ONE_CANONICAL_BENCHMARK_EXECUTION"
        ),
        "decision": (
            "AUTHORIZE_CANONICAL_EXECUTION"
        ),
        "authorization_state": (
            "ISSUED_NOT_EXECUTED"
        ),
        "single_use": True,
        "issued_by_or_role_reference": (
            "TEST-BENCHMARK-AUTHORITY"
        ),
        "authority_basis": (
            "CONTROLLED_COMPONENT_TEST_ONLY"
        ),
        "issued_at": (
            "2026-10-11T07:00:00+00:00"
        ),
        "materialization_commit": (
            adjudication[
                "materialization_commit"
            ]
        ),
        "adjudication_commit": (
            latest_commit_for(
                adjudication_reference
            )
        ),
        "materialization_reference": (
            adjudication[
                "materialization_manifest_path"
            ]
        ),
        "materialization_sha256": (
            adjudication[
                "materialization_manifest_sha256"
            ]
        ),
        "adjudication_reference": (
            adjudication_reference
        ),
        "adjudication_sha256": (
            sha256(
                adjudication_path
            )
        ),
        "executor_id": (
            spec.executor_id
        ),
        "executor_version": (
            spec.executor_version
        ),
        "executor_reference": (
            spec.executor_reference
        ),
        "contract_fingerprint": (
            harness.contract_fingerprint
        ),
        "canonical_execution_performed": False,
        "canonical_result_recorded": False,
        "notes": [
            (
                "Component-test semantic "
                "authorization only."
            ),
            (
                "This file is not a canonical "
                "execution authorization."
            ),
        ],
    }


@contextmanager
def authorization_artifact(
    payload: dict,
):
    fd, path_string = tempfile.mkstemp(
        prefix=".sifi-semantic-auth-",
        suffix=".json",
        dir=ROOT,
        text=True,
    )

    os.close(
        fd
    )

    path = Path(
        path_string
    )

    try:
        path.write_text(
            json.dumps(
                payload,
                indent=2,
                sort_keys=True,
            )
            + "\n",
            encoding="utf-8",
        )

        yield (
            path
            .relative_to(
                ROOT
            )
            .as_posix()
        )
    finally:
        path.unlink(
            missing_ok=True
        )


class SemanticAuthorizationIssuerTests(
    unittest.TestCase
):
    def test_001_current_cb01_is_semantically_eligible(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        eligibility = issuer.evaluate(
            "CB01"
        )

        self.assertTrue(
            eligibility.eligible
        )

        self.assertEqual(
            eligibility.case_id,
            "CB01",
        )

        self.assertEqual(
            eligibility.contract_fingerprint,
            harness.contract_fingerprint,
        )

    def test_002_issue_returns_case_execution_authorization(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        with authorization_artifact(
            payload
        ) as reference:
            result = issuer.issue(
                CASE_ID,
                reference,
            )

        self.assertIsInstance(
            result,
            CaseExecutionAuthorization,
        )

        self.assertEqual(
            result.authorization_id,
            payload[
                "authorization_id"
            ],
        )

    def test_003_issue_does_not_authorize_harness(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        with authorization_artifact(
            payload
        ) as reference:
            issuer.issue(
                CASE_ID,
                reference,
            )

        self.assertFalse(
            harness
            .has_execution_authorization(
                CASE_ID
            )
        )

    def test_004_wrong_case_binding_rejected(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "case_id"
        ] = "CB07"

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_005_contract_fingerprint_drift_rejected(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "contract_fingerprint"
        ] = "0" * 64

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_006_executor_identity_drift_rejected(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "executor_version"
        ] = "v999"

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_007_materialization_binding_drift_rejected(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "materialization_sha256"
        ] = "0" * 64

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_008_non_authorize_decision_rejected(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "decision"
        ] = "DO_NOT_AUTHORIZE"

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_009_single_use_false_rejected(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "single_use"
        ] = False

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_010_execution_intent_must_equal_authorization_id(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "execution_intent_id"
        ] = "DIFFERENT-INTENT"

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_011_issued_at_must_be_timezone_aware(self):
        harness = make_harness()

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        payload[
            "issued_at"
        ] = "2026-10-11T07:00:00"

        with authorization_artifact(
            payload
        ) as reference:
            with self.assertRaises(
                SemanticAuthorizationError
            ):
                issuer.issue(
                    CASE_ID,
                    reference,
                )

    def test_012_harness_can_accept_issued_object_without_execution(self):
        harness = make_harness()

        spec = (
            MATERIALIZED_EXECUTOR_SPECS[
                CASE_ID
            ]
        )

        harness.register_executor(
            CASE_ID,
            MATERIALIZED_EXECUTORS[
                CASE_ID
            ],
            executor_id=(
                spec.executor_id
            ),
            executor_version=(
                spec.executor_version
            ),
            executor_reference=(
                spec.executor_reference
            ),
        )

        issuer = (
            SemanticAuthorizationIssuer(
                ROOT,
                harness,
            )
        )

        payload = authorization_payload(
            harness
        )

        with authorization_artifact(
            payload
        ) as reference:
            authorization = (
                issuer.issue(
                    CASE_ID,
                    reference,
                )
            )

            harness.authorize_execution(
                authorization
            )

            self.assertTrue(
                harness
                .has_execution_authorization(
                    CASE_ID
                )
            )

        # Deliberately no run_case().
        # This verifies compatibility of the
        # semantic issuer with the generic
        # authorization kernel, not canonical
        # benchmark execution.


if __name__ == "__main__":
    unittest.main()
