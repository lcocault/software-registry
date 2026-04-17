<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Dependency.php';
require_once __DIR__ . '/../src/models/Component.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/database/ComponentRepository.php';
require_once __DIR__ . '/../src/database/UserRepository.php';

/**
 * Creates an SQLite in-memory PDO instance with the application schema.
 */
function createCatalogTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        );
        CREATE TABLE users (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            firstname TEXT NOT NULL,
            name      TEXT NOT NULL,
            email     TEXT UNIQUE NOT NULL
        );
        CREATE TABLE components (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            version    TEXT NOT NULL,
            owner_id   INTEGER NOT NULL REFERENCES users(id),
            language   TEXT NOT NULL,
            project_id INTEGER NOT NULL REFERENCES projects(id),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX idx_components_project_id ON components(project_id);
        CREATE INDEX idx_components_owner_id   ON components(owner_id);
        CREATE TABLE dependencies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        );
        CREATE TABLE versioned_dependencies (
            component_id INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
            dependency_id INTEGER NOT NULL REFERENCES dependencies(id),
            version TEXT NOT NULL,
            PRIMARY KEY (component_id, dependency_id)
        );
        CREATE INDEX idx_versioned_dependencies_dependency_id ON versioned_dependencies(dependency_id);'
    );

    return $pdo;
}

// ---------------------------------------------------------------------------
// listDependencyNames() — empty database
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createCatalogTestPdo());
$names = $repo->listDependencyNames();
assertTestSame([], $names, 'listDependencyNames() should return empty array when no dependencies exist.');

// ---------------------------------------------------------------------------
// listDependencyNames() — returns names with usage counts
// ---------------------------------------------------------------------------

$pdo = createCatalogTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Alice', 'Smith', 'alice@example.com');

// comp-a uses dep-x:1.0 and dep-y:2.0
$repo->save('comp-a', '1.0', $ownerId, 'proj', 'Java', [
    ['name' => 'dep-x', 'version' => '1.0'],
    ['name' => 'dep-y', 'version' => '2.0'],
]);
// comp-b also uses dep-x:1.0
$repo->save('comp-b', '1.0', $ownerId, 'proj', 'Java', [
    ['name' => 'dep-x', 'version' => '1.0'],
]);

$names = $repo->listDependencyNames();
assertTestSame(2, count($names), 'listDependencyNames() should return 2 distinct dependency names.');

$depXEntry = null;
$depYEntry = null;
foreach ($names as $entry) {
    if ($entry['name'] === 'dep-x') {
        $depXEntry = $entry;
    }
    if ($entry['name'] === 'dep-y') {
        $depYEntry = $entry;
    }
}

assertTestTrue($depXEntry !== null, 'listDependencyNames() should include dep-x.');
assertTestSame(2, $depXEntry['usage_count'], 'dep-x should have usage_count of 2 (used by comp-a and comp-b).');
assertTestTrue($depYEntry !== null, 'listDependencyNames() should include dep-y.');
assertTestSame(1, $depYEntry['usage_count'], 'dep-y should have usage_count of 1 (used by comp-a only).');

// Names should be sorted alphabetically
assertTestSame('dep-x', $names[0]['name'], 'listDependencyNames() should return dep-x first (alphabetical order).');
assertTestSame('dep-y', $names[1]['name'], 'listDependencyNames() should return dep-y second (alphabetical order).');

// ---------------------------------------------------------------------------
// listDependencyVersions() — unknown dependency
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createCatalogTestPdo());
$versions = $repo->listDependencyVersions('unknown-dep');
assertTestSame([], $versions, 'listDependencyVersions() should return empty array for unknown dependency.');

// ---------------------------------------------------------------------------
// listDependencyVersions() — multiple versions with counts
// ---------------------------------------------------------------------------

$pdo = createCatalogTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Bob', 'Jones', 'bob@example.com');

// Two components use dep-x:1.0, one uses dep-x:2.0
$repo->save('comp-a', '1.0', $ownerId, 'proj', 'Java', [['name' => 'dep-x', 'version' => '1.0']]);
$repo->save('comp-b', '2.0', $ownerId, 'proj', 'Java', [['name' => 'dep-x', 'version' => '1.0']]);
$repo->save('comp-c', '1.0', $ownerId, 'proj', 'Java', [['name' => 'dep-x', 'version' => '2.0']]);

$versions = $repo->listDependencyVersions('dep-x');
assertTestSame(2, count($versions), 'listDependencyVersions() should return 2 versions for dep-x.');

$v10 = null;
$v20 = null;
foreach ($versions as $entry) {
    if ($entry['version'] === '1.0') {
        $v10 = $entry;
    }
    if ($entry['version'] === '2.0') {
        $v20 = $entry;
    }
}

assertTestTrue($v10 !== null, 'listDependencyVersions() should include version 1.0.');
assertTestSame(2, $v10['usage_count'], 'dep-x:1.0 usage_count should be 2.');
assertTestTrue($v20 !== null, 'listDependencyVersions() should include version 2.0.');
assertTestSame(1, $v20['usage_count'], 'dep-x:2.0 usage_count should be 1.');

// ---------------------------------------------------------------------------
// listComponentsUsingDependency() — no components use this dep+version
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createCatalogTestPdo());
$using = $repo->listComponentsUsingDependency('dep-x', '1.0');
assertTestSame([], $using, 'listComponentsUsingDependency() should return empty array when no components use it.');

// ---------------------------------------------------------------------------
// listComponentsUsingDependency() — returns correct components
// ---------------------------------------------------------------------------

$pdo = createCatalogTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Carol', 'Taylor', 'carol@example.com');

$repo->save('comp-a', '1.0', $ownerId, 'proj', 'Java', [['name' => 'dep-x', 'version' => '1.0']]);
$repo->save('comp-b', '2.0', $ownerId, 'proj', 'Java', [['name' => 'dep-x', 'version' => '1.0']]);
$repo->save('comp-c', '1.0', $ownerId, 'proj', 'Java', [['name' => 'dep-x', 'version' => '2.0']]);

$using = $repo->listComponentsUsingDependency('dep-x', '1.0');
assertTestSame(2, count($using), 'listComponentsUsingDependency() should return 2 components using dep-x:1.0.');

$usingNames = array_map(static fn (Component $c): string => $c->name, $using);
sort($usingNames);
assertTestSame(['comp-a', 'comp-b'], $usingNames, 'listComponentsUsingDependency() should return comp-a and comp-b.');

// comp-x:2.0 is only used by comp-c
$using20 = $repo->listComponentsUsingDependency('dep-x', '2.0');
assertTestSame(1, count($using20), 'listComponentsUsingDependency() should return 1 component using dep-x:2.0.');
assertTestSame('comp-c', $using20[0]->name, 'listComponentsUsingDependency() dep-x:2.0 should return comp-c.');

// dep-x:1.0 returned components should have all Component fields populated
$componentA = null;
foreach ($using as $c) {
    if ($c->name === 'comp-a') {
        $componentA = $c;
    }
}
assertTestTrue($componentA instanceof Component, 'listComponentsUsingDependency() should return Component instances.');
assertTestSame('1.0', $componentA->version, 'Component version should be set correctly.');
assertTestSame('Java', $componentA->language, 'Component language should be set correctly.');
assertTestSame('proj', $componentA->projectName, 'Component projectName should be set correctly.');

echo "Catalog repository tests passed.\n";
