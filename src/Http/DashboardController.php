<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use ClearStats\Rollup\DashboardQuery;

final class DashboardController
{
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
        $trend = 'Today\'s rollup';
        $chartBars = '<p class="chart-empty">No daily rollup data for this period.</p>';
        $topPageRows = $this->emptyRow('No rollup data for this period.');
        $referrerRows = $this->emptyRow();
        $countryRows = $this->emptyRow();
        $deviceRows = $this->emptyRow();
        $osRows = $this->emptyRow('No OS data for today.');
        $languageRows = $this->emptyRow('No language data for today.');
        $eventRows = $this->emptyRow('No conversion events for today.');
        $campaignRows = $this->emptyRow('No campaign data for today.');
        $siteOptions = '<option value="">No assigned sites</option>';
        $selectedSiteLabel = 'No site selected';

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
                $overview = $this->query->portfolioOverview($siteIds, gmdate('Y-m-d'));
                $pageviews = number_format($overview['pageviews']);
                $visitors = number_format($overview['unique_visitor_hashes_count']);
                $sessions = number_format($overview['sessions']);
                $bounceRate = $overview['bounce_rate'] === null ? '0%' : number_format($overview['bounce_rate'], 1) . '%';
                $engagement = $this->formatDuration((int) $overview['avg_engagement_seconds']);
                $chartBars = $this->formatChart($this->query->portfolioDailyPageviews($siteIds, gmdate('Y-m-d'))) ?? $chartBars;
                $topPageRows = $this->formatRows($this->query->portfolioTopPages($siteIds, gmdate('Y-m-d'), 5), 'url_path', 'pageviews');
                $referrerRows = $this->formatRows($this->query->portfolioTopReferrers($siteIds, gmdate('Y-m-d'), 5), 'referrer_domain');
                $countryRows = $this->formatRows($this->query->portfolioTopCountries($siteIds, gmdate('Y-m-d'), 5), 'country_code');
                $deviceRows = $this->formatRows($this->query->portfolioDevices($siteIds, gmdate('Y-m-d')), 'device_type');
                $osRows = $this->formatRows($this->query->portfolioOperatingSystems($siteIds, gmdate('Y-m-d')), 'operating_system');
                $languageRows = $this->formatRows($this->query->portfolioLanguages($siteIds, gmdate('Y-m-d')), 'language_code');
            } elseif ($selectedSite !== null) {
                $selectedSiteLabel = (string) $selectedSite['name'];
                $overview = $this->query->overview($selectedSiteId, gmdate('Y-m-d'));
                $pageviews = number_format($overview['pageviews']);
                $visitors = number_format($overview['unique_visitor_hashes_count']);
                $sessions = number_format($overview['sessions']);
                $bounceRate = $overview['bounce_rate'] === null ? '0%' : number_format($overview['bounce_rate'], 1) . '%';
                $engagement = $this->formatDuration((int) $overview['avg_engagement_seconds']);
                $chartBars = $this->formatChart($this->query->dailyPageviews($selectedSiteId, gmdate('Y-m-d'))) ?? $chartBars;
                $topPageRows = $this->formatRows($this->query->topPages($selectedSiteId, gmdate('Y-m-d'), 5), 'url_path', 'pageviews');
                $referrerRows = $this->formatRows($this->query->topReferrers($selectedSiteId, gmdate('Y-m-d'), 5), 'referrer_domain');
                $countryRows = $this->formatRows($this->query->topCountries($selectedSiteId, gmdate('Y-m-d'), 5), 'country_code');
                $deviceRows = $this->formatRows($this->query->devices($selectedSiteId, gmdate('Y-m-d')), 'device_type');
                $osRows = $this->formatRows($this->query->operatingSystems($selectedSiteId, gmdate('Y-m-d')), 'operating_system');
                $languageRows = $this->formatRows($this->query->languages($selectedSiteId, gmdate('Y-m-d')), 'language_code');
                $eventRows = $this->formatRows($this->query->topEvents($selectedSiteId, gmdate('Y-m-d'), 5), 'event_name', 'events');
                $campaignRows = '';
                foreach ($this->query->campaigns($selectedSiteId, gmdate('Y-m-d'), 5) as $campaign) {
                    $campaignRows .= sprintf(
                        '<tr><td>%s / %s / %s</td><td>%s</td></tr>',
                        htmlspecialchars($campaign['campaign_source'], ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($campaign['campaign_medium'], ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($campaign['campaign_name'], ENT_QUOTES, 'UTF-8'),
                        number_format((int) $campaign['visits']),
                    );
                }
                if ($campaignRows === '') {
                    $campaignRows = $this->emptyRow('No campaign data for today.');
                }
            }
        }

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
                    <select name="site" aria-label="Select site" onchange="this.form.submit()">{{SITE_OPTIONS}}</select>
                </form>
                <a class="btn" href="/sites">Manage sites</a>
                <a class="btn btn-primary" href="/sites/new">New site</a>
            </div>
        </div>

        <section class="grid grid-5">
            <article class="stat">
                <span class="stat-label">Pageviews</span>
                <span class="stat-value">{{PAGEVIEWS}}</span>
                <span class="stat-note">{{TREND}}</span>
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
                <section class="card">
                    <h2 class="card-title">Weekly pageviews</h2>
                    <div class="chart">{{CHART_BARS}}</div>
                </section>
                <section class="card">
                    <h2 class="card-title">Top pages</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{TOP_PAGES}}</tbody></table></div>
                </section>
            </div>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Top referrers</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{REFERRERS}}</tbody></table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Countries</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{COUNTRIES}}</tbody></table></div>
                </section>
            </div>

            <section class="card">
                <h2 class="card-title">Devices</h2>
                <div class="table-wrap"><table class="table"><tbody>{{DEVICES}}</tbody></table></div>
            </section>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Operating systems</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{OS}}</tbody></table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Languages</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{LANGUAGES}}</tbody></table></div>
                </section>
            </div>

            <div class="grid grid-2">
                <section class="card">
                    <h2 class="card-title">Conversions and custom events</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{EVENTS}}</tbody></table></div>
                </section>
                <section class="card">
                    <h2 class="card-title">Campaigns</h2>
                    <div class="table-wrap"><table class="table"><tbody>{{CAMPAIGNS}}</tbody></table></div>
                </section>
            </div>
        </div>

        <p class="page-note">Privacy note: ClearStats reports aggregate traffic and does not identify new versus returning visitors.</p>
    </main>
