# ClearStats Tracking Guide

This guide describes the data collected by the ClearStats browser tracker and the integration hooks available to tracked sites.

## Install the tracker

Add the site-specific snippet before the closing `</body>` tag:

```html
<script defer data-site-id="YOUR_SITE_ID" src="https://clearstats.online/js/track.js"></script>
```

Use the site ID shown by ClearStats after site creation or from the site's **Install** page.

### Optional automatic tracking

Outbound-link, file-download, and 404 tracking are opt-in — installing the base snippet above does not enable them. Add the relevant attribute to collect more than the default:

```html
<script defer data-site-id="YOUR_SITE_ID" data-outbound-links data-file-downloads
        src="https://clearstats.online/js/track.js"></script>
```

- `data-outbound-links` — fires an `Outbound Link: <hostname>` event when a visitor clicks a link to a different domain.
- `data-file-downloads` — fires a `File Download: <filename>` event when a visitor clicks a link to a common downloadable file type (PDF, ZIP, DOCX, MP3, etc.).
- `data-404` — place this attribute on the script tag **only on your site's actual 404/error page**. The tracker has no way to see the HTTP status code the server sent, so this fires a `404` event by placement, not by detection, the same way Plausible's own 404 tracking works.

All three appear in the dashboard's **Conversions and custom events** panel alongside conversions and custom events.

## Automatic pageviews

The tracker sends one `pageview` event when it loads. It also detects SPA navigation through `history.pushState`, `history.replaceState`, and `popstate`.

Each pageview may include:

- Page path and query string
- Referrer domain
- Site-specific ephemeral session ID
- Sanitized UTM campaign values
- Coarse browser, operating system, device, language, and country metadata
- Region and city, if the operator has configured a GeoLite2-City database (`geoip.city_database_path`); empty otherwise

## Campaign attribution

Campaign values are read automatically from the page URL:

```text
https://example.com/article?utm_source=newsletter&utm_medium=email&utm_campaign=launch
```

Supported values:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_term`
- `utm_content`

Values are limited to 128 characters and should never contain email addresses, user IDs, tokens, or other personal data. Campaigns appear in the dashboard after the queue worker and rollup job have run. `utm_term`/`utm_content` are reported as their own breakdowns (**Campaign term**/**Campaign content**), separate from the source/medium/campaign breakdown — the same separation Plausible uses, rather than combining all five into one wide key.

## Conversion events

Use the conversion helper for named goals:

```js
window.clearstats.trackConversion('signup_completed');
window.clearstats.trackConversion('contact_form_submitted');
window.clearstats.trackConversion('purchase_completed');
```

Conversions are stored as custom events with the `conversion:` prefix and appear in the dashboard's **Conversions and custom events** panel.

Event names should be stable, short, and free of personal data.

### Revenue goals

Attach a monetary value to a conversion for the dashboard's **Revenue** panel:

```js
window.clearstats.trackConversion('purchase_completed', {
    revenue: { amount: 49.00, currency: 'EUR' }
});
```

`amount` must be a non-negative number; `currency` an ISO 4217 code (e.g. `EUR`, `USD`). Both are required together — an amount sent without a valid currency (or vice versa) is dropped, never stored as a dangling value. Revenue is summed **per currency**, never combined across currencies, since amounts in different currencies cannot be added together without a live exchange rate the platform doesn't track.

## Custom events

For other named events:

```js
window.clearstats.trackEvent('video_started');
window.clearstats.trackEvent('pricing_cta_clicked');
window.clearstats.trackEvent('video_started', { video_id: 'intro', position: 0 });
```

The event name is stored and counted in the dashboard's **Conversions and custom events** panel. An optional `props` object is stored alongside the raw event (bounded to 2KB once serialized — anything larger is dropped, not truncated) for the event's normal retention window, but there is currently no dashboard breakdown or filtering by property value; storing them stops the data from being silently discarded, it doesn't yet make them visible anywhere in the UI. Do not send personal data in event names or properties.

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
