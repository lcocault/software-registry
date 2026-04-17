<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Dependency.php';
require_once __DIR__ . '/../src/models/Component.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/database/ComponentRepository.php';
require_once __DIR__ . '/../src/database/UserRepository.php';
require_once __DIR__ . '/../src/DependencyParser.php';

/**
 * Creates an SQLite in-memory PDO instance with the application schema.
 */
function createTestPdo(): PDO
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
// save() — basic
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Alice', 'Smith', 'alice@example.com');
$id = $repo->save('my-lib', '1.0.0', $ownerId, 'my-project', 'Java', []);
assertTestTrue(is_int($id) && $id > 0, 'save() should return a positive integer ID.');

// ---------------------------------------------------------------------------
// findById() — found
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Bob', 'Jones', 'bob@example.com');
$id = $repo->save('demo-service', '3.1.0', $ownerId, 'backend', 'Python', []);
$component = $repo->findById($id);
assertTestTrue($component instanceof Component, 'findById() should return a Component instance.');
assertTestSame($id, $component->id, 'findById() component id should match saved id.');
assertTestSame('demo-service', $component->name, 'findById() name should match.');
assertTestSame('3.1.0', $component->version, 'findById() version should match.');
assertTestSame($ownerId, $component->ownerId, 'findById() ownerId should match.');
assertTestSame('Bob Jones', $component->owner, 'findById() owner display name should match.');
assertTestSame('Python', $component->language, 'findById() language should match.');
assertTestSame('backend', $component->projectName, 'findById() projectName should match.');

// ---------------------------------------------------------------------------
// findById() — not found
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createTestPdo());
$notFound = $repo->findById(999);
assertTestNull($notFound, 'findById() should return null for a non-existent ID.');

// ---------------------------------------------------------------------------
// findByIdWithDependencies() — found with dependencies
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Carol', 'Taylor', 'carol@example.com');
$deps = [
    ['name' => 'org.slf4j:slf4j-api', 'version' => '2.0.13'],
    ['name' => 'com.google.guava:guava', 'version' => '31.1-jre'],
];
$id = $repo->save('svc-with-deps', '4.0.0', $ownerId, 'platform', 'Java', $deps);
$component = $repo->findByIdWithDependencies($id);
assertTestTrue($component instanceof Component, 'findByIdWithDependencies() should return a Component instance.');
assertTestSame($id, $component->id, 'findByIdWithDependencies() component id should match saved id.');
assertTestSame('svc-with-deps', $component->name, 'findByIdWithDependencies() name should match.');
assertTestSame(2, count($component->dependencies), 'findByIdWithDependencies() should include 2 dependencies.');
$depNames = array_map(static fn (Dependency $d): string => $d->name, $component->dependencies);
sort($depNames);
assertTestSame(
    ['com.google.guava:guava', 'org.slf4j:slf4j-api'],
    $depNames,
    'findByIdWithDependencies() dependency names should match (sorted alphabetically).'
);

// ---------------------------------------------------------------------------
// findByIdWithDependencies() — found with no dependencies
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Dave', 'Lee', 'dave@example.com');
$id = $repo->save('svc-no-deps', '1.0.0', $ownerId, 'project', 'Python', []);
$component = $repo->findByIdWithDependencies($id);
assertTestTrue($component instanceof Component, 'findByIdWithDependencies() with no deps should return a Component.');
assertTestSame([], $component->dependencies, 'findByIdWithDependencies() should return empty dependencies array.');

// ---------------------------------------------------------------------------
// findByIdWithDependencies() — not found
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createTestPdo());
$notFound = $repo->findByIdWithDependencies(999);
assertTestNull($notFound, 'findByIdWithDependencies() should return null for a non-existent ID.');

// ---------------------------------------------------------------------------
// delete() — found
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Carol', 'Taylor', 'carol@example.com');
$id = $repo->save('to-delete', '0.1', $ownerId, 'proj', 'JavaScript', []);
assertTestTrue($repo->delete($id), 'delete() should return true when component exists.');
assertTestNull($repo->findById($id), 'findById() should return null after deletion.');

// ---------------------------------------------------------------------------
// delete() — not found
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createTestPdo());
assertTestTrue(!$repo->delete(999), 'delete() should return false for a non-existent ID.');

