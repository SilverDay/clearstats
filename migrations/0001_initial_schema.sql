-- SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
-- SPDX-License-Identifier: MIT
--
-- Initial ClearStats schema, per docs/analytics-platform-spec.md §6.
-- Skeleton only — review column types/lengths before running against production.
CREATE TABLE IF NOT EXISTS sites (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    ip_source ENUM('direct', 'x-forwarded-for', 'cf-connecting-ip') NOT NULL DEFAULT 'direct',
    trusted_proxy_config TEXT NULL,
    raw_event_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sites_domain (domain)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS salt_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    current_salt CHAR(64) NOT NULL,
    previous_salt CHAR(64) NULL,
    generated_at DATETIME NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS user_site_access (
    user_id BIGINT UNSIGNED NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'viewer',
    PRIMARY KEY (user_id, site_id),
    CONSTRAINT fk_usa_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_usa_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Raw events: short-lived, purged per-site per sites.raw_event_retention_days.
-- No IP, no raw User-Agent, no persistent visitor identifier — see spec §3, §6.1.
CREATE TABLE IF NOT EXISTS events_raw (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id CHAR(64) NULL,
    site_id VARCHAR(32) NOT NULL,
    session_id CHAR(32) NULL,
    session_started_at DATETIME NULL,
    visitor_hash CHAR(64) NOT NULL,
    event_type ENUM('pageview', 'custom', 'session_end') NOT NULL,
    event_name VARCHAR(128) NULL,
    engagement_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    url_path VARCHAR(2048) NOT NULL,
    campaign_source VARCHAR(128) NULL,
    campaign_medium VARCHAR(128) NULL,
    campaign_name VARCHAR(128) NULL,
    referrer_domain VARCHAR(255) NULL,
    country_code CHAR(2) NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'other') NOT NULL DEFAULT 'other',
    browser VARCHAR(32) NULL,
    operating_system VARCHAR(32) NULL,
    language_code CHAR(5) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_events_raw_site_date (site_id, created_at),
    UNIQUE KEY uq_events_raw_event_id (event_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
ALTER TABLE events_raw
ADD COLUMN IF NOT EXISTS event_id CHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS session_id CHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS session_started_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS engagement_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campaign_source VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS campaign_medium VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS campaign_name VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS operating_system VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS language_code CHAR(5) NULL,
    MODIFY COLUMN event_type ENUM('pageview', 'custom', 'session_end') NOT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_events_raw_event_id (event_id);
-- Rollup tables — dashboard reads only ever hit these, never events_raw. See spec §6.2.
CREATE TABLE IF NOT EXISTS daily_site_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    pageviews INT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitor_hashes_count INT UNSIGNED NOT NULL DEFAULT 0,
    sessions INT UNSIGNED NOT NULL DEFAULT 0,
    bounces INT UNSIGNED NOT NULL DEFAULT 0,
    avg_engagement_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    bounce_rate DECIMAL(5, 2) NULL,
    PRIMARY KEY (site_id, date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
ALTER TABLE daily_site_stats
ADD COLUMN IF NOT EXISTS sessions INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bounces INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS avg_engagement_seconds INT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS daily_page_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    url_path VARCHAR(2048) NOT NULL,
    pageviews INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, url_path(255))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Per-page visitor/bounce detail for the Top Pages dashboard breakdown.
-- entrances/bounces are keyed to the page a session STARTED on, not every page
-- it viewed, so bounce_rate = bounces / entrances mirrors how Plausible/GA
-- attribute bounces to the landing page rather than every page visited.
ALTER TABLE daily_page_stats
ADD COLUMN IF NOT EXISTS visitors INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS entrances INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bounces INT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS daily_referrer_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    referrer_domain VARCHAR(255) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, referrer_domain)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS daily_country_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    country_code CHAR(2) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, country_code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS daily_device_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'other') NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, device_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS daily_os_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    operating_system VARCHAR(32) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, operating_system)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS daily_language_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    language_code CHAR(5) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, language_code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS daily_event_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    event_name VARCHAR(128) NOT NULL,
    events INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, event_name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS daily_campaign_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    campaign_source VARCHAR(128) NOT NULL,
    campaign_medium VARCHAR(128) NOT NULL,
    campaign_name VARCHAR(128) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (
        site_id,
        date,
        campaign_source,
        campaign_medium,
        campaign_name
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
