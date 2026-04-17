-- Migration 002: Add users table and replace free-text owner with a FK reference.
--
-- Before: components.owner is a free-text VARCHAR(255) column.
--
-- After:
--   "users"     — registered users (id, firstname, name, email)
--   components.owner_id — FK to users(id) replacing the owner text column

-- Step 1: Create the users table.
CREATE TABLE users (
    id        SERIAL PRIMARY KEY,
    firstname VARCHAR(100) NOT NULL,
    name      VARCHAR(100) NOT NULL,
    email     VARCHAR(255) UNIQUE NOT NULL
);

-- Step 2: Insert one placeholder user for each distinct owner value so that
--         existing data keeps a valid FK reference.
--         The email is derived from the owner name (adjust manually if needed).
INSERT INTO users (firstname, name, email)
SELECT DISTINCT
    split_part(owner, ' ', 1)                                             AS firstname,
    CASE WHEN owner LIKE '% %'
         THEN regexp_replace(owner, '^[^ ]+ ', '')
         ELSE owner
    END                                                                    AS name,
    lower(regexp_replace(owner, '[^a-zA-Z0-9]', '', 'g')) || '@example.com' AS email
FROM components
ON CONFLICT (email) DO NOTHING;

-- Step 3: Add owner_id column (nullable for now so the UPDATE below can run).
ALTER TABLE components ADD COLUMN owner_id INTEGER REFERENCES users(id);

-- Step 4: Populate owner_id by matching on the migrated user email/name.
UPDATE components c
SET owner_id = u.id
FROM users u
WHERE lower(regexp_replace(c.owner, '[^a-zA-Z0-9]', '', 'g')) || '@example.com' = u.email;

-- Step 5: Make owner_id NOT NULL now that all rows are populated.
ALTER TABLE components ALTER COLUMN owner_id SET NOT NULL;

-- Step 6: Add index on owner_id.
CREATE INDEX idx_components_owner_id ON components(owner_id);

-- Step 7: Drop the old free-text owner column.
ALTER TABLE components DROP COLUMN owner;
