# software-registry

Web site managing a registry of software components and their dependencies.

## Features

Each software component stores:
- name
- version
- owner
- project
- language
- list of versioned dependencies

The registration form supports dependency import from:
- Java: output from `mvn dependency:tree`
- Python: output from `pip list` (table or `name==version` format)
- JavaScript: `package-lock.json`

## Run locally

1. Create a PostgreSQL database and configure:
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `5432`)
   - `DB_NAME` (default `software_registry`)
   - `DB_USER` (default `postgres`)
   - `DB_PASSWORD` (**required**)
2. Initialize schema:
   ```sql
   \i schema.sql
   ```
3. Start PHP server:
   ```bash
   php -S 127.0.0.1:8000 -t .
   ```
