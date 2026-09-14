# ClearStats Deployment Runbook

## Web deployment

- Set the web server document root to `public/`; do not expose `config/`, `src/`, `tests/`, `migrations/`, or `logs/`.
- Require HTTPS in production. The application marks session cookies `Secure` when HTTPS is detected.
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

## Privacy and retention

- Never persist raw IP addresses or raw User-Agent strings.
- Run rollup regularly so aggregate data is available before raw-event retention removes source rows.
- Review each site's `raw_event_retention_days` setting during onboarding and operational reviews.
- Include the privacy model, proxy trust, Redis behavior, and retention policy in the DPIA and change records.
- Session, bounce, engagement, campaign, conversion, scroll, and click data are aggregate analytics only; review event names and campaign values to ensure sites do not send personal data.
