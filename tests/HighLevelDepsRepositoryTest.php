<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Dependency.php';
require_once __DIR__ . '/../src/models/HighLevelDependency.php';
require_once __DIR__ . '/../src/models/ComponentVersion.php';
require_once __DIR__ . '/../src/models/Component.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/database/ComponentRepository.php';
require_once __DIR__ . '/../src/database/UserRepository.php';

/**
 * Creates an SQLite in-memory PDO instance with the application schema,
 * including the high-level dependency tables.
 */
function createHldTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
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
        CREATE TABLE component_high_level_deps (
            id                   INTEGER PRIMARY KEY AUTOINCREMENT,
            component_id         INTEGER NOT NULL REFERENCES components(id) ON DELETE CASCADE,
            name                 TEXT NOT NULL,
            reuse_justification  TEXT NOT NULL DEFAULT \'\',
            integration_strategy TEXT NOT NULL DEFAULT \'\',
            validation_strategy  TEXT NOT NULL DEFAULT \'\',
            UNIQUE (component_id, name)
        );
        CREATE INDEX idx_component_hld_component_id ON component_high_level_deps(component_id);
        CREATE TABLE high_level_dep_third_party (
            high_level_dep_id INTEGER NOT NULL REFERENCES component_high_level_deps(id) ON DELETE CASCADE,
            dependency_name   TEXT NOT NULL,
            PRIMARY KEY (high_level_dep_id, dependency_name)
        );'
    );

    return $pdo;
}

// ---------------------------------------------------------------------------
// findByIdWithHighLevelDeps() — no high-level deps initially
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Alice', 'Smith', 'alice@example.com');
$id      = $repo->save('my-lib', '1.0.0', $ownerId, 'my-project', 'Java', []);
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestTrue($component instanceof Component, 'findByIdWithHighLevelDeps() should return a Component instance.');
assertTestSame($id, $component->id, 'findByIdWithHighLevelDeps() component id should match.');
assertTestSame([], $component->highLevelDependencies, 'New component should have no high-level dependencies.');

// ---------------------------------------------------------------------------
// findByIdWithHighLevelDeps() — not found
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createHldTestPdo());
$notFound = $repo->findByIdWithHighLevelDeps(999);
assertTestNull($notFound, 'findByIdWithHighLevelDeps() should return null for a non-existent component.');

