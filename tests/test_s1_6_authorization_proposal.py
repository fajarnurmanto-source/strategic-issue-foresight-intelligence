from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from sifi.benchmarks.authorization_proposal import (
    AuthorizationProposalBuilder,
)
from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.semantic_authorization import (
    SemanticAuthorizationError,
    SemanticAuthorizationIssuer,
)


ROOT = Path(__file__).resolve().parents[1]


class AuthorizationProposalTests(unittest.TestCase):
    def setUp(self):
        self.harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        self.issuer = SemanticAuthorizationIssuer(ROOT, self.harness)
        self.builder = AuthorizationProposalBuilder(self.issuer)

    def test_001_initial_batch_proposals_are_bound_but_not_executable(self):
        for case_id in ("CB01", "CB07", "CB20"):
            proposal = self.builder.build(case_id)

            self.assertEqual(proposal.case_id, case_id)
            self.assertFalse(proposal.executable)
            self.assertEqual(
                proposal.proposal_state,
                "PROPOSED_AWAITING_HUMAN_AUTHORITY",
            )
            self.assertEqual(
                proposal.payload["decision"],
                "PENDING_HUMAN_AUTHORIZATION",
            )

    def test_002_human_authority_fields_are_intentionally_empty(self):
        payload = self.builder.build("CB01").payload

        for field in (
            "authorization_id",
            "execution_intent_id",
            "issued_by_or_role_reference",
            "authority_basis",
            "issued_at",
        ):
            self.assertIsNone(payload[field])

    def test_003_proposal_preserves_zero_execution_claims(self):
        payload = self.builder.build("CB01").payload

        self.assertFalse(payload["canonical_execution_performed"])
        self.assertFalse(payload["canonical_result_recorded"])
        self.assertTrue(payload["single_use"])

    def test_004_semantic_issuer_rejects_proposal_as_authorization(self):
        payload = self.builder.build("CB01").payload

        with tempfile.NamedTemporaryFile(
            mode="w",
            suffix=".json",
            dir=ROOT,
            encoding="utf-8",
            delete=False,
        ) as handle:
            json.dump(payload, handle, sort_keys=True)
            path = Path(handle.name)

        try:
            reference = path.relative_to(ROOT).as_posix()
            with self.assertRaises(SemanticAuthorizationError):
                self.issuer.issue("CB01", reference)
        finally:
            path.unlink(missing_ok=True)


if __name__ == "__main__":
    unittest.main()
