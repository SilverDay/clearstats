<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
// SPDX-License-Identifier: MIT

namespace ClearStats\Http;

final class InfoController
{
    public function about(): void
    {
        $this->page('About ClearStats', <<<'HTML'
<h1 class="page-title">Analytics with a smaller footprint.</h1>
<div class="prose">
    <p class="lede">ClearStats is a self-hosted, cookieless analytics platform for teams that want useful traffic insight without turning visitors into permanent records.</p>
</div>
<div class="grid grid-2">
    <article class="card">
        <h2>Designed for owned sites</h2>
        <p>Manage multiple domains from one operator-controlled installation. Each site has its own canonical domain, access assignments, retention policy, and dashboard view.</p>
    </article>
    <article class="card">
        <h2>Useful without surveillance</h2>
        <p>ClearStats derives a short-lived visitor hash from the request and discards the raw IP address and User-Agent immediately. It does not use cookies, browser storage, fingerprinting, or cross-day visitor identity.</p>
    </article>
    <article class="card">
        <h2>Built for operators</h2>
        <p>Events are validated at the edge, buffered through Redis, rolled into daily aggregates, and purged according to each site's retention policy. The dashboard reads rollups rather than raw events.</p>
    </article>
    <article class="card">
        <h2>Built by SilverDay Media</h2>
        <p>ClearStats is operated by Klaus-E. Klingner / SilverDay Media, the team behind Daybreak and security-focused software for people who need clarity without unnecessary data collection.</p>
    </article>
</div>
HTML);
    }

    public function faq(): void
    {
        $this->page('Frequently asked questions', <<<'HTML'
<h1 class="page-title">Frequently asked questions</h1>
<div class="prose">
    <p class="lede">The short version of how ClearStats works and what it deliberately does not do.</p>
</div>
<div class="faq">
    <details open><summary>Does ClearStats use cookies?</summary><p>No. The tracking script does not use cookies, localStorage, sessionStorage, fingerprinting, or persistent browser identifiers.</p></details>
    <details><summary>Can I track multiple domains?</summary><p>Yes. Each domain is configured as a separate site and users can be assigned access to one or more sites.</p></details>
    <details><summary>What is stored for a visitor?</summary><p>The server uses IP address and User-Agent transiently to derive a daily visitor hash. The raw values are not placed in the queue, database, or application event payload.</p></details>
    <details><summary>Can I see individual site statistics?</summary><p>Yes. The dashboard defaults to all assigned sites and includes a site selector for individual site views.</p></details>
    <details><summary>What metrics are available?</summary><p>Pageviews, unique daily visitor hashes, sessions, bounce rate, engagement time, top pages, referrers, countries, devices, campaign attribution, and named custom/conversion events.</p></details>
    <details><summary>Can I identify returning visitors?</summary><p>No. Cross-day returning-visitor identification is intentionally not supported because the visitor hash is tied to a rotating daily salt.</p></details>
    <details><summary>Where is data hosted?</summary><p>ClearStats is self-hosted. The operator controls the application server, database, Redis instance, GeoIP database, backups, and access logs.</p></details>
    <details><summary>How do I get help?</summary><p>Contact <a href="mailto:support@skyggn.dev">support@skyggn.dev</a>.</p></details>
</div>
HTML);
    }

    public function imprint(): void
    {
        $this->page('Legal notice', <<<'HTML'
<h1 class="page-title">Legal notice</h1>
<section class="prose">
    <h2>Operator</h2>
    <p>Klaus-E. Klingner, operating as SilverDay Media<br>c/o IP-Management #6585<br>Ludwig-Erhard-Str. 18<br>20459 Hamburg<br>Germany</p>
    <h2>Contact</h2>
    <p>Email: <a href="mailto:support@skyggn.dev">support@skyggn.dev</a></p>
    <h2>Responsible for content</h2>
    <p>Klaus-E. Klingner at the address above.</p>
    <h2>Disclaimer</h2>
    <p>ClearStats is a software platform for self-hosted website analytics. The operator does not provide legal, tax, security, or compliance advice through this software. Site operators remain responsible for their configuration, notices, retention settings, infrastructure, and legal obligations.</p>
</section>
HTML);
    }

