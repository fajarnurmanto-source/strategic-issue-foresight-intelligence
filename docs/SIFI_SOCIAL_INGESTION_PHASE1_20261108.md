# SIFI — Social-first ingestion / phase 1 (8 Nov 2026)

## Mandatory product direction

Social platforms are a **first-class attention and public-perception sensor**. Monitoring may discover and triage individual public posts automatically. Operator review applies to exceptions, material decisions, contested claims and publication of conclusions, **not every collected mention**. A post, a like, an independent origin, an actual event, prevalence, and impact severity are different units. Never convert one into another.

Operational UX benchmark: Brand24 organizes keywords inside one monitoring project, then surfaces mentions, required/excluded terms and alerts. SIFI must reuse the configured monitoring object, keywords, aliases and exclusions, support a single operator action, show coverage/limitations and automate routine sorting. Do not add a second per-post approval workflow.

## E4-SOC1 delivered source slice

- Native Laravel one-shot public Bluesky AppView search by an **existing configured monitoring-object term**.
- Exactly one public API GET per run, latest-first, maximum ten posts, response cap 256 KiB, no pagination/redirect/retry, DNS-pinned HTTPS, 18 s timeout.
- Local budget at most 3 requests/day separated by 180 seconds, opt-in disabled by default, local/testing + isolated SQLite only; no credentials or paid services.
- Deduplicate by canonical post URL; store link, source, declared posting time, derived Indonesian topic hints, a text fingerprint, and an engagement snapshot. **Do not retain full post text, images, video, author profile or user credentials.**
- Existing SIFI research history and owner-scoped shadow connector-observation index receive the receipt; evidence/claim/production admission remain absent.
- Social coverage in Superadmin Integration Hub transparently lists Bluesky, Instagram, TikTok, X, YouTube and Mastodon, without presenting missing connectors as connected.

No live request, enablement, background job, restart, migrations, paid provider call or production change occurs when source code is copied.

## Isolated operator activation (after source-only bridge and focused checks)

1. Use an **isolated copy of the SIFI database** at an approved SQLite path under `/mnt/d/Research/SIFI/05_VALIDATION/`. Do not point this collector at production.
2. Configure `SIFI_SOCIAL_BLUESKY_PUBLIC_SEARCH=true`, `SIFI_SOCIAL_CONTACT_EMAIL=<real maintainer email>`, and `SIFI_IH03_SHADOW_INGEST=true` in the isolated local runtime. Do not commit .env.
3. Use an already verified owner and configured monitoring object. Run once:

   ```bash
   php artisan sifi:social-bluesky --owner=<owner-id> --object=<object-uuid> --query='sampah'
   ```

   The optional query must equal that object's name, a keyword, or an alias. When omitted, SIFI uses its first configured keyword or alias. No independent per-post review is necessary.
4. Review the created receipt in the existing research/observations screen. Platform engagement is displayed as a **snapshot, not an event count**. If the provider returns 429 or changes access, the run fails closed without automatic retry.
5. For safety, the isolated source authorization expires at the reviewed date in `config/sifi_social.php`; source-policy review is source-level and periodic, not per mention.

## Next capabilities, by value and available rights

1. One-click **Social Watch** control in the monitoring object's existing UI, default keyword bundle + exclusions, one-screen status, without CLI handoff.
2. Official Instagram/Facebook integration via available professional-account permissions, then YouTube searches/comments. Show exact accessible scopes and quotas, never imply all public content is available.
3. X and TikTok only when permitted developer/research access and zero-spend constraints are satisfied; report missing coverage instead of scraping private endpoints or bypassing anti-bot controls.
4. Mastodon public-tag connector conditioned by each instance's access policy.
5. Incremental deduplication across runs, burst/velocity by observation timestamps, inferred topic clusters and watch conditions, and optional scheduling within the configured rate budget. Distinguish same-post repost metrics from independently observed origins.

## Non-goals and limits

This phase does not claim cross-platform completeness, Instagram/TikTok/X coverage, representative sampling of public opinion, or factual validation of allegations. It does not fetch whole feeds or arbitrary accounts, bypass authentication, or train models on user posts. Full commercial/production admission remains denied.

References: Bluesky API / public AppView (https://docs.bsky.app/docs/api/app-bsky-feed-search-posts); Brand24 project setup (https://help.brand24.com/en/articles/3392669-how-do-i-create-my-first-project); Brand24 mention relevance (https://help.brand24.com/en/articles/10021500-how-to-prevent-non-relevant-mentions).


## Live access finding, 9 Nov 2026 — action on observed HTTP 403

The first operator-run isolated pilot **did reach a provider**. It used a new SQLite file in `05_VALIDATION`, ran isolated migrations, made exactly one public search request, and received **HTTP 403** with zero accessible post links. These zero results **do not imply an absence of posts or issues**. SIFI's previous `VALIDATION_OR_HTTP_FAILURE` classification was too generic; HTTP 403 is now represented as `PUBLIC_SEARCH_ACCESS_RESTRICTED` and `RESTRICTED`.

Official Bluesky maintainers have documented deliberate public search 403/load-shedding. **Do not** bypass that policy with case-shifted XRPC paths, alternate unprotected front doors, IP rotation, or automated retries. Do not claim generic unauthenticated keyword search coverage. Keep the existing search source opt-in default OFF; no new dispatch automatically occurs with this patch.

**Next direct-data path:** the official Jetstream v2 **live** WebSocket at `wss://jetstream.us-east.bsky.network/xrpc/network.bsky.jetstream.subscribeEvents`, filtered by `collections=app.bsky.feed.post&kinds=commit`. Jetstream v2's live tail is unauthenticated, and streams published-post records as JSON; it has no server-side keyword filter, so bounded clients may match configured keywords locally. Do not misrepresent a 25-second global-window sample as exhaustive search, representative sentiment, or event prevalence. Historical replay/snapshot HTTP requires a provider API key and is not part of this zero-spend pilot.

The source-only operator diagnostic `scripts/bridge/sifi-jetstream-live-probe.php` uses a single 25-second-or-less authenticated-TLS WebSocket handshake with strict frame, byte and event caps. It stores no user content, uses no DB, and starts no background watcher. Run from WSL after pulling the updated snapshot:

```bash
php -l "$SNAPSHOT/scripts/bridge/sifi-jetstream-live-probe.php" &&
php "$SNAPSHOT/scripts/bridge/sifi-jetstream-live-probe.php" sampah
```

If Jetstream is reachable, proceed to a Laravel owner-scoped, one-click ingestion adapter only after a bounded real sample confirms the protocol. Preserve create/update/delete and account-deletion semantics before establishing a durable historical index.

Sources: Bluesky maintainer 403 explanation (https://github.com/bluesky-social/bsky-docs/issues/332); official Jetstream access and filtering (https://bsky.network/docs/jetstream/); Jetstream replay authentication (https://bsky.network/docs/jetstream-replay/).
