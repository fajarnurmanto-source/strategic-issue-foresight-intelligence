# SIFI S1.7E — Local Path Boundary Audit v0.1

Status: PASS — RUNTIME CONFINED TO SIFI WORKSPACE

SIFI now defines canonical local runtime paths below
`D:\Research\SIFI\runtime` for data, logs, backups, configuration, and the
SQLite database. When the workspace boundary is supplied, the workflow
runtime rejects a database outside the SIFI workspace before creating it.

Focused tests: 5 PASS. Full regression: 313 PASS.

The runtime directory is intentionally ignored by Git. Source code,
configuration templates, scripts, tests, and operational documentation remain
versioned in the SIFI repository; generated local data and secrets do not.

This boundary does not authorize production ingestion or deployment. The
next S1.7E slice is local domain orchestration across Issue, admitted evidence,
review, workflow session, and evidence-linked brief output.
