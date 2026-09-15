Yes. I found three high-confidence latent failures and two correctness risks in the current ClearStats repository.

## Priority findings

### 1. Malformed tracking JSON can produce HTTP 500

In [`src/Ingestion/EventController.php`](https://github.com/SilverDay/clearstats/blob/main/src/Ingestion/EventController.php), `readRequestPayload()` uses:

```php
$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
```

But the resulting `JsonException` is not caught. A malformed request to the public ingestion endpoint therefore bypasses:

```php
$this->respondJson(400, ['error' => 'Invalid JSON payload.']);
```

Fix:

```php
try {
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException) {
    return null;
}
```

This should be fixed soon because arbitrary Internet clients can trigger it.

---

### 2. One corrupted Redis event can repeatedly stop the queue worker

In [`src/Ingestion/QueueWorker.php`](https://github.com/SilverDay/clearstats/blob/main/src/Ingestion/QueueWorker.php):

```php
$event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
```

An invalid payload throws and terminates the entire worker. The payload remains in the processing list. On the next run, `recoverProcessing()` returns it to the queue, where it can fail again.

This is a classic poison-message loop.

The worker should catch failures per event and move bad payloads to a dead-letter queue, then acknowledge/remove them from processing:

```php
try {
    $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($event)) {
        throw new \RuntimeException('Queued event must decode to an object.');
    }

    // Insert event...
    $this->queue->acknowledge($payload);
    $processed++;
} catch (\Throwable $exception) {
    $this->queue->reject($payload, $exception->getMessage());
}
```

Do not log the complete payload because analytics fields could contain sensitive or unexpected data.

---

### 3. First-use salt initialization has a race condition

In [`src/Ingestion/SaltProvider.php`](https://github.com/SilverDay/clearstats/blob/main/src/Ingestion/SaltProvider.php):

```php
$row = $this->pdo
    ->query('SELECT current_salt, generated_at FROM salt_state WHERE id = 1')
    ->fetch();

if (!is_array($row)) {
    // ...
    INSERT INTO salt_state (id, ...)
}
```

Two simultaneous first tracking requests can both see no row and both attempt the insert. One receives a duplicate-primary-key exception, causing ingestion to fail.

Use an atomic upsert and then read the stored value. Importantly, the request that loses the race must use the winner’s salt, not its independently generated salt.

This needs a concurrency-oriented integration test.

## Correctness risks

### 4. Engagement duration wraps after one hour

Both dashboard branches use:

```php
gmdate('i:s', $overview['avg_engagement_seconds']);
```

`gmdate('i:s', 3600)` returns `00:00`, not `60:00`. Since engagement is allowed up to 86,400 seconds, longer averages will display incorrectly.

Use an explicit duration formatter:

```php
private function formatDuration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    return $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
        : sprintf('%02d:%02d', $minutes, $remainingSeconds);
}
```

---

### 5. Salt rotation does not implement the documented boundary behaviour

`SaltProvider` stores `previous_salt`, but `VisitorHasher` appears to use only `currentSalt()`. At rotation time, simultaneous requests can therefore be hashed with different salts around the boundary.

This will not crash the application, but it can temporarily inflate unique-visitor counts. The class documentation explicitly promises current/previous-salt handling that the implementation does not currently provide.

## Missing regression tests

I would add these tests first:

* Malformed ingestion JSON returns `400`, not an uncaught exception.
* A malformed queued payload does not prevent later valid events from processing.
* Simultaneous salt initialization produces one database row and one effective salt.
* Portfolio engagement average is an `int`.
* Durations of `3599`, `3600`, and `86400` seconds format correctly.
* Salt rotation behaviour matches the intended visitor-counting model.

The malformed public JSON and poison-queue issues are the most urgent. They have the same character as the `gmdate()` issue: normal tests pass, but a particular production input activates a previously untouched failure path.
