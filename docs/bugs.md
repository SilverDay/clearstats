# Bug report — triage status

Status as of 2026-09-15. Four of five findings are fixed and pushed; one is
partially addressed and still needs a decision. See the closing section for the
regression tests that are still missing.

| # | Finding | Status |
|---|---|---|
| 1 | Malformed tracking JSON can produce HTTP 500 | **Fixed** |
| 2 | One corrupted Redis event can repeatedly stop the queue worker | **Fixed** |
| 3 | First-use salt initialization has a race condition | **Fixed** |
| 4 | Engagement duration wraps after one hour | **Fixed** |
| 5 | Salt rotation does not implement the documented boundary behaviour | **Partially fixed — open** |

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

**Resolution.** `QueueWorker::processBatch()` now guards each event individually.
`EventQueue::reject()` moves the payload to `clearstats:events:dead-letter` and
acknowledges it, so it leaves the processing list permanently and later valid events
still drain. The dead-letter record stores the payload and a reason string; it is not
written to the application log, so analytics fields are not scattered into log files.

**Test.** `QueueWorkerTest::testRejectsMalformedEventAndContinuesWithLaterValidEvent`
pushes `{malformed` ahead of a valid event and asserts the valid one is still inserted,
the dead-letter list has one entry, and the processing list is empty.

> **Operational note:** nothing drains `clearstats:events:dead-letter` yet. It will grow
> unbounded under a sustained bad-payload source. Needs a retention or alerting decision.

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

### 5. Salt rotation does not implement the documented boundary behaviour — Partially fixed, still open

**What was fixed.** Rotation is now atomic (see #3), so simultaneous requests can no
longer rotate twice and destroy a salt that is still in use.

**What is still broken.** The underlying finding stands:
[`VisitorHasher::hash()`](../src/Ingestion/VisitorHasher.php) still reads only
`currentSalt()`. `SaltProvider` writes `previous_salt`, but nothing ever reads it, so
the class documentation still promises current/previous handling that the implementation
does not provide. Requests either side of a rotation boundary continue to produce
different hashes for the same visitor, which can temporarily inflate unique-visitor
counts. This does not crash anything.

**Why it was not fixed here.** Resolving it properly is a data-model decision, not a
patch, and spec §3.2 leaves the boundary handling explicitly "to be defined at
implementation time". The options are not equivalent:

- **Bucket by the salt's generation timestamp** rather than wall clock, as §3.2
  recommends. Cleanest, but the rollup's notion of a "day" then has to follow the salt
  epoch rather than the calendar date, which affects `daily_*_stats` keys.
- **Hash with the current salt and additionally check the previous salt** when counting
  uniques. Preserves calendar-day rollups, but the dedupe cost moves into the rollup and
  raw events would need to retain both hashes.
- **Accept the boundary error.** Rotation is once per 24h, so the inflation is bounded
  and arguably below the noise floor of an aggregate-only product.

This needs an owner decision before implementation. Until then, the docblock on
`VisitorHasher` overstates what the code does.

---

## Regression tests

Added:

- Malformed queued payload does not prevent later valid events from processing.
- Durations of `3599`, `3600` and `86400` seconds format correctly.
- Expired-salt rotation yields one winner and preserves the previous salt.

Still missing:

- **Malformed ingestion JSON returns `400`, not an uncaught exception.** The fix is in
  place but untested: `readRequestPayload()` reads `php://input` directly, so it is not
  reachable from a unit test without extracting a seam for the raw request body.
- **Simultaneous salt initialization produces one database row and one effective salt.**
  Needs genuine parallel connections; the current test is single-threaded.
- **Portfolio engagement average is an `int`.** `DashboardQueryTest` exercises
  `portfolioOverview()` but asserts nothing about the type of `avg_engagement_seconds`.
- **Salt rotation matches the intended visitor-counting model.** Blocked on the decision
  in finding 5 — there is no agreed behaviour to assert yet.
