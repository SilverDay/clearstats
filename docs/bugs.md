# Bug report — triage status

Status as of 2026-09-15. All five findings are fixed and pushed. See the closing
section for the regression tests that are still missing and one operational gap.

| # | Finding | Status |
|---|---|---|
| 1 | Malformed tracking JSON can produce HTTP 500 | **Fixed** |
| 2 | One corrupted Redis event can repeatedly stop the queue worker | **Fixed** |
| 3 | First-use salt initialization has a race condition | **Fixed** |
| 4 | Engagement duration wraps after one hour | **Fixed** |
| 5 | Salt rotation does not implement the documented boundary behaviour | **Fixed** |

Fixes landed in commits `3f1b129` (ingestion) and `b192567` (duration formatting).

## Priority findings

### 1. Malformed tracking JSON can produce HTTP 500 — Fixed

In [`src/Ingestion/EventController.php`](../src/Ingestion/EventController.php), `readRequestPayload()` used:

```php
$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
```

The resulting `JsonException` was not caught, so a malformed request to the public
ingestion endpoint bypassed the intended `400` response.

**Resolution.** The decode is wrapped and returns `null` on failure, which the caller
already maps to `400 Invalid JSON payload.`:

```php
try {
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException) {
    return null;
}
```

---

### 2. One corrupted Redis event can repeatedly stop the queue worker — Fixed

An invalid payload threw and terminated the whole worker. The payload stayed in the
processing list, and the next run's `recoverProcessing()` returned it to the queue —
a poison-message loop.

**Resolution.** `QueueWorker::processBatch()` now guards each event individually and
splits failures by whether a retry could ever succeed:

- **Unprocessable** (undecodable JSON, non-object payload, or a data/constraint error):
  the payload is dropped via `EventQueue::discard()`, which acknowledges it and appends a
  **payload-free** record to `clearstats:events:rejected` — SHA-256 fingerprint, failure
  type plus SQLSTATE, byte count, timestamp. The list is capped at 1000 entries.
- **Transient** (connection loss, deadlock, lock-wait timeout — SQLSTATE `08*`/`40*` or
  driver codes 1205/1213/2006/2013): the batch stops and the event stays reserved, so
  `recoverProcessing()` requeues it on the next run instead of throwing valid data away.

The event body is deliberately not retained. It would outlive
`sites.raw_event_retention_days` and can carry operator-supplied values (`url_path`,
campaign fields, `event_name`), so keeping it would move event data outside the
documented retention model. Failure reasons record the exception class and SQLSTATE only,
because driver messages can embed SQL and bound parameter values.

**Tests.** `QueueWorkerTest::testRejectsMalformedEventAndContinuesWithLaterValidEvent`
asserts the valid event still lands, the rejection log holds one entry, the processing
list is empty, and the record contains a fingerprint but no `payload` key.
`testConnectionFailuresAreRetryableButDataErrorsAreNot` pins the retry classification.

---

### 3. First-use salt initialization has a race condition — Fixed

Two simultaneous first tracking requests could both see no row and both attempt the
insert; the loser received a duplicate-primary-key exception and ingestion failed.

**Resolution.** Initialization is an atomic upsert followed by a read, so the request
that loses the race uses the winner's salt rather than its own generated value
(`ON CONFLICT(id) DO NOTHING` on SQLite, `ON DUPLICATE KEY UPDATE id = id` on MariaDB).

Rotation had the same shape of problem — two requests could both observe an expired
salt and rotate twice, discarding a salt that was still in use. Rotation triggered from
`currentSalt()` is now a compare-and-swap against the observed `generated_at`, so only
one request performs the transition.

**Test.** `SaltProviderTest::testExpiredSaltRotationKeepsOneWinner` asserts the rotation
produces one new current salt and preserves the prior value as `previous_salt`.

> **Gap:** this is still single-threaded coverage. The concurrency-oriented integration
> test called for in the original report has not been written — see below.

---

## Correctness risks

