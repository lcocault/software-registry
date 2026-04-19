-- Migration 005: Add catalog_entries table for manually-added 3rd party entries.
--
-- This allows users to add a 3rd party dependency and version directly from
-- the catalog without requiring it to be referenced by a component version.

CREATE TABLE IF NOT EXISTS catalog_entries (
    id      SERIAL PRIMARY KEY,
    name    VARCHAR(255) NOT NULL,
    version VARCHAR(100) NOT NULL,
    UNIQUE (name, version)
);

CREATE INDEX IF NOT EXISTS idx_catalog_entries_name ON catalog_entries(name);
