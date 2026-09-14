<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Domain;

final class DomainNormalizer
{
    public static function normalize(string $domain): string
    {
        $normalized = strtolower(trim($domain));
        $normalized = rtrim($normalized, '.');

        if (str_starts_with($normalized, 'www.')) {
            $normalized = substr($normalized, 4);
        }

        return $normalized;
    }

    public static function matches(string $leftDomain, string $rightDomain): bool
    {
        return self::normalize($leftDomain) === self::normalize($rightDomain);
    }
}
