-- trackEvent(name, props) has always accepted a props object client-side and
-- POST /api/event has always allowed a `props` field, but nothing ever stored
-- it — it was silently discarded after ingestion. This column stops that:
-- props are now persisted (bounded, see EventController::boundedProps()) for
-- the raw event's normal retention window.
--
-- Scope note: this only fixes the silent data loss. There is still no
-- rollup or dashboard breakdown by property value — reading stored props
-- back out is a future feature, not part of this migration.

ALTER TABLE events_raw
ADD COLUMN IF NOT EXISTS event_props TEXT NULL;
