<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/database/UserRepository.php';

/**
 * Creates an SQLite in-memory PDO instance with the users table.
 */
function createUserTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE users (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            firstname TEXT NOT NULL,
            name      TEXT NOT NULL,
            email     TEXT UNIQUE NOT NULL
        );'
    );

    return $pdo;
}

// ---------------------------------------------------------------------------
// save() — basic
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$id = $repo->save('Alice', 'Smith', 'alice@example.com');
assertTestTrue(is_int($id) && $id > 0, 'save() should return a positive integer ID.');

// ---------------------------------------------------------------------------
// findById() — found
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$id = $repo->save('Bob', 'Jones', 'bob@example.com');
$user = $repo->findById($id);
assertTestTrue($user instanceof User, 'findById() should return a User instance.');
assertTestSame($id, $user->id, 'findById() user id should match saved id.');
assertTestSame('Bob', $user->firstname, 'findById() firstname should match.');
assertTestSame('Jones', $user->name, 'findById() name should match.');
assertTestSame('bob@example.com', $user->email, 'findById() email should match.');

// ---------------------------------------------------------------------------
// findById() — not found
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$notFound = $repo->findById(999);
assertTestNull($notFound, 'findById() should return null for a non-existent ID.');

// ---------------------------------------------------------------------------
// fullName() helper
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$id = $repo->save('Carol', 'Taylor', 'carol@example.com');
$user = $repo->findById($id);
assertTestSame('Carol Taylor', $user->fullName(), 'fullName() should return "Firstname Name".');

// ---------------------------------------------------------------------------
// update() — success
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$id = $repo->save('Dave', 'Lee', 'dave@example.com');
$updated = $repo->update($id, 'David', 'Lee', 'david@example.com');
assertTestTrue($updated, 'update() should return true for an existing user.');
$user = $repo->findById($id);
assertTestSame('David', $user->firstname, 'update() should change firstname.');
assertTestSame('Lee', $user->name, 'update() name should remain unchanged.');
assertTestSame('david@example.com', $user->email, 'update() should change email.');

// ---------------------------------------------------------------------------
// update() — not found
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$notUpdated = $repo->update(999, 'X', 'Y', 'xy@example.com');
assertTestTrue(!$notUpdated, 'update() should return false for a non-existent ID.');

// ---------------------------------------------------------------------------
// delete() — found
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$id = $repo->save('Eve', 'Martin', 'eve@example.com');
assertTestTrue($repo->delete($id), 'delete() should return true when user exists.');
assertTestNull($repo->findById($id), 'findById() should return null after deletion.');

// ---------------------------------------------------------------------------
// delete() — not found
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
assertTestTrue(!$repo->delete(999), 'delete() should return false for a non-existent ID.');

// ---------------------------------------------------------------------------
// listAll() — ordered by name, firstname
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$repo->save('Frank', 'White', 'frank@example.com');
$repo->save('Alice', 'Smith', 'alice@example.com');
$repo->save('Grace', 'Brown', 'grace@example.com');
$all = $repo->listAll();
assertTestSame(3, count($all), 'listAll() should return all 3 users.');
// Ordered by name (Brown, Smith, White)
assertTestSame('Grace', $all[0]->firstname, 'listAll() first user should be Brown (Grace).');
assertTestSame('Alice', $all[1]->firstname, 'listAll() second user should be Smith (Alice).');
assertTestSame('Frank', $all[2]->firstname, 'listAll() third user should be White (Frank).');

// ---------------------------------------------------------------------------
// listAll() — empty
// ---------------------------------------------------------------------------

$repo = new UserRepository(createUserTestPdo());
$all = $repo->listAll();
assertTestSame([], $all, 'listAll() should return an empty array when no users exist.');

echo "UserRepository tests passed.\n";
