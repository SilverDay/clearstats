# Website Privacy Notice: ClearStats Analytics

*Template for websites that use ClearStats. Replace the bracketed values and have the final wording reviewed for the specific site and deployment.*

## Privacy-friendly analytics

We use ClearStats to understand how visitors use this website. ClearStats is a self-hosted, privacy-focused analytics platform operated by [CONTROLLER / COMPANY NAME]. It is configured without advertising, cross-site tracking, or user profiling.

ClearStats does not use analytics cookies, localStorage, sessionStorage, browser fingerprinting, or persistent visitor identifiers.

## Data processed

When you visit a page, the ClearStats tracking script may send:

- The page path and query string
- The referring domain, where available
- The event type, such as a pageview or an explicitly configured conversion event
- Coarse technical information, such as browser, operating system, device type, preferred language, and country
- Sanitized campaign values, such as UTM source, medium, and campaign name, when present in the page URL
- A short-lived in-memory session identifier used to calculate session and engagement statistics

The ClearStats server necessarily receives the network address and User-Agent header as part of the HTTP request. These values are used transiently to calculate a site-specific, daily visitor hash. The raw network address and raw User-Agent are discarded immediately and are not stored in the analytics event, database, or application queue.

The daily visitor hash cannot be used by the website operator to identify you by name. It is rotated with the daily salt and is not used to identify returning visitors across days.

## Purpose and legal basis

We process this information to measure website reach, diagnose technical problems, understand aggregate content usage, and improve the website.

The applicable legal basis is [LEGAL BASIS, FOR EXAMPLE: our legitimate interest under Article 6(1)(f) GDPR]. The configuration is designed to minimize the data needed for aggregate analytics and does not create advertising profiles.

## Retention

Short-lived raw analytics events are retained for approximately [RETENTION PERIOD] and then deleted according to the ClearStats configuration. Aggregated statistics may be retained for longer because they no longer contain raw network addresses or raw User-Agent values.

The exact retention period is controlled by the operator of this website and may be [RETENTION PERIOD, FOR EXAMPLE: 30 days].

## Service provider and hosting

ClearStats is operated by [CONTROLLER / COMPANY NAME] at [HOSTING LOCATION / PROVIDER]. The analytics data is processed on infrastructure controlled by the operator or its configured hosting providers.

Where a local MaxMind country database is configured, country resolution takes place on the ClearStats server and only the resulting coarse country code is retained. No raw network address is sent to MaxMind by the tracking script.

## Your rights

Depending on your jurisdiction, you may have rights to access, correct, delete, restrict, or object to the processing of your personal data, and to lodge a complaint with a supervisory authority.

For privacy questions or to exercise applicable rights, contact:

[PRIVACY CONTACT NAME]
[PRIVACY EMAIL]
[POSTAL ADDRESS, IF REQUIRED]

## Changes

We may update this notice when the website, analytics configuration, or applicable legal requirements change.

*This template is informational and is not legal advice. The website operator is responsible for completing and reviewing it for the actual ClearStats deployment, retention settings, hosting providers, legal basis, and jurisdiction.*
