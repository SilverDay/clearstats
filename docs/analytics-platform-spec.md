# Software Specification: ClearStats

**Status:** Draft v2
**Author:** Klaus-E. Klingner (SilverDay Media)
**Domain:** clearstats.de
**Stack:** LAMP (Linux, Apache, MariaDB, PHP 8.3, strict types, PDO/prepared statements, server-rendered templates)
**Model reference:** Plausible Analytics (architectural inspiration, not a fork)

---

## 1. Purpose and scope

A self-hosted, multi-site web analytics platform that provides pageview and basic engagement analytics without cookies, without persistent identifiers, and without storing personal data beyond a transient per-request hashing operation. Designed to track sites the operator owns end-to-end (no third-party client onboarding in scope for v1).

### 1.1 Goals
- Cookieless, no persistent client-side storage (no localStorage/sessionStorage identifiers either)
- GDPR-compliant by design: no personal data at rest, defensible DPIA position
- Multi-site: one central platform, N tracked sites, per-site dashboards and cross-site overview
- Lightweight client script (target: <2KB gzipped)
- LAMP-native, consistent with existing SilverDay tooling conventions (PDO, front-controller routing, no heavy framework)

### 1.2 Non-goals (v1)
- No cross-day visitor identification (new vs. returning) — same trade-off Plausible accepts, inherent to the privacy model
- No third-party/client site onboarding (self-service signup, billing, per-client DPAs) — assumes operator controls every tracked site
- No session replay, heatmaps, or form analytics
- No server-side (PHP-to-PHP) dispatch — this spec supersedes the earlier server-side-dispatch direction; see §9 for why v1 reverts to a JS-based client

---

## 2. Architecture overview

```
┌─────────────────┐        POST /api/event         ┌──────────────────────┐
│  Tracked Site A  │ ──────────────────────────────▶│                      │
│  <script> tag    │                                 │   Central Platform   │
└─────────────────┘                                 │                      │
                                                       │  ┌────────────────┐ │
┌─────────────────┐        POST /api/event           │  │ Ingestion API  │ │
│  Tracked Site B  │ ──────────────────────────────▶│  └───────┬────────┘ │
│  <script> tag    │                                 │          │          │
└─────────────────┘                                 │  ┌───────▼────────┐ │
                                                       │  │ Raw events tbl │ │
┌─────────────────┐        POST /api/event           │  └───────┬────────┘ │
│  Tracked Site N  │ ──────────────────────────────▶│          │          │
│  <script> tag    │                                 │  ┌───────▼────────┐ │
└─────────────────┘                                 │  │ Rollup cron job│ │
                                                       │  └───────┬────────┘ │
                                                       │          │          │
                                                       │  ┌───────▼────────┐ │
                                                       │  │ Rollup tables  │ │
                                                       │  └───────┬────────┘ │
                                                       │          │          │
                                                       │  ┌───────▼────────┐ │
                                                       │  │ Dashboard UI   │ │
                                                       │  └────────────────┘ │
                                                       └──────────────────────┘
```

The client script runs in the visitor's browser and sends one beacon per pageview (and optionally per custom event) directly to the central platform's ingestion API. No cookies, no client-side storage, no third-party requests beyond the beacon itself.

---

## 3. Visitor identification model

### 3.1 Method
On each incoming request to the ingestion API:

```
visitor_hash = HMAC-SHA256(
  key   = daily_salt,
  data  = site_domain || client_ip || user_agent
)
```

