from __future__ import annotations

import hashlib
import json
import re
from dataclasses import dataclass
from pathlib import Path

from sifi.local import LocalSIFIPaths

from .model import CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION


TASK_PACKAGE_SHA256 = "6371EF15A5431809C1111B2E4E2DCA9237A0EB585F7C9E9D3C5CDD4C7A845FE1"
PROTOCOL_SHA256 = "27758189FE7520D6209C1AB213126614F2EA38B7B062DEDADA3A3D302045D197"
OBSERVATION_SCHEMA_SHA256 = "972E82B6B921C6451728CC065B2D84F7304D70B91D028E485FF91E0682F3275A"
_STAFF_CODE = re.compile(r"^[A-Z0-9][A-Z0-9_-]{2,63}$")


@dataclass(frozen=True, slots=True)
class CB22GateStatus:
    ready_for_counted_sessions: bool
    reasons: tuple[str, ...]


def cb22_gate_status(root: str | Path) -> CB22GateStatus:
    paths = LocalSIFIPaths.from_root(root)
    reasons: list[str] = []
    task_path = paths.root / "05_VALIDATION" / "CB22_PREP" / "CB22_TASK_PACKAGE_v1_0.json"
    if not task_path.is_file() or hashlib.sha256(task_path.read_bytes()).hexdigest().upper() != TASK_PACKAGE_SHA256:
        reasons.append("TASK_PACKAGE_MISSING_OR_CHANGED")
    protocol_path = paths.root / "05_VALIDATION" / "CB22_PREP" / "CB22_7_FROZEN_PROTOCOL_v1_0.md"
    if not protocol_path.is_file() or hashlib.sha256(protocol_path.read_bytes()).hexdigest().upper() != PROTOCOL_SHA256:
        reasons.append("FROZEN_PROTOCOL_MISSING_OR_CHANGED")
    schema_path = paths.root / "05_VALIDATION" / "CB22_PREP" / "CB22_10_OBSERVATION_RECORD_SCHEMA_v1_0.json"
    if not schema_path.is_file() or hashlib.sha256(schema_path.read_bytes()).hexdigest().upper() != OBSERVATION_SCHEMA_SHA256:
        reasons.append("OBSERVATION_SCHEMA_MISSING_OR_CHANGED")
    config_path = paths.config / "cb22-execution-gate.json"
    if not config_path.is_file():
        reasons.append("LOCAL_GATE_CONFIGURATION_MISSING")
        return CB22GateStatus(False, tuple(reasons))
    try:
        config = json.loads(config_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        reasons.append("LOCAL_GATE_CONFIGURATION_INVALID")
        return CB22GateStatus(False, tuple(reasons))
    if config.get("protocol_version") != CB22_PROTOCOL_VERSION:
        reasons.append("PROTOCOL_VERSION_NOT_AUTHORIZED")
    if config.get("task_package_version") != CB22_TASK_PACKAGE_VERSION:
        reasons.append("TASK_PACKAGE_VERSION_NOT_AUTHORIZED")
    primary = config.get("primary_adjudicator_staff_code")
    checker = config.get("independent_checker_staff_code")
    if not isinstance(primary, str) or not _STAFF_CODE.fullmatch(primary):
        reasons.append("PRIMARY_ADJUDICATOR_UNASSIGNED")
    if not isinstance(checker, str) or not _STAFF_CODE.fullmatch(checker):
        reasons.append("INDEPENDENT_CHECKER_UNASSIGNED")
    if primary == checker and primary is not None:
        reasons.append("ADJUDICATORS_MUST_BE_DISTINCT")
    if config.get("conflict_review_complete") is not True:
        reasons.append("CONFLICT_REVIEW_INCOMPLETE")
    if config.get("external_review_requirement") not in {"NOT_REQUIRED_DOCUMENTED", "SATISFIED"}:
        reasons.append("EXTERNAL_REVIEW_REQUIREMENT_UNRESOLVED")
    return CB22GateStatus(not reasons, tuple(reasons))
