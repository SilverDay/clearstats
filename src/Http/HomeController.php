<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

use ClearStats\Rollup\DashboardQuery;

final class HomeController
{
    public function __construct(
        private readonly ?DashboardQuery $query = null,
    ) {}

    public function index(): void
    {
        $pageviews = '0';
        $visitors = '0';
        $bounceRate = '0%';
        $chartBars = '<p class="chart-empty">No traffic data yet.</p>';

        if ($this->query !== null) {
            $endDate = gmdate('Y-m-d');
            $startDate = gmdate('Y-m-d', strtotime('-6 days'));
            $overview = $this->query->publicOverview($endDate, $startDate);
            $pageviews = number_format($overview['pageviews']);
            $visitors = number_format($overview['unique_visitor_hashes_count']);
            $bounceRate = $overview['bounce_rate'] === null ? '0%' : number_format($overview['bounce_rate'], 1) . '%';
            $chartBars = $this->formatChart($this->query->publicDailyPageviews($endDate, 7)) ?? $chartBars;
        }

        echo str_replace(
            ['{{HERO_PAGEVIEWS}}', '{{HERO_VISITORS}}', '{{HERO_BOUNCE}}', '{{HERO_CHART}}'],
            [$pageviews, $visitors, $bounceRate, $chartBars],
            <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ClearStats - Privacy-first analytics</title>
</head>
<body>
    <main class="page">
        <section class="hero">
            <div class="panel">
                <h1 class="panel-title">Useful numbers. <em>Zero surveillance.</em></h1>
                <p class="panel-lede">ClearStats gives you the traffic signals you need to improve your websites, without cookies, fingerprinting, or raw visitor data at rest.</p>
                <div class="actions">
                    <a class="btn btn-primary" href="/login">Open dashboard</a>
                    <a class="btn" href="/about">How it works</a>
                </div>
                <ul class="marker-list">
                    <li>No cookies</li>
                    <li>No raw IP storage</li>
                    <li>GDPR-ready by design</li>
                    <li>Open source (MIT)</li>
                </ul>
            </div>

            <div class="hero-preview">
                <div class="hero-preview-head">
                    <div class="stat stat-plain">
                        <span class="stat-label">Portfolio overview</span>
                        <span class="stat-value">{{HERO_PAGEVIEWS}}</span>
                    </div>
                    <span class="badge badge-success">All active sites</span>
                </div>
                <div class="grid grid-3">
                    <div class="stat stat-plain"><span class="stat-value">{{HERO_PAGEVIEWS}}</span><span class="stat-label">Pageviews</span></div>
                    <div class="stat stat-plain"><span class="stat-value">{{HERO_VISITORS}}</span><span class="stat-label">Visitors</span></div>
                    <div class="stat stat-plain"><span class="stat-value">{{HERO_BOUNCE}}</span><span class="stat-label">Bounce rate</span></div>
                </div>
                <div class="chart" aria-hidden="true">{{HERO_CHART}}</div>
                <div class="hero-preview-foot"><span>Last 7 days</span><span>Aggregated daily, not real-time</span></div>
            </div>
        </section>

        <section class="features">
            <h2>Analytics you can explain.</h2>
            <div class="grid grid-3">
                <article>
                    <h3>See what matters</h3>
                    <p>Pageviews, referrers, campaigns, devices, and engagement in one calm workspace.</p>
                </article>
                <article>
                    <h3>Keep data minimal</h3>
                    <p>Visitor hashes are computed server-side. Raw IP addresses and user agents are never persisted.</p>
                </article>
                <article>
                    <h3>Own the platform</h3>
                    <p>Run ClearStats on your infrastructure and keep operational analytics under your control.</p>
                </article>
            </div>
        </section>

        <footer class="site-footer">
            <span>ClearStats by <a href="https://silverday.media" target="_blank" rel="noopener noreferrer">SilverDay Media</a></span>
            <nav aria-label="Footer navigation">
                <a href="/about">About</a>
                <a href="/faq">FAQ</a>
                <a href="/privacy">Privacy</a>
                <a href="/terms">Terms</a>
                <a href="/imprint">Legal notice</a>
                <a href="https://github.com/SilverDay/clearstats" target="_blank" rel="noopener noreferrer">Open source</a>
            </nav>
        </footer>
    </main>
</body>
</html>
HTML,
        );
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
            $bars .= sprintf('<span class="chart-bar" style="height:%d%%"></span>', $height);
        }

        return $bars;
    }
}
