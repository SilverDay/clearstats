<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

final class LanguageResolver
{
    public function resolve(array $server): string
    {
        $value = trim((string) ($server['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($value === '') return '';
        $language = strtolower(trim(explode(',', $value)[0]));
        $language = preg_replace('/[^a-z0-9-].*$/', '', $language) ?? '';
        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language) === 1 ? substr($language, 0, 5) : '';
    }
}
