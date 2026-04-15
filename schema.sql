CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS components (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    version VARCHAR(100) NOT NULL,
    owner VARCHAR(255) NOT NULL,
    language VARCHAR(32) NOT NULL,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS dependencies (
    id SERIAL PRIMARY KEY,
    component_id INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    version VARCHAR(100) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_dependencies_component_id ON dependencies(component_id);
