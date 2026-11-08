# SIFI Social Watch — source-only scheduled rotation (2026-11-08)

## Operator outcome

A configured monitoring object no longer requires pressing **Pantau isu sosial sekarang** for every scan. An eight-hour Laravel schedule tick may select one configured object belonging to one explicitly opted-in verified owner, choosing the object whose Bluesky Social Watch receipt is oldest (including scans returning zero posts). Its result appears in the existing Research/History UI with first-local-sighting and repeat labels.

There are **no per-publication approval steps**, new database tables, paid providers, automatic logins, or production activation.

## One-time local configuration (initially OFF)

Only on a specifically isolated local SIFI instance whose SQLite file lives in `/mnt/d/Research/SIFI/05_VALIDATION/`, a maintainer may configure:

```dotenv
SIFI_SOCIAL_BLUESKY_PUBLIC_SEARCH=true
SIFI_SOCIAL_CONTACT_EMAIL=maintainer@your-real-domain.example
SIFI_IH03_SHADOW_INGEST=true
SIFI_SOCIAL_AUTOWATCH_ENABLED=true
SIFI_SOCIAL_AUTOWATCH_OWNER_ID=YOUR_VERIFIED_LOCAL_USER_ID
```

These are **examples**, not active credentials or a claim that source access was approved. Use a real contact email and locally valid existing user ID. Keep the existing gate and review expiry valid; do not put secrets into GitHub. Configuration values and cached configuration may need a local refresh.

The Laravel scheduler must actually be running (for example `php artisan schedule:work` on a local development instance). A schedule definition alone does **not** start a process. Only one attempted collection per eight-hour tick; provider/local quota can still hold a scan.

## Bounded behavior

- Bluesky official public AppView search only; at most 10 public post references per query.
- At most 3 provider requests/day and minimum 180 seconds spacing by existing source budget.
- At most 30 candidate objects for the explicitly configured owner; stale object/query or missing owner results in fail-closed status.
- Round-robin chooses the least recently scanned eligible object based on owner-bound research receipt audit, **not** only items returned. Zero-result scans are included, preventing starvation.
- No live API calls during source merge, build, default-off tests or installation.
- Metadata is still an **attention sensor**; even repeated links do not establish independent origin, event frequency, public prevalence, severity, or causality.
- `PRODUCTION_ADMISSION=DENIED`. Disable immediately by setting `SIFI_SOCIAL_AUTOWATCH_ENABLED=false`.

## Local acceptance

After source bridge apply, one narrow test plus one Vite build:

```bash
php artisan test --filter='SocialWatchTickTest|ReviewedBlueskySearchTest'
npm run build
```

Tests use an in-process fake response; a successful test is **not evidence of real network harvesting**.

## Future

Official YouTube integration remains next, subject to YouTube API permission, quota, non-authorized API-data retention and restrictions on cross-channel aggregation/derived metrics. Do not silently present YouTube API data as an unrestricted cross-platform intelligence dataset.
