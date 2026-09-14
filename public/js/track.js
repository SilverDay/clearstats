/*
 * SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
 * SPDX-License-Identifier: MIT
 *
 * ClearStats tracking script.
 * See docs/analytics-platform-spec.md §4 for the full behavior spec.
 *
 * Constraints (do not violate without flagging explicitly, see /CLAUDE.md):
 *  - No cookies.
 *  - No localStorage / sessionStorage.
 *  - No fingerprinting-adjacent data collection (canvas, fonts, plugins, etc).
 */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) return;

    var siteId = script.getAttribute('data-site-id');
    if (!siteId) return;

    var endpoint = script.src.replace(/\/js\/track\.js.*$/, '/api/event?site_id=' + encodeURIComponent(siteId));
    var sessionId = Array.from(crypto.getRandomValues(new Uint8Array(16))).map(function (value) {
        return value.toString(16).padStart(2, '0');
    }).join('');
    var sessionStartedAt = new Date().toISOString();
    var pageStartedAt = Date.now();

    function campaign(name) {
        var value = new URLSearchParams(location.search).get(name);
        return value ? value.slice(0, 128) : undefined;
    }

    function send(eventType, extra) {
        var payload = Object.assign(
            {
                site_id: siteId,
                domain: location.hostname,
                event_type: eventType,
                url_path: location.pathname + location.search,
                referrer_url: document.referrer || ''
                , session_id: sessionId
                , session_started_at: sessionStartedAt
                , campaign_source: campaign('utm_source')
                , campaign_medium: campaign('utm_medium')
                , campaign_name: campaign('utm_campaign')
            },
            extra || {}
        );

        var body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
            var blob = new Blob([body], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);
        } else {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                keepalive: true
            }).catch(function () {
                /* best-effort; analytics failures must never surface to the visitor */
            });
        }
    }

    // Public API for SPA route changes / custom events (see spec §4.3).
    window.clearstats = window.clearstats || {};
    window.clearstats.trackPageview = function () {
        send('pageview');
    };
    window.clearstats.trackEvent = function (name, props) {
        send('custom', { event_name: name, props: props || {} });
    };
    window.clearstats.trackConversion = function (name) {
        send('custom', { event_name: 'conversion:' + String(name).slice(0, 96) });
    };
    window.clearstats.trackClick = function (name) {
        send('custom', { event_name: 'click:' + String(name || 'unnamed').slice(0, 96) });
    };
    window.clearstats.trackScroll = function (depth) {
        send('custom', { event_name: 'scroll:' + String(depth) });
    };

    var scrollMarks = [25, 50, 75, 100];
    var reached = {};
    window.addEventListener('scroll', function () {
        var documentHeight = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight) - window.innerHeight;
        var depth = documentHeight > 0 ? Math.min(100, Math.round((window.scrollY / documentHeight) * 100)) : 100;
        scrollMarks.forEach(function (mark) {
            if (depth >= mark && !reached[mark]) {
                reached[mark] = true;
                window.clearstats.trackScroll(mark);
            }
        });
    }, { passive: true });

    var lastLocation = location.href;
    function trackRouteChange() {
        if (location.href === lastLocation) return;
        lastLocation = location.href;
        window.clearstats.trackPageview();
    }

    var originalPushState = history.pushState;
    history.pushState = function () {
        var result = originalPushState.apply(this, arguments);
        trackRouteChange();
        return result;
    };
    var originalReplaceState = history.replaceState;
    history.replaceState = function () {
        var result = originalReplaceState.apply(this, arguments);
        trackRouteChange();
        return result;
    };
    window.addEventListener('popstate', trackRouteChange);

    var ended = false;
    function sendSessionEnd() {
        if (ended) return;
        ended = true;
        send('session_end', { engagement_seconds: Math.max(0, Math.floor((Date.now() - pageStartedAt) / 1000)) });
    }
    window.addEventListener('pagehide', sendSessionEnd);

    // Initial pageview on script load.
    send('pageview');
})();
