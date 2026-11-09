# SIFI Source Connections — canonical product mandate (2026-11-09)

## Product outcome

A normal SIFI user needs **one compact Source Data page**, not an engineering inventory of disconnected providers.
The user selects sources by category, sees the real API method and access/cost conditions, authorizes an external provider account when the official API supports OAuth, and may then enable an approved bounded collector.
Provider choice, provider login, API entitlement, budget permission, and collector activation are independent states.

### UX contract

1. **Pilih sumber:** collapsed category → recognizable provider name → access label → account/API method → selection toggle.
2. **Hubungkan:** either public metadata (no login), developer API key configured for the workspace, or **Masuk dengan [Provider]** using the provider's own OAuth consent page.
3. **Periksa:** show granted scopes, account identity, dataset/endpoint capability, provider quota and timestamp of last successful test. Never claim all posts or unlimited historical search.
4. **Aktifkan:** only if a verified adapter, provider rights, application credentials, appropriate user consent, and budget guard are satisfied. User may pause or revoke at any time.
5. **Hasil:** provenance, publication/event timestamps, platform identity, rights, and coverage limitations; social signals are indicators of attention/perception, not population prevalence.

### Cost and permission presentation

- **Public/free metadata:** no paid subscription implied, but rate limits/terms apply.
- **Free quota or paid plan:** link to provider's official plan page; show quota, potential costs and last-verified timestamp. Do not hardcode an unverified price.
- **App approval / restricted research:** approval may be required even if no fee is charged. Do not incorrectly label it as paid.
- **API plan/usage charges:** **BYOK (bring your own key)** is the default; SIFI auto-spend stays OFF. The user must separately approve billing and any limits before a paid request.
- **Not yet connected:** selection can be saved as intent but cannot produce acquisition or "connected" status.

### API / authentication architecture

Use a provider adapter interface, with independent implementations per provider and a single SIFI orchestration layer:

- `capabilities(): array` — public/account/enterprise endpoints, time window, search scope, pagination, quota and rights.
- `connection(): array` — read-only user-specific status (unconnected / needs developer app / requires permission / connected / expired / revoked).
- `connect()` / `callback()` — allowed only for reviewed OAuth providers, using authorization code (prefer PKCE), state binding, exact redirect URI and the minimal scopes.
- `testConnection()` — one bounded request and sanitized receipt, never an open proxy for arbitrary URLs.
- `collect()` — uses a reviewed source-specific adapter, explicit purpose and quota guard, data minimization, attribution, provenance and retention.
- `disconnect()` — revoke provider authorization where supported; remove encrypted tokens locally.

Laravel-first reuse: assess `laravel/socialite` for Facebook, X, Google/YouTube and LinkedIn, and vetted provider adapters for others. Socialite is **not installed by this increment**. Every provider still needs registered developer credentials, allowed callback and approved API scope.

User-account OAuth is **distinct from SIFI login**. Do not receive Facebook/X/TikTok passwords in SIFI forms or offer fake login controls. Tokens and refresh tokens must be encrypted at rest and excluded from frontend props, logs, exception messages, commits, backups shared outside the owner's control, and downloaded reports. Enforce exact owner/workspace binding, CSRF/state/PKCE, HTTPS redirect in deployed environments, encryption-key rotation and revocation/expiry checks. A successful login proves account identity/consent only; **it does not grant access to all public posts, competitor posts or historical backfill**.

Superadmin manages **developer app credentials**, provider API URLs and policy; each user manages **their own authorization and selected sources**. Per-provider adapters enforce the real terms.

### Implementation state

- Present increment: `SourceModuleCatalog` exposes conservative `api_family`, `access_tier`, `auth_method` and explanatory warnings.
- New `sifi_user_source_selections` + audit: owned, persisted **selection intent**; no token, password, billing authorization or external provider request.
- Source & Coverage is refactored to a compact source picker. Selection requests reject unknown providers and client-supplied credentials.
- OAuth implementation, real token storage, provider-consent redirect/callback, per-platform API calls and collector activation are **NOT YET IMPLEMENTED**. Never display a working Login/Connect button until implemented and tested.
- Migration and UI are candidate-only, awaiting isolated Laravel/TypeScript/UI validation. Production and real-evidence admission remain DENIED.

### Acceptance tests

1. Normal user can select/deselect sources without collecting; choices scoped to that user.
2. Paid/restricted source selection never permits API billing, credential writes or collector activation.
3. Unknown provider, owner impersonation, API-key/password/billing fields in selection payload are rejected.
4. Every supported provider shows API family, cost/approval warning, auth method and rights capability.
5. When a provider OAuth adapter is eventually installed: invalid state, wrong callback, insufficient scope, expiry, revocation and owner mismatch must all fail closed.
6. Mobile/desktop UI remains compact, keyboard accessible, and does not imply that connected means exhaustive access.

Focused checks after isolated installation:
`php artisan test --filter='SourceModuleCatalogTest|SifiSourceCatalogWorkflowTest|E4PeriodDiscoveryTest'`
`npm run types:check`
`npm run build`

No live user login, API credentials, provider account, remote payment, migration or production activation is performed by writing this document.
