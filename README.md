# software-registry

Web site managing a registry of software components and their dependencies.

## Features

A **user registry** stores:
- First name
- Last name
- Email address

Each software component stores:
- name
- version
- owner (selected from the user registry)
- project
- language
- list of versioned dependencies

The registration form supports dependency import from:
- Java: output from `mvn dependency:tree`
- Python: output from `pip list` (table or `name==version` format)
- JavaScript: `package-lock.json`

## High-level dependency import format

High-level dependencies can be bulk-imported from a JSON file on the component's
*High-Level Dependencies* page.

The file must be a JSON object with a single top-level key `highLevelDependencies`
whose value is an array of dependency entries.

### Fields

| Field | Type | Required | Constraints |
|---|---|---|---|
| `name` | string | **yes** | Non-empty, ≤ 255 characters, unique within the file |
| `license` | string | no | Must be one of the recognised license values listed below, or omitted / empty |
| `reuseJustification` | string | no | Free text |
| `integrationStrategy` | string | no | Free text |
| `validationStrategy` | string | no | Free text |
| `thirdPartyDependencies` | array of strings | no | Each entry must be a non-empty string ≤ 255 characters |

### Recognised license values

`2-clause BSD License (free BSD)`, `3-clause BSD License (Modified / new BSD)`,
`AGPL3`, `Apache 2.0`, `CDDL-1.0/CDDL1.1`, `CPL/EPL`, `GPL v2`, `GPL v3`,
`LGPL v2.1`, `LGPL v3`, `MIT License`, `MPL2.0/MPL1.1`, `MS-PL`, `Proprietary`,
`Other`

### Example

```json
{
  "highLevelDependencies": [
    {
      "name": "Logging",
      "license": "MIT License",
      "reuseJustification": "Provides structured, levelled logging across all application layers.",
      "integrationStrategy": "Use the SLF4J facade so the underlying implementation can be swapped without changing application code.",
      "validationStrategy": "Unit tests verify that every log call site produces the expected log level and message.",
      "thirdPartyDependencies": [
        "ch.qos.logback:logback-classic",
        "org.slf4j:slf4j-api"
      ]
    },
    {
      "name": "Security — Minimal entry"
    }
  ]
}
```

A ready-to-use sample file is available at
[`docs/high_level_deps_import_sample.json`](docs/high_level_deps_import_sample.json).

## Run with Docker (recommended)

This is the easiest way to run the application locally. You only need
[Docker](https://docs.docker.com/get-docker/) and
[Docker Compose](https://docs.docker.com/compose/install/) installed.

1. Copy the example environment file and set a database password:
   ```bash
   cp .env.example .env
   # Edit .env and replace "changeme" with a password of your choice
   ```
2. Build and start the services:
   ```bash
   docker compose up --build
   ```
   Docker Compose will:
   - Build the PHP/Apache application image.
   - Start a PostgreSQL 16 database and initialise it automatically with
     `database/schema.sql`.
   - Wait for the database to be healthy before starting the application.
3. Open <http://localhost:8080> in your browser.

To stop and remove the containers (data is kept in the `db_data` volume):
```bash
docker compose down
```

To also remove all stored data:
```bash
docker compose down -v
```

## Run locally (without Docker)

1. Create a PostgreSQL database and configure:
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `5432`)
   - `DB_NAME` (default `software_registry`)
   - `DB_USER` (default `postgres`)
   - `DB_PASSWORD` (**required**)
2. Initialize schema:
   ```sql
   \i database/schema.sql
   ```
3. Start PHP server:
   ```bash
   php -S 127.0.0.1:8000 -t .
   ```

## Upgrading an existing database

If you already have data from a previous version, run the migration script:
```sql
\i database/update_002.sql
```
