<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

/**
 * Computes the per-request visitor hash per docs/analytics-platform-spec.md §3.
 *
 * visitor_hash = HMAC-SHA256(daily_salt, site_domain || client_ip || user_agent)
 *
 * - Single shared salt across all sites (decisions log #1) — domain is baked
 *   into the hash input specifically to prevent cross-site correlation.
 * - Callers MUST NOT persist $clientIp or $userAgent anywhere. This class
 *   only ever returns the hash; it does not log or store its inputs.
 * - Pass the event's own timestamp so the salt matches the rollup day the
 *   event will be counted in; otherwise a visitor seen either side of a
 *   rotation is counted twice.
 */
final class VisitorHasher
{
    public function __construct(
        private readonly SaltProvider $saltProvider,
    ) {}

    public function hash(string $siteDomain, string $clientIp, string $userAgent, ?int $timestamp = null): string
    {
        $salt = $this->saltProvider->saltForTimestamp($timestamp ?? time());

        return hash_hmac(
            'sha256',
            $siteDomain . '|' . $clientIp . '|' . $userAgent,
            $salt,
        );
    }
}
