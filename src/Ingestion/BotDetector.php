<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

final class BotDetector
{
    private const SIGNATURES = [
        'bot',
        'crawler',
        'spider',
        'slurp',
        'headlesschrome',
        'lighthouse',
        'facebookexternalhit',
        'bytespider',
    ];

    public function isLikelyBot(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);
        foreach (self::SIGNATURES as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
}