// ---------------------------------------------------------------------------
// update() — basic fields
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Dave', 'Lee', 'dave@example.com');
$id = $repo->save('old-name', '1.0', $ownerId, 'proj-a', 'Java', []);
$updated = $repo->update($id, 'new-name', '2.0', $ownerId, 'proj-b', 'Python', null);
assertTestTrue($updated, 'update() should return true for an existing component.');
$component = $repo->findById($id);
assertTestSame('new-name', $component->name, 'update() should change name.');
assertTestSame('2.0', $component->version, 'update() should change version.');
assertTestSame('proj-b', $component->projectName, 'update() should change project name.');
assertTestSame('Python', $component->language, 'update() should change language.');

// ---------------------------------------------------------------------------
// update() — not found
// ---------------------------------------------------------------------------

$repo = new ComponentRepository(createTestPdo());
$notUpdated = $repo->update(999, 'x', '1', 1, 'z', 'Java', null);
assertTestTrue(!$notUpdated, 'update() should return false for a non-existent ID.');

// ---------------------------------------------------------------------------
// listAll() — returns components with dependencies
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Eve', 'Martin', 'eve@example.com');
$deps = [
    ['name' => 'org.slf4j:slf4j-api', 'version' => '2.0.13'],
    ['name' => 'org.junit.jupiter:junit-jupiter', 'version' => '5.10.2'],
];
$id = $repo->save('my-app', '1.0.0', $ownerId, 'platform', 'Java', $deps);
$all = $repo->listAll();
assertTestTrue(count($all) === 1, 'listAll() should return one component.');
$saved = $all[0];
assertTestSame($id, $saved->id, 'listAll() component id should match saved id.');
assertTestSame('my-app', $saved->name, 'listAll() name should match.');
assertTestSame(2, count($saved->dependencies), 'listAll() should include 2 dependencies.');
$depNames = array_map(static fn (Dependency $d): string => $d->name, $saved->dependencies);
sort($depNames);
assertTestSame(
    ['org.junit.jupiter:junit-jupiter', 'org.slf4j:slf4j-api'],
    $depNames,
    'listAll() dependency names should match (sorted alphabetically).'
);

// ---------------------------------------------------------------------------
// update() — replace dependencies
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Frank', 'White', 'frank@example.com');
$id = $repo->save('lib', '1.0', $ownerId, 'proj', 'Java', [
    ['name' => 'old-dep', 'version' => '1.0'],
]);
$repo->update($id, 'lib', '2.0', $ownerId, 'proj', 'Java', [
    ['name' => 'new-dep-a', 'version' => '2.1'],
    ['name' => 'new-dep-b', 'version' => '3.0'],
]);
$all = $repo->listAll();
assertTestTrue(count($all) === 1, 'listAll() should return one component after update.');
$depNames = array_map(static fn (Dependency $d): string => $d->name, $all[0]->dependencies);
sort($depNames);
assertTestSame(
    ['new-dep-a', 'new-dep-b'],
    $depNames,
    'update() should replace the old dependency list with the new one.'
);

// ---------------------------------------------------------------------------
// upsertProject — same project name yields same project for multiple components
// ---------------------------------------------------------------------------

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Grace', 'Brown', 'grace@example.com');
$repo->save('comp-a', '1.0', $ownerId, 'shared-project', 'Java', []);
$repo->save('comp-b', '2.0', $ownerId, 'shared-project', 'Java', []);
$all = $repo->listAll();
assertTestTrue(count($all) === 2, 'listAll() should return two components under the same project.');
assertTestSame('shared-project', $all[0]->projectName, 'Both components should belong to shared-project.');
assertTestSame('shared-project', $all[1]->projectName, 'Both components should belong to shared-project.');

// ---------------------------------------------------------------------------
// Full Java import flow: parse Maven dependency tree, save, read back
// ---------------------------------------------------------------------------

$javaInput = <<<TXT
[INFO] com.example:demo:jar:1.0-SNAPSHOT
[INFO] +- org.slf4j:slf4j-api:jar:2.0.13:compile
[INFO] \- org.junit.jupiter:junit-jupiter:jar:5.10.2:test
TXT;

