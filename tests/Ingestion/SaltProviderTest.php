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
}
