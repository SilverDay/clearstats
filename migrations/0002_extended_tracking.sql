-- Extended tracking dimensions: UTM term/content, revenue-tagged goals, and
-- optional city/region geo. See docs/analytics-platform-spec.md §4/§6 and
-- CLAUDE.md's migration-tooling decision — this is a new numbered file, not
-- an edit to 0001_initial_schema.sql.
--
-- Outbound-link, file-download, and 404 tracking need no schema change at
-- all: they ride the existing custom/conversion event pipeline
-- (events_raw.event_type = 'custom', event_name like 'Outbound Link: ...'),
-- which already rolls into daily_event_stats.

ALTER TABLE events_raw
ADD COLUMN IF NOT EXISTS campaign_term VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS campaign_content VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS revenue_amount DECIMAL(12, 2) NULL,
    ADD COLUMN IF NOT EXISTS revenue_currency CHAR(3) NULL,
    ADD COLUMN IF NOT EXISTS region VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS city VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS daily_campaign_term_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    campaign_term VARCHAR(128) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, campaign_term)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS daily_campaign_content_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    campaign_content VARCHAR(128) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, campaign_content)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- region is a country-scoped subdivision code (e.g. "CA"), not globally
-- unique on its own, so the key includes country_code.
CREATE TABLE IF NOT EXISTS daily_region_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    country_code CHAR(2) NOT NULL,
    region VARCHAR(8) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, country_code, region)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Same reasoning as daily_page_stats' url_path(255) prefix key: city names
-- are free text, so the key uses a prefix rather than the full column.
CREATE TABLE IF NOT EXISTS daily_city_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    country_code CHAR(2) NOT NULL,
    city VARCHAR(255) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, country_code, city(191))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Revenue is summed per currency, never across currencies — a goal fired in
-- both EUR and USD produces two rows for the same (site_id, date, event_name).
CREATE TABLE IF NOT EXISTS daily_revenue_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    currency CHAR(3) NOT NULL,
    conversions INT UNSIGNED NOT NULL DEFAULT 0,
    revenue_total DECIMAL(14, 2) NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, event_name, currency)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
