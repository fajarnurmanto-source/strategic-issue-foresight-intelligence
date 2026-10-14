from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain.versioning import (
    VersionedSnapshot,
    require_aware_datetime,
)
from sifi.persistence import (
    DuplicateVersionError,
    SQLiteVersionStore,
)

from .authorization import (
    CaseExecutionAuthorization,
)


AUTHORIZATION_CONSUMPTION_OBJECT_TYPE = (
    "BENCHMARK_AUTHORIZATION_CONSUMPTION"
)


class AuthorizationConsumptionError(Exception):
    """Authorization consumption boundary violation."""


class AuthorizationAlreadyConsumedError(
    AuthorizationConsumptionError
):
    """A single-use authorization was already consumed."""


@dataclass(frozen=True, slots=True)
class AuthorizationConsumption:
    authorization_id: str
    case_id: str
    execution_id: str
    authorization_sha256: str
    contract_fingerprint: str

    @property
    def consumption_state(self) -> str:
        return "CONSUMED"

    def to_payload(self) -> dict[str, str]:
        return {
            "consumption_state": self.consumption_state,
            "authorization_id": self.authorization_id,
            "case_id": self.case_id,
            "execution_id": self.execution_id,
            "authorization_sha256": self.authorization_sha256,
            "contract_fingerprint": self.contract_fingerprint,
        }

    @classmethod
    def from_payload(
        cls,
        payload: dict,
    ) -> "AuthorizationConsumption":
        if payload.get("consumption_state") != "CONSUMED":
            raise AuthorizationConsumptionError(
                "stored authorization consumption state is invalid"
            )

        return cls(
            authorization_id=payload["authorization_id"],
            case_id=payload["case_id"],
            execution_id=payload["execution_id"],
            authorization_sha256=payload["authorization_sha256"],
            contract_fingerprint=payload["contract_fingerprint"],
        )


@dataclass(frozen=True, slots=True)
class PersistedAuthorizationConsumption:
    consumed_at: datetime
    recorded_at: datetime
    consumption: AuthorizationConsumption


class AuthorizationConsumptionRepository:
    """Durably claims a single-use benchmark authorization.

    The authorization id is the persistence identity and version 1 is
    the only valid consumption event. The store primary key therefore
    makes duplicate claims fail closed, including after process restart.
    """

    def __init__(self, store: SQLiteVersionStore) -> None:
        self._store = store

    def consume(
        self,
        authorization: CaseExecutionAuthorization,
        *,
        execution_id: str,
        consumed_at: datetime,
        recorded_at: datetime,
    ) -> PersistedAuthorizationConsumption:
        if not isinstance(
            authorization,
            CaseExecutionAuthorization,
        ):
            raise AuthorizationConsumptionError(
                "authorization must be CaseExecutionAuthorization"
            )

        if (
            not isinstance(execution_id, str)
            or not execution_id.strip()
        ):
            raise AuthorizationConsumptionError(
                "execution_id must be non-empty"
            )

        require_aware_datetime(consumed_at, "consumed_at")
        require_aware_datetime(recorded_at, "recorded_at")

        consumption = AuthorizationConsumption(
            authorization_id=authorization.authorization_id,
            case_id=authorization.case_id,
            execution_id=execution_id,
            authorization_sha256=authorization.authorization_sha256,
            contract_fingerprint=authorization.contract_fingerprint,
        )

        try:
            self._store.append(
                VersionedSnapshot(
                    object_type=(
                        AUTHORIZATION_CONSUMPTION_OBJECT_TYPE
                    ),
                    object_id=authorization.authorization_id,
                    version=1,
                    effective_at=consumed_at,
                    recorded_at=recorded_at,
                    payload=consumption.to_payload(),
                )
            )
        except DuplicateVersionError as exc:
            raise AuthorizationAlreadyConsumedError(
                authorization.authorization_id
                + ": single-use authorization already consumed"
            ) from exc

        return PersistedAuthorizationConsumption(
            consumed_at=consumed_at,
            recorded_at=recorded_at,
            consumption=consumption,
        )

    def get(
        self,
        authorization_id: str,
    ) -> PersistedAuthorizationConsumption | None:
        snapshot = self._store.get(
            AUTHORIZATION_CONSUMPTION_OBJECT_TYPE,
            authorization_id,
            1,
        )

        if snapshot is None:
            return None

        return PersistedAuthorizationConsumption(
            consumed_at=snapshot.effective_at,
            recorded_at=snapshot.recorded_at,
            consumption=AuthorizationConsumption.from_payload(
                dict(snapshot.payload)
            ),
        )

    def is_consumed(self, authorization_id: str) -> bool:
        return self.get(authorization_id) is not None
