# SIFI — Merge-first local module installation (effective IH03H)

For subsequent SIFI module installers, prefer an additive, scope-limited patch
against the *current* canonical checkout. Do not require an obsolete whole-file
checksum for files that other authorized work has legitimately modified.

1. Read the current exact target bytes and record their SHA-256 at preflight.
2. Apply an anchored, idempotent patch to a disposable staging copy, preserving
   the rest of each target byte-for-byte. Never replace a shared page or route
   file with a historical template.
3. Check source identity again immediately before promotion. Stop on concurrent
   edits or unresolved semantic conflicts; do not silently overwrite them.
4. Keep newly added files fail-closed on name collisions; if bytes are identical
   treat an earlier installation as already complete.
5. Run the smallest relevant regression and, for UI edits, a staging frontend
   build. If an optional UI anchor is incompatible, defer only that UI link and
   still install an independently valid backend route.
6. Record originals in 05_VALIDATION, promote atomically, and automatically
   restore touched files if partial promotion fails.
7. Do not mutate operational databases or relax authorization, rights, external
   provider quotas, evidence admission, or production-admission restrictions.

This is a *future installer engineering policy*, not permission to bypass a
legitimate failing test, consent gate, semantic conflict, or security check.
