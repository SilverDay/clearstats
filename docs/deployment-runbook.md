# ClearStats Deployment Runbook

## Deploying a schema change

Every deploy that includes a new file under `migrations/` must run it against production before (or as part of) the code rollout that depends on it — the dashboard/ingestion code is not written defensively against a missing column, so a code deploy that outruns its own migration fails the same way the September 2026 `daily_page_stats` incident did.

```bash
php bin/migrate.php --status   # see what's pending first
php bin/migrate.php            # apply it
```

`schema_migrations` tracks what has run, so this is safe to invoke on every deploy even when nothing is pending. New schema changes are new numbered files (`0002_...`, `0003_...`) — `migrations/0001_initial_schema.sql` is closed history and must not be edited in place.

## Web deployment

- Set the web server document root to `public/`; do not expose `config/`, `src/`, `tests/`, `migrations/`, or `logs/`.
- Require HTTPS in production. The application marks session cookies `Secure` when HTTPS is detected.
- Set `app.https` to `true` in production so HSTS is emitted consistently, including behind a reverse proxy.
- Confirm Apache rewrite support and `public/.htaccess` routing in a staging environment.
- Confirm access and error logs do not record request bodies, raw User-Agent values, or raw client IP values for the ingestion endpoint.
- Keep `config/config.php` outside version control and rotate any credential that has appeared in shell history, logs, or shared configuration.

## Background jobs

Run the queue worker frequently enough to keep both reported queue counts near zero:

```bash
php /path/to/clearstats/bin/queue-worker.php --limit=100
php /path/to/clearstats/bin/rollup.php
```

The queue worker recovers reserved events after restart and deduplicates event IDs. A database failure leaves the reserved event in the processing list for recovery on the next run.

## Testing

Run PHPUnit from the project root. Database-backed tests use an in-memory SQLite schema and cannot delete production MariaDB rows. Redis integration tests use namespaced `clearstats:` keys and clean up after themselves.

## Redis

- All keys must remain under `clearstats:`.
- Redis is required for ingestion. There is deliberately no local-file fallback.
- Confirm the shared Redis instance has an eviction policy and memory headroom appropriate for the ClearStats queue; a dedicated logical DB or instance is preferable when isolation is required.
- Alert when `queued` or `processing` counts stay above the normal baseline.

## Proxy and country metadata

- Configure only trusted reverse-proxy networks in `ingestion.trusted_proxy_ips`.
- Use the site `ip_source` setting consistently with the proxy configuration.
- Configure `geoip.country_database_path` to a readable MaxMind Country `.mmdb` file, such as `GeoLite2-Country.mmdb`.
- Country lookup happens in memory from the transient client IP; only the two-letter country code is queued and persisted.
- If the MaxMind database is unavailable or has no result, `CF-IPCountry` is accepted only from a trusted proxy. Otherwise country data remains empty.
- Optionally configure `geoip.city_database_path` to a readable MaxMind **City** `.mmdb` file (GeoLite2-City, ~70MB — a different, larger download than the Country database) to populate region/city. There is no proxy-header fallback for region/city the way country has `CF-IPCountry`: leave this unset and they stay empty, no other configuration required.

## Privacy and retention

- Never persist raw IP addresses or raw User-Agent strings.
- Run rollup regularly so aggregate data is available before raw-event retention removes source rows.
- Review each site's `raw_event_retention_days` setting during onboarding and operational reviews.
- Include the privacy model, proxy trust, Redis behavior, and retention policy in the DPIA and change records.
- Session, bounce, engagement, campaign, conversion, scroll, and click data are aggregate analytics only; review event names and campaign values to ensure sites do not send personal data.
- Browser, operating-system, and language values are coarse classifications only. Raw User-Agent and full `Accept-Language` headers are never persisted.
- The visitor salt is stored in `salt_state` and rotates automatically when its configured age is exceeded. Restrict database access to the application account and never expose the salt table through backups or diagnostics.
