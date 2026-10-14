from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from .semantic_authorization import (
    SemanticAuthorizationEligibility,
    SemanticAuthorizationIssuer,
)


@dataclass(frozen=True, slots=True)
class AuthorizationProposal:
    """Non-authoritative proposal for a future human decision."""

    payload: dict[str, Any]

    @property
    def case_id(self) -> str:
        return self.payload["case_id"]

    @property
    def proposal_state(self) -> str:
        return self.payload["authorization_state"]

    @property
    def executable(self) -> bool:
        return False


class AuthorizationProposalBuilder:
    """Builds bound proposals without granting execution authority."""

    def __init__(self, issuer: SemanticAuthorizationIssuer) -> None:
        self._issuer = issuer

    def build(self, case_id: str) -> AuthorizationProposal:
        eligibility = self._issuer.evaluate(case_id)
        return AuthorizationProposal(
            payload=self._payload(eligibility)
        )

    @staticmethod
    def _payload(
        eligibility: SemanticAuthorizationEligibility,
    ) -> dict[str, Any]:
        return {
            "schema_version": "0.1",
            "record_type": (
                "SIFI_CANONICAL_EXECUTION_AUTHORIZATION_PROPOSAL"
            ),
            "proposal_id": (
                "S1.6D-AUTH-PROPOSAL-" + eligibility.case_id
            ),
            "authorization_id": None,
            "execution_intent_id": None,
            "case_id": eligibility.case_id,
            "authorization_scope": (
                "ONE_CANONICAL_BENCHMARK_EXECUTION"
            ),
            "decision": "PENDING_HUMAN_AUTHORIZATION",
            "authorization_state": (
                "PROPOSED_AWAITING_HUMAN_AUTHORITY"
            ),
            "single_use": True,
            "issued_by_or_role_reference": None,
            "authority_basis": None,
            "issued_at": None,
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
            "executor_id": eligibility.executor_id,
            "executor_version": eligibility.executor_version,
            "executor_reference": eligibility.executor_reference,
            "contract_fingerprint": (
                eligibility.contract_fingerprint
            ),
            "canonical_execution_performed": False,
            "canonical_result_recorded": False,
            "authority_guard": (
                "AI_PROPOSAL_ONLY_HUMAN_AUTHORITY_REQUIRED"
            ),
            "notes": [
                (
                    "This proposal is not an execution "
                    "authorization artifact."
                ),
                (
                    "A human authority must supply an authorization "
                    "identifier, matching execution intent, role "
                    "reference, authority basis, decision, and issued_at."
                ),
            ],
        }
