# SIFI IH03I — Owner-bound dynamic multi-source dashboard

- The existing authenticated, object-scoped `MultiSourceResearchController` passes
  history already authorized for the current owner and envelope-SHA verified by
  `MultiSourceResearchWorkspace::history()`.
- A reusable React component derives a read-only summary of the newest
  non-expired, reviewed `CROSSREF` and `ANTARA_RSS` receipts for that object.
- No new database table, query, write, provider call, grant, evidence admission,
  AI call, or operational database migration is introduced.
- The synthetic isolated IH03B/IH03E source data are not joined across accounts.
  Empty state is required until the logged-in account actually has both sources.
- Distinct publication URLs do not establish independent origins, event
  frequency, prevalence, severity, speed, or foresight.
- The UI is a local shadow feature pending desktop/mobile visual acceptance.
  It does not constitute production admission.

Installer: merge-first current React page and one new additive component;
preserve unrelated user changes and exact-SHA backup with rollback. This policy
is inherited from `docs/SIFI_MODULE_INSTALLER_MERGE_FIRST_POLICY.md`.
