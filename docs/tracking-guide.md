# ClearStats Tracking Guide

This guide describes the data collected by the ClearStats browser tracker and the integration hooks available to tracked sites.

## Install the tracker

Add the site-specific snippet before the closing `</body>` tag:

```html
<script defer data-site-id="YOUR_SITE_ID" src="https://clearstats.online/js/track.js"></script>
```

Use the site ID shown by ClearStats after site creation or from the site's **Install** page.

## Automatic pageviews

The tracker sends one `pageview` event when it loads. It also detects SPA navigation through `history.pushState`, `history.replaceState`, and `popstate`.

Each pageview may include:

- Page path and query string
- Referrer domain
- Site-specific ephemeral session ID
- Sanitized UTM campaign values
- Coarse browser, operating system, device, language, and country metadata

## Campaign attribution

Campaign values are read automatically from the page URL:

```text
https://example.com/article?utm_source=newsletter&utm_medium=email&utm_campaign=launch
```

Supported values:

- `utm_source`
- `utm_medium`
- `utm_campaign`

Values are limited to 128 characters and should never contain email addresses, user IDs, tokens, or other personal data. Campaigns appear in the dashboard after the queue worker and rollup job have run.

## Conversion events

Use the conversion helper for named goals:

```js
window.clearstats.trackConversion('signup_completed');
window.clearstats.trackConversion('contact_form_submitted');
window.clearstats.trackConversion('purchase_completed');
```

Conversions are stored as custom events with the `conversion:` prefix and appear in the dashboard's **Conversions and custom events** panel.

Event names should be stable, short, and free of personal data.

## Custom events

For other named events:

```js
window.clearstats.trackEvent('video_started');
window.clearstats.trackEvent('pricing_cta_clicked');
```

The current server stores the event name. Do not send personal data in event names or properties.

## Click and scroll hooks

The tracker exposes optional hooks for coarse interaction events:

```js
window.clearstats.trackClick('pricing_cta');
window.clearstats.trackScroll(50);
```

The tracker also emits scroll milestone events at 25%, 50%, 75%, and 100% page depth. These are aggregate interaction signals, not session replay or heatmaps.

## Sessions and engagement

A random 32-character session value is generated in memory when the tracker loads. It is not stored in cookies, localStorage, or sessionStorage. It is used only for ephemeral session, bounce, and engagement calculations and does not identify a visitor across days.

When the page is hidden or unloaded, the tracker sends a `session_end` event with the elapsed page time, subject to normal browser beacon behavior.

## Privacy boundaries

ClearStats does not use:

- Cookies
- `localStorage` or `sessionStorage`
- Persistent visitor IDs
- Cross-day returning-visitor identification
- Browser fingerprinting
- Raw IP or raw User-Agent storage
- Session replay, heatmaps, or form analytics

The server uses the request IP and User-Agent transiently to calculate a daily, site-specific HMAC visitor hash. Raw values are discarded before the event is queued.

## Processing and dashboard timing

Events are accepted by the ingestion API and placed in Redis. The queue worker writes them to the short-lived raw event table. The rollup job then updates daily aggregate tables used by the dashboard.

Typical cron schedule:

```cron
* * * * * cd /srv/vhosts/clearstats.online && /usr/bin/flock -n /tmp/clearstats-queue.lock /usr/bin/php bin/queue-worker.php --limit=500 >> logs/queue-worker.log 2>&1
5 * * * * cd /srv/vhosts/clearstats.online && /usr/bin/flock -n /tmp/clearstats-rollup.lock /usr/bin/php bin/rollup.php >> logs/rollup.log 2>&1
```

Data may therefore take up to the worker/rollup interval to appear in the dashboard.
