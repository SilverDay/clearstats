<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Ingestion;

use ClearStats\Ingestion\CountryResolver;
use ClearStats\Ingestion\UserAgentClassifier;
use ClearStats\Ingestion\LanguageResolver;
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

    public function testRegionCityStaysEmptyWithoutACityDatabaseConfigured(): void
    {
        // No CF-IPCountry-style proxy fallback exists for region/city, so
        // without a GeoLite2-City file this must always degrade to empty
        // rather than guessing from the country-only signal.
        $resolver = new CountryResolver(['10.0.0.0/8']);

        $this->assertSame(['region' => '', 'city' => ''], $resolver->resolveRegionCity('203.0.113.10'));
        $this->assertSame(['region' => '', 'city' => ''], $resolver->resolveRegionCity('not-an-ip'));
    }

    public function testClassifiesCoarseDeviceAndBrowserMetadata(): void
    {
        $classifier = new UserAgentClassifier();

        $this->assertSame(['device_type' => 'mobile', 'browser' => 'chrome', 'operating_system' => 'android'], $classifier->classify('Mozilla/5.0 Android Mobile Chrome/120'));
        $this->assertSame(['device_type' => 'desktop', 'browser' => 'safari', 'operating_system' => 'macos'], $classifier->classify('Mozilla/5.0 Macintosh Safari/17.0'));
    }

    public function testNormalizesPreferredLanguage(): void
    {
        $resolver = new LanguageResolver();

        $this->assertSame('de-de', $resolver->resolve(['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8']));
        $this->assertSame('', $resolver->resolve(['HTTP_ACCEPT_LANGUAGE' => 'not-valid']));
    }
}