// ---------------------------------------------------------------------------
// addHighLevelDependency() — basic
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Bob', 'Jones', 'bob@example.com');
$id      = $repo->save('my-service', '2.0.0', $ownerId, 'project-b', 'Python', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', 'We need structured logging.', 'Use SLF4J facade.', 'Unit tests cover all log calls.');
assertTestTrue(is_int($hldId) && $hldId > 0, 'addHighLevelDependency() should return a positive integer ID.');
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestSame(1, count($component->highLevelDependencies), 'Component should have 1 high-level dependency after adding.');
$hld = $component->highLevelDependencies[0];
assertTestSame('Logging', $hld->name, 'High-level dep name should match.');
assertTestSame('We need structured logging.', $hld->reuseJustification, 'Reuse justification should match.');
assertTestSame('Use SLF4J facade.', $hld->integrationStrategy, 'Integration strategy should match.');
assertTestSame('Unit tests cover all log calls.', $hld->validationStrategy, 'Validation strategy should match.');
assertTestSame([], $hld->thirdPartyDependencies, 'New high-level dep should have no 3rd party dependencies.');

// ---------------------------------------------------------------------------
// addHighLevelDependency() — component not found
// ---------------------------------------------------------------------------

$repo   = new ComponentRepository(createHldTestPdo());
$result = $repo->addHighLevelDependency(999, 'Logging', '', '', '');
assertTestTrue($result === false, 'addHighLevelDependency() should return false for a non-existent component.');

// ---------------------------------------------------------------------------
// addHighLevelDependency() — multiple
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Carol', 'Taylor', 'carol@example.com');
$id      = $repo->save('multi-dep-lib', '1.0', $ownerId, 'proj', 'Java', []);
$repo->addHighLevelDependency($id, 'Logging', 'Need logs.', 'SLF4J.', 'Tests.');
$repo->addHighLevelDependency($id, 'HTTP Client', 'Need HTTP.', 'Apache HC.', 'Integration tests.');
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestSame(2, count($component->highLevelDependencies), 'Component should have 2 high-level dependencies.');
$names = array_map(static fn (HighLevelDependency $h): string => $h->name, $component->highLevelDependencies);
sort($names);
assertTestSame(['HTTP Client', 'Logging'], $names, 'High-level dep names should match (sorted alphabetically).');

// ---------------------------------------------------------------------------
// deleteHighLevelDependency() — basic
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Dave', 'Lee', 'dave@example.com');
$id      = $repo->save('del-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
assertTestTrue(is_int($hldId), 'addHighLevelDependency() should return an integer.');
$deleted = $repo->deleteHighLevelDependency($id, $hldId);
assertTestTrue($deleted, 'deleteHighLevelDependency() should return true when dep exists.');
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestSame([], $component->highLevelDependencies, 'Component should have no high-level deps after deletion.');

// ---------------------------------------------------------------------------
// deleteHighLevelDependency() — not found
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Eve', 'Martin', 'eve@example.com');
$id      = $repo->save('my-lib', '1.0', $ownerId, 'proj', 'Java', []);
$deleted = $repo->deleteHighLevelDependency($id, 999);
assertTestTrue(!$deleted, 'deleteHighLevelDependency() should return false for a non-existent high-level dep.');

// ---------------------------------------------------------------------------
// deleteHighLevelDependency() — wrong component
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Frank', 'White', 'frank@example.com');
$id1     = $repo->save('lib-a', '1.0', $ownerId, 'proj', 'Java', []);
$id2     = $repo->save('lib-b', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id1, 'Logging', '', '', '');
$deleted = $repo->deleteHighLevelDependency($id2, $hldId);
assertTestTrue(!$deleted, 'deleteHighLevelDependency() should return false when component ID does not match.');

// ---------------------------------------------------------------------------
// addHighLevelDepThirdParty() — basic
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Grace', 'Brown', 'grace@example.com');
$id      = $repo->save('tp-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
$result  = $repo->addHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
assertTestTrue($result, 'addHighLevelDepThirdParty() should return true.');
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestSame(1, count($component->highLevelDependencies[0]->thirdPartyDependencies), 'Should have 1 third-party dep.');
assertTestSame('org.slf4j:slf4j-api', $component->highLevelDependencies[0]->thirdPartyDependencies[0], 'Third-party dep name should match.');

// ---------------------------------------------------------------------------
// addHighLevelDepThirdParty() — idempotent (ON CONFLICT DO NOTHING)
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Hannah', 'Green', 'hannah@example.com');
$id      = $repo->save('idem-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
$repo->addHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
$repo->addHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestSame(1, count($component->highLevelDependencies[0]->thirdPartyDependencies), 'Duplicate third-party dep should not be added twice.');

// ---------------------------------------------------------------------------
// addHighLevelDepThirdParty() — multiple 3rd party deps sorted alphabetically
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Ian', 'Black', 'ian@example.com');
$id      = $repo->save('multi-tp-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
$repo->addHighLevelDepThirdParty($id, $hldId, 'ch.qos.logback:logback-classic');
$repo->addHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
$component = $repo->findByIdWithHighLevelDeps($id);
$deps = $component->highLevelDependencies[0]->thirdPartyDependencies;
assertTestSame(2, count($deps), 'Should have 2 third-party deps.');
assertTestSame('ch.qos.logback:logback-classic', $deps[0], 'Third-party deps should be sorted alphabetically (first).');
assertTestSame('org.slf4j:slf4j-api', $deps[1], 'Third-party deps should be sorted alphabetically (second).');

// ---------------------------------------------------------------------------
// addHighLevelDepThirdParty() — high-level dep not found
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Jane', 'Doe', 'jane@example.com');
$id      = $repo->save('my-lib', '1.0', $ownerId, 'proj', 'Java', []);
$result  = $repo->addHighLevelDepThirdParty($id, 999, 'some-dep');
assertTestTrue(!$result, 'addHighLevelDepThirdParty() should return false for a non-existent high-level dep.');

// ---------------------------------------------------------------------------
// addHighLevelDepThirdParty() — wrong component
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Karl', 'White', 'karl@example.com');
$id1     = $repo->save('lib-a', '1.0', $ownerId, 'proj', 'Java', []);
$id2     = $repo->save('lib-b', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id1, 'Logging', '', '', '');
$result  = $repo->addHighLevelDepThirdParty($id2, $hldId, 'some-dep');
assertTestTrue(!$result, 'addHighLevelDepThirdParty() should return false when component ID does not match.');

// ---------------------------------------------------------------------------
// deleteHighLevelDepThirdParty() — basic
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Laura', 'Gray', 'laura@example.com');
$id      = $repo->save('del-tp-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
$repo->addHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
$deleted = $repo->deleteHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
assertTestTrue($deleted, 'deleteHighLevelDepThirdParty() should return true when link exists.');
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestSame([], $component->highLevelDependencies[0]->thirdPartyDependencies, 'Third-party dep should be removed.');

// ---------------------------------------------------------------------------
// deleteHighLevelDepThirdParty() — not found
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Mike', 'Brown', 'mike@example.com');
$id      = $repo->save('my-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
$deleted = $repo->deleteHighLevelDepThirdParty($id, $hldId, 'non-existent-dep');
assertTestTrue(!$deleted, 'deleteHighLevelDepThirdParty() should return false for a non-existent link.');

// ---------------------------------------------------------------------------
// deleteHighLevelDepThirdParty() — wrong component
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Nina', 'Clark', 'nina@example.com');
$id1     = $repo->save('lib-a', '1.0', $ownerId, 'proj', 'Java', []);
$id2     = $repo->save('lib-b', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id1, 'Logging', '', '', '');
$repo->addHighLevelDepThirdParty($id1, $hldId, 'org.slf4j:slf4j-api');
$deleted = $repo->deleteHighLevelDepThirdParty($id2, $hldId, 'org.slf4j:slf4j-api');
assertTestTrue(!$deleted, 'deleteHighLevelDepThirdParty() should return false when component ID does not match.');

// ---------------------------------------------------------------------------
// Cascade delete: deleting component removes high-level deps and 3rd party links
// ---------------------------------------------------------------------------

$pdo     = createHldTestPdo();
$userRepo = new UserRepository($pdo);
$repo    = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Oscar', 'Davis', 'oscar@example.com');
$id      = $repo->save('cascade-lib', '1.0', $ownerId, 'proj', 'Java', []);
$hldId   = $repo->addHighLevelDependency($id, 'Logging', '', '', '');
$repo->addHighLevelDepThirdParty($id, $hldId, 'org.slf4j:slf4j-api');
$repo->delete($id);
$component = $repo->findByIdWithHighLevelDeps($id);
assertTestNull($component, 'findByIdWithHighLevelDeps() should return null after component deletion.');

echo "HighLevelDeps tests passed.\n";
