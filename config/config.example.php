<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

/**
 * Copy to config.php (gitignored) and fill in real values.
 * Never commit real credentials.
 */
return [
    'app' => [
        'https' => true,
    ],
    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=clearstats;charset=utf8mb4',
        'user'     => 'clearstats',
        'password' => '',
    ],

    // Redis instance shared with skyggn — see /CLAUDE.md on key namespacing.
    // All ClearStats keys MUST use the 'clearstats:' prefix.
    'redis' => [
        'host'         => '127.0.0.1',
        'port'         => 6379,
        'key_prefix'   => 'clearstats:',
        'database'     => null, // set to a dedicated logical DB index if isolation from skyggn is needed
    ],

    // Ingestion validation and request guards.
    'ingestion' => [
        'max_payload_bytes' => 32768,
        'rate_limit_per_minute' => 120,
        'request_timeout_seconds' => 10,
        'trusted_proxy_ips' => [
            '127.0.0.1',
            '10.0.0.0/8',
        ],
    ],

    // Salt rotation — see spec §3.2. Never log or expose this value.
    // Rotation is aligned to UTC period boundaries, so 24 gives exactly one
    // salt per calendar day. Other values split a day across two salts and
    // inflate unique-visitor counts.
    'salt' => [
        'rotation_hours' => 24,
    ],

    // Default retention policy for raw events.
    'site' => [
        'default_raw_event_retention_days' => 30,
    ],
    'auth' => [
        'failed_login_limit' => 10,
        'failed_login_window_seconds' => 900,
    ],
    'geoip' => [
        'country_database_path' => '/var/lib/GeoIP/GeoLite2-Country.mmdb',
    ],
];