### 4. Engagement duration wraps after one hour — Fixed

Both dashboard branches used `gmdate('i:s', $seconds)`, so `3600` rendered as `00:00`
rather than `60:00`, while engagement is allowed up to 86,400 seconds.

**Resolution.** Both branches call a shared `formatDuration()` that promotes to an
`H:MM:SS` form past the hour.

**Test.** `DashboardControllerTest::testFormatsDurationsWithoutWrappingAtOneHour`
covers `3599`, `3600` and `86400`.

---

### 5. Salt rotation does not implement the documented boundary behaviour — Fixed

`VisitorHasher::hash()` read only `currentSalt()`, and rotation fired 24h after the
last rotation rather than on a fixed boundary. A rotation landing mid-day therefore
gave one visitor two hashes inside a single calendar day, and the rollup counts
`COUNT(DISTINCT visitor_hash)` grouped by `DATE(created_at)` — so that visitor was
counted twice.

**Resolution.** Spec §3.2's recommendation ("bucket by day using the salt's generation
timestamp, not wall-clock") is now implemented as period alignment:

- `SaltProvider::saltForTimestamp()` rotates when the request's period differs from the
  stored salt's period, where the period is `intdiv($timestamp, rotation_hours * 3600)`.
  With the default 24 that is exactly the UTC day, so a calendar day always resolves to
  one salt no matter when the first request of that day arrives.
- `EventController` takes a single `time()` reading and uses it for both the salt lookup
  and `created_at`, so the hash and the rollup bucket cannot land on opposite sides of a
  boundary — which closes the split-second case the spec called out.

Because one calendar day now maps to exactly one salt, dual-salt hashing is unnecessary:
`previous_salt` is still written for recovery, but no read path needs it. The
`VisitorHasher` docblock has been corrected to describe what the code actually does.

**Tests.**
- `SaltProviderTest::testSaltIsStableAcrossAUtcDayAndRotatesAtTheBoundary` — same salt at
  `00:00:01`, mid-day and `23:59:59`; a new one at the next midnight, with the old value
  retained as `previous_salt`.
- `VisitorHasherTest::testSameVisitorKeepsOneHashPerRollupDay` — one visitor hashes
  identically morning and evening, and differently the next day.

Verified against the real aggregate: one visitor with five events from `00:00:05` to
`23:59:58` yields `uniques=1` for that date.

> `rotation_hours` values other than 24 split a calendar day across two salts and
> reintroduce the inflation. Documented in `config/config.example.php`. The unused
> `previous_window_hours` key was removed — nothing ever read it, and period alignment
> makes a grace window unnecessary.

---

## Regression tests

Added:

- Malformed queued payload does not prevent later valid events from processing.
- Durations of `3599`, `3600` and `86400` seconds format correctly.
- Expired-salt rotation yields one winner and preserves the previous salt.
- Salt is stable across a UTC day and rotates at the boundary.
- One visitor keeps a single hash per rollup day.

Still missing:

- **Malformed ingestion JSON returns `400`, not an uncaught exception.** The fix is in
  place but untested: `readRequestPayload()` reads `php://input` directly, so it is not
  reachable from a unit test without extracting a seam for the raw request body.
- **Simultaneous salt initialization produces one database row and one effective salt.**
  Needs genuine parallel connections; the current test is single-threaded.
- **Portfolio engagement average is an `int`.** `DashboardQueryTest` exercises
  `portfolioOverview()` but asserts nothing about the type of `avg_engagement_seconds`.

## Operational gap

`clearstats:events:rejected` is capped at 1000 entries and holds no event bodies, so it
cannot grow unbounded in the Redis instance shared with skyggn and cannot outlive the
retention model. `bin/queue-worker.php` reports `rejected=N` so a rising count is
visible.

What is still missing is alerting: nothing notices when that count climbs. A sustained
bad-payload source would be silently discarded apart from the worker's stdout. Deciding
where that signal should go — log, metric, or health endpoint — is still open.
