CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id        SERIAL PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    name      VARCHAR(100) NOT NULL,
    email     VARCHAR(255) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS components (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    version    VARCHAR(100) NOT NULL,
    owner_id   INTEGER NOT NULL REFERENCES users(id),
    language   VARCHAR(32) NOT NULL,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_components_project_id ON components(project_id);
CREATE INDEX IF NOT EXISTS idx_components_owner_id   ON components(owner_id);

CREATE TABLE IF NOT EXISTS dependencies (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS versioned_dependencies (
    component_id  INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    dependency_id INTEGER NOT NULL REFERENCES dependencies(id),
    version       VARCHAR(100) NOT NULL,
    PRIMARY KEY (component_id, dependency_id)
);

CREATE INDEX IF NOT EXISTS idx_versioned_dependencies_dependency_id ON versioned_dependencies(dependency_id);
