from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from datetime import datetime
from enum import Enum
from pathlib import Path

from sifi.domain.versioning import VersionedSnapshot, require_aware_datetime
from sifi.local import LocalSIFIPaths
from sifi.persistence import SQLiteVersionStore

from .adjudication import CB22Result
from .gate import cb22_gate_status


VOTE_OBJECT_TYPE = "CB22_ADJUDICATION_VOTE"


class AdjudicationRole(str, Enum):
    PRIMARY_ADJUDICATOR = "PRIMARY_ADJUDICATOR"
    INDEPENDENT_CHECKER = "INDEPENDENT_CHECKER"


@dataclass(frozen=True, slots=True)
class AdjudicationVote:
    bundle_sha256: str
    role: AdjudicationRole
    staff_code: str
    decision: CB22Result
    reviewed_at: datetime
    conflict_free: bool


@dataclass(frozen=True, slots=True)
class AdjudicationReceipt:
    bundle_sha256: str
    automated_result: CB22Result
    votes: tuple[AdjudicationVote, ...]
    final_result: CB22Result | None
    claim_allowed: bool
    status: str


class CB22AdjudicationRuntime:
    def __init__(self, root: str | Path) -> None:
        self.paths = LocalSIFIPaths.from_root(root)
        self.paths.initialize()
        self._store = SQLiteVersionStore(self.paths.database)

    def __enter__(self) -> "CB22AdjudicationRuntime":
        return self

    def __exit__(self, *_args) -> None:
        self._store.close()

    def record_vote(
        self, *, bundle_path: str | Path, role: AdjudicationRole,
        staff_code: str, decision: CB22Result, reviewed_at: datetime,
        conflict_free: bool,
    ) -> AdjudicationReceipt:
        require_aware_datetime(reviewed_at, "reviewed_at")
        gate = cb22_gate_status(self.paths.root)
        if not gate.ready_for_counted_sessions:
            raise ValueError("adjudication gate is closed: " + ", ".join(gate.reasons))
        if not conflict_free:
            raise ValueError("conflicted adjudicator cannot record a vote")
        bundle, digest = self._load_bundle(bundle_path)
        automated = CB22Result(bundle["assessment"]["result"])
        if automated not in {CB22Result.PASS, CB22Result.FAIL, CB22Result.INCONCLUSIVE}:
            raise ValueError("bundle has no adjudicable automated result")
        if decision not in {automated, CB22Result.INCONCLUSIVE}:
            raise ValueError("vote may confirm automated result or mark it inconclusive")
        config = json.loads((self.paths.config / "cb22-execution-gate.json").read_text(encoding="utf-8"))
        expected = (
            config["primary_adjudicator_staff_code"]
            if role is AdjudicationRole.PRIMARY_ADJUDICATOR
            else config["independent_checker_staff_code"]
        )
        if staff_code != expected:
            raise ValueError("staff code is not assigned to this adjudication role")
        object_id = f"{digest}:{role.value}"
        if self._store.latest(VOTE_OBJECT_TYPE, object_id) is not None:
            raise ValueError("adjudication vote is immutable and already exists")
        self._store.append(VersionedSnapshot(
            object_type=VOTE_OBJECT_TYPE, object_id=object_id, version=1,
            effective_at=reviewed_at, recorded_at=reviewed_at,
            payload={
                "bundle_sha256": digest, "role": role.value,
                "staff_code": staff_code, "decision": decision.value,
                "reviewed_at": reviewed_at.isoformat(), "conflict_free": True,
            },
        ))
        return self.receipt(bundle_path)

    def receipt(self, bundle_path: str | Path) -> AdjudicationReceipt:
        bundle, digest = self._load_bundle(bundle_path)
        automated = CB22Result(bundle["assessment"]["result"])
        votes = tuple(
            self._decode(snapshot.payload)
            for role in AdjudicationRole
            if (snapshot := self._store.latest(VOTE_OBJECT_TYPE, f"{digest}:{role.value}")) is not None
        )
        final = None
        status = "PENDING_TWO_ROLE_REVIEW"
        if len(votes) == 2:
            final = votes[0].decision if votes[0].decision == votes[1].decision else CB22Result.INCONCLUSIVE
            status = "FINAL"
        return AdjudicationReceipt(
            bundle_sha256=digest, automated_result=automated, votes=votes,
            final_result=final,
            claim_allowed=final is CB22Result.PASS,
            status=status,
        )

    def export_final_receipt(self, bundle_path: str | Path) -> Path:
        receipt = self.receipt(bundle_path)
        if receipt.status != "FINAL" or receipt.final_result is None:
            raise ValueError("two-role adjudication is not final")
        destination = self.paths.require_inside(
            self.paths.exports / f"cb22-adjudication-receipt-{receipt.bundle_sha256[:12]}.json"
        )
        payload = {
            "record_type": "SIFI_CB22_FINAL_ADJUDICATION_RECEIPT",
            "record_version": "1.0",
            "bundle_sha256": receipt.bundle_sha256,
            "automated_result": receipt.automated_result.value,
            "final_result": receipt.final_result.value,
            "claim_allowed": receipt.claim_allowed,
            "status": receipt.status,
            "votes": [
                {"role": vote.role.value, "staff_code": vote.staff_code,
                 "decision": vote.decision.value,
                 "reviewed_at": vote.reviewed_at.isoformat(),
                 "conflict_free": vote.conflict_free}
                for vote in receipt.votes
            ],
        }
        destination.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
        return destination

    def _load_bundle(self, bundle_path: str | Path) -> tuple[dict, str]:
        path = self.paths.require_inside(bundle_path)
        if not path.is_file() or not path.is_relative_to(self.paths.exports):
            raise ValueError("adjudication bundle must be an exported SIFI file")
        content = path.read_bytes()
        try:
            payload = json.loads(content)
        except (UnicodeDecodeError, json.JSONDecodeError) as exc:
            raise ValueError("adjudication bundle is invalid JSON") from exc
        if payload.get("record_type") != "SIFI_CB22_ADJUDICATION_BUNDLE":
            raise ValueError("file is not a CB22 adjudication bundle")
        return payload, hashlib.sha256(content).hexdigest().upper()

    @staticmethod
    def _decode(payload) -> AdjudicationVote:
        return AdjudicationVote(
            bundle_sha256=payload["bundle_sha256"],
            role=AdjudicationRole(payload["role"]), staff_code=payload["staff_code"],
            decision=CB22Result(payload["decision"]),
            reviewed_at=datetime.fromisoformat(payload["reviewed_at"]),
            conflict_free=payload["conflict_free"],
        )