- `daily_salt`: a cryptographically random value, generated once per 24h period, shared across all sites (per operator decision — see §3.2), never persisted beyond its active window plus a short grace period for late-arriving requests spanning midnight.
- `client_ip`: source is **configurable per site** via an `ip_source` setting on the `sites` table:
  - `direct` — use the raw TCP connection's remote address (default, for sites not behind any proxy)
  - `x-forwarded-for` — use the left-most (or Nth-from-right, configurable) entry in `X-Forwarded-For`, honored only when the request's immediate connecting IP matches a configured trusted-proxy allowlist (prevents a client from spoofing the header directly against the ingestion API and injecting an arbitrary "client IP")
  - `cf-connecting-ip` — use Cloudflare's header, honored only when the connecting IP is in Cloudflare's published IP ranges
  This makes the setting per-tenant rather than a single global assumption, since different ClearStats-tracked sites may sit behind different (or no) proxying.
- `user_agent`: the raw UA string from the request header.
- **Neither `client_ip` nor `user_agent` is stored.** They exist only as function inputs for the duration of the request and are discarded immediately after hash computation.
- `site_domain` is included in the hash input specifically so the same physical visitor produces a different, uncorrelatable hash on each different tracked site, even under a shared salt.

### 3.2 Salt scope and rotation
- **Single shared salt across all sites** (confirmed decision), with domain baked into the hash input to prevent cross-site correlation.
- Rotation: every 24h, generated by a scheduled job on the central platform.
- Distribution: the ingestion API holds the current and previous salt in memory/cache (e.g., APCu or a small MariaDB table) so a request arriving just after rotation using the "old" logical day still hashes consistently within its own day's bucket — exact boundary handling to be defined in implementation (recommend: bucket by day using the salt's generation timestamp, not wall-clock at request time, to avoid split-second edge cases).
- The salt itself is never exposed to any tracked site or client script — it lives only on the central platform, since visitor-hash computation now happens server-side within the ingestion API (this is a deliberate architectural choice: the client script sends raw-request-implicit IP/UA — which the server already receives as part of the HTTP request — rather than the client computing the hash itself, which would require exposing the salt to the browser and defeat its purpose).

### 3.3 Consequence
This means, unlike the server-side-dispatch design discussed earlier, **the central platform's ingestion endpoint does, transiently, process raw IP and UA per request** (it has to — it's an HTTP server) before immediately discarding them post-hash. This is architecturally equivalent to what Plausible itself does, and is a defensible "no data at rest" position, but it is a different data-flow shape than the earlier server-side-dispatch proposal, where raw IP never left the origin site at all. See §9 for the explicit trade-off discussion.

---

## 4. Client tracking script

### 4.1 Delivery
Single static JS file served from the central platform (e.g., `https://analytics.example/js/track.js`), cacheable, versioned via filename or query param for cache-busting on updates.

### 4.2 Embed snippet
```html
<script defer src="https://analytics.example/js/track.js"
        data-site-id="abc123"></script>
```
- `data-site-id`: a public, non-secret per-site identifier (not a security boundary — see §7 for anti-spoofing).

### 4.3 Behavior
- On script load: fire one `pageview` event via `navigator.sendBeacon()` (fallback to `fetch(..., {keepalive: true})` for older browsers).
- Payload: `{ site_id, url, referrer, screen_width, event: "pageview" }` — deliberately minimal, no fingerprinting-adjacent data collection (no canvas, no font enumeration, no plugin lists).
- SPA support: expose a small `window.plausibleLike.trackPageview()` (naming placeholder) function tracked sites can call on route changes, for any of your sites that are client-rendered.
- Custom events (optional, v1.1): `window.plausibleLike.trackEvent(name, props)` for goal/conversion tracking.
- **No cookies, no localStorage, no sessionStorage** — script is fully stateless on the client.
- `navigator.doNotTrack` / `Sec-GPC` are **not honored** — decided: since no persistent user tracking occurs and no personal data is retained, these signals don't apply to ClearStats' data model. (Worth a one-line note in the DPIA explaining this reasoning explicitly, so it reads as a considered decision rather than an oversight if ever reviewed.)

---

## 5. Ingestion API

### 5.1 Endpoint
`POST /api/event`

