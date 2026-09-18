<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use ClearStats\Rollup\DashboardQuery;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardController
{
    private const RANGE_LABELS = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'month' => 'This month (UTC)',
    ];

    private readonly AuthSession $session;

    public function __construct(
        private readonly ?DashboardQuery $query = null,
        private readonly ?SiteRepository $sites = null,
        ?AuthSession $session = null,
    ) {
        $this->session = $session ?? new AuthSession();
    }

    public function index(): void
    {
        $pageviews = '0';
        $visitors = '0';
        $sessions = '0';
        $bounceRate = '0%';
        $engagement = '0s';
        $chartBars = '<p class="chart-empty">No rollup data for this period.</p>';
        $chartAxis = '';
        $topPageRows = $this->emptyRow('No rollup data for this period.', 4);
        $referrerRows = $this->emptyRow();
        $countryRows = $this->emptyRow();
        $deviceRows = $this->emptyRow();
        $osRows = $this->emptyRow('No OS data for this period.');
        $browserRows = $this->emptyRow('No browser data for this period.');
        $languageRows = $this->emptyRow('No language data for this period.');
        $eventRows = $this->emptyRow('No conversion events for this period.');
        $campaignRows = $this->emptyRow('No campaign data for this period.');
        $regionRows = $this->emptyRow('No region data for this period.');
        $cityRows = $this->emptyRow('No city data for this period.');
        $campaignTermRows = $this->emptyRow('No campaign term data for this period.');
        $campaignContentRows = $this->emptyRow('No campaign content data for this period.');
        $revenueRows = $this->emptyRow('No revenue data for this period.', 4);
        $siteOptions = '<option value="">No assigned sites</option>';
        $selectedSiteLabel = 'No site selected';

        $today = gmdate('Y-m-d');
        $range = (string) ($_GET['range'] ?? '7d');
        [$startDate, $endDate, $rangeLabel, $chartDays] = $this->resolveRange(
            $range,
            $today,
            isset($_GET['start']) ? (string) $_GET['start'] : null,
            isset($_GET['end']) ? (string) $_GET['end'] : null,
        );

        if ($this->query !== null && $this->sites !== null && ($userId = $this->session->userId()) !== null) {
            $assignedSites = $this->sites->forUser($userId);
            $selectedSiteId = (string) ($_GET['site'] ?? 'all');
            $siteOptions = '<option value="all"' . ($selectedSiteId === 'all' ? ' selected' : '') . '>All assigned sites</option>';
            foreach ($assignedSites as $site) {
                $siteId = (string) $site['id'];
                $selected = $siteId === $selectedSiteId ? ' selected' : '';
                $siteOptions .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8'),
                    $selected,
                    htmlspecialchars((string) $site['name'], ENT_QUOTES, 'UTF-8'),
                );
            }
            $siteIds = array_map(static fn(array $site): string => (string) $site['id'], $assignedSites);
            $selectedSite = null;
            foreach ($assignedSites as $site) {
                if ((string) $site['id'] === $selectedSiteId) {
                    $selectedSite = $site;
                    break;
                }
            }

            if ($selectedSiteId === 'all' && $siteIds !== []) {
                $selectedSiteLabel = 'All assigned sites';
                $overview = $this->query->portfolioOverview($siteIds, $endDate, $startDate);
                $pageviews = number_format($overview['pageviews']);
                $visitors = number_format($overview['unique_visitor_hashes_count']);
                $sessions = number_format($overview['sessions']);
                $bounceRate = $overview['bounce_rate'] === null ? '0%' : number_format($overview['bounce_rate'], 1) . '%';
                $engagement = $this->formatDuration((int) $overview['avg_engagement_seconds']);
                $chart = $this->formatChart($this->query->portfolioDailyPageviews($siteIds, $endDate, $chartDays));
                if ($chart !== null) {
                    $chartBars = $chart['bars'];
                    $chartAxis = $chart['axis'];
                }
                $topPageRows = $this->formatPageRows($this->query->portfolioTopPages($siteIds, $endDate, 5, $startDate));
                $referrerRows = $this->formatRows($this->query->portfolioTopReferrers($siteIds, $endDate, 5, $startDate), 'referrer_domain');
                $countryRows = $this->formatRows($this->query->portfolioTopCountries($siteIds, $endDate, 5, $startDate), 'country_code');
                $deviceRows = $this->formatRows($this->query->portfolioDevices($siteIds, $endDate, $startDate), 'device_type');
                $osRows = $this->formatRows($this->query->portfolioOperatingSystems($siteIds, $endDate, $startDate), 'operating_system');
                $browserRows = $this->formatRows($this->query->portfolioBrowsers($siteIds, $endDate, $startDate), 'browser');
                $languageRows = $this->formatRows($this->query->portfolioLanguages($siteIds, $endDate, $startDate), 'language_code');
                $eventRows = $this->formatRows($this->query->portfolioTopEvents($siteIds, $endDate, 5, $startDate), 'event_name', 'events');
                $campaignRows = $this->formatCampaignRows($this->query->portfolioCampaigns($siteIds, $endDate, 5, $startDate));
                $regionRows = $this->formatRegionRows($this->query->portfolioTopRegions($siteIds, $endDate, 5, $startDate));
                $cityRows = $this->formatCityRows($this->query->portfolioTopCities($siteIds, $endDate, 5, $startDate));
                $campaignTermRows = $this->formatRows($this->query->portfolioTopCampaignTerms($siteIds, $endDate, 5, $startDate), 'campaign_term');
                $campaignContentRows = $this->formatRows($this->query->portfolioTopCampaignContent($siteIds, $endDate, 5, $startDate), 'campaign_content');
                $revenueRows = $this->formatRevenueRows($this->query->portfolioRevenue($siteIds, $endDate, 5, $startDate));
            } elseif ($selectedSite !== null) {
                $selectedSiteLabel = (string) $selectedSite['name'];
                $overview = $this->query->overview($selectedSiteId, $endDate, $startDate);
                $pageviews = number_format($overview['pageviews']);
                $visitors = number_format($overview['unique_visitor_hashes_count']);
                $sessions = number_format($overview['sessions']);
                $bounceRate = $overview['bounce_rate'] === null ? '0%' : number_format($overview['bounce_rate'], 1) . '%';
                $engagement = $this->formatDuration((int) $overview['avg_engagement_seconds']);
                $chart = $this->formatChart($this->query->dailyPageviews($selectedSiteId, $endDate, $chartDays));
                if ($chart !== null) {
                    $chartBars = $chart['bars'];
                    $chartAxis = $chart['axis'];
                }
                $topPageRows = $this->formatPageRows($this->query->topPages($selectedSiteId, $endDate, 5, $startDate));
                $referrerRows = $this->formatRows($this->query->topReferrers($selectedSiteId, $endDate, 5, $startDate), 'referrer_domain');
                $countryRows = $this->formatRows($this->query->topCountries($selectedSiteId, $endDate, 5, $startDate), 'country_code');
                $deviceRows = $this->formatRows($this->query->devices($selectedSiteId, $endDate, $startDate), 'device_type');
                $osRows = $this->formatRows($this->query->operatingSystems($selectedSiteId, $endDate, $startDate), 'operating_system');
                $browserRows = $this->formatRows($this->query->browsers($selectedSiteId, $endDate, $startDate), 'browser');
                $languageRows = $this->formatRows($this->query->languages($selectedSiteId, $endDate, $startDate), 'language_code');
                $eventRows = $this->formatRows($this->query->topEvents($selectedSiteId, $endDate, 5, $startDate), 'event_name', 'events');
                $campaignRows = $this->formatCampaignRows($this->query->campaigns($selectedSiteId, $endDate, 5, $startDate));
                $regionRows = $this->formatRegionRows($this->query->topRegions($selectedSiteId, $endDate, 5, $startDate));
                $cityRows = $this->formatCityRows($this->query->topCities($selectedSiteId, $endDate, 5, $startDate));
                $campaignTermRows = $this->formatRows($this->query->topCampaignTerms($selectedSiteId, $endDate, 5, $startDate), 'campaign_term');
                $campaignContentRows = $this->formatRows($this->query->topCampaignContent($selectedSiteId, $endDate, 5, $startDate), 'campaign_content');
                $revenueRows = $this->formatRevenueRows($this->query->revenueByGoal($selectedSiteId, $endDate, 5, $startDate));
            }
        }

        $rangePicker = $this->formatRangePicker($selectedSiteId ?? 'all', $range, $startDate, $endDate);

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats Dashboard</title>
</head>
<body>
    <main class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-sub">{{SELECTED_SITE}}</p>
            </div>
            <div class="actions">
                <form method="get" action="/dashboard">
                    <input type="hidden" name="range" value="{{RANGE}}">
                    <select name="site" aria-label="Select site" onchange="this.form.submit()">{{SITE_OPTIONS}}</select>
                </form>
                <a class="btn" href="/sites">Manage sites</a>
                <a class="btn btn-primary" href="/sites/new">New site</a>
            </div>
        </div>

        {{RANGE_PICKER}}

        <section class="grid grid-5 stat-grid">
            <article class="stat">
                <span class="stat-label">Pageviews</span>
                <span class="stat-value">{{PAGEVIEWS}}</span>
                <span class="stat-note">{{RANGE_LABEL}}</span>
            </article>
            <article class="stat">
                <span class="stat-label">Visitors</span>
                <span class="stat-value">{{VISITORS}}</span>
                <span class="stat-note">Aggregate count</span>
            </article>
            <article class="stat">
                <span class="stat-label">Sessions</span>
                <span class="stat-value">{{SESSIONS}}</span>
                <span class="stat-note">Ephemeral, in-memory client sessions</span>
            </article>
            <article class="stat">
                <span class="stat-label">Bounce rate</span>
                <span class="stat-value">{{BOUNCE_RATE}}</span>
                <span class="stat-note">Single-page sessions</span>
            </article>
            <article class="stat">
                <span class="stat-label">Avg. engagement</span>
                <span class="stat-value">{{ENGAGEMENT}}</span>
                <span class="stat-note">Time until page exit</span>
            </article>
        </section>

        <div class="stack">
            <div class="grid-split">
                <section class="card card-chart">
                    <h2 class="card-title">Pageviews</h2>
                    <div class="chart">{{CHART_BARS}}</div>
                    {{CHART_AXIS}}
                </section>
                <section class="card">
                    <h2 class="card-title">Top pages</h2>
                    <div class="table-wrap"><table class="table table-fixed">
                        <thead><tr><th>Page</th><th>Views</th><th>Visitors</th><th>Bounce</th></tr></thead>
                        <tbody>{{TOP_PAGES}}</tbody>
                    </table></div>
                </section>
            </div>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Top referrers</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Referrer</th><th>Visits</th></tr></thead>
                        <tbody>{{REFERRERS}}</tbody>
                    </table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Countries</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Country</th><th>Visits</th></tr></thead>
                        <tbody>{{COUNTRIES}}</tbody>
                    </table></div>
                </section>
            </div>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Regions</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Region</th><th>Visits</th></tr></thead>
                        <tbody>{{REGIONS}}</tbody>
                    </table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Cities</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>City</th><th>Visits</th></tr></thead>
                        <tbody>{{CITIES}}</tbody>
                    </table></div>
                </section>
            </div>

            <section class="card">
                <h2 class="card-title">Devices</h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Device</th><th>Visits</th></tr></thead>
                    <tbody>{{DEVICES}}</tbody>
                </table></div>
            </section>

            <div class="grid grid-3">
                <section class="card">
                    <h2 class="card-title">Operating systems</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>OS</th><th>Visits</th></tr></thead>
                        <tbody>{{OS}}</tbody>
                    </table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Browsers</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Browser</th><th>Visits</th></tr></thead>
                        <tbody>{{BROWSERS}}</tbody>
                    </table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Languages</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Language</th><th>Visits</th></tr></thead>
                        <tbody>{{LANGUAGES}}</tbody>
                    </table></div>
                </section>
            </div>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Conversions and custom events</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Event</th><th>Count</th></tr></thead>
                        <tbody>{{EVENTS}}</tbody>
                    </table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Campaigns</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Source / medium / campaign</th><th>Visits</th></tr></thead>
                        <tbody>{{CAMPAIGNS}}</tbody>
                    </table></div>
                </section>
            </div>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Campaign term (UTM)</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Term</th><th>Visits</th></tr></thead>
                        <tbody>{{CAMPAIGN_TERMS}}</tbody>
                    </table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Campaign content (UTM)</h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Content</th><th>Visits</th></tr></thead>
                        <tbody>{{CAMPAIGN_CONTENT}}</tbody>
                    </table></div>
                </section>
            </div>

            <section class="card">
                <h2 class="card-title">Revenue</h2>
                <div class="table-wrap"><table class="table table-fixed">
                    <thead><tr><th>Goal</th><th>Currency</th><th>Conversions</th><th>Revenue</th></tr></thead>
                    <tbody>{{REVENUE}}</tbody>
                </table></div>
            </section>
        </div>

        <p class="page-note">Privacy note: ClearStats reports aggregate traffic and does not identify new versus returning visitors.</p>
    </main>