    public function privacy(): void
    {
        $this->page('Privacy policy', <<<'HTML'
<h1 class="page-title">Privacy policy</h1>
<section class="prose">
    <p class="lede">Last updated: 14 September 2026</p>
    <h2>1. Controller</h2>
    <p>Klaus-E. Klingner, operating as SilverDay Media, c/o IP-Management #6585, Ludwig-Erhard-Str. 18, 20459 Hamburg, Germany. Contact: <a href="mailto:support@skyggn.dev">support@skyggn.dev</a>.</p>
    <h2>2. What ClearStats processes</h2>
    <p>For a pageview, the tracked site sends the site identifier, page path, referrer domain, event type, and optional bounded campaign or custom-event values. The ClearStats server receives the request IP and User-Agent as part of normal HTTP processing.</p>
    <h2>3. What is not retained</h2>
    <p>The raw IP address and raw User-Agent are used only transiently to derive a daily, site-specific HMAC visitor hash. They are discarded before the event enters the Redis queue or database. ClearStats does not store cookies, persistent browser IDs, localStorage/sessionStorage identifiers, fingerprints, names, email addresses, or cross-day visitor identity.</p>
    <h2>4. Aggregated analytics</h2>
    <p>Short-lived raw events are rolled into daily aggregates: pageviews, daily unique visitor hashes, ephemeral sessions, engagement and bounce metrics, pages, referrers, countries, devices, campaigns, and named events. Raw events are purged per site retention policy.</p>
    <h2>5. GeoIP</h2>
    <p>When configured, a local MaxMind country database resolves a country code in memory. Only the two-letter code is retained. A trusted proxy country header may be used as fallback. The GeoIP database is operated by the site owner.</p>
    <h2>6. Hosting and operators</h2>
    <p>ClearStats is self-hosted. The organization operating a ClearStats instance controls its hosting, database, Redis service, backups, access logs, and retention settings. This notice describes the ClearStats software model; the operator must adapt it to the actual deployment.</p>
    <h2>7. Rights and requests</h2>
    <p>For questions about this ClearStats installation or requests concerning operator-managed account data, contact the installation operator. For software questions, contact <a href="mailto:support@skyggn.dev">support@skyggn.dev</a>. You may also have rights under applicable data protection law, including access, rectification, deletion, restriction, objection, and complaint to a supervisory authority.</p>
    <h2>8. Changes</h2>
    <p>This policy may be updated when the software, deployment, or legal requirements change.</p>
</section>
HTML);
    }

    public function terms(): void
    {
        $this->page('Terms of use', <<<'HTML'
<h1 class="page-title">Terms of use</h1>
<section class="prose">
    <p class="lede">Last updated: 14 September 2026</p>
    <h2>1. Scope</h2>
    <p>These terms govern use of a ClearStats installation operated by SilverDay Media or by an organization using the software. The applicable operator and deployment terms should be identified before production use.</p>
    <h2>2. The software</h2>
    <p>ClearStats provides self-hosted website analytics, event ingestion, queue processing, aggregate reporting, user management, and site access controls. Features may change as the software develops.</p>
    <h2>3. Accounts and access</h2>
    <p>Accounts are created by an administrator. Users are responsible for protecting credentials and for activity under their account. Administrators must assign access only where appropriate and must remove access when it is no longer needed.</p>
    <h2>4. Acceptable use</h2>
    <p>You must not use ClearStats unlawfully, attempt to access another user's sites or data, bypass validation or access controls, interfere with the service, or configure tracking to collect data contrary to the stated privacy model.</p>
    <h2>5. Your data and configuration</h2>
    <p>The site operator remains responsible for tracked-site content, campaign values, custom event names, GeoIP data, infrastructure, retention settings, and compliance notices. Do not send personal data in URLs, campaign parameters, event names, or custom event properties.</p>
    <h2>6. Availability</h2>
    <p>Self-hosted availability depends on the operator's infrastructure, database, Redis service, backups, and scheduled workers. No uptime guarantee is provided by the software itself.</p>
    <h2>7. Liability</h2>
    <p>To the extent permitted by law, the software is provided without warranty. Nothing in these terms excludes liability that cannot legally be excluded, including liability for intent or gross negligence.</p>
    <h2>8. Governing law</h2>
    <p>Unless separate deployment terms apply, these terms are governed by the laws of Germany. Where legally permissible, Hamburg is the place of jurisdiction.</p>
    <h2>9. Contact</h2>
    <p>Questions: <a href="mailto:support@skyggn.dev">support@skyggn.dev</a>.</p>
</section>
HTML);
    }

    private function page(string $title, string $content): void
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} | ClearStats</title>
</head>
<body>
    <main class="page page-narrow">
        {$content}
        <footer class="site-footer">
            <span>ClearStats</span>
            <nav aria-label="Footer navigation">
                <a href="/">Home</a>
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
