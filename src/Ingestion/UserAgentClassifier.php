<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

final class UserAgentClassifier
{
    /**
     * @return array{device_type: string, browser: string}
     */
    public function classify(string $userAgent): array
    {
        $lower = strtolower($userAgent);
        $device = str_contains($lower, 'ipad') || str_contains($lower, 'tablet')
            ? 'tablet'
            : (str_contains($lower, 'mobile') || str_contains($lower, 'android') ? 'mobile' : 'desktop');
        $browser = match (true) {
            str_contains($lower, 'edg/') => 'edge',
            str_contains($lower, 'firefox/') => 'firefox',
            str_contains($lower, 'chrome/') || str_contains($lower, 'chromium/') => 'chrome',
            str_contains($lower, 'safari/') && !str_contains($lower, 'chrome/') => 'safari',
            str_contains($lower, 'trident/') || str_contains($lower, 'msie ') => 'ie',
            default => 'other',
        };

        return ['device_type' => $device, 'browser' => $browser];
    }
}
