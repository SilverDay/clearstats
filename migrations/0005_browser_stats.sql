-- events_raw.browser has been classified and stored since 0001, but nothing
-- ever rolled it up — there is no dashboard breakdown for it. Mirrors
-- daily_os_stats exactly.

CREATE TABLE IF NOT EXISTS daily_browser_stats (
    site_id VARCHAR(32) NOT NULL,
    date DATE NOT NULL,
    browser VARCHAR(32) NOT NULL,
    visits INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, browser)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
