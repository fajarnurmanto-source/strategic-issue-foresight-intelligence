from __future__ import annotations

import hashlib
import json
import subprocess
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path

from .authorization import (
    CaseExecutionAuthorization,
)
from .materialized_cases import (
    MATERIALIZED_EXECUTOR_SPECS,
)


class SemanticAuthorizationError(
    RuntimeError
):
    """Fail-closed semantic authorization error."""


@dataclass(
    frozen=True,
    slots=True,
)
class SemanticAuthorizationEligibility:
    case_id: str
    eligible: bool

    materialization_commit: str
    adjudication_commit: str

    materialization_reference: str
    materialization_sha256: str

    adjudication_reference: str
    adjudication_sha256: str

    executor_id: str
    executor_version: str
    executor_reference: str

    contract_fingerprint: str


def _sha256_bytes(
    value: bytes,
) -> str:
    return hashlib.sha256(
        value
    ).hexdigest()


def _sha256_file(
    path: Path,
) -> str:
    return _sha256_bytes(
        path.read_bytes()
    )


def _require_text(
    name: str,
    value,
) -> str:
    if (
        not isinstance(
            value,
            str,
        )
        or not value.strip()
    ):
        raise SemanticAuthorizationError(
            name
            + " must be non-empty"
        )

    return value


