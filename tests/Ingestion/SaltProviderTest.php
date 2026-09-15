<?php

declare(strict_types=1);

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\SaltProvider;
use ClearStats\Tests\TestDatabase;
use PHPUnit\Framework\TestCase;

final class SaltProviderTest extends TestCase
{
    public function testPersistentSaltIsSharedAndRotates(): void
    {
        $database = TestDatabase::create();
        $first = new SaltProvider(null, $database->pdo(), 24);
        $current = $first->currentSalt();
        $this->assertSame(64, strlen($current));
        $this->assertSame($current, (new SaltProvider(null, $database->pdo(), 24))->currentSalt());

        $first->rotate();
        $rotated = $first->currentSalt();
        $this->assertNotSame($current, $rotated);
        $this->assertSame($rotated, (new SaltProvider(null, $database->pdo(), 24))->currentSalt());
    }

    public function testFixedSaltRemainsDeterministicForTests(): void
    {
        $provider = new SaltProvider('test-salt');
        $provider->rotate();

        $this->assertSame('test-salt', $provider->currentSalt());
    }

    public function testExpiredSaltRotationKeepsOneWinner(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $pdo->exec("INSERT INTO salt_state (id, current_salt, previous_salt, generated_at) VALUES (1, 'old-salt', NULL, '2020-01-01 00:00:00')");

        $current = (new SaltProvider(null, $pdo, 24))->currentSalt();
        $row = $pdo->query('SELECT current_salt, previous_salt FROM salt_state WHERE id = 1')->fetch();

        $this->assertSame($current, $row['current_salt']);
        $this->assertSame('old-salt', $row['previous_salt']);
        $this->assertNotSame('old-salt', $current);
    }

    public function testSaltIsStableAcrossAUtcDayAndRotatesAtTheBoundary(): void
    {
        $database = TestDatabase::create();
        $pdo = $database->pdo();
        $provider = new SaltProvider(null, $pdo, 24);

        $firstRequest = (int) strtotime('2026-03-10 00:00:01 UTC');
        $midDay = (int) strtotime('2026-03-10 13:45:00 UTC');
        $lastSecond = (int) strtotime('2026-03-10 23:59:59 UTC');
        $nextDay = (int) strtotime('2026-03-11 00:00:00 UTC');

        $salt = $provider->saltForTimestamp($firstRequest);

        $this->assertSame($salt, $provider->saltForTimestamp($midDay));
        $this->assertSame($salt, $provider->saltForTimestamp($lastSecond));

        $rotated = $provider->saltForTimestamp($nextDay);
        $this->assertNotSame($salt, $rotated);
        $this->assertSame($salt, $pdo->query('SELECT previous_salt FROM salt_state WHERE id = 1')->fetchColumn());
    }
}
