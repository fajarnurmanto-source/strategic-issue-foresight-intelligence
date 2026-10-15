from __future__ import annotations

import hashlib
import json
import re
import os
from dataclasses import dataclass
from pathlib import Path
from tempfile import NamedTemporaryFile

from sifi.local import LocalSIFIPaths
from sifi.persistence import SQLiteVersionStore

from .model import CB22_PROTOCOL_VERSION, CB22_TASK_PACKAGE_VERSION


TASK_PACKAGE_SHA256 = "B222F121927292C802C7D8CF57939720BD628FBD0B964EEC595687E08FB1460B"
PROTOCOL_SHA256 = "27758189FE7520D6209C1AB213126614F2EA38B7B062DEDADA3A3D302045D197"
OBSERVATION_SCHEMA_SHA256 = "F4985C84AFBAF88E6408D211AE1797E5B5F629CBBB01BA7530C99FF86413C3E2"
_STAFF_CODE = re.compile(r"^[A-Z0-9][A-Z0-9_-]{2,63}$")
GATE_LOCK_OBJECT_TYPE = "CB22_EXECUTION_GATE_LOCK"
GATE_LOCK_OBJECT_ID = "CB22-v1.0"


@dataclass(frozen=True, slots=True)
class CB22GateStatus:
    ready_for_counted_sessions: bool
    reasons: tuple[str, ...]


def _controlled_configuration(config: dict) -> dict:
    controlled = {
        "protocol_version": config["protocol_version"],
        "task_package_version": config["task_package_version"],
        "primary_adjudicator_staff_code": config["primary_adjudicator_staff_code"],
        "independent_checker_staff_code": config["independent_checker_staff_code"],
        "conflict_review_complete": config["conflict_review_complete"],
        "external_review_requirement": config["external_review_requirement"],
        "protocol_sha256": PROTOCOL_SHA256,
        "task_package_sha256": TASK_PACKAGE_SHA256,
        "observation_schema_sha256": OBSERVATION_SCHEMA_SHA256,
    }
    controlled["configuration_sha256"] = hashlib.sha256(
        json.dumps(controlled, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest().upper()
    return controlled


def _canonical_json_sha256(path: Path) -> str:
    payload = json.loads(path.read_text(encoding="utf-8"))
    canonical = json.dumps(
        payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False
    ).encode("utf-8")
    return hashlib.sha256(canonical).hexdigest().upper()


def _normalized_text_sha256(path: Path) -> str:
    normalized = path.read_text(encoding="utf-8").replace("\r\n", "\n")
    return hashlib.sha256(normalized.encode("utf-8")).hexdigest().upper()


def _matches_json_hash(path: Path, expected: str) -> bool:
    try:
        return path.is_file() and _canonical_json_sha256(path) == expected
    except (OSError, UnicodeDecodeError, json.JSONDecodeError):
        return False


def _matches_text_hash(path: Path, expected: str) -> bool:
    try:
        return path.is_file() and _normalized_text_sha256(path) == expected
    except (OSError, UnicodeDecodeError):
        return False


def current_gate_configuration(root: str | Path) -> dict:
    paths = LocalSIFIPaths.from_root(root)
    status = cb22_gate_status(paths.root)
    if not status.ready_for_counted_sessions:
        raise ValueError("CB22 counted gate is not ready")
    config = json.loads((paths.config / "cb22-execution-gate.json").read_text(encoding="utf-8"))
    return _controlled_configuration(config)


def configure_cb22_gate(
    root: str | Path, *, primary_staff_code: str,
    checker_staff_code: str, external_review_requirement: str,
    conflict_review_complete: bool,
) -> Path:
    paths = LocalSIFIPaths.from_root(root)
    paths.initialize()
    if paths.database.is_file():
        with SQLiteVersionStore(paths.database) as store:
            if any(
                item.payload.get("mode") == "COUNTED"
                for item in store.list_latest("CB22_STUDY_OBSERVATION", limit=1000)
            ):
                raise ValueError(
                    "CB22 gate configuration is frozen after the first counted session"
                )
    primary = primary_staff_code.strip().upper()
    checker = checker_staff_code.strip().upper()
    if not _STAFF_CODE.fullmatch(primary) or not _STAFF_CODE.fullmatch(checker):
        raise ValueError("adjudicator codes must be 3-64 character pseudonymous codes")
    if primary == checker:
        raise ValueError("primary adjudicator and checker must be distinct")
    if not conflict_review_complete:
        raise ValueError("conflict review must be complete before configuration")
    review = external_review_requirement.strip().upper()
    if review not in {"SATISFIED", "NOT_REQUIRED_DOCUMENTED"}:
        raise ValueError("external review must be SATISFIED or NOT_REQUIRED_DOCUMENTED")
    payload = {
        "protocol_version": CB22_PROTOCOL_VERSION,
        "task_package_version": CB22_TASK_PACKAGE_VERSION,
        "primary_adjudicator_staff_code": primary,
        "independent_checker_staff_code": checker,
        "conflict_review_complete": True,
        "external_review_requirement": review,
    }
    target = paths.config / "cb22-execution-gate.json"
    with NamedTemporaryFile(
        mode="w", encoding="utf-8", dir=paths.config,
        prefix=".cb22-gate-", suffix=".tmp", delete=False,
    ) as handle:
        temporary = Path(handle.name)
        json.dump(payload, handle, indent=2)
        handle.write("\n"); handle.flush(); os.fsync(handle.fileno())
    temporary.replace(target)
    return target


def cb22_gate_status(root: str | Path) -> CB22GateStatus:
    paths = LocalSIFIPaths.from_root(root)
    reasons: list[str] = []
    task_path = paths.root / "05_VALIDATION" / "CB22_PREP" / "CB22_TASK_PACKAGE_v1_0.json"
    if not _matches_json_hash(task_path, TASK_PACKAGE_SHA256):
        reasons.append("TASK_PACKAGE_MISSING_OR_CHANGED")
    protocol_path = paths.root / "05_VALIDATION" / "CB22_PREP" / "CB22_7_FROZEN_PROTOCOL_v1_0.md"
    if not _matches_text_hash(protocol_path, PROTOCOL_SHA256):
        reasons.append("FROZEN_PROTOCOL_MISSING_OR_CHANGED")
    schema_path = paths.root / "05_VALIDATION" / "CB22_PREP" / "CB22_10_OBSERVATION_RECORD_SCHEMA_v1_0.json"
    if not _matches_json_hash(schema_path, OBSERVATION_SCHEMA_SHA256):
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
    if not reasons and paths.database.is_file():
        with SQLiteVersionStore(paths.database) as store:
            lock = store.latest(GATE_LOCK_OBJECT_TYPE, GATE_LOCK_OBJECT_ID)
        if (
            lock is not None
            and lock.payload.get("configuration_sha256")
            != _controlled_configuration(config)["configuration_sha256"]
        ):
            reasons.append("CURRENT_CONFIGURATION_DIFFERS_FROM_COUNTED_STUDY_LOCK")
    return CB22GateStatus(not reasons, tuple(reasons))
