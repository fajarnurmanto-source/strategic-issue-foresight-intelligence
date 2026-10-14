from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Callable

from sifi.domain.versioning import require_aware_datetime

from .authorization import CaseExecutionAuthorization
from .authorization_consumption import (
    AuthorizationConsumptionRepository,
)
from .model import CaseExecutionRecord


class ExecutionBoundaryError(Exception):
    """Canonical execution identity or time boundary violation."""


@dataclass(frozen=True, slots=True)
class CanonicalExecutionAttempt:
    execution_id: str
    authorization_id: str
    case_id: str
    requested_at: datetime
    started_at: datetime
    completed_at: datetime
    record: CaseExecutionRecord

    def __post_init__(self) -> None:
        for name in (
            "execution_id",
            "authorization_id",
            "case_id",
        ):
            value = getattr(self, name)
            if not isinstance(value, str) or not value.strip():
                raise ExecutionBoundaryError(
                    name + " must be non-empty"
                )

        for name in (
            "requested_at",
            "started_at",
            "completed_at",
        ):
            require_aware_datetime(getattr(self, name), name)

        if not (
            self.requested_at
            <= self.started_at
            <= self.completed_at
        ):
            raise ExecutionBoundaryError(
                "execution timestamps must be monotonic"
            )

        if self.record.case_id != self.case_id:
            raise ExecutionBoundaryError(
                "execution record case does not match attempt"
            )

        if self.record.authorization_id != self.authorization_id:
            raise ExecutionBoundaryError(
                "execution record authorization does not match attempt"
            )


class CanonicalExecutionCoordinator:
    """Coordinates authorization, durable consumption, and execution."""

    def __init__(
        self,
        harness,
        consumption_repository: AuthorizationConsumptionRepository,
        *,
        clock: Callable[[], datetime] | None = None,
    ) -> None:
        self._harness = harness
        self._consumption_repository = consumption_repository
        self._clock = clock or (
            lambda: datetime.now(timezone.utc)
        )

    def execute(
        self,
        authorization: CaseExecutionAuthorization,
        *,
        execution_id: str,
        requested_at: datetime,
    ) -> CanonicalExecutionAttempt:
        if not isinstance(
            authorization,
            CaseExecutionAuthorization,
        ):
            raise ExecutionBoundaryError(
                "authorization must be CaseExecutionAuthorization"
            )

        if not isinstance(execution_id, str) or not execution_id.strip():
            raise ExecutionBoundaryError(
                "execution_id must be non-empty"
            )

        require_aware_datetime(requested_at, "requested_at")

        started_at = self._clock()
        require_aware_datetime(started_at, "started_at")

        if started_at < requested_at:
            raise ExecutionBoundaryError(
                "started_at cannot precede requested_at"
            )

        self._harness.authorize_execution(authorization)

        self._consumption_repository.consume(
            authorization,
            execution_id=execution_id,
            consumed_at=started_at,
            recorded_at=started_at,
        )

        record = self._harness.run_case(
            authorization.case_id
        )

        completed_at = self._clock()
        require_aware_datetime(completed_at, "completed_at")

        return CanonicalExecutionAttempt(
            execution_id=execution_id,
            authorization_id=authorization.authorization_id,
            case_id=authorization.case_id,
            requested_at=requested_at,
            started_at=started_at,
            completed_at=completed_at,
            record=record,
        )
