# SIFI — Google fallback / Indonesian public search trends (9 Nov 2026)

## Product outcome

SIFI gains an **explicit, bounded Google Trends Trending Now Indonesia RSS source** for metadata-only public search-attention research. This is a Google-origin data stream, **not** Google web search, an index of all news, news-article full text, social media, a representation of public opinion or evidence of an actual event. RSS allows at most ten derived trend terms per run; feed terms unrelated to the configured monitoring object are retained as `NOT_MATCHED_TO_CURRENT_WATCH`, never falsely matched to its keyword.

## First-class Laravel integration

- `GoogleTrendsRssTransport`: one official public RSS GET `https://trends.google.com/trending/rss?geo=ID`, HTTPS, allowlisted host, public IPv4 pin, no redirects, proxy, retries or auth, bounded 512 KiB, time-limited, rejects non-XML and unexpected encoding.
- `GoogleTrendsRssParser`: no DTD/external entities, max 100 incoming RSS items inspected, max 10 distinct terms retained, stable Google Explore links, text/volume labels only, no article or RSS body persisted. Parser and outbound transport receive **no monitoring names, keywords, aliases, or other owner-specific input**; local receipt service matches terms only after the one fixed feed has been downloaded.
- `ReviewedGoogleTrends`: requires verified owner, owned configured monitoring object and unchanged revision; automatically identifies potential keyword relevance without per-item human approval; stores owner-scoped research receipt, summary, and isolated shadow observations; no evidence admission.
- `sifi:google-trends` manual command available for existing owner/object in an *individually approved isolated database only*, source opt-in default OFF. No auto-watch or background scheduling.
- `ProviderInventory` and Integration Hub display real Google-source states. Google Custom Search JSON API closed to new customers; Google News RSS remains rights-review-only, not an activated fallback.

## One-command real-source isolated pilot (WSL)

After user `git pull` plus `sifi-apply-source-delta.sh --apply` (source-only), from WSL:

```bash
SNAPSHOT="/mnt/d/Research/SIFI/05_VALIDATION/SIFI_GITHUB_SOURCE_SYNC_20261108_134500"
bash "$SNAPSHOT/scripts/bridge/sifi-google-trends-isolated-pilot.sh"
```

The wrapper creates a fresh SQLite file `D:\Research\SIFI\05_VALIDATION\SIFI_GOOGLE_TRENDS_LIVE_*.sqlite` and separate storage; refuses to run if live Laravel config is cached; bootstrap verifies exact file and storage paths; only the **new isolated file** receives migrations, owner/object fixtures, owner-scoped research receipt and observations. Normal application SQLite and `.env` are not modified. Main application runtime is not restarted.

Expected output: `DB_SCOPE=ISOLATED_VALIDATION`, `GOOGLE_SOURCE=TRENDING_NOW_RSS_ID`, one network request, feed and retained term counts, relevance candidates, shadow observation count, session ID, three sample Google Explore links, `RAW_RSS_PERSISTED=NO`, `EVIDENCE_ADMISSION=NONE`, `PRODUCTION_ADMISSION=DENIED`, and `SIFI_GOOGLE_PILOT=PASS` if live RSS returned at least one valid term. Any HTTP 403/429/other failure is classified as provider restricted/quota/unavailable. Never evade such controls.

## Interpretation and gaps

Google Trends measures an upturn in search activity, not the number of people searching, not independent reports, and not incidence/prevalence/severity. A nonmatching term is still useful as national context, but must **not** be passed off as an issue-specific observation. One RSS export does not guarantee continuous availability or issue coverage; a true always-on fallback needs a licensed/authorized news or web-discovery provider, retry/circuit breaker rules, source health status, and a broader no-cost multi-source portfolio.

Before commercial production, perform explicit reuse/terms assessment for Google Trends RSS, release the deployment/ops gates, and ensure non-automated/automatic frequency is permitted. No scraping Google Search pages, Google News article bodies or protected social content; no proof of production rights from free anonymous access alone.

Official documentation:
- Google Trending Now RSS export: https://support.google.com/trends/answer/3076011?hl=id
- Google Custom Search JSON API closure: https://developers.google.com/custom-search/v1/overview
