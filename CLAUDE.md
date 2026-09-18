# CLAUDE.md — ClearStats

This file orients Claude Code (or any agent working in this repo). Read it before making changes. The full architecture and rationale live in `docs/analytics-platform-spec.md` — treat that as the source of truth for design decisions; this file is a working-conventions summary, not a replacement for it.

## What this project is

ClearStats (clearstats.de) is a self-hosted, multi-site, GDPR-compliant, cookieless web analytics platform, inspired architecturally by Plausible Analytics but not a fork. Owner tracks their own sites end-to-end (no third-party client onboarding in v1). Full spec: `docs/analytics-platform-spec.md`.

## Non-negotiable constraints

- **No cookies. No client-side persistent storage on the tracking surface** (no localStorage/sessionStorage identifiers). This is the core product promise for anything a tracked site's visitor loads — above all the tracking script, edited at `assets/track.js` (source) and shipped as the minified `public/js/track.js` (build — see the source-vs-build-file decision below; edit the source, not the built file). Do not introduce either there, even for "just" a UX convenience, without flagging it explicitly as a design change and stopping for confirmation. `tests/Tracking/TrackScriptTest.php` (source) and `TrackScriptBuildTest.php` (built file) enforce this; do not weaken either.
- **Exception — first-party dashboard UI** (owner-approved, 2026-09-15): the authenticated/marketing pages served by ClearStats itself may persist *UI preferences* in `localStorage`. Currently only the theme choice (`clearstats-theme`, values `system`/`light`/`dark`). This is operator-facing chrome, not visitor tracking. The boundary: never store a visitor or session identifier, and never extend this to `track.js` or anything it loads.
- **No raw IP or raw User-Agent is ever persisted to disk or DB.** They are valid inputs to the per-request visitor-hash computation (see below) and must be discarded immediately after. If you're about to write either to a table, a log file, or anywhere durable — stop and ask first. This is the load-bearing privacy control for the whole compliance posture; treat it as a hard boundary, not a style preference.
- **Salt secrecy.** The daily HMAC salt (§3 of the spec) must never be logged, never exposed via any API response, never sent to any client script.

## Stack and conventions

