<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Dependency.php';
require_once __DIR__ . '/../src/models/ComponentVersion.php';
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
            owner_id   INTEGER NOT NULL REFERENCES users(id),
            language   TEXT NOT NULL,
            project_id INTEGER NOT NULL REFERENCES projects(id),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX idx_components_project_id ON components(project_id);
        CREATE INDEX idx_components_owner_id   ON components(owner_id);
        CREATE TABLE component_versions (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            component_id INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
            label        TEXT NOT NULL,
            UNIQUE (component_id, label)
        );
        CREATE INDEX idx_component_versions_component_id ON component_versions(component_id);
        CREATE TABLE dependencies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        );
        CREATE TABLE versioned_dependencies (
            component_version_id INTEGER NOT NULL REFERENCES component_versions(id) ON DELETE CASCADE,
            dependency_id INTEGER NOT NULL REFERENCES dependencies(id),
            version TEXT NOT NULL,
            PRIMARY KEY (component_version_id, dependency_id)
        );
        CREATE INDEX idx_versioned_dependencies_dependency_id ON versioned_dependencies(dependency_id);
        CREATE TABLE catalog_entries (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            name    TEXT NOT NULL,
            version TEXT NOT NULL,
            UNIQUE (name, version)
        );
        CREATE INDEX idx_catalog_entries_name ON catalog_entries(name);'
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

// dep-x:1.0 returned components should have all Component fields populated,
// with exactly the matching version in their versions array
$componentA = null;
foreach ($using as $c) {
    if ($c->name === 'comp-a') {
        $componentA = $c;
    }
}
assertTestTrue($componentA instanceof Component, 'listComponentsUsingDependency() should return Component instances.');
assertTestSame(1, count($componentA->versions), 'listComponentsUsingDependency() component should have exactly one version.');
assertTestSame('1.0', $componentA->versions[0]->label, 'Component version label should be set correctly.');
assertTestSame('Java', $componentA->language, 'Component language should be set correctly.');
assertTestSame('proj', $componentA->projectName, 'Component projectName should be set correctly.');

// ---------------------------------------------------------------------------
// addCatalogEntry() — standalone entry appears in listDependencyNames()
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createCatalogTestPdo());
$repo->addCatalogEntry('standalone-lib', '1.0.0');

$names = $repo->listDependencyNames();
assertTestSame(1, count($names), 'listDependencyNames() should return the manually-added entry.');
assertTestSame('standalone-lib', $names[0]['name'], 'listDependencyNames() should return the correct name.');
assertTestSame(0, $names[0]['usage_count'], 'Manually-added entry should have usage_count of 0.');

// ---------------------------------------------------------------------------
// addCatalogEntry() — duplicate is silently ignored (idempotent)
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createCatalogTestPdo());
$repo->addCatalogEntry('my-lib', '2.0.0');
$repo->addCatalogEntry('my-lib', '2.0.0'); // duplicate

$names = $repo->listDependencyNames();
assertTestSame(1, count($names), 'addCatalogEntry() duplicate should be ignored; still only 1 entry.');

// ---------------------------------------------------------------------------
// addCatalogEntry() — appears in listDependencyVersions()
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createCatalogTestPdo());
$repo->addCatalogEntry('dep-q', '1.0');
$repo->addCatalogEntry('dep-q', '2.0');

$versions = $repo->listDependencyVersions('dep-q');
assertTestSame(2, count($versions), 'listDependencyVersions() should return both manually-added versions.');
$versionLabels = array_column($versions, 'version');
sort($versionLabels);
assertTestSame(['1.0', '2.0'], $versionLabels, 'listDependencyVersions() should return both versions.');
foreach ($versions as $ver) {
    assertTestSame(0, $ver['usage_count'], 'Manually-added version should have usage_count of 0.');
}

// ---------------------------------------------------------------------------
// addCatalogEntry() — existing versioned dep takes precedence for usage_count
// ---------------------------------------------------------------------------

$pdo = createCatalogTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Dave', 'Brown', 'dave@example.com');

// comp-a uses dep-z:3.0 via a component version
$repo->save('comp-a', '1.0', $ownerId, 'proj', 'Java', [['name' => 'dep-z', 'version' => '3.0']]);
// Also add the same entry manually
$repo->addCatalogEntry('dep-z', '3.0');

$names = $repo->listDependencyNames();
$depZ = null;
foreach ($names as $entry) {
    if ($entry['name'] === 'dep-z') {
        $depZ = $entry;
    }
}
assertTestTrue($depZ !== null, 'dep-z should appear in listDependencyNames().');
assertTestSame(1, $depZ['usage_count'], 'dep-z usage_count should reflect actual component usage, not 0.');

$versions = $repo->listDependencyVersions('dep-z');
$v30 = null;
foreach ($versions as $ver) {
    if ($ver['version'] === '3.0') {
        $v30 = $ver;
    }
}
assertTestTrue($v30 !== null, 'dep-z:3.0 should appear in listDependencyVersions().');
assertTestSame(1, $v30['usage_count'], 'dep-z:3.0 usage_count should reflect actual component usage.');

echo "Catalog repository tests passed.\n";
