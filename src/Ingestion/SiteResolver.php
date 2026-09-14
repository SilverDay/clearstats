<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

interface SiteResolver
{
    /**
     * @return array{id: string, domain: string, ip_source: string}|null
     */
    public function resolve(string $siteId): ?array;
}