### 5.2 Request validation
- `site_id` must match a registered, active site.
- Origin/Referer header check against the site's canonical domain after normalizing a leading `www.` — e.g. `example.com` and `www.example.com` are treated as the same site for validation purposes.
- This is a practical anti-spoofing check, not a hard security boundary, since `site_id` is public and Origin is spoofable server-side; it still blocks casual browser-based injection from unrelated origins.
- Rate limiting per IP-hash-of-the-moment (before discard) to blunt basic flooding — needs a lightweight in-memory counter (APCu or Redis if available) since we can't key on a persisted IP.
- Payload size cap, strict schema validation (reject unexpected fields).

### 5.3 Write path
- Validated event written to a write buffer, **not** directly to the primary events table on every request (see §6 for write-scaling rationale).
- **Decided: Redis**, reusing the instance already installed on the same host for skyggn. The ingestion API `LPUSH`es each validated event (as a small JSON payload) onto a dedicated ClearStats-namespaced list/stream key (e.g. `clearstats:events`) rather than writing to a file or DB table directly. A separate worker process (cron-triggered or a long-running consumer, TBD at implementation time) drains the queue in batches and performs bulk `INSERT`s into the raw events table.
  - **Namespacing/isolation**: since Redis is shared with skyggn, use a distinct key prefix (`clearstats:*`) and confirm skyggn's `maxmemory`/eviction policy won't cause ClearStats keys to be evicted under memory pressure from skyggn's own workload — if skyggn's Redis usage is memory-intensive, consider a separate logical DB index (`SELECT n` within the same Redis instance) or, if isolation needs to be stronger, a dedicated Redis instance later. Not a blocker for v1, but worth a quick check of skyggn's current Redis config before relying on it.
  - **Reliability**: since this is now an at-least-once queue rather than an append-only file, use `BRPOPLPUSH`/Redis Streams consumer groups (rather than plain `LPOP`) so an event isn't lost if the worker crashes mid-batch — a plain `LPOP` immediately removes the item with no re-delivery guarantee.
  - This replaces the local-file-queue approach discussed earlier in the (now superseded) server-side-dispatch design; the trade-offs are similar in spirit but Redis gives atomic operations and built-in consumer-group semantics for free, at the cost of one more moving part (Redis itself) in the ingestion API's dependency chain — if Redis is down, event writes fail rather than degrading to local disk. Worth deciding whether the ingestion endpoint should have a **local-file fallback** if Redis is unreachable, or whether "briefly drop events during a Redis outage" is acceptable given skyggn's Redis instance presumably already has its own uptime expectations.

