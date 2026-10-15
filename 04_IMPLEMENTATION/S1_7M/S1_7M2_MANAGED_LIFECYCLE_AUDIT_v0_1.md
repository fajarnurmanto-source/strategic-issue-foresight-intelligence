# SIFI S1.7M — Managed Windows Lifecycle Audit v0.1

Status: PASS — MANAGED WINDOWS START/STOP VERIFIED

SIFI now supports a background Windows-local lifecycle:

- `scripts/start-sifi.ps1` initializes local prerequisites when needed, can
  seed the synthetic demo, starts the server in a hidden window, waits for an
  `OK` loopback health response, writes local process state, and optionally
  opens the local dashboard;
- `scripts/stop-sifi.ps1` verifies the recorded executable and SIFI command
  line before stopping the process, then removes the local state file;
- stdout and stderr remain in `runtime\logs`, while PID, port, root, and start
  time remain in `runtime\config`.

Actual Windows verification used loopback port 8891. Start returned a managed
PID, health reported `OK` and `local_only=true`, stop returned `STOPPED`, and
the PID state was removed. The subsequent full Windows release verification
passed all 337 tests and all release checks.

The foreground `start-local.ps1` path remains available for diagnostics. This
lifecycle improvement does not alter the frozen S1.7 maturity boundary or
authorize production operation.
