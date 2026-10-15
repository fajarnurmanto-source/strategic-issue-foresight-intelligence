# SIFI S1.7R — Local Brief Export Audit v0.1

Status: PASS — LOCAL MARKDOWN BRIEF EXPORT VERIFIED

Evidence-linked briefs can now be exported from the dashboard as readable
Markdown while remaining inside `D:\Research\SIFI\runtime\exports`. The
filename is derived from a SHA-256 digest of the brief ID, not from raw user
input, and the exporter performs an atomic temporary-file replacement inside
the confined export directory.

The exported document includes issue and brief identities, timestamps,
analytical cutoff, summary, evidence references, `analytical_only=true`, and
`production_action_authorized=false`. The export endpoint uses the same
loopback, same-origin, JSON-size, and unpredictable-token controls as the
synthetic workflow writes.

Eleven focused export/path/workflow tests passed. Browser verification opened
`DEMO-BRIEF-001`, exported it, and displayed the relative path
`runtime\exports\brief-d92f51cbd708fb6b.md`. The complete Windows suite then
passed 350 tests, with database integrity `OK`, schema version 1, and a PASS
release check.

This is a local analytical-output feature, not permission to distribute
source material or a claim of production or commercial readiness.
