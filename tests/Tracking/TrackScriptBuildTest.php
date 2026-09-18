<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Tracking;

use PHPUnit\Framework\TestCase;

/**
 * public/js/track.js is a minified build of assets/track.js (see
 * bin/build-track-js.php) — this is what every already-installed site's
 * <script src> actually points at. These are lighter checks on the built
 * artifact itself (minification renames local variables, so it can't carry
 * TrackScriptTest's structural assertions); functional equivalence with the
 * source was verified by executing both in a stubbed DOM and diffing their
 * actual output payloads, not by a test in this suite (no JS runtime
 * dependency in the PHPUnit run).
 */
final class TrackScriptBuildTest extends TestCase
{
    public function testBuiltScriptExistsIsMinifiedAndKeepsKeyLiterals(): void
    {
        $built = file_get_contents(__DIR__ . '/../../public/js/track.js');
        $this->assertNotFalse($built);
        $this->assertNotSame('', trim($built));

        $source = file_get_contents(__DIR__ . '/../../assets/track.js');
        $this->assertNotFalse($source);

        // Sanity check it's actually a minified build, not an accidental
        // stale copy of the readable source.
        $this->assertLessThan(strlen($source) / 2, strlen($built), 'public/js/track.js does not look minified — run php bin/build-track-js.php.');

        // String literals and property/method names survive mangling (only
        // local variable/function identifiers get renamed), so these are
        // still meaningful checks against the built file.
        $this->assertStringContainsString('data-site-id', $built);
        $this->assertStringContainsString('navigator.sendBeacon', $built);
        $this->assertStringContainsString('/api/event?site_id=', $built);
        $this->assertStringContainsString('data-outbound-links', $built);
        $this->assertStringContainsString('data-file-downloads', $built);
        $this->assertStringContainsString('data-404', $built);
        $this->assertStringNotContainsString('window.localStorage', $built);
        $this->assertStringNotContainsString('window.sessionStorage', $built);

        // Public API called by site owners' own page code must keep its
        // real names — only this internal script's own local variables are
        // safe to mangle.
        foreach (['trackPageview', 'trackEvent', 'trackConversion', 'trackClick', 'trackScroll'] as $method) {
            $this->assertStringContainsString('window.clearstats.' . $method, $built);
        }
    }
}
