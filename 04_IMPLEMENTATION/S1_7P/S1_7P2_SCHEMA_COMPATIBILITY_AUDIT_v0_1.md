# SIFI S1.7P — Schema Compatibility and Restore Audit v0.1

Status: PASS — SCHEMA VERSION AND RESTORE COMPATIBILITY VERIFIED

The local SQLite store now records schema version 1 through SQLite
`user_version`. Existing version-0 local databases are upgraded on first
controlled open. Databases with a version newer than the running SIFI package
fail closed instead of being interpreted by older code.

Restore now requires all of the following before the active database changes:

- the source is a file inside `runtime\backups`;
- SQLite integrity is `ok`;
- the schema version is not newer than supported;
- the canonical `object_versions` table is present.

An unrelated but structurally valid SQLite database and a SIFI backup marked
with future schema version 999 were both rejected. Eight focused Windows tests
passed. The complete Windows suite then passed 346 tests, with database
integrity `OK`, schema version 1, and a PASS release check.

This protects local upgrade and recovery behavior. It does not alter the S1.7
maturity closure or any production, CB22, or commercial-readiness hold.
