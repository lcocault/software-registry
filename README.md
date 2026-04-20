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