$parsed = DependencyParser::parse('Java', $javaInput);
$dependencies = array_values(array_filter(
    $parsed,
    static fn (array $d): bool => strlen($d['name']) <= 255 && strlen($d['version']) <= 100,
));

assertTestSame(2, count($dependencies), 'Java import: parsed dependency count should be 2.');

$pdo = createTestPdo();
$userRepo = new UserRepository($pdo);
$repo = new ComponentRepository($pdo);
$ownerId = $userRepo->save('Team', 'Dev', 'team@example.com');
$savedId = $repo->save('com.example:demo', '1.0-SNAPSHOT', $ownerId, 'demo-project', 'Java', $dependencies);
assertTestTrue($savedId > 0, 'Java import: save() should return a positive ID.');

$all = $repo->listAll();
assertTestTrue(count($all) === 1, 'Java import: listAll() should return one component.');
assertTestSame('com.example:demo', $all[0]->name, 'Java import: component name should match.');
assertTestSame('1.0-SNAPSHOT', $all[0]->version, 'Java import: component version should match.');
assertTestSame(2, count($all[0]->dependencies), 'Java import: component should have 2 dependencies.');

$depNames = array_map(static fn (Dependency $d): string => $d->name, $all[0]->dependencies);
sort($depNames);
assertTestSame(
    ['org.junit.jupiter:junit-jupiter', 'org.slf4j:slf4j-api'],
    $depNames,
    'Java import: stored dependency names should match parsed output.'
);

// Also test with the larger Maven dependency tree (without [INFO] prefix)
$javaInputLarge = <<<TXT
fr.codemap:cli:jar:0.0.1-SNAPSHOT
+- fr.codemap:engine:jar:0.0.1-SNAPSHOT:compile
|  \- com.github.javaparser:javaparser-symbol-solver-core:jar:3.25.3:compile
|     +- com.github.javaparser:javaparser-core:jar:3.25.3:compile
|     +- org.javassist:javassist:jar:3.29.2-GA:compile
|     \- com.google.guava:guava:jar:31.1-jre:compile
|        +- com.google.guava:failureaccess:jar:1.0.1:compile
|        +- com.google.guava:listenablefuture:jar:9999.0-empty-to-avoid-conflict-with-guava:compile
|        +- com.google.code.findbugs:jsr305:jar:3.0.2:compile
|        +- org.checkerframework:checker-qual:jar:3.12.0:compile
|        +- com.google.errorprone:error_prone_annotations:jar:2.11.0:compile
|        \- com.google.j2objc:j2objc-annotations:jar:1.3:compile
+- commons-cli:commons-cli:jar:1.5.0:compile
\- org.junit.jupiter:junit-jupiter-engine:jar:5.4.2:test
   +- org.apiguardian:apiguardian-api:jar:1.0.0:test
   +- org.junit.platform:junit-platform-engine:jar:1.4.2:test
   |  +- org.opentest4j:opentest4j:jar:1.1.1:test
   |  \- org.junit.platform:junit-platform-commons:jar:1.4.2:test
   \- org.junit.jupiter:junit-jupiter-api:jar:5.4.2:test
TXT;

$parsedLarge = DependencyParser::parse('Java', $javaInputLarge);
$dependenciesLarge = array_values(array_filter(
    $parsedLarge,
    static fn (array $d): bool => strlen($d['name']) <= 255 && strlen($d['version']) <= 100,
));

assertTestTrue(count($dependenciesLarge) > 0, 'Java large import: parser should produce dependencies.');

$pdo2 = createTestPdo();
$userRepo2 = new UserRepository($pdo2);
$repo2 = new ComponentRepository($pdo2);
$ownerId2 = $userRepo2->save('Codemap', 'Team', 'codemap@example.com');
$savedId2 = $repo2->save('fr.codemap:cli', '0.0.1-SNAPSHOT', $ownerId2, 'codemap', 'Java', $dependenciesLarge);
assertTestTrue($savedId2 > 0, 'Java large import: save() should return a positive ID.');

$all2 = $repo2->listAll();
assertTestTrue(count($all2) === 1, 'Java large import: listAll() should return one component.');
assertTestSame(
    count($parsedLarge),
    count($all2[0]->dependencies),
    'Java large import: all parsed dependencies should be stored.'
);

echo "ComponentRepository tests passed.\n";
