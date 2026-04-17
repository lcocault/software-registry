-- Migration 003: Add CVE storage tables.
--
-- Adds two new tables to persist CVE data retrieved from the OSV API:
--
--   "dependency_cve_fetches"  — one row per (dependency_name, dependency_version),
--                               recording when CVEs were last retrieved from the API.
--
--   "dependency_cves"         — the individual CVE records for each (dependency_name,
--                               dependency_version), linked to the fetch record.

CREATE TABLE dependency_cve_fetches (
    dependency_name    VARCHAR(255) NOT NULL,
    dependency_version VARCHAR(100) NOT NULL,
    fetched_at         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dependency_name, dependency_version)
);

CREATE TABLE dependency_cves (
    id                 SERIAL PRIMARY KEY,
    dependency_name    VARCHAR(255) NOT NULL,
    dependency_version VARCHAR(100) NOT NULL,
    cve_id             VARCHAR(255) NOT NULL,
    description        TEXT NOT NULL DEFAULT '',
    severity           VARCHAR(50) NOT NULL DEFAULT '',
    UNIQUE (dependency_name, dependency_version, cve_id)
);

CREATE INDEX IF NOT EXISTS idx_dependency_cves_dep ON dependency_cves(dependency_name, dependency_version);