class SemanticAuthorizationIssuer:
    """Semantic gate above CaseExecutionAuthorization.

    This layer verifies that:
    - materialization is immutable and committed;
    - adjudication is immutable and committed;
    - adjudication semantically authorizes only eligibility,
      not execution itself;
    - fixture / materialization / executor hashes remain bound;
    - executor identity matches the materialized executor spec;
    - the explicit authorization decision artifact binds the
      same objects and current canonical contract fingerprint.

    issue() creates a CaseExecutionAuthorization object only.

    It does NOT call harness.authorize_execution().
    It does NOT execute a benchmark case.
    """

    def __init__(
        self,
        repo_root: str | Path,
        harness,
    ) -> None:
        self._repo_root = Path(
            repo_root
        ).resolve()

        self._harness = harness

    def _safe_relative_path(
        self,
        reference: str,
    ) -> Path:
        _require_text(
            "reference",
            reference,
        )

        relative = Path(
            reference
        )

        if relative.is_absolute():
            raise SemanticAuthorizationError(
                "absolute artifact path prohibited"
            )

        candidate = (
            self._repo_root
            / relative
        ).resolve()

        if not candidate.is_relative_to(
            self._repo_root
        ):
            raise SemanticAuthorizationError(
                "artifact path escapes repository root"
            )

        return candidate

    def _git_bytes(
        self,
        commit: str,
        reference: str,
    ) -> bytes:
        _require_text(
            "commit",
            commit,
        )

        _require_text(
            "reference",
            reference,
        )

        result = subprocess.run(
            [
                "git",
                "show",
                commit
                + ":"
                + reference,
            ],
            cwd=self._repo_root,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )

        if result.returncode != 0:
            raise SemanticAuthorizationError(
                "committed artifact unavailable: "
                + reference
            )

        return result.stdout

    def _latest_commit_for(
        self,
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
            cwd=self._repo_root,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False,
        )

        commit = result.stdout.strip()

        if (
            result.returncode != 0
            or not commit
        ):
            raise SemanticAuthorizationError(
                "unable to resolve committed "
                "adjudication provenance"
            )

        return commit

    def _read_json(
        self,
        reference: str,
    ) -> tuple[
        Path,
        bytes,
        dict,
    ]:
        path = self._safe_relative_path(
            reference
        )

        if not path.is_file():
            raise SemanticAuthorizationError(
                "artifact not found: "
                + reference
            )

        content = path.read_bytes()

        try:
            payload = json.loads(
                content.decode(
                    "utf-8"
                )
            )
        except (
            UnicodeDecodeError,
            json.JSONDecodeError,
        ) as exc:
            raise SemanticAuthorizationError(
                "invalid JSON artifact: "
                + reference
            ) from exc

        if not isinstance(
            payload,
            dict,
        ):
            raise SemanticAuthorizationError(
                "authorization artifact must "
                "be a JSON object"
            )

        return (
            path,
            content,
            payload,
        )

    def _adjudication_reference(
        self,
        case_id: str,
    ) -> str:
        return (
            "04_IMPLEMENTATION/S1_6D/"
            "materialized_cases/"
            + case_id
            + ".adjudication.json"
        )

    def evaluate(
        self,
        case_id: str,
    ) -> SemanticAuthorizationEligibility:
        if (
            case_id
            not in MATERIALIZED_EXECUTOR_SPECS
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": no materialized executor spec"
            )

        spec = (
            MATERIALIZED_EXECUTOR_SPECS[
                case_id
            ]
        )

        adjudication_reference = (
            self._adjudication_reference(
                case_id
            )
        )

        (
            _,
            adjudication_bytes,
            adjudication,
        ) = self._read_json(
            adjudication_reference
        )

        adjudication_sha256 = (
            _sha256_bytes(
                adjudication_bytes
            )
        )

        adjudication_commit = (
            self._latest_commit_for(
                adjudication_reference
            )
        )

        committed_adjudication = (
            self._git_bytes(
                adjudication_commit,
                adjudication_reference,
            )
        )

        if (
            committed_adjudication
            != adjudication_bytes
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudication differs "
                "from committed bytes"
            )

        if (
            adjudication.get(
                "case_id"
            )
            != case_id
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudication case mismatch"
            )

        if (
            adjudication.get(
                "adjudication_state"
            )
            != "ADJUDICATED_NOT_AUTHORIZED"
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialization is not "
                "ADJUDICATED_NOT_AUTHORIZED"
            )

        if (
            adjudication.get(
                "decision"
            )
            != (
                "MATERIALIZATION_CONFORMS_"
                "TO_CANONICAL_CASE_CONTRACT"
            )
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudication decision "
                "does not establish conformance"
            )

        if (
            adjudication.get(
                "materialization_conformance_verified"
            )
            is not True
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialization conformance "
                "not verified"
            )

        if (
            adjudication.get(
                "authorization_required_separately"
            )
            is not True
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": separate authorization "
                "boundary missing"
            )

        if (
            adjudication.get(
                "canonical_execution_authorized"
            )
            is not False
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudication unexpectedly "
                "claims execution authority"
            )

        if (
            adjudication.get(
                "canonical_execution_performed"
            )
            is not False
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudication indicates "
                "prior canonical execution"
            )

        if (
            adjudication.get(
                "canonical_result_recorded"
            )
            is not False
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudication indicates "
                "prior canonical result"
            )

        materialization_commit = (
            _require_text(
                "materialization_commit",
                adjudication.get(
                    "materialization_commit"
                ),
            )
        )

        materialization_reference = (
            _require_text(
                "materialization_manifest_path",
                adjudication.get(
                    "materialization_manifest_path"
                ),
            )
        )

        materialization_path = (
            self._safe_relative_path(
                materialization_reference
            )
        )

        if not materialization_path.is_file():
            raise SemanticAuthorizationError(
                case_id
                + ": materialization manifest "
                "not found"
            )

        materialization_bytes = (
            materialization_path.read_bytes()
        )

        materialization_sha256 = (
            _sha256_bytes(
                materialization_bytes
            )
        )

        if (
            materialization_sha256
            != adjudication.get(
                "materialization_manifest_sha256"
            )
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialization hash "
                "does not match adjudication"
            )

        if (
            self._git_bytes(
                materialization_commit,
                materialization_reference,
            )
            != materialization_bytes
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialization differs "
                "from bound commit"
            )

        materialization = json.loads(
            materialization_bytes.decode(
                "utf-8"
            )
        )

        fixture_reference = (
            _require_text(
                "fixture_path",
                adjudication.get(
                    "fixture_path"
                ),
            )
        )

        fixture_path = (
            self._safe_relative_path(
                fixture_reference
            )
        )

        if not fixture_path.is_file():
            raise SemanticAuthorizationError(
                case_id
                + ": fixture not found"
            )

        fixture_bytes = (
            fixture_path.read_bytes()
        )

        fixture_sha256 = (
            _sha256_bytes(
                fixture_bytes
            )
        )

        if (
            fixture_sha256
            != adjudication.get(
                "fixture_sha256"
            )
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": fixture hash mismatch"
            )

        if (
            self._git_bytes(
                materialization_commit,
                fixture_reference,
            )
            != fixture_bytes
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": fixture differs "
                "from bound commit"
            )

        executor_module_reference = (
            _require_text(
                "executor_module_path",
                adjudication.get(
                    "executor_module_path"
                ),
            )
        )

        executor_module_path = (
            self._safe_relative_path(
                executor_module_reference
            )
        )

        if not executor_module_path.is_file():
            raise SemanticAuthorizationError(
                case_id
                + ": executor module not found"
            )

        executor_module_bytes = (
            executor_module_path.read_bytes()
        )

        executor_module_sha256 = (
            _sha256_bytes(
                executor_module_bytes
            )
        )

        if (
            executor_module_sha256
            != adjudication.get(
                "executor_module_sha256"
            )
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": executor module hash mismatch"
            )

        if (
            self._git_bytes(
                materialization_commit,
                executor_module_reference,
            )
            != executor_module_bytes
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": executor module differs "
                "from bound materialization commit"
            )

        if (
            materialization.get(
                "fixture_sha256"
            )
            != fixture_sha256
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialization-to-fixture "
                "binding mismatch"
            )

        if (
            materialization.get(
                "executor_module_sha256"
            )
            != executor_module_sha256
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialization-to-executor "
                "binding mismatch"
            )

        expected_executor = (
            spec.executor_id,
            spec.executor_version,
            spec.executor_reference,
        )

        adjudicated_executor = (
            adjudication.get(
                "executor_id"
            ),
            adjudication.get(
                "executor_version"
            ),
            adjudication.get(
                "executor_reference"
            ),
        )

        materialized_executor = (
            materialization.get(
                "executor_id"
            ),
            materialization.get(
                "executor_version"
            ),
            materialization.get(
                "executor_reference"
            ),
        )

        if (
            adjudicated_executor
            != expected_executor
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": adjudicated executor "
                "identity mismatch"
            )

        if (
            materialized_executor
            != expected_executor
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": materialized executor "
                "identity mismatch"
            )

        contract_fingerprint = (
            _require_text(
                "contract_fingerprint",
                self._harness
                .contract_fingerprint,
            )
        )

        return (
            SemanticAuthorizationEligibility(
                case_id=case_id,
                eligible=True,
                materialization_commit=(
                    materialization_commit
                ),
                adjudication_commit=(
                    adjudication_commit
                ),
                materialization_reference=(
                    materialization_reference
                ),
                materialization_sha256=(
                    materialization_sha256
                ),
                adjudication_reference=(
                    adjudication_reference
                ),
                adjudication_sha256=(
                    adjudication_sha256
                ),
                executor_id=(
                    spec.executor_id
                ),
                executor_version=(
                    spec.executor_version
                ),
                executor_reference=(
                    spec.executor_reference
                ),
                contract_fingerprint=(
                    contract_fingerprint
                ),
            )
        )

    def issue(
        self,
        case_id: str,
        authorization_reference: str,
    ) -> CaseExecutionAuthorization:
        eligibility = self.evaluate(
            case_id
        )

        (
            _,
            authorization_bytes,
            authorization,
        ) = self._read_json(
            authorization_reference
        )

        expected_pairs = {
            "case_id": (
                case_id
            ),
            "authorization_scope": (
                "ONE_CANONICAL_BENCHMARK_EXECUTION"
            ),
            "decision": (
                "AUTHORIZE_CANONICAL_EXECUTION"
            ),
            "authorization_state": (
                "ISSUED_NOT_EXECUTED"
            ),
            "materialization_commit": (
                eligibility.materialization_commit
            ),
            "adjudication_commit": (
                eligibility.adjudication_commit
            ),
            "materialization_reference": (
                eligibility.materialization_reference
            ),
            "materialization_sha256": (
                eligibility.materialization_sha256
            ),
            "adjudication_reference": (
                eligibility.adjudication_reference
            ),
            "adjudication_sha256": (
                eligibility.adjudication_sha256
            ),
            "executor_id": (
                eligibility.executor_id
            ),
            "executor_version": (
                eligibility.executor_version
            ),
            "executor_reference": (
                eligibility.executor_reference
            ),
            "contract_fingerprint": (
                eligibility.contract_fingerprint
            ),
        }

        for name, expected in (
            expected_pairs.items()
        ):
            if (
                authorization.get(
                    name
                )
                != expected
            ):
                raise SemanticAuthorizationError(
                    case_id
                    + ": authorization artifact "
                    + name
                    + " mismatch"
                )

        authorization_id = (
            _require_text(
                "authorization_id",
                authorization.get(
                    "authorization_id"
                ),
            )
        )

        execution_intent_id = (
            _require_text(
                "execution_intent_id",
                authorization.get(
                    "execution_intent_id"
                ),
            )
        )

        if (
            execution_intent_id
            != authorization_id
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": execution_intent_id must "
                "equal authorization_id"
            )

        if (
            authorization.get(
                "single_use"
            )
            is not True
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": canonical authorization "
                "must declare single_use=true"
            )

        if (
            authorization.get(
                "canonical_execution_performed"
            )
            is not False
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": authorization artifact "
                "indicates prior execution"
            )

        if (
            authorization.get(
                "canonical_result_recorded"
            )
            is not False
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": authorization artifact "
                "indicates prior result"
            )

        _require_text(
            "issued_by_or_role_reference",
            authorization.get(
                "issued_by_or_role_reference"
            ),
        )

        _require_text(
            "authority_basis",
            authorization.get(
                "authority_basis"
            ),
        )

        issued_at = _require_text(
            "issued_at",
            authorization.get(
                "issued_at"
            ),
        )

        try:
            parsed_issued_at = (
                datetime.fromisoformat(
                    issued_at
                )
            )
        except ValueError as exc:
            raise SemanticAuthorizationError(
                case_id
                + ": issued_at is not "
                "valid ISO-8601"
            ) from exc

        if (
            parsed_issued_at.tzinfo is None
            or
            parsed_issued_at.utcoffset()
            is None
        ):
            raise SemanticAuthorizationError(
                case_id
                + ": issued_at must be "
                "timezone-aware"
            )

        return CaseExecutionAuthorization(
            authorization_id=(
                authorization_id
            ),
            case_id=case_id,
            executor_id=(
                eligibility.executor_id
            ),
            executor_version=(
                eligibility.executor_version
            ),
            executor_reference=(
                eligibility.executor_reference
            ),
            materialization_reference=(
                eligibility.materialization_reference
            ),
            materialization_sha256=(
                eligibility.materialization_sha256
            ),
            adjudication_reference=(
                eligibility.adjudication_reference
            ),
            adjudication_sha256=(
                eligibility.adjudication_sha256
            ),
            authorization_reference=(
                authorization_reference
            ),
            authorization_sha256=(
                _sha256_bytes(
                    authorization_bytes
                )
            ),
            contract_fingerprint=(
                eligibility.contract_fingerprint
            ),
        )
