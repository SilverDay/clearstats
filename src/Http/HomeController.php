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
    <title>ClearStats — Privacy-first analytics</title>
    <style>
        :root {
            --bg: #08111f;
            --bg-2: #0e1f32;
            --panel: rgba(15, 23, 42, 0.82);
            --panel-border: rgba(148, 163, 184, 0.18);
            --text: #e5eefb;
            --muted: #9fb3c8;
            --brand: #68e1fd;
            --brand-2: #8a7dff;
            --accent: #7ef0c8;
            --shadow: rgba(15, 23, 42, 0.45);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; font-family: Inter, "Segoe UI", sans-serif; background: radial-gradient(circle at top, rgba(104,225,253,0.16), transparent 30%), linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%); color: var(--text); }
        body { display: flex; align-items: center; justify-content: center; }
        .shell {
            width: min(1200px, calc(100% - 32px));
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 48px 0;
        }
        .hero {
            width: 100%;
            background: rgba(10, 15, 26, 0.72);
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            box-shadow: 0 32px 80px var(--shadow);
            overflow: hidden;
            backdrop-filter: blur(16px);
        }
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 22px 30px;
            border-bottom: 1px solid var(--panel-border);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .brand-mark {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            box-shadow: 0 0 30px rgba(104,225,253,0.5);
        }
        .nav-links {
            display: flex;
            gap: 18px;
            color: var(--muted);
            font-size: 0.96rem;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1px solid rgba(104,225,253,0.35);
            border-radius: 999px;
            padding: 12px 18px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .button.primary {
            background: linear-gradient(135deg, rgba(104,225,253,0.22), rgba(138,125,255,0.22));
            color: var(--text);
            box-shadow: 0 10px 35px rgba(104,225,253,0.18);
        }
        .button.secondary {
            color: var(--muted);
            background: rgba(148, 163, 184, 0.06);
        }
        .button:hover { transform: translateY(-1px); }
        .content {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 32px;
            padding: 52px 30px 34px;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(126, 240, 200, 0.12);
            color: var(--accent);
            border: 1px solid rgba(126,240,200,0.18);
            margin-bottom: 22px;
        }
        h1 {
            margin: 0 0 18px;
            font-size: clamp(2.7rem, 5vw, 5rem);
            line-height: 0.96;
            letter-spacing: -0.06em;
        }
        .gradient {
            background: linear-gradient(135deg, #eafcff, #9fe9ff 35%, #8a7dff 70%, #d9c6ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .lead {
            max-width: 620px;
            font-size: 1.08rem;
            line-height: 1.7;
            color: var(--muted);
            margin: 0 0 26px;
        }
        .cta-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .meta span { display: inline-flex; align-items: center; gap: 8px; }
        .meta .dot {
            width: 8px; height: 8px; border-radius: 999px; background: var(--accent);
            box-shadow: 0 0 15px rgba(126,240,200,0.8);
        }
        .panel {
            background: linear-gradient(180deg, rgba(14, 23, 36, 0.7), rgba(15, 23, 42, 0.95));
            border: 1px solid var(--panel-border);
            border-radius: 24px;
            padding: 18px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(120px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }
        .stat-card {
            background: rgba(148, 163, 184, 0.05);
            border: 1px solid rgba(148,163,184,0.08);
            border-radius: 16px;
            padding: 18px 16px;
        }
        .stat-card .value {
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            margin-bottom: 6px;
        }
        .stat-card .label {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .mini-table {
            margin-top: 18px;
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        .mini-table th, .mini-table td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid rgba(148,163,184,0.1);
        }
        .mini-table th { color: var(--muted); font-weight: 600; }
        .mini-table td { color: var(--text); }
        @media (max-width: 860px) {
            .content { grid-template-columns: 1fr; }
            .nav { flex-wrap: wrap; gap: 12px; }
            .nav-links { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="hero">
            <nav class="nav">
                <div class="brand">
                    <span class="brand-mark"></span>
                    <span>ClearStats</span>
                </div>
                <div class="nav-links">
                    <span>Platform</span>
                    <span>Privacy</span>
                    <span>Pricing</span>
                    <span>Docs</span>
                </div>
                <a class="button secondary" href="/login">Log in</a>
            </nav>
            <div class="content">
                <div>
                    <div class="eyebrow">Cookieless • GDPR-safe • Multi-site</div>
                    <h1>Analytics that <span class="gradient">respect the user.</span></h1>
                    <p class="lead">Track visits, pageviews, traffic patterns, and conversion signals across your owned websites without cookies, fingerprinting, or invasive tracking. ClearStats keeps your analytics honest, privacy-first, and self-hosted.</p>
                    <div class="cta-row">
                        <a class="button primary" href="/login">Open dashboard</a>
                        <a class="button secondary" href="/sites/new">Add a site</a>
                    </div>
                    <div class="meta">
                        <span><i class="dot"></i> No cookieless IDs</span>
                        <span><i class="dot"></i> No raw IP storage</span>
                        <span><i class="dot"></i> Built for self-hosting</span>
                    </div>
                </div>

                <aside class="panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <div>
                            <div style="font-size:0.75rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);">Portfolio overview</div>
                            <div style="font-size:1.6rem;font-weight:800;margin-top:6px;">4.8k</div>
                        </div>
                        <div class="button secondary" style="padding:10px 12px;">+18.4%</div>
                    </div>
                    <div class="stats">
                        <div class="stat-card">
                            <div class="value">41.2k</div>
                            <div class="label">Pageviews</div>
                        </div>
                        <div class="stat-card">
                            <div class="value">12.4k</div>
                            <div class="label">Visitors</div>
                        </div>
                        <div class="stat-card">
                            <div class="value">3.1%</div>
                            <div class="label">Bounce</div>
                        </div>
                        <div class="stat-card">
                            <div class="value">92</div>
                            <div class="label">Countries</div>
                        </div>
                    </div>
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>/pricing</td><td>8,214</td></tr>
                            <tr><td>/blog</td><td>5,903</td></tr>
                            <tr><td>/docs</td><td>4,271</td></tr>
                        </tbody>
                    </table>
                </aside>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