</body>
</html>
HTML;

        echo str_replace(
            ['{{PAGEVIEWS}}', '{{VISITORS}}', '{{SESSIONS}}', '{{BOUNCE_RATE}}', '{{ENGAGEMENT}}', '{{TOP_PAGES}}', '{{REFERRERS}}', '{{COUNTRIES}}', '{{REGIONS}}', '{{CITIES}}', '{{DEVICES}}', '{{OS}}', '{{BROWSERS}}', '{{LANGUAGES}}', '{{EVENTS}}', '{{CAMPAIGNS}}', '{{CAMPAIGN_TERMS}}', '{{CAMPAIGN_CONTENT}}', '{{REVENUE}}', '{{RANGE_LABEL}}', '{{CHART_BARS}}', '{{CHART_AXIS}}', '{{SITE_OPTIONS}}', '{{SELECTED_SITE}}', '{{RANGE_PICKER}}', '{{RANGE}}'],
            [$pageviews, $visitors, $sessions, $bounceRate, $engagement, $topPageRows, $referrerRows, $countryRows, $regionRows, $cityRows, $deviceRows, $osRows, $browserRows, $languageRows, $eventRows, $campaignRows, $campaignTermRows, $campaignContentRows, $revenueRows, htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'), $chartBars, $chartAxis, $siteOptions, htmlspecialchars($selectedSiteLabel, ENT_QUOTES, 'UTF-8'), $rangePicker, htmlspecialchars($range, ENT_QUOTES, 'UTF-8')],
            $html,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: int} [startDate, endDate, label, chartDays]
     */
    private function resolveRange(string $range, string $today, ?string $customStart, ?string $customEnd): array
    {
        $todayDate = new DateTimeImmutable($today, new DateTimeZone('UTC'));

        return match ($range) {
            'today' => [$today, $today, self::RANGE_LABELS['today'], 1],
            '30d' => [$todayDate->modify('-29 days')->format('Y-m-d'), $today, self::RANGE_LABELS['30d'], 30],
            'month' => [$todayDate->format('Y-m-01'), $today, self::RANGE_LABELS['month'], (int) $todayDate->format('j')],
            'custom' => $this->resolveCustomRange($customStart, $customEnd, $today),
            default => [$todayDate->modify('-6 days')->format('Y-m-d'), $today, self::RANGE_LABELS['7d'], 7],
        };
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: int}
     */
    private function resolveCustomRange(?string $customStart, ?string $customEnd, string $today): array
    {
        $end = $this->validDate($customEnd) ?? $today;
        $start = $this->validDate($customStart) ?? $end;
        if ($start > $end) {
            $start = $end;
        }

        $days = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
        $label = $start === $end ? $start : ($start . ' – ' . $end);

        return [$start, $end, $label, min(31, $days)];
    }

    private function validDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return ($parsed !== false && $parsed->format('Y-m-d') === $value) ? $value : null;
    }

    private function formatRangePicker(string $selectedSiteId, string $range, string $startDate, string $endDate): string
    {
        $presets = ['today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month'];
        $siteParam = htmlspecialchars($selectedSiteId, ENT_QUOTES, 'UTF-8');

        $links = '';
        foreach ($presets as $key => $label) {
            $class = $range === $key ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-ghost';
            $links .= sprintf(
                '<a class="%s" href="/dashboard?site=%s&range=%s">%s</a>',
                $class,
                $siteParam,
                $key,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            );
        }

        $customClass = $range === 'custom' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-ghost';
        $customForm = sprintf(
            '<form method="get" action="/dashboard" class="range-picker-custom">'
                . '<input type="hidden" name="site" value="%s">'
                . '<input type="hidden" name="range" value="custom">'
                . '<input type="date" name="start" value="%s" aria-label="Start date">'
                . '<span class="range-picker-sep">to</span>'
                . '<input type="date" name="end" value="%s" aria-label="End date">'
                . '<button class="%s" type="submit">Custom</button>'
                . '</form>',
            $siteParam,
            htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'),
            $customClass,
        );

        return '<div class="range-picker">' . $links . $customForm . '</div>';
    }

    private function emptyRow(string $message = 'No rollup data for this period.', int $colspan = 2): string
    {
        return '<tr><td class="table-empty" colspan="' . $colspan . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    /**
     * @param list<array{date: string, pageviews: int}> $daily
     * @return array{bars: string, axis: string}|null
     */
    private function formatChart(array $daily): ?array
    {
        if ($daily === []) {
            return null;
        }

        // A per-bar value and a date axis only stay readable up to about two
        // weeks of bars; beyond that (30 days, a full month) fall back to the
        // hover title on each bar instead of cluttering the chart.
        $showLabels = count($daily) <= 14;
        $max = max(1, ...array_column($daily, 'pageviews'));

        $bars = '';
        $axis = '';
        foreach ($daily as $day) {
            $pageviews = (int) $day['pageviews'];
            $height = max(4, (int) round($pageviews / $max * 100));
            $value = $showLabels
                ? sprintf('<span class="chart-bar-value">%s</span>', number_format($pageviews))
                : '';
            $bars .= sprintf(
                '<div class="chart-bar" style="height:%d%%" title="%s: %s pageviews">%s</div>',
                $height,
                htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8'),
                number_format($pageviews),
                $value,
            );
            if ($showLabels) {
                $axis .= sprintf(
                    '<span class="chart-axis-label">%s</span>',
                    htmlspecialchars($this->formatShortDate($day['date']), ENT_QUOTES, 'UTF-8'),
                );
            }
        }

        return [
            'bars' => $bars,
            'axis' => $showLabels ? '<div class="chart-axis">' . $axis . '</div>' : '',
        ];
    }

    private function formatShortDate(string $date): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        return $parsed !== false ? $parsed->format('M j') : $date;
    }

    /**
     * @param list<array<string, int|string>> $rows
     */
    private function formatRows(array $rows, string $label, string $countLabel = 'visits'): string
    {
        if ($rows === []) {
            return $this->emptyRow();
        }

        $max = max(1, ...array_map(static fn(array $row): int => (int) $row[$countLabel], $rows));
        $html = '';
        foreach ($rows as $row) {
            $count = (int) $row[$countLabel];
            $html .= sprintf(
                '<tr><td style="%s">%s</td><td>%s</td></tr>',
                $this->barStyle($count, $max),
                htmlspecialchars((string) $row[$label], ENT_QUOTES, 'UTF-8'),
                number_format($count),
            );
        }

        return $html;
    }

    /**
     * @param list<array{url_path: string, pageviews: int, visitors: int, entrances: int, bounces: int, bounce_rate: float|null}> $rows
     */
    private function formatPageRows(array $rows): string
    {
        if ($rows === []) {
            return $this->emptyRow('No rollup data for this period.', 4);
        }

        $max = max(1, ...array_map(static fn(array $row): int => $row['pageviews'], $rows));
        $html = '';
        foreach ($rows as $row) {
            $path = htmlspecialchars($row['url_path'], ENT_QUOTES, 'UTF-8');
            $html .= sprintf(
                '<tr><td style="%s"><span class="table-truncate" title="%s">%s</span></td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->barStyle($row['pageviews'], $max),
                $path,
                $path,
                number_format($row['pageviews']),
                number_format($row['visitors']),
                $row['bounce_rate'] === null ? '—' : number_format($row['bounce_rate'], 1) . '%',
            );
        }

        return $html;
    }

    /**
     * @param list<array{campaign_source: string, campaign_medium: string, campaign_name: string, visits: int|string}> $rows
     */
    private function formatCampaignRows(array $rows): string
    {
        if ($rows === []) {
            return $this->emptyRow('No campaign data for this period.');
        }

        $max = max(1, ...array_map(static fn(array $row): int => (int) $row['visits'], $rows));
        $html = '';
        foreach ($rows as $row) {
            $count = (int) $row['visits'];
            $label = sprintf(
                '%s / %s / %s',
                htmlspecialchars((string) $row['campaign_source'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $row['campaign_medium'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $row['campaign_name'], ENT_QUOTES, 'UTF-8'),
            );
            $html .= sprintf(
                '<tr><td style="%s"><span class="table-truncate" title="%s">%s</span></td><td>%s</td></tr>',
                $this->barStyle($count, $max),
                $label,
                $label,
                number_format($count),
            );
        }

        return $html;
    }

    /**
     * @param list<array{country_code: string, region: string, visits: int}> $rows
     */
    private function formatRegionRows(array $rows): string
    {
        $labeled = array_map(static fn(array $row): array => [
            'label' => $row['country_code'] . '-' . $row['region'],
            'visits' => $row['visits'],
        ], $rows);

        return $this->formatRows($labeled, 'label');
    }

    /**
     * @param list<array{country_code: string, city: string, visits: int}> $rows
     */
    private function formatCityRows(array $rows): string
    {
        $labeled = array_map(static fn(array $row): array => [
            'label' => $row['city'] . ', ' . $row['country_code'],
            'visits' => $row['visits'],
        ], $rows);

        return $this->formatRows($labeled, 'label');
    }

    /**
     * @param list<array{event_name: string, currency: string, conversions: int, revenue_total: float}> $rows
     */
    private function formatRevenueRows(array $rows): string
    {
        if ($rows === []) {
            return $this->emptyRow('No revenue data for this period.', 4);
        }

        $max = max(1, ...array_map(static fn(array $row): float => $row['revenue_total'], $rows));
        $html = '';
        foreach ($rows as $row) {
            $goal = htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8');
            $html .= sprintf(
                '<tr><td style="%s">%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->barStyle((int) round($row['revenue_total']), (int) round($max)),
                $goal,
                htmlspecialchars($row['currency'], ENT_QUOTES, 'UTF-8'),
                number_format($row['conversions']),
                number_format($row['revenue_total'], 2),
            );
        }

        return $html;
    }

    /**
     * Bar width is relative to the largest value already shown in the list
     * (not a share of the site's full total, which isn't fetched for this
     * view), matching the same "relative to what's visible" logic as the
     * pageviews chart bars above.
     */
    private function barStyle(int $value, int $max): string
    {
        $percentage = max(2, (int) round(($value / $max) * 100));

        return sprintf(
            'background:linear-gradient(to right, var(--color-accent-muted) %1$d%%, transparent %1$d%%)',
            $percentage,
        );
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
