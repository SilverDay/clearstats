<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Domain;

final class SiteValidation
{
    public function isValidSiteDomain(string $configuredDomain, string $requestHost): bool
    {
        return DomainNormalizer::matches($configuredDomain, $requestHost);
    }
}