### 5.4 Bot filtering
- User-agent pattern matching against a maintained bot-signature list (evaluate reusing an existing open-source UA bot list rather than hand-rolling one).
- Requests with missing/empty UA or Referer header patterns typical of headless tooling get flagged (not necessarily dropped — consider a `is_likely_bot` flag retained in aggregation so it's filterable rather than silently discarded, giving visibility into filtering accuracy).

---

## 6. Data model

### 6.1 Raw events table (short-lived, purged after rollup)
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | |
| site_id | VARCHAR(32) | indexed |
| visitor_hash | CHAR(64) | daily HMAC output |
| event_type | ENUM('pageview','custom') | |
| event_name | VARCHAR(128) | NULL for pageview |
| url_path | VARCHAR(2048) | |
| referrer_domain | VARCHAR(255) | parsed, not full referrer URL (avoids incidentally storing query strings that may contain personal data from the referring page) |
| country_code | CHAR(2) | resolved via in-memory GeoIP lookup, no raw IP retained |
| device_type | ENUM('desktop','mobile','tablet','other') | derived from UA at ingest, raw UA not retained |
| browser | VARCHAR(32) | derived, coarse-grained (no full UA string) |
| created_at | DATETIME | date-partitioned |

No IP, no raw UA, no visitor identifier that persists beyond 24h relevance (the hash itself is only meaningful within its salt-rotation window).

This table (like `daily_page_stats` in §6.2) has grown several columns since this list was last reconciled — session/engagement fields, extended campaign attribution, revenue, region/city, and `event_props` (an optional, size-bounded JSON blob for `trackEvent()`'s custom properties — stored so it isn't silently discarded, but not yet rolled up or exposed anywhere in the dashboard). Treat the migrations under `migrations/` as authoritative for the exact current column set.

**Retention: configurable per tenant** — decided. Add a `raw_event_retention_days` column to the `sites` table (§6.3), defaulted to a sensible value (e.g. 30) at site-creation time but overridable per site. The purge job (cron, daily) reads each site's configured window rather than applying one global constant. Note for the DPIA: per-tenant retention needs its own one-line justification per site if any site's window is set notably longer than the default — data minimization still applies per-site, "configurable" isn't itself the compliance argument.

### 6.2 Rollup tables (long-term retention)
Pre-aggregated, indexed for dashboard query performance:
- `daily_site_stats` (site_id, date, pageviews, unique_visitor_hashes_count, bounce_rate)
- `daily_page_stats` (site_id, date, url_path, pageviews, visitors, entrances, bounces) — `entrances`/`bounces` are attributed to the page a session *started* on, not every page it viewed, so the dashboard's per-page bounce rate (`bounces / entrances`) means the same thing as a landing-page bounce rate elsewhere
- `daily_referrer_stats` (site_id, date, referrer_domain, visits)
- `daily_country_stats` (site_id, date, country_code, visits)
- `daily_device_stats` (site_id, date, device_type, visits)
- `daily_campaign_term_stats` / `daily_campaign_content_stats` (site_id, date, campaign_term|campaign_content, visits) — `utm_term`/`utm_content`, reported as their own breakdowns rather than folded into `daily_campaign_stats`' source/medium/name key
- `daily_region_stats` / `daily_city_stats` (site_id, date, country_code, region|city, visits) — populated only when `geoip.city_database_path` is configured (see `docs/deployment-runbook.md`); empty otherwise, no proxy-header fallback exists for these the way country has `CF-IPCountry`
- `daily_revenue_stats` (site_id, date, event_name, currency, conversions, revenue_total) — summed per currency, never combined across currencies

Rollup job (cron, e.g. hourly for the current day + a final pass after midnight) aggregates raw events into these tables; dashboard queries read only from rollups, never raw events, keeping query latency flat regardless of raw event volume growth.

### 6.3 Multi-user / multi-site tables
- `sites` (id, name, domain, owner_user_id, created_at, active, `ip_source`, `trusted_proxy_config`, `raw_event_retention_days`) — site == domain for v1; `www.` is accepted as an alternate hostname for validation but is normalized to the canonical domain before comparison.
- `users` (id, email, password_hash, role) — auth per NIST SP 800-63B baseline (existing convention)
- `user_site_access` (user_id, site_id, role) — supports per-user access to many sites in a multi-user system

---

## 7. Multi-user / multi-site management

- ClearStats is a multi-user platform: many users can be registered, and each user may have access to multiple sites.
- Each site corresponds to a single canonical domain in v1 (`site == domain`). The `www.` variant is accepted for validation and normalized away so `www.example.com` and `example.com` resolve to the same site.
- `site_id` generated as a public, non-guessable-but-not-secret token (e.g., 8-char base62) — uniqueness matters more than secrecy, since it's embedded in public page source anyway.
- Dashboard: per-site view (default) + an aggregate cross-site overview (total pageviews across the portfolio, useful given the number of SilverDay properties).
- Site CRUD via the platform's own admin UI, standard PDO/prepared-statement patterns consistent with existing tooling.

---

## 8. Dashboard / reporting

- Time-series pageview chart (day/week/month granularity, backed entirely by rollup tables)
- Top pages, top referrers, country breakdown, device breakdown — all standard rollup-table reads
- Site switcher + "all sites" aggregate view
- Custom event/goal reporting (v1.1, once `daily_event_stats` rollup exists)
- No new-vs-returning-visitor metric (inherent limitation of the privacy model, same as Plausible — worth stating explicitly in the UI so it reads as an intentional privacy trade-off rather than a missing feature)

---

## 9. Design decision: why JS-based, superseding the server-dispatch direction

Earlier discussion explored a server-side (PHP-to-PHP API) dispatch model specifically to keep raw IP from ever leaving the origin site. That remains a valid, arguably *stronger* privacy design. This spec reverts to a JS-based client for the following practical reasons — worth confirming these still hold before finalizing:

- **Client-side signals**: SPA route changes, custom/goal events tied to client-side interactions, and any future need for engagement metrics (scroll depth, time-on-page) are natively available to a JS client and require no per-site custom instrumentation.
- **Deployment simplicity**: a single `<script>` tag is a one-line change on any site, versus wiring a shared PHP library into each site's bootstrap/footer and maintaining consistent hash-computation logic across independently-deployed codebases (the "byte-identical hashing logic across sites" risk flagged in the earlier design goes away entirely, since hashing now happens once, centrally, in the ingestion API).
- **Trade-off accepted**: raw IP/UA now transiently touch the central platform's ingestion endpoint per request (§3.3), rather than never leaving the origin site. This is the Plausible-equivalent model rather than the stronger origin-only model discussed earlier.

**Open question for you to confirm:** is trading that stronger data-locality property for JS-native client-side signals and simpler multi-site deployment the right call, or should v1 instead keep server-side dispatch and treat SPA/custom-event tracking as an explicit, deferred v2 feature? This spec assumes the former based on your latest instruction, but flagging it since it reverses the direction from earlier in this conversation.

---

## 10. GDPR / compliance notes

- No personal data at rest (raw events table, once defined per §6.1, contains no IP, no raw UA, no persistent identifier).
- Server access logs (Apache) must be configured to **not** log full IPs for the ingestion endpoint specifically, or the whole design's compliance posture is undermined by infrastructure logging outside the application layer — this is a deployment/config checklist item, not a code item, and easy to miss.
- GeoIP resolution must happen in-memory per-request with only the resolved country code persisted (confirm chosen GeoIP dataset/library doesn't itself log lookups to disk).
- Document the data flow (this spec's §2–§6) as the core of a DPIA; the "why JS vs. server-dispatch" trade-off in §9 should be explicitly reasoned through in that DPIA rather than left implicit.
- Salt secrecy and reliable rotation (§3.2) is the actual privacy-critical control — recommend monitoring/alerting if the rotation job fails to run, since a stuck salt silently weakens the whole model without any visible symptom.

---

## 11. Decisions log

| # | Question | Decision |
|---|---|---|
| 1 | Reverse proxy topology | Configurable per site (`ip_source` + trusted-proxy allowlist, §3.1) |
| 2 | Raw event retention window | Configurable per tenant, `sites.raw_event_retention_days` (§6.1) |
| 3 | Write buffering mechanism | Redis (shared instance already installed for skyggn on the same host), namespaced under `clearstats:*` (§5.3) |
| 4 | DNT/GPC honoring | Not honored — no user tracking occurs, signals don't apply (§4.3) |
| 5 | Project name | **ClearStats** (clearstats.de) |

## 12. Remaining open items

- **skyggn Redis config check**: confirm `maxmemory`/eviction policy before relying on the shared instance for ClearStats' queue (§5.3).
- **Redis-down fallback**: decide whether the ingestion endpoint needs a local-file fallback for event writes during a Redis outage, or whether brief data loss during an outage is acceptable (§5.3).
- **Rollup job scheduling specifics**: exact cron cadence and how the Redis-consuming worker process is run (persistent daemon vs. cron-triggered batch drain) — implementation-level detail, not blocking further spec work.
