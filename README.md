# ClearStats

Self-hosted, multi-site, GDPR-compliant, cookieless web analytics — a LAMP-native alternative to Plausible Analytics for sites you own end-to-end.

## Status

The working foundation is implemented: multi-user authentication, site and user administration, privacy-safe ingestion, Redis queue processing, rollups, retention purging, and the initial dashboard are available. Remaining work is tracked in `docs/implementation-plan.md`, including Redis outage operations, failed-auth/permission monitoring, and deployment hardening.

## Requirements

- PHP 8.3+
- MariaDB 10.11+
- Apache (with `mod_rewrite` for front-controller routing)
- Redis (shared instance acceptable — see `CLAUDE.md` on namespacing)
- Composer

## Getting started

```bash
composer install
cp config/config.example.php config/config.php
# edit config/config.php with DB/Redis credentials
```

Run pending migrations against your MariaDB instance before starting the app, and again after every pull that touches `migrations/`:

```bash
php bin/migrate.php            # apply everything pending
php bin/migrate.php --status   # list applied/pending without running anything
```

Applied migrations are tracked in a `schema_migrations` table, so re-running is always safe. Schema changes are added as new numbered files in `migrations/` (`0002_...`, `0003_...`); `0001_initial_schema.sql` is closed history and is not edited in place anymore.

Create the first administrator after migrations have run. The password is entered interactively and is not placed in shell history:

```bash
php bin/create-user.php admin@example.com
```

Additional accounts can be created by an administrator in the web UI or with the same command and an explicit role.

Run the application from the configured web root with `public/` as the document root. The front controller serves the landing page, login flow, protected admin pages, and `POST /api/event`.

Set `app.https` to `true` in production, including deployments behind an HTTPS reverse proxy.

The background jobs are finite, cron-friendly commands:

```bash
php bin/queue-worker.php --limit=100
php bin/rollup.php
php bin/rollup.php --date=2026-09-14
```

PHPUnit uses an isolated in-memory SQLite database and does not connect to the configured production MariaDB database. Redis-backed ingestion tests still use the configured local Redis instance and clean their `clearstats:` test keys.

The queue worker drains validated Redis events into `events_raw`, recovers reserved events after a restart, deduplicates event IDs, and reports queued/processing counts. Redis is a required dependency for ingestion; if it is unavailable, the API fails the event request rather than writing raw event data to a local fallback. The rollup command aggregates active sites for the current UTC date by default and purges raw events according to each site's retention policy.

Country metadata is resolved in memory from the configured MaxMind country database when available. Configure `geoip.country_database_path` in `config/config.php`. If the database is unavailable or has no result, ClearStats falls back to `CF-IPCountry` only when the request comes through a configured trusted proxy; otherwise country data remains empty.

The tracker also supports ephemeral in-memory sessions, session-end engagement, bounce rate, sanitized UTM campaign attribution, conversion/custom events, scroll milestones, and click-event hooks. ClearStats stores only coarse browser, operating-system, and preferred-language values derived at ingestion; it never stores raw User-Agent strings. It never uses cookies, localStorage, sessionStorage, or persistent visitor IDs. Cross-day returning-visitor identification is intentionally not supported.

See [docs/tracking-guide.md](docs/tracking-guide.md) for tracker installation, campaign attribution, conversion events, custom events, interaction hooks, privacy boundaries, and dashboard processing timing.

## Project layout

```
public/            Front controller + static tracking script
src/Http/           Routing / request-response plumbing
src/Ingestion/       Event validation, visitor-hash computation, Redis queue push
src/Domain/          Site/user/tenant domain logic
src/Db/              PDO wrappers, migrations runner
src/Rollup/          Raw-event → aggregate rollup logic
bin/                 CLI workers (queue drain, rollup cron entrypoint)
migrations/          SQL schema migrations
docs/                Full software specification
config/              Environment configuration (config.php is gitignored)
tests/               PHPUnit tests
integrations/        CMS/platform plugins that install track.js for you (currently: Joomla)
```

## License

Code: MIT. Non-code assets: CC BY-SA 4.0. See `REUSE.toml` and `LICENSES/`.
