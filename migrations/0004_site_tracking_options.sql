-- Per-site opt-in for the optional track.js features (see
-- docs/tracking-guide.md): outbound-link, file-download, and 404 tracking
-- are opt-in at the script-tag level (data-outbound-links etc.) so that
-- installing track.js never silently starts collecting more than a site
-- asks for. These flags let the Sites UI generate the correct snippet for
-- each site instead of the operator hand-editing data attributes.

ALTER TABLE sites
ADD COLUMN IF NOT EXISTS track_outbound_links TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS track_file_downloads TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS track_404 TINYINT(1) NOT NULL DEFAULT 0;