- PHP 8.3, `declare(strict_types=1)` in every file.
- MariaDB 10.11+, PDO with prepared statements only — no raw string interpolation into SQL, ever.
- No heavy framework. Front-controller routing (`public/index.php` dispatches to `src/`).
- Server-rendered templates for the dashboard UI (no SPA framework).
- **All page styling lives in `public/css/app-shell.css`** — one token-based stylesheet, shared primitives (`.btn`, `.card`, `.panel`, `.stat`, `.table`, `.grid`, `.form`). Templates must not ship local `<style>` blocks; they conflict with the tokens and force `!important` wars. Tokens follow `docs/design-system.md`.
- Fonts are self-hosted in `public/fonts/` (Inter + JetBrains Mono, OFL-1.1). Never load fonts, icons, or scripts from a third-party CDN — it would disclose visitor IPs on every page load.
- Files created under `public/` default to `rw-rw----` and Apache (`www-data`) then 403s them. `chmod 644` any new public asset (`chgrp` needs root).
- Redis is used only for the inbound event queue (§5.3 of spec) — it's a shared instance also used by another project (skyggn) on the same host, so all ClearStats keys **must** be prefixed `clearstats:` and nothing else should assume exclusive access to the Redis instance.
- Authentication baseline: NIST SP 800-63B.
- Licensing: MIT for code (default), EUPL-1.2 considered as a European alternative if ever needed; non-code assets under CC BY-SA 4.0. See `REUSE.toml`. Follow REUSE spec conventions (SPDX headers in new files, `.license` sidecars where a header can't be embedded).

## Commands

```bash
composer install                                    # install deps (PHP 8.3+, ext-pdo, ext-redis)
cp config/config.example.php config/config.php      # then edit with real DB/Redis creds (gitignored)

vendor/bin/phpunit tests                             # full suite — no phpunit.xml; point at tests/ explicitly
vendor/bin/phpunit tests/Ingestion/VisitorHasherTest.php          # single file
vendor/bin/phpunit --filter testSaltIsStableAcrossAUtcDayAndRotatesAtTheBoundary   # single test by name (any file)

vendor/bin/phpcs --standard=PSR12 src tests          # phpcs is a dev dependency but no committed ruleset.xml —
                                                       # bare `vendor/bin/phpcs src` falls back to the PEAR
                                                       # standard and is noisy; pass --standard explicitly
vendor/bin/phpcbf --standard=PSR12 src tests          # auto-fix what phpcbf can

php bin/migrate.php --status                          # list applied/pending schema migrations
php bin/migrate.php                                    # apply pending migrations, tracked in schema_migrations
php bin/create-user.php admin@example.com            # first admin account; password prompted, not passed as arg
php bin/queue-worker.php --limit=100                  # drain Redis -> events_raw (cron-friendly, finite run)
php bin/rollup.php                                    # aggregate today (UTC) + purge expired raw events
php bin/rollup.php --date=2026-09-14                  # aggregate a specific UTC date
php bin/build-track-js.php                            # rebuild public/js/track.js from assets/track.js (needs Node/npm)
```

PHPUnit tests run against an isolated in-memory SQLite schema (`tests/sqlite-schema.sql`, loaded by `tests/TestDatabase.php`) — they never touch the configured MariaDB database. A few ingestion tests (queue, abuse monitor, rate limiter) hit the real local Redis and clean up their own `clearstats:` test keys, so a reachable local Redis is required for the full suite to pass, not just for manual testing.

There is no CI config in this repo (no `.github/workflows`) — running the commands above locally before handing back a change is the only check that happens.

## Architecture summary (see spec for full detail)

- Client: a single static JS file (`public/js/track.js`, built from `assets/track.js`) embedded via `<script defer data-site-id="...">` on tracked pages. Sends one beacon per pageview via `sendBeacon`/`fetch keepalive`. No cookies, no fingerprinting-adjacent data collection.
- Ingestion API (`POST /api/event`, routed through `src/Http/` → `src/Ingestion/`): validates the request, computes the visitor hash server-side (`HMAC-SHA256(daily_salt, domain||ip||ua)`), discards raw IP/UA immediately, pushes the event onto Redis (`clearstats:events`).
- A worker (`bin/`) drains the Redis queue in batches and bulk-inserts into the raw events table.
- A rollup cron job aggregates raw events into `daily_*_stats` tables; the dashboard reads only from rollup tables, never raw events.
- Raw events are purged per-site on a configurable retention window (`sites.raw_event_retention_days`).
- `ip_source` is configurable per site (`direct` / `x-forwarded-for` / `cf-connecting-ip`), each with a trusted-proxy allowlist check before the header is trusted.

## Request & data flow (spans multiple files — read together, not in isolation)

**Every HTTP request** goes through `public/index.php`, which is more than a dispatcher:
1. Sets security headers (CSP-adjacent headers, HSTS gated on `config.app.https`) before anything else runs.
2. `Router::route()` (`src/Http/Router.php`) is a flat, hand-written `"METHOD /path" => [controller, action]` map — no dynamic segments, no regex. New routes are added there, literally.
3. Routes to `DashboardController`/`SiteController`/`UserController` are treated as protected: `index.php` itself checks `AuthSession::isAuthenticated()` and looks up the user's `role` directly (bypassing repositories) before instantiating anything, and redirects to `/login` on failure. Don't assume a controller enforces its own auth — the front controller does it for these three.
4. `index.php` also hand-wires every controller's constructor dependencies inline (`match ($controllerClass) { ... }`) — there is no DI container. Adding a controller dependency means editing this match arm, not just the class.
5. For HTML routes (everything except `EventController`/`HealthController`) it output-buffers the controller, then string-injects the top nav, font preload, `app-shell.css`, and an inline pre-paint theme-detection `<script>` (reads `localStorage['clearstats-theme']`) via `preg_replace_callback`/`str_replace` on the buffered HTML — there's no templating engine doing this, it's literally post-processing the rendered string.

**The ingestion pipeline** (`POST /api/event`) is the most security-sensitive path — trace it across these files together when touching it: `EventController` (orchestration + validation) → `Domain\SiteValidation`/`SiteAccessPolicy` (is this site_id/origin allowed) → `ClientIpResolver` (resolves the real client IP per the site's configured `ip_source`, honoring only `trusted_proxy_ips`) → `VisitorHasher` + `SaltProvider` (computes `HMAC-SHA256(daily_salt, domain||ip||ua)`; `SaltProvider` reads/rotates `salt_state` keyed to UTC-day boundaries) → `BotDetector`/`UserAgentClassifier`/`LanguageResolver`/`CountryResolver` (coarse, non-identifying metadata only) → `AbuseMonitor` + `RequestRateLimiter` (both Redis-backed, `clearstats:`-namespaced) → `EventQueue::push()` (LPUSH onto `clearstats:events`). Raw IP/UA never leave `EventController`'s local scope.

**The queue worker** (`bin/queue-worker.php` → `QueueWorker`) uses `EventQueue::reserve()` (`RPOPLPUSH events -> events:processing`), not plain `LPOP` — a payload sits in the processing list until `acknowledge()` removes it, so a crashed/killed worker doesn't lose events; the next run's `recoverProcessing()` puts anything still in `events:processing` back on the main queue. Unparseable/invalid payloads go through `discard()`, which stores only a SHA-256 fingerprint + reason in `clearstats:events:rejected` (capped at 1000), never the payload itself — event bodies can carry operator-supplied strings (`url_path`, campaign fields) that shouldn't outlive `raw_event_retention_days`. Takes a file lock (`/tmp/clearstats-queue-worker.lock`) so cron can overlap invocations safely; a locked-out run exits 0, not an error.

**The rollup job** (`bin/rollup.php` → `StatsRollup::aggregateDay()`) is idempotent per `(site_id, date)` — it deletes then re-inserts that day's rows in every `daily_*_stats` table before recomputing, so re-running a date is always safe. It reads only from `events_raw`; the dashboard (`DashboardQuery`) reads only from the `daily_*_stats` tables — raw events and the dashboard never touch directly. `StatsRollup` also purges rows past each site's `raw_event_retention_days` in the same run.

## Decisions already made (don't re-litigate without flagging why)

| Area | Decision |
|---|---|
| Salt scope | Single shared salt across all sites, domain baked into hash input |
| Salt rotation boundary | Aligned to the UTC day (not "24h since last rotation"), and the salt is selected from the event's own timestamp — one salt per rollup day |
| Client architecture | JS snippet (not server-to-server PHP dispatch) |
| Reverse proxy IP handling | Configurable per site |
| Raw event retention | Configurable per tenant |
| Event queue | Redis (shared with skyggn), namespaced `clearstats:*` |
| DNT/GPC | Not honored — no user tracking occurs, so the signal doesn't apply |
| DNT/GPC honoring | Explicitly rejected as a decision — do not add DNT/Sec-GPC handling |
| Dashboard UI storage | `localStorage` allowed for UI preferences only (theme); never on the tracking surface |
| Web fonts | Self-hosted WOFF2, not Google Fonts — avoids leaking visitor IPs |
| UI styling | Single stylesheet (`public/css/app-shell.css`); no per-template `<style>` blocks |
| Schema migrations | Tracked via `bin/migrate.php` + a `schema_migrations` table (2026-09-18, after a prod outage from a hand-applied schema change). `migrations/0001_initial_schema.sql` is closed history — do not edit it further; every new schema change is a new numbered file (`0002_...`) |
| track.js source vs. served file | `assets/track.js` is the canonical, tested source; `public/js/track.js` is a minified **build** of it (`bin/build-track-js.php`, terser) — every already-installed site's `<script src>` points at that exact URL, so it's what's actually served, not the source. Edit `assets/track.js`, then rebuild and commit both files |

## Open items (see spec §12 — do not resolve unilaterally, surface to the user)

- skyggn's Redis `maxmemory`/eviction policy hasn't been checked yet — don't assume ClearStats' queue keys are safe from eviction under memory pressure until confirmed.
- Whether the ingestion endpoint needs a local-file fallback for Redis-down scenarios is undecided.
- Exact rollup-worker execution model (persistent daemon vs. cron-triggered batch drain) is undecided.

## Working style for this repo

- This user is a senior InfoSec specialist (CISSP/CISM/ISO 27001/GWAPT). Assume a technical audience — don't over-explain basic security concepts, but do flag any deviation from the spec's stated privacy model explicitly and immediately, rather than silently "improving" it.
- Never guess at unstated requirements — if a task requires a decision the spec doesn't cover, ask rather than assume, consistent with how the spec itself was built (see the "Decisions log" and "Open items" sections in `docs/analytics-platform-spec.md`).
- Prefer small, reviewable commits/diffs over large speculative scaffolding.
