<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Tests\Tracking;

use PHPUnit\Framework\TestCase;

final class TrackScriptTest extends TestCase
{
    public function testTrackingScriptUsesMinimalPrivacySafePayload(): void
    {
        $script = file_get_contents(__DIR__ . '/../../public/js/track.js');
        $this->assertNotFalse($script);

        $this->assertStringContainsString('data-site-id', $script);
        $this->assertStringContainsString('sendBeacon', $script);
        $this->assertStringContainsString('keepalive', $script);
        $this->assertStringNotContainsString('window.localStorage', $script, 'The script must not access browser storage APIs.');
        $this->assertStringNotContainsString('window.sessionStorage', $script, 'The script must not access browser storage APIs.');
        $this->assertStringNotContainsString('screen_width', $script);
        $this->assertStringNotContainsString('user_id', $script, 'The public tracker must not send client-supplied user identity.');
        $this->assertStringContainsString('navigator.sendBeacon', $script);
        $this->assertStringContainsString('fetch(endpoint', $script);
        $this->assertStringContainsString('/api/event?site_id=', $script);
        $this->assertStringContainsString('history.pushState', $script);
        $this->assertStringContainsString('history.replaceState', $script);
        $this->assertStringContainsString("addEventListener('popstate'", $script);
    }
}
