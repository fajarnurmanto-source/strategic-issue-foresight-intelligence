# SIFI development quality gate

## Standing owner authorization

```text
LOCAL_DEVELOPMENT_AUTHORIZATION=GRANTED
SAFE_LOCAL_INSTALLATION=PREAUTHORIZED
AUTOMATED_TESTING=PREAUTHORIZED
REVERSIBLE_LOCAL_CODE_CHANGES=PREAUTHORIZED
PRODUCTION_ADMISSION=DENIED
REAL_EVIDENCE_ADMISSION=DENIED
DESTRUCTIVE_OPERATIONS=SEPARATE_APPROVAL_REQUIRED
```

Install local candidates automatically after the relevant staging QA passes, the exact scope and checksums match, source drift is excluded, and rollback is verified. A new checksum within those boundaries does not require renewed owner approval. Keep operational databases, authorization, production services, and external providers outside these installations. Stop on data-loss risk, conflicting source, or unmet environment permissions. Seek separate approval for public deployment, paid services, production activation, real evidence admission, destructive operations, or risky remote changes. Preserve all required environment permission checks.

Use automated development and integration testing. Request owner testing only when the product is READY_FOR_PRODUCT_ACCEPTANCE_TESTING. That status requires a working core journey, no known critical defects, passing automated security and desktop/mobile checks, repeatable installation and verified recovery, user documentation, and a prepared product acceptance scenario. Product acceptance does not authorize production activation.

Reuse Laravel tests, Playwright, axe-core, visual comparisons, TypeScript and build checks. Add tooling only for a documented capability gap after checking compatibility, license, security, maintenance and dependency cost. Stop near 2% of the five-hour quota only when remaining quota can be verified. Never estimate an unavailable quota.

Use the canonical Laravel checkout and one reusable disposable QA environment. Batch changes by product outcome. Run a focused check for low risk, targeted regression for medium risk, and integration plus relevant security tests for high risk. Run the release gate for frontend milestones. Reuse applicable evidence when its inputs are unchanged.

Before accepting a frontend release, require Laravel regression, TypeScript, a frontend build, automated browser journeys on desktop and mobile, accessibility, visual comparison against reviewed screenshots, source integrity, and relevant authorization checks. Record the reviewed candidate and evidence. A missing, failing, or stale critical check holds release acceptance. A successful build alone does not establish UI acceptance.

The current Core V2 entry point is `D:/Research/SIFI/05_VALIDATION/SIFI_CORE_V2_AUTOMATED_ANALYST_QA/Run-Gate.ps1`. This first profile pins the installed Core V2 scope and its matching disposable stage. For a successor batch, bind the gate to the new candidate while retaining a separate before/after fingerprint of the active baseline. Reuse the runner, assertions, fixture contracts and reviewed screenshots. A visual baseline changes only after explicit review of an intended UI change. Normal release runs do not regenerate baselines.

Real stored E2E sessions are read through query-only connections. Authentication and writes under test use disposable accounts and databases. Respect provider, evidence and lifecycle boundaries. Automated accessibility and journey tests cover what they can observe, with human UX judgment tracked separately.

Keep the canonical source, active database, WAL, rollback and approved artifacts intact. Reclaim only proven regenerable caches in scope. Historical package removal requires one reviewed batch with absolute paths, size, consumers and recovery evidence.

## Mandatory Product Development

SIFI-CBM-01 and SIFI-EBPD-01 are mandatory.

Canonical mandate document:
00_CONTROL/SIFI_CBM01_EBPD01_MANDATE_20261106.md

All future development must follow these directives.
Existing standing authorization and production restrictions remain effective.
