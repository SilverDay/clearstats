# CLAUDE.md — ClearStats

This file orients Claude Code (or any agent working in this repo). Read it before making changes. The full architecture and rationale live in `docs/analytics-platform-spec.md` — treat that as the source of truth for design decisions; this file is a working-conventions summary, not a replacement for it.

## What this project is

ClearStats (clearstats.de) is a self-hosted, multi-site, GDPR-compliant, cookieless web analytics platform, inspired architecturally by Plausible Analytics but not a fork. Owner tracks their own sites end-to-end (no third-party client onboarding in v1). Full spec: `docs/analytics-platform-spec.md`.

## Non-negotiable constraints

- **No cookies. No client-side persistent storage** (no localStorage/sessionStorage identifiers). This is the core product promise — do not introduce either, even for "just" a UX convenience, without flagging it explicitly as a design change and stopping for confirmation.
- **No raw IP or raw User-Agent is ever persisted to disk or DB.** They are valid inputs to the per-request visitor-hash computation (see below) and must be discarded immediately after. If you're about to write either to a table, a log file, or anywhere durable — stop and ask first. This is the load-bearing privacy control for the whole compliance posture; treat it as a hard boundary, not a style preference.
- **Salt secrecy.** The daily HMAC salt (§3 of the spec) must never be logged, never exposed via any API response, never sent to any client script.

## Stack and conventions

- PHP 8.3, `declare(strict_types=1)` in every file.
- MariaDB 10.11+, PDO with prepared statements only — no raw string interpolation into SQL, ever.
- No heavy framework. Front-controller routing (`public/index.php` dispatches to `src/`).
- Server-rendered templates for the dashboard UI (no SPA framework).
- Redis is used only for the inbound event queue (§5.3 of spec) — it's a shared instance also used by another project (skyggn) on the same host, so all ClearStats keys **must** be prefixed `clearstats:` and nothing else should assume exclusive access to the Redis instance.
- Authentication baseline: NIST SP 800-63B.
- Licensing: MIT for code (default), EUPL-1.2 considered as a European alternative if ever needed; non-code assets under CC BY-SA 4.0. See `REUSE.toml`. Follow REUSE spec conventions (SPDX headers in new files, `.license` sidecars where a header can't be embedded).

## Architecture summary (see spec for full detail)

- Client: a single static JS file (`public/js/track.js`) embedded via `<script defer data-site-id="...">` on tracked pages. Sends one beacon per pageview via `sendBeacon`/`fetch keepalive`. No cookies, no fingerprinting-adjacent data collection.
- Ingestion API (`POST /api/event`, routed through `src/Http/` → `src/Ingestion/`): validates the request, computes the visitor hash server-side (`HMAC-SHA256(daily_salt, domain||ip||ua)`), discards raw IP/UA immediately, pushes the event onto Redis (`clearstats:events`).
- A worker (`bin/`) drains the Redis queue in batches and bulk-inserts into the raw events table.
- A rollup cron job aggregates raw events into `daily_*_stats` tables; the dashboard reads only from rollup tables, never raw events.
- Raw events are purged per-site on a configurable retention window (`sites.raw_event_retention_days`).
- `ip_source` is configurable per site (`direct` / `x-forwarded-for` / `cf-connecting-ip`), each with a trusted-proxy allowlist check before the header is trusted.

## Decisions already made (don't re-litigate without flagging why)

| Area | Decision |
|---|---|
| Salt scope | Single shared salt across all sites, domain baked into hash input |
| Client architecture | JS snippet (not server-to-server PHP dispatch) |
| Reverse proxy IP handling | Configurable per site |
| Raw event retention | Configurable per tenant |
| Event queue | Redis (shared with skyggn), namespaced `clearstats:*` |
| DNT/GPC | Not honored — no user tracking occurs, so the signal doesn't apply |
| DNT/GPC honoring | Explicitly rejected as a decision — do not add DNT/Sec-GPC handling |

## Open items (see spec §12 — do not resolve unilaterally, surface to the user)

- skyggn's Redis `maxmemory`/eviction policy hasn't been checked yet — don't assume ClearStats' queue keys are safe from eviction under memory pressure until confirmed.
- Whether the ingestion endpoint needs a local-file fallback for Redis-down scenarios is undecided.
- Exact rollup-worker execution model (persistent daemon vs. cron-triggered batch drain) is undecided.

## Working style for this repo

- This user is a senior InfoSec specialist (CISSP/CISM/ISO 27001/GWAPT). Assume a technical audience — don't over-explain basic security concepts, but do flag any deviation from the spec's stated privacy model explicitly and immediately, rather than silently "improving" it.
- Never guess at unstated requirements — if a task requires a decision the spec doesn't cover, ask rather than assume, consistent with how the spec itself was built (see the "Decisions log" and "Open items" sections in `docs/analytics-platform-spec.md`).
- Prefer small, reviewable commits/diffs over large speculative scaffolding.
