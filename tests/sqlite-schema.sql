CREATE TABLE IF NOT EXISTS sites (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    domain TEXT NOT NULL UNIQUE,
    owner_user_id INTEGER NOT NULL,
    ip_source TEXT NOT NULL DEFAULT 'direct',
    trusted_proxy_config TEXT,
    raw_event_retention_days INTEGER NOT NULL DEFAULT 30,
    active INTEGER NOT NULL DEFAULT 1,
    track_outbound_links INTEGER NOT NULL DEFAULT 0,
    track_file_downloads INTEGER NOT NULL DEFAULT 0,
    track_404 INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS salt_state (
    id INTEGER PRIMARY KEY,
    current_salt TEXT NOT NULL,
    previous_salt TEXT,
    generated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS user_site_access (
    user_id INTEGER NOT NULL,
    site_id TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'viewer',
    PRIMARY KEY (user_id, site_id)
);
CREATE TABLE IF NOT EXISTS events_raw (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id TEXT UNIQUE,
    site_id TEXT NOT NULL,
    session_id TEXT,
    session_started_at TEXT,
    visitor_hash TEXT NOT NULL,
    event_type TEXT NOT NULL,
    event_name TEXT,
    engagement_seconds INTEGER NOT NULL DEFAULT 0,
    url_path TEXT NOT NULL,
    campaign_source TEXT,
    campaign_medium TEXT,
    campaign_name TEXT,
    campaign_term TEXT,
    campaign_content TEXT,
    revenue_amount REAL,
    revenue_currency TEXT,
    referrer_domain TEXT,
    country_code TEXT,
    region TEXT,
    city TEXT,
    device_type TEXT NOT NULL DEFAULT 'other',
    browser TEXT,
    operating_system TEXT,
    language_code TEXT,
    event_props TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS daily_site_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    unique_visitor_hashes_count INTEGER NOT NULL DEFAULT 0,
    sessions INTEGER NOT NULL DEFAULT 0,
    bounces INTEGER NOT NULL DEFAULT 0,
    avg_engagement_seconds INTEGER NOT NULL DEFAULT 0,
    bounce_rate REAL,
    PRIMARY KEY (site_id, date)
);
CREATE TABLE IF NOT EXISTS daily_page_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    url_path TEXT NOT NULL,
    pageviews INTEGER NOT NULL DEFAULT 0,
    visitors INTEGER NOT NULL DEFAULT 0,
    entrances INTEGER NOT NULL DEFAULT 0,
    bounces INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, url_path)
);
CREATE TABLE IF NOT EXISTS daily_referrer_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    referrer_domain TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, referrer_domain)
);
CREATE TABLE IF NOT EXISTS daily_country_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    country_code TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, country_code)
);
CREATE TABLE IF NOT EXISTS daily_device_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    device_type TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, device_type)
);
CREATE TABLE IF NOT EXISTS daily_event_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    event_name TEXT NOT NULL,
    events INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, event_name)
);
CREATE TABLE IF NOT EXISTS daily_campaign_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    campaign_source TEXT NOT NULL,
    campaign_medium TEXT NOT NULL,
    campaign_name TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (
        site_id,
        date,
        campaign_source,
        campaign_medium,
        campaign_name
    )
);
CREATE TABLE IF NOT EXISTS daily_browser_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    browser TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, browser)
);
CREATE TABLE IF NOT EXISTS daily_os_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    operating_system TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, operating_system)
);
CREATE TABLE IF NOT EXISTS daily_language_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    language_code TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, language_code)
);
CREATE TABLE IF NOT EXISTS daily_campaign_term_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    campaign_term TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, campaign_term)
);
CREATE TABLE IF NOT EXISTS daily_campaign_content_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    campaign_content TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, campaign_content)
);
CREATE TABLE IF NOT EXISTS daily_region_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    country_code TEXT NOT NULL,
    region TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, country_code, region)
);
CREATE TABLE IF NOT EXISTS daily_city_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    country_code TEXT NOT NULL,
    city TEXT NOT NULL,
    visits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, country_code, city)
);
CREATE TABLE IF NOT EXISTS daily_revenue_stats (
    site_id TEXT NOT NULL,
    date TEXT NOT NULL,
    event_name TEXT NOT NULL,
    currency TEXT NOT NULL,
    conversions INTEGER NOT NULL DEFAULT 0,
    revenue_total REAL NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, date, event_name, currency)
);
