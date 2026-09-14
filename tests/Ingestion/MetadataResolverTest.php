<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\CountryResolver;
use ClearStats\Ingestion\UserAgentClassifier;
use PHPUnit\Framework\TestCase;

final class MetadataResolverTest extends TestCase
{
    public function testCountryRequiresTrustedProxyAndValidCode(): void
    {
        $resolver = new CountryResolver(['10.0.0.0/8', '127.0.0.1']);

        $this->assertSame('DE', $resolver->resolve(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_IPCOUNTRY' => 'de']));
        $this->assertSame('', $resolver->resolve(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_CF_IPCOUNTRY' => 'DE']));
        $this->assertSame('', $resolver->resolve(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_IPCOUNTRY' => 'unknown']));
    }

    public function testClassifiesCoarseDeviceAndBrowserMetadata(): void
    {
        $classifier = new UserAgentClassifier();

        $this->assertSame(['device_type' => 'mobile', 'browser' => 'chrome'], $classifier->classify('Mozilla/5.0 Android Mobile Chrome/120'));
        $this->assertSame(['device_type' => 'desktop', 'browser' => 'safari'], $classifier->classify('Mozilla/5.0 Macintosh Safari/17.0'));
    }
}
