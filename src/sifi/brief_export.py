from __future__ import annotations

import hashlib
import os
from pathlib import Path

from sifi.local import LocalSIFIPaths
from sifi.workflow import LocalWorkflowRuntime


class BriefExportError(ValueError):
    pass


def export_brief_markdown(root: str | Path, brief_id: str) -> Path:
    identifier = brief_id.strip()
    if not identifier or len(identifier) > 128:
        raise BriefExportError("brief_id must contain 1 to 128 characters")
    paths = LocalSIFIPaths.from_root(root)
    paths.initialize()
    if not paths.database.is_file():
        raise BriefExportError("local database is not initialized")
    with LocalWorkflowRuntime(paths.database, workspace_root=paths.root) as runtime:
        brief = runtime.latest_brief(identifier)
    if brief is None:
        raise BriefExportError("brief not found")
    digest = hashlib.sha256(identifier.encode("utf-8")).hexdigest()[:16]
    destination = paths.require_inside(paths.exports / f"brief-{digest}.md")
    temporary = paths.require_inside(paths.exports / f".brief-{digest}.tmp")
    evidence = "\n".join(f"- `{item}`" for item in brief.evidence_refs)
    content = (
        f"# SIFI Analytical Brief — {brief.brief_id}\n\n"
        f"**Issue:** `{brief.issue_id}`  \n"
        f"**Created:** {brief.created_at.isoformat()}  \n"
        f"**Analytical cutoff:** {brief.analytical_cutoff.isoformat()}  \n"
        f"**Analytical only:** `{str(brief.analytical_only).lower()}`  \n"
        f"**Production action authorized:** "
        f"`{str(brief.production_action_authorized).lower()}`\n\n"
        f"## Summary\n\n{brief.summary}\n\n"
        f"## Evidence references\n\n{evidence}\n\n"
        "---\nGenerated locally by SIFI. This document does not authorize production action.\n"
    )
    temporary.write_text(content, encoding="utf-8")
    os.replace(temporary, destination)
    return destination
