-- Migration 007: Add license field to high-level dependencies.
--
-- The license field stores the license of the high-level dependency.
-- Allowed values are listed in the application layer; the column stores
-- an empty string when no license has been specified yet.

ALTER TABLE component_high_level_deps
    ADD COLUMN license VARCHAR(100) NOT NULL DEFAULT '';
