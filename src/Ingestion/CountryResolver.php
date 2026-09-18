<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Ingestion;

final class CountryResolver
{
    private readonly ?\MaxMind\Db\Reader $reader;
    private readonly ?\MaxMind\Db\Reader $cityReader;

    /**
     * @param list<string> $trustedProxyIps
     */
    public function __construct(
        private readonly array $trustedProxyIps = [],
        ?string $countryDatabasePath = null,
        ?string $cityDatabasePath = null,
    ) {
        $this->reader = $countryDatabasePath !== null && is_readable($countryDatabasePath)
            ? new \MaxMind\Db\Reader($countryDatabasePath)
            : null;
        // Optional, config-gated: no CF-IPCountry-style proxy header fallback
        // exists for region/city, so without a City DB configured these are
        // always empty rather than degrading to a coarser signal.
        $this->cityReader = $cityDatabasePath !== null && is_readable($cityDatabasePath)
            ? new \MaxMind\Db\Reader($cityDatabasePath)
            : null;
    }

    /**
     * @param array<string, mixed> $server
     */
    public function resolve(array $server, string $clientIp = ''): string
    {
        if ($this->reader !== null && filter_var($clientIp, FILTER_VALIDATE_IP) !== false) {
            try {
                $country = $this->reader->get($clientIp)['country']['iso_code'] ?? '';
                return is_string($country) && preg_match('/^[A-Z]{2}$/', $country) === 1
                    ? $country
                    : '';
            } catch (\Throwable) {
                // Fall back to the trusted proxy header below.
            }
        }

        $remoteAddress = (string) ($server['REMOTE_ADDR'] ?? '');
        if (!$this->isTrustedProxy($remoteAddress)) {
            return '';
        }

        $country = strtoupper(trim((string) ($server['HTTP_CF_IPCOUNTRY'] ?? '')));
        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : '';
    }

    /**
     * @return array{region: string, city: string}
     */
    public function resolveRegionCity(string $clientIp): array
    {
        $empty = ['region' => '', 'city' => ''];
        if ($this->cityReader === null || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return $empty;
        }

        try {
            $data = $this->cityReader->get($clientIp);
            $region = (string) ($data['subdivisions'][0]['iso_code'] ?? '');
            $city = trim((string) ($data['city']['names']['en'] ?? ''));

            return [
                'region' => preg_match('/^[A-Z0-9]{1,8}$/', $region) === 1 ? $region : '',
                'city' => $city === '' ? '' : mb_substr($city, 0, 255),
            ];
        } catch (\Throwable) {
            return $empty;
        }
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

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
        return (ip2long($address) & $mask) === (ip2long($network) & $mask);
    }
}
