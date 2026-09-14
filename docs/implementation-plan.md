# ClearStats implementation plan

## Purpose

This is the implementation plan for the complete ClearStats product, not a set of disconnected feature stubs. The project must be built in dependency order: foundation, identity and access, site management, ingest pipeline, rollup pipeline, dashboard, then hardening.

## Product decisions that are now fixed

- Multi-user platform: ClearStats is a multi-user app and not a single-owner utility.
- One user may manage many sites.
- One site == one canonical domain in v1.
- The `www` variant is accepted for validation and normalized away, so `www.example.com` and `example.com` are treated as the same site.
- Access is user-to-site based via `user_site_access`.
- The admin app, user management, and dashboards are in scope for the initial product.
- Raw IP and raw UA are never stored; only a derived visitor hash is retained.

## Implementation order (logical product sequence)

### Phase 1 — project foundation and database schema

Goal: establish the data model that underpins auth, site management, ingest, and analytics.

Tasks:
- [x] finalise the schema for `users`, `sites`, `user_site_access`, and the PHP-session boundary used for admin access
- [x] define `sites.domain` as the canonical site identifier for v1
- [x] define `raw_event_retention_days` and the retention policy on the `sites` table
- [x] create the raw-events table and the rollup tables
- [x] define indexes and constraints for site ownership, role checks, and per-site aggregation
- [x] add config defaults for DB, Redis, salts, proxy settings, and retention

Acceptance criteria:
- the schema supports multi-user site access
- each site resolves to one canonical domain
- the database supports both admin management and analytics ingestion

### Phase 2 — authentication and admin app shell

Goal: build the product entrypoint for users and site administration.

Tasks:
- [x] build a protected admin shell in the front controller and request router
- [x] implement login/logout flow for admin users
- [x] implement session handling and authentication checks
- [x] create route guards for protected admin and dashboard pages
- [x] expose user and site management endpoints, even if initially server-rendered and minimal

Acceptance criteria:
- a user cannot access admin pages without authentication
- a user can only access sites and actions covered by their role and site assignment
- the admin shell is ready before the dashboard and analytics UI are built

### Phase 3 — site management and user assignment

Goal: make the app operational for a real owner/admin workflow.

Tasks:
- [x] add site creation and editing screens/handlers
- [x] define site metadata: name, canonical domain, active status, retention, trusted proxy settings, and owner/admin assignments
- [x] add user management screens and role assignment
- [x] add access-control checks so users are limited to their assigned sites
- [x] add admin listing pages for users and sites

Acceptance criteria:
- an admin can create a site and assign it to users
- a user sees only the sites they are allowed to manage or view
- the domain rule (`www` normalized to canonical host) is enforced during site creation and validation

### Phase 4 — ingestion contract and validation

Goal: lock the request pipeline before touching queueing or analytics.

Tasks:
- [x] finalize `/api/event` validation rules
- [x] validate `site_id` and site domain against the configured canonical domain
- [x] enforce `Origin`/`Referer` checks against the normalized host
- [x] enforce payload schema and size limits
- [x] apply lightweight HMAC-keyed rate limiting
- [x] apply bot filtering policy
- [x] compute `visitor_hash` using the daily salt, site domain, IP, and UA
- [x] ensure raw IP and raw UA are discarded immediately after hashing

Acceptance criteria:
- invalid requests are rejected consistently
- valid requests are accepted only when the site and role are valid
- no raw IP or UA are written anywhere durable

### Phase 5 — Redis queueing and worker pipeline

Goal: move ingestion into a safe at-least-once write buffer.

Tasks:
- [x] implement the Redis queue contract with `clearstats:*` namespacing
- [x] ensure queue reliability semantics avoid silent data loss
- [x] build the queue worker to drain batches and write to `events_raw`
- [x] implement Redis outage strategy and operational decision
- [x] monitor queue backlog and worker health

Acceptance criteria:
- queued events are processed without loss under worker crashes or retries
- the app has a defined fallback or failure policy for Redis outages
- raw ingestion is decoupled from dashboard reads

### Phase 6 — rollup engine and retention

Goal: turn raw events into query-friendly aggregate tables.

Tasks:
- [x] implement the rollup job for pageviews, unique visitors, pages, referrers, countries, and devices
- [x] aggregate by site and date
- [x] enforce raw-data retention purge based on `raw_event_retention_days`
- [x] ensure the dashboard reads only rollup tables, never raw events

Acceptance criteria:
- rollup tables are populated reliably
- raw event retention is enforced per site
- dashboard queries remain performant and privacy-safe

### Phase 7 — dashboard and reporting UI

Goal: deliver the analytics experience for authorized users.

Tasks:
- [x] implement the dashboard shell and site switcher
- [x] add overview metrics and per-site drill-downs
- [x] add page, referrer, country, and device breakdowns
- [x] add explicit UI messaging for intentional limitations (e.g. no new-vs-returning visitor metric)
- [x] enforce dashboard access by user/site role

Acceptance criteria:
- authorized users can inspect analytics for their assigned sites
- unauthorized users cannot view other sites or admin pages
- the dashboard is entirely backed by rollup data

### Phase 8 — client tracking script and SPA support

Goal: complete the measurement loop from browser to dashboard.

Tasks:
- [x] implement the tracking JS snippet
- [x] send minimal pageview payloads without cookies or persistent client state
- [x] support SPA route-change tracking
- [x] validate payload shape against the server-side ingestion schema

Acceptance criteria:
- a pageview from a tracked site is accepted by the ingest API
- the client remains stateless and privacy-preserving
- route changes emit valid tracking events for SPA apps

### Phase 9 — hardening, observability, and deployment readiness

Goal: make the app production-safe.

Tasks:
- [ ] add monitoring for failed auth and site permission mismatches
- verify Apache logging and privacy-sensitive deployments
- confirm proxy trust handling and GeoIP behavior
- audit the compliance story for the DPIA
- finish release docs and operational runbooks

Acceptance criteria:
- all critical operational risks are documented and monitored
- the app is ready for deployment under the privacy and access assumptions of the spec

## Key rule for execution

Do not implement the dashboard, client tracking, or analytics polish before the auth model, site management, and ingestion pipeline exist and are tested. The app must be built in this order:

1. schema and identity
2. auth and admin shell
3. site/user administration
4. ingestion validation
5. queue and worker
6. rollup
7. dashboard
8. tracking client
9. ops and hardening

That is the coherent product plan for the complete application.
