-- Migration 001: Refactor dependencies into a normalized two-table model.
--
-- Before: a single "dependencies" table with (id, component_id, name, version)
--         — a 1-N relationship between components and dependencies.
--
-- After:
--   "dependencies"          — unique list of dependency names (id, name)
--   "versioned_dependencies"— N-N join between components and dependencies
--                             with a version column (component_id, dependency_id, version)

-- Step 1: Keep the old data in a temporary table.
ALTER TABLE dependencies RENAME TO dependencies_old;

-- Step 2: Create the new normalised "dependencies" table (names only).
CREATE TABLE dependencies (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL
);

-- Step 3: Populate it with every distinct dependency name.
INSERT INTO dependencies (name)
SELECT DISTINCT name FROM dependencies_old;

-- Step 4: Create the "versioned_dependencies" join table.
CREATE TABLE versioned_dependencies (
    component_id  INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    dependency_id INTEGER NOT NULL REFERENCES dependencies(id),
    version       VARCHAR(100) NOT NULL,
    PRIMARY KEY (component_id, dependency_id)
);

CREATE INDEX idx_versioned_dependencies_dependency_id ON versioned_dependencies(dependency_id);

-- Step 5: Migrate existing rows into the new join table.
INSERT INTO versioned_dependencies (component_id, dependency_id, version)
SELECT d.component_id, dn.id, d.version
FROM dependencies_old d
JOIN dependencies dn ON dn.name = d.name;

-- Step 6: Drop the old table now that the data has been migrated.
DROP TABLE dependencies_old;
