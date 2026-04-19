-- Migration 004: Separate component versions from components.
--
-- Before: components.version holds the version label directly.
--         versioned_dependencies.component_id references components(id).
--
-- After:
--   "component_versions" — one row per (component, version label), with a
--                          stable surrogate key used by versioned_dependencies.
--   versioned_dependencies.component_version_id references component_versions(id).

-- Step 1: Create the component_versions table.
CREATE TABLE component_versions (
    id           SERIAL PRIMARY KEY,
    component_id INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    label        VARCHAR(100) NOT NULL,
    UNIQUE (component_id, label)
);

CREATE INDEX idx_component_versions_component_id ON component_versions(component_id);

-- Step 2: Populate component_versions from the existing version column.
INSERT INTO component_versions (component_id, label)
SELECT id, version FROM components;

-- Step 3: Add component_version_id (nullable for now) to versioned_dependencies.
ALTER TABLE versioned_dependencies
    ADD COLUMN component_version_id INTEGER REFERENCES component_versions(id) ON DELETE CASCADE;

-- Step 4: Populate component_version_id by matching the old component_id.
UPDATE versioned_dependencies vd
SET component_version_id = cv.id
FROM component_versions cv
WHERE cv.component_id = vd.component_id;

-- Step 5: Make component_version_id NOT NULL.
ALTER TABLE versioned_dependencies ALTER COLUMN component_version_id SET NOT NULL;

-- Step 6: Drop the old composite primary key (component_id, dependency_id).
ALTER TABLE versioned_dependencies DROP CONSTRAINT versioned_dependencies_pkey;

-- Step 7: Drop the old component_id FK column.
ALTER TABLE versioned_dependencies DROP COLUMN component_id;

-- Step 8: Establish the new primary key on (component_version_id, dependency_id).
ALTER TABLE versioned_dependencies ADD PRIMARY KEY (component_version_id, dependency_id);

-- Step 9: Remove the version column from components (now stored in component_versions).
ALTER TABLE components DROP COLUMN version;
