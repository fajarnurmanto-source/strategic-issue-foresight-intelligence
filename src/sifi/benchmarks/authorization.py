from __future__ import annotations

import re
from dataclasses import dataclass


_SHA256_RE = re.compile(
    r"^[0-9a-f]{64}$"
)


def _require_text(
    name: str,
    value: str,
) -> None:
    if (
        not isinstance(value, str)
        or not value.strip()
    ):
        raise ValueError(
            f"{name} must be non-empty"
        )


def _normalize_sha256(
    name: str,
    value: str,
) -> str:
    if not isinstance(value, str):
        raise ValueError(
            f"{name} must be a string"
        )

    normalized = value.lower()

    if not _SHA256_RE.fullmatch(
        normalized
    ):
        raise ValueError(
            f"{name} must be 64 lowercase "
            "hexadecimal characters"
        )

    return normalized


class BenchmarkAuthorizationError(
    ValueError
):
    """Execution authorization boundary violation."""


@dataclass(frozen=True, slots=True)
class CaseExecutionAuthorization:
    """Explicit authorization for one canonical case executor.

    Executor registration proves only that executable mechanics exist.

    This binding separately requires repository-backed:
    - materialization evidence;
    - adjudication evidence;
    - explicit execution authorization;
    - exact canonical Stage0 contract fingerprint;
    - exact executor identity/version/reference.

    This authorization does not imply benchmark PASS.
    """

    authorization_id: str
    case_id: str

    executor_id: str
    executor_version: str
    executor_reference: str

    materialization_reference: str
    materialization_sha256: str

    adjudication_reference: str
    adjudication_sha256: str

    authorization_reference: str
    authorization_sha256: str

    contract_fingerprint: str

    def __post_init__(self) -> None:
        for name in (
            "authorization_id",
            "case_id",
            "executor_id",
            "executor_version",
            "executor_reference",
            "materialization_reference",
            "adjudication_reference",
            "authorization_reference",
        ):
            _require_text(
                name,
                getattr(self, name),
            )

        references = (
            self.materialization_reference,
            self.adjudication_reference,
            self.authorization_reference,
        )

        if len(set(references)) != 3:
            raise ValueError(
                "materialization, adjudication, "
                "and authorization references "
                "must be distinct"
            )

        for name in (
            "materialization_sha256",
            "adjudication_sha256",
            "authorization_sha256",
            "contract_fingerprint",
        ):
            object.__setattr__(
                self,
                name,
                _normalize_sha256(
                    name,
                    getattr(self, name),
                ),
            )
