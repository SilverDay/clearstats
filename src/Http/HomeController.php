<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class HomeController
{
    public function index(): void
    {
        echo <<<'HTML'
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
                </ul>
            </div>

            <div class="hero-preview">
                <div class="hero-preview-head">
                    <div class="stat stat-plain">
                        <span class="stat-label">Portfolio overview</span>
                        <span class="stat-value">41,204</span>
                    </div>
                    <span class="badge badge-success">Today</span>
                </div>
                <div class="grid grid-3">
                    <div class="stat stat-plain"><span class="stat-value">31.2k</span><span class="stat-label">Pageviews</span></div>
                    <div class="stat stat-plain"><span class="stat-value">12.4k</span><span class="stat-label">Visitors</span></div>
                    <div class="stat stat-plain"><span class="stat-value">4.8%</span><span class="stat-label">Bounce rate</span></div>
                </div>
                <div class="chart" aria-hidden="true">
                    <span class="chart-bar" style="height:34%"></span>
                    <span class="chart-bar" style="height:48%"></span>
                    <span class="chart-bar" style="height:42%"></span>
                    <span class="chart-bar" style="height:63%"></span>
                    <span class="chart-bar" style="height:54%"></span>
                    <span class="chart-bar" style="height:78%"></span>
                    <span class="chart-bar" style="height:69%"></span>
                    <span class="chart-bar" style="height:92%"></span>
                </div>
                <div class="hero-preview-foot"><span>Last 7 days</span><span>Updated now</span></div>
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
            <span>ClearStats</span>
            <nav aria-label="Footer navigation">
                <a href="/about">About</a>
                <a href="/faq">FAQ</a>
                <a href="/privacy">Privacy</a>
                <a href="/terms">Terms</a>
                <a href="/imprint">Legal notice</a>
            </nav>
        </footer>
    </main>
</body>
</html>
HTML;
    }
}
