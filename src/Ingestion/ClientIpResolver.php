<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

final class ClientIpResolver
{
    /**
     * @param list<string> $trustedProxyIps
     */
    public function __construct(
        private readonly array $trustedProxyIps = [],
    ) {}

    /**
     * @param array<string, mixed> $server
     */
    public function resolve(string $source, array $server): string
    {
        $remoteAddress = $this->validIp((string) ($server['REMOTE_ADDR'] ?? '')) ?? '0.0.0.0';
        if ($source === 'direct' || !$this->isTrustedProxy($remoteAddress)) {
            return $remoteAddress;
        }

        if ($source === 'cf-connecting-ip') {
            return $this->validIp((string) ($server['HTTP_CF_CONNECTING_IP'] ?? '')) ?? $remoteAddress;
        }

        if ($source === 'x-forwarded-for') {
            $forwarded = explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
            return $this->validIp(trim((string) ($forwarded[0] ?? ''))) ?? $remoteAddress;
        }

        return $remoteAddress;
    }

    private function validIp(string $value): ?string
    {
        return filter_var(trim($value), FILTER_VALIDATE_IP) === false ? null : trim($value);
    }

    private function isTrustedProxy(string $address): bool
    {
        foreach ($this->trustedProxyIps as $trusted) {
            if ($address === $trusted || $this->matchesCidr($address, $trusted)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCidr(string $address, string $cidr): bool
    {
        if (!str_contains($cidr, '/') || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        [$network, $bits] = explode('/', $cidr, 2);
        if (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || !ctype_digit($bits)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $addressLong = ip2long($address);
        $networkLong = ip2long($network);
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($addressLong & $mask) === ($networkLong & $mask);
    }
}