</body>
</html>
HTML;

        echo str_replace(
            ['{{PAGEVIEWS}}', '{{VISITORS}}', '{{SESSIONS}}', '{{BOUNCE_RATE}}', '{{ENGAGEMENT}}', '{{TOP_PAGES}}', '{{REFERRERS}}', '{{COUNTRIES}}', '{{DEVICES}}', '{{OS}}', '{{LANGUAGES}}', '{{EVENTS}}', '{{CAMPAIGNS}}', '{{TREND}}', '{{CHART_BARS}}', '{{SITE_OPTIONS}}', '{{SELECTED_SITE}}'],
            [$pageviews, $visitors, $sessions, $bounceRate, $engagement, $topPageRows, $referrerRows, $countryRows, $deviceRows, $osRows, $languageRows, $eventRows, $campaignRows, $trend, $chartBars, $siteOptions, htmlspecialchars($selectedSiteLabel, ENT_QUOTES, 'UTF-8')],
            $html,
        );
    }

    private function emptyRow(string $message = 'No rollup data for today.'): string
    {
        return '<tr><td class="table-empty" colspan="2">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }

    /**
     * @param list<array{date: string, pageviews: int}> $daily
     */
    private function formatChart(array $daily): ?string
    {
        if ($daily === []) {
            return null;
        }

        $max = max(1, ...array_column($daily, 'pageviews'));
        $bars = '';
        foreach ($daily as $day) {
            $height = max(4, (int) round(((int) $day['pageviews'] / $max) * 100));
            $bars .= sprintf(
                '<span class="chart-bar" style="height:%d%%" title="%s"></span>',
                $height,
                htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8'),
            );
        }

        return $bars;
    }

    /**
     * @param list<array<string, int|string>> $rows
     */
    private function formatRows(array $rows, string $label, string $countLabel = 'visits'): string
    {
        if ($rows === []) {
            return $this->emptyRow();
        }

        $html = '';
        foreach ($rows as $row) {
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                htmlspecialchars((string) $row[$label], ENT_QUOTES, 'UTF-8'),
                number_format((int) $row[$countLabel]),
            );
        }

        return $html;
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
