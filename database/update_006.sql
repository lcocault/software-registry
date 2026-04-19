-- Migration 006: Add component-level high-level dependencies.
--
-- A high-level dependency belongs to a component (not a specific version).
-- It has a name, a reuse justification, an integration strategy, a validation
-- strategy, and a set of 3rd party dependency names that implement it.

CREATE TABLE component_high_level_deps (
    id                   SERIAL PRIMARY KEY,
    component_id         INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    name                 VARCHAR(255) NOT NULL,
    reuse_justification  TEXT NOT NULL DEFAULT '',
    integration_strategy TEXT NOT NULL DEFAULT '',
    validation_strategy  TEXT NOT NULL DEFAULT '',
    UNIQUE (component_id, name)
);

CREATE INDEX IF NOT EXISTS idx_component_hld_component_id ON component_high_level_deps(component_id);

CREATE TABLE high_level_dep_third_party (
    high_level_dep_id INTEGER      NOT NULL REFERENCES component_high_level_deps(id) ON DELETE CASCADE,
    dependency_name   VARCHAR(255) NOT NULL,
    PRIMARY KEY (high_level_dep_id, dependency_name)
);
