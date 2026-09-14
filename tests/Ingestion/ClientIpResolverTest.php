<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\ClientIpResolver;
use PHPUnit\Framework\TestCase;

final class ClientIpResolverTest extends TestCase
{
    public function testDirectModeIgnoresForwardedHeaders(): void
    {
        $resolver = new ClientIpResolver(['10.0.0.0/8']);

        $this->assertSame('203.0.113.10', $resolver->resolve('direct', [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.4',
        ]));
    }

    public function testTrustedProxyMaySupplyConfiguredForwardedSource(): void
    {
        $resolver = new ClientIpResolver(['10.0.0.0/8']);

        $this->assertSame('198.51.100.4', $resolver->resolve('x-forwarded-for', [
            'REMOTE_ADDR' => '10.12.0.4',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.4, 10.12.0.4',
        ]));
    }

    public function testUntrustedProxyFallsBackToRemoteAddress(): void
    {
        $resolver = new ClientIpResolver(['10.0.0.0/8']);

        $this->assertSame('203.0.113.10', $resolver->resolve('cf-connecting-ip', [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.4',
        ]));
    }
}
