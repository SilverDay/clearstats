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
        $chartBars = '<span class="chart-empty">No daily rollup data for this period.</span>';
        $topPageRows = '<tr><td colspan="2">No rollup data for this period.</td></tr>';
        $referrerRows = '<tr><td colspan="2">No rollup data for today.</td></tr>';
        $countryRows = '<tr><td colspan="2">No rollup data for today.</td></tr>';
        $deviceRows = '<tr><td colspan="2">No rollup data for today.</td></tr>';
        $osRows = '<tr><td colspan="2">No OS data for today.</td></tr>';
        $languageRows = '<tr><td colspan="2">No language data for today.</td></tr>';
        $eventRows = '<tr><td colspan="2">No conversion events for today.</td></tr>';
        $campaignRows = '<tr><td colspan="2">No campaign data for today.</td></tr>';
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
                $engagement = gmdate('i:s', $overview['avg_engagement_seconds']);
                $daily = $this->query->portfolioDailyPageviews($siteIds, gmdate('Y-m-d'));
                if ($daily !== []) {
                    $max = max(1, ...array_column($daily, 'pageviews'));
                    $chartBars = '';
                    foreach ($daily as $day) {
                        $height = max(8, (int) round(((int) $day['pageviews'] / $max) * 100));
                        $chartBars .= sprintf('<span class="bar" style="height:%d%%" title="%s"></span>', $height, htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8'));
                    }
                }
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
                $engagement = gmdate('i:s', $overview['avg_engagement_seconds']);
                $daily = $this->query->dailyPageviews($selectedSiteId, gmdate('Y-m-d'));
                if ($daily !== []) {
                    $max = max(1, ...array_column($daily, 'pageviews'));
                    $chartBars = '';
                    foreach ($daily as $day) {
                        $height = max(8, (int) round(((int) $day['pageviews'] / $max) * 100));
                        $chartBars .= sprintf('<span class="bar" style="height:%d%%" title="%s"></span>', $height, htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8'));
                    }
                }
                $topPageRows = '';
                foreach ($this->query->topPages($selectedSiteId, gmdate('Y-m-d'), 5) as $page) {
                    $topPageRows .= sprintf(
                        '<tr><td>%s</td><td>%s</td></tr>',
                        htmlspecialchars($page['url_path'], ENT_QUOTES, 'UTF-8'),
                        number_format($page['pageviews']),
                    );
                }
                if ($topPageRows === '') {
                    $topPageRows = '<tr><td colspan="2">No rollup data for today.</td></tr>';
                }
                $referrerRows = $this->formatRows($this->query->topReferrers($selectedSiteId, gmdate('Y-m-d'), 5), 'referrer_domain');
                $countryRows = $this->formatRows($this->query->topCountries($selectedSiteId, gmdate('Y-m-d'), 5), 'country_code');
                $deviceRows = $this->formatRows($this->query->devices($selectedSiteId, gmdate('Y-m-d')), 'device_type');
                $osRows = $this->formatRows($this->query->operatingSystems($selectedSiteId, gmdate('Y-m-d')), 'operating_system');
                $languageRows = $this->formatRows($this->query->languages($selectedSiteId, gmdate('Y-m-d')), 'language_code');
                $eventRows = $this->formatRows($this->query->topEvents($selectedSiteId, gmdate('Y-m-d'), 5), 'event_name', 'events');
                $campaignRows = '';
                foreach ($this->query->campaigns($selectedSiteId, gmdate('Y-m-d'), 5) as $campaign) {
                    $campaignRows .= sprintf('<tr><td>%s / %s / %s</td><td>%s</td></tr>', htmlspecialchars($campaign['campaign_source'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($campaign['campaign_medium'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($campaign['campaign_name'], ENT_QUOTES, 'UTF-8'), number_format((int) $campaign['visits']));
                }
                if ($campaignRows === '') $campaignRows = '<tr><td colspan="2">No campaign data for today.</td></tr>';
            }
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats Dashboard</title>
    <style>
        :root {
            --bg: #07111b;
            --bg-2: #0d1d2d;
            --panel: rgba(15, 23, 42, 0.86);
            --panel-border: rgba(148, 163, 184, 0.16);
            --text: #edf3ff;
            --muted: #9bb0c8;
            --brand: #7ae7ff;
            --brand-2: #8a7dff;
            --accent: #7ef0c8;
            --orange: #ffbf7b;
            --shadow: rgba(2, 6, 23, 0.45);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%); color: var(--text); font-family: Inter, "Segoe UI", sans-serif; }
        body { min-height: 100vh; }
        .layout { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .sidebar {
            background: rgba(9, 15, 25, 0.92); border-right: 1px solid var(--panel-border); padding: 22px 18px; display: flex; flex-direction: column; gap: 20px;
        }
        .brand { display: inline-flex; align-items: center; gap: 12px; font-size: 1.1rem; font-weight: 700; letter-spacing: 0.04em; }
        .brand-mark { width: 24px; height: 24px; border-radius: 10px; background: linear-gradient(135deg, var(--brand), var(--brand-2)); }
        .nav {
            display: grid; gap: 8px; margin-top: 12px;
        }
        .nav a {
            text-decoration: none; color: var(--muted); padding: 10px 12px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px;
            border: 1px solid transparent;
        }
        .nav a.active, .nav a:hover { background: rgba(148,163,184,0.05); border-color: rgba(148,163,184,0.12); color: var(--text); }
        .badge { font-size: 0.72rem; background: rgba(122,231,255,0.12); color: var(--brand); border-radius: 999px; padding: 4px 8px; }
        .content { padding: 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .title { font-size: 2rem; letter-spacing: -0.05em; margin: 0; }
        .site-switcher { display: flex; align-items: center; gap: 10px; }
        .site-switcher label { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; }
        .site-switcher select { min-width: 220px; padding: 10px 12px; border-radius: 10px; }
        .top-actions { display: flex; gap: 12px; }
        .button {
            display: inline-flex; align-items: center; justify-content: center; padding: 11px 16px; border-radius: 12px; text-decoration: none; border: 1px solid rgba(148,163,184,0.14); color: var(--text); background: rgba(148,163,184,0.04);
        }
        .button.primary {
            background: linear-gradient(135deg, rgba(122,231,255,0.2), rgba(138,125,255,0.25)); border-color: rgba(122,231,255,0.3);
        }
        .stats {
            display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap: 18px; margin-bottom: 24px;
        }
        .card {
            background: rgba(14, 23, 36, 0.7); border: 1px solid var(--panel-border); border-radius: 20px; padding: 18px 18px 16px; box-shadow: 0 20px 45px var(--shadow);
        }
        .stat-label { color: var(--muted); font-size: 0.76rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .stat-value { font-size: 2rem; font-weight: 800; letter-spacing: -0.06em; margin: 12px 0 8px; }
        .trend { color: var(--accent); font-size: 0.82rem; }
        .panel-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 18px; }
        .chart-box { min-height: 250px; }
        .chart-bars { display: flex; align-items: end; gap: 12px; height: 170px; margin-top: 16px; }
        .bar { flex: 1; border-radius: 12px 12px 0 0; background: linear-gradient(180deg, var(--brand), var(--brand-2)); min-height: 40px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .table th, .table td { text-align: left; padding: 12px 8px; border-bottom: 1px solid rgba(148,163,184,0.12); }
        .table th { color: var(--muted); font-weight: 600; }
        .pills { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
        .pill { border-radius: 999px; padding: 8px 12px; background: rgba(148,163,184,0.08); color: var(--muted); border: 1px solid rgba(148,163,184,0.1); }
        @media (max-width: 920px) { .layout { grid-template-columns: 1fr; } .sidebar { border-right: 0; border-bottom: 1px solid var(--panel-border); } .stats, .panel-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark"></span> ClearStats</div>
            <nav class="nav">
                <a class="active" href="/dashboard">Overview <span class="badge">Live</span></a>
                <a href="/sites">Sites</a>
                <a href="/users">Users</a>
                <a href="/dashboard">Reports</a>
                <a href="/login">Logout</a>
            </nav>
        </aside>

        <main class="content">
            <div class="topbar">
                <div><h1 class="title">Dashboard</h1><div class="muted" style="margin-top:6px;">{{SELECTED_SITE}}</div></div>
                <div class="top-actions">
                    <form class="site-switcher" method="get" action="/dashboard"><label for="dashboard-site">Site</label><select id="dashboard-site" name="site" onchange="this.form.submit()">{{SITE_OPTIONS}}</select></form>
                    <a class="button" href="/sites">Manage sites</a>
                    <a class="button primary" href="/sites/new">New site</a>
                </div>
            </div>

            <section class="stats">
                <div class="card">
                    <div class="stat-label">Pageviews</div>
                    <div class="stat-value">{{PAGEVIEWS}}</div>
                    <div class="trend">{{TREND}}</div>
                </div>
                <div class="card">
                    <div class="stat-label">Visitors</div>
                    <div class="stat-value">{{VISITORS}}</div>
                    <div class="trend">Aggregate count</div>
                </div>
                <div class="card"><div class="stat-label">Sessions</div><div class="stat-value">{{SESSIONS}}</div><div class="trend">Ephemeral, in-memory client sessions</div></div>
                <div class="card"><div class="stat-label">Bounce rate</div><div class="stat-value">{{BOUNCE_RATE}}</div><div class="trend">Single-page sessions</div></div>
                <div class="card"><div class="stat-label">Avg. engagement</div><div class="stat-value">{{ENGAGEMENT}}</div><div class="trend">Time until page exit</div></div>
            </section>

            <section class="panel-grid">
                <div class="card chart-box">
                    <div class="stat-label">Weekly pageviews</div>
                    <div class="chart-bars">{{CHART_BARS}}</div>
                </div>

                <div class="card">
                    <div class="stat-label">Top pages</div>
                    <table class="table">
                        <tbody>
                            {{TOP_PAGES}}
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="muted" style="margin:18px 0 0;">Privacy note: ClearStats reports aggregate traffic and does not identify new versus returning visitors.</p>

            <section class="panel-grid" style="margin-top:18px;">
                <div class="card"><div class="stat-label">Top referrers</div><table class="table"><tbody>{{REFERRERS}}</tbody></table></div>
                <div class="card"><div class="stat-label">Countries</div><table class="table"><tbody>{{COUNTRIES}}</tbody></table></div>
            </section>
            <section class="card" style="margin-top:18px;"><div class="stat-label">Devices</div><table class="table"><tbody>{{DEVICES}}</tbody></table></section>
            <section class="panel-grid" style="margin-top:18px;"><div class="card"><div class="stat-label">Operating systems</div><table class="table"><tbody>{{OS}}</tbody></table></div><div class="card"><div class="stat-label">Languages</div><table class="table"><tbody>{{LANGUAGES}}</tbody></table></div></section>
            <section class="panel-grid" style="margin-top:18px;"><div class="card"><div class="stat-label">Conversions and custom events</div><table class="table"><tbody>{{EVENTS}}</tbody></table></div><div class="card"><div class="stat-label">Campaigns</div><table class="table"><tbody>{{CAMPAIGNS}}</tbody></table></div></section>
        </main>
    </div>
</body>
</html>
HTML;

        echo str_replace(
            ['{{PAGEVIEWS}}', '{{VISITORS}}', '{{SESSIONS}}', '{{BOUNCE_RATE}}', '{{ENGAGEMENT}}', '{{TOP_PAGES}}', '{{REFERRERS}}', '{{COUNTRIES}}', '{{DEVICES}}', '{{OS}}', '{{LANGUAGES}}', '{{EVENTS}}', '{{CAMPAIGNS}}', '{{TREND}}', '{{CHART_BARS}}', '{{SITE_OPTIONS}}', '{{SELECTED_SITE}}'],
            [$pageviews, $visitors, $sessions, $bounceRate, $engagement, $topPageRows, $referrerRows, $countryRows, $deviceRows, $osRows, $languageRows, $eventRows, $campaignRows, $trend, $chartBars, $siteOptions, htmlspecialchars($selectedSiteLabel, ENT_QUOTES, 'UTF-8')],
            $html,
        );
    }

    /**
     * @param list<array<string, int|string>> $rows
     */
    private function formatRows(array $rows, string $label, string $countLabel = 'visits'): string
    {
        if ($rows === []) {
            return '<tr><td colspan="2">No rollup data for today.</td></tr>';
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
}
