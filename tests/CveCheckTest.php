<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Cve.php';
require_once __DIR__ . '/../src/models/Dependency.php';
require_once __DIR__ . '/../src/models/ComponentVersion.php';
require_once __DIR__ . '/../src/models/Component.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/database/CveRepository.php';
require_once __DIR__ . '/../src/database/ComponentRepository.php';
require_once __DIR__ . '/../src/database/UserRepository.php';

/**
 * Creates an SQLite in-memory PDO instance with both the component and CVE tables.
 */
function createCveCheckTestPdo(): PDO
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
        CREATE TABLE dependency_cve_fetches (
            dependency_name    TEXT NOT NULL,
            dependency_version TEXT NOT NULL,
            fetched_at         TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (dependency_name, dependency_version)
        );
        CREATE TABLE dependency_cves (
            id                 INTEGER PRIMARY KEY AUTOINCREMENT,
            dependency_name    TEXT NOT NULL,
            dependency_version TEXT NOT NULL,
            cve_id             TEXT NOT NULL,
            description        TEXT NOT NULL DEFAULT \'\',
            severity           TEXT NOT NULL DEFAULT \'\',
            UNIQUE (dependency_name, dependency_version, cve_id)
        );
        CREATE INDEX idx_dependency_cves_dep ON dependency_cves(dependency_name, dependency_version);'
    );

    return $pdo;
}

// ---------------------------------------------------------------------------
// CVE check for a component version with no dependencies
// ---------------------------------------------------------------------------

$pdo     = createCveCheckTestPdo();
$repo    = new ComponentRepository($pdo);
$cveRepo = new CveRepository($pdo);
$userRepo = new UserRepository($pdo);
$ownerId = $userRepo->save('Alice', 'Smith', 'alice@example.com');

$componentId = $repo->save('my-app', '1.0', $ownerId, 'proj', 'Java', []);
$comp = $repo->findByIdWithVersions($componentId);

assertTestTrue($comp !== null, 'Component should be found.');
assertTestSame(1, count($comp->versions), 'Component should have one version.');
assertTestSame([], $comp->versions[0]->dependencies, 'Version should have no dependencies.');

// When there are no dependencies, CVE data array should be empty
$versionId = $comp->versions[0]->id;
$cveData = [];
foreach ($comp->versions[0]->dependencies as $dep) {
    $cves = $cveRepo->findByDependency($dep->name, $dep->version);
    $cveData[] = ['dep' => $dep, 'cves' => $cves];
}
assertTestSame([], $cveData, 'CVE check data should be empty when version has no dependencies.');

// ---------------------------------------------------------------------------
// CVE check: unfetched dependencies return null from findByDependency()
// ---------------------------------------------------------------------------

$pdo     = createCveCheckTestPdo();
$repo    = new ComponentRepository($pdo);
$cveRepo = new CveRepository($pdo);
$userRepo = new UserRepository($pdo);
$ownerId = $userRepo->save('Bob', 'Jones', 'bob@example.com');

$componentId = $repo->save('service-a', '2.0', $ownerId, 'proj', 'Java', [
    ['name' => 'log4j:log4j', 'version' => '1.2.17'],
    ['name' => 'commons-io:commons-io', 'version' => '2.11.0'],
]);
$comp = $repo->findByIdWithVersions($componentId);

assertTestTrue($comp !== null, 'Component should be found.');
assertTestSame(2, count($comp->versions[0]->dependencies), 'Version should have two dependencies.');

// Before any CVE fetch, findByDependency returns null for both
$result1 = $cveRepo->findByDependency('log4j:log4j', '1.2.17');
$result2 = $cveRepo->findByDependency('commons-io:commons-io', '2.11.0');
assertTestNull($result1, 'findByDependency() should return null before first fetch.');
assertTestNull($result2, 'findByDependency() should return null before first fetch.');

// ---------------------------------------------------------------------------
// CVE check: stored CVEs are returned correctly per dependency
// ---------------------------------------------------------------------------

$pdo     = createCveCheckTestPdo();
$repo    = new ComponentRepository($pdo);
$cveRepo = new CveRepository($pdo);
$userRepo = new UserRepository($pdo);
$ownerId = $userRepo->save('Carol', 'Taylor', 'carol@example.com');

$componentId = $repo->save('service-b', '3.0', $ownerId, 'proj', 'Java', [
    ['name' => 'log4j:log4j', 'version' => '1.2.17'],
    ['name' => 'safe:lib', 'version' => '5.0.0'],
]);

// Pre-store CVE data for both dependencies
$cveRepo->store('log4j:log4j', '1.2.17', [
    new Cve('CVE-2021-44228', 'Remote code execution in Log4j', 'CRITICAL'),
    new Cve('CVE-2022-23302', 'JMSSink deserialization', 'HIGH'),
]);
$cveRepo->store('safe:lib', '5.0.0', []);  // no vulnerabilities

$comp = $repo->findByIdWithVersions($componentId);
assertTestTrue($comp !== null, 'Component should be found.');

// Simulate what index.php does for the CVE check page
$cveCheckData = [];
foreach ($comp->versions[0]->dependencies as $dep) {
    $cves = $cveRepo->findByDependency($dep->name, $dep->version);
    $cveCheckData[] = ['dep' => $dep, 'cves' => $cves];
}

assertTestSame(2, count($cveCheckData), 'CVE check data should have one entry per dependency.');

// Find entries by dependency name
$log4jEntry = null;
$safeEntry  = null;
foreach ($cveCheckData as $entry) {
    if ($entry['dep']->name === 'log4j:log4j') {
        $log4jEntry = $entry;
    }
    if ($entry['dep']->name === 'safe:lib') {
        $safeEntry = $entry;
    }
}

assertTestTrue($log4jEntry !== null, 'CVE check data should include log4j entry.');
assertTestTrue(is_array($log4jEntry['cves']), 'log4j CVEs should be an array (not null).');
assertTestSame(2, count($log4jEntry['cves']), 'log4j should have 2 CVEs.');
assertTestSame('CVE-2021-44228', $log4jEntry['cves'][0]->id, 'First CVE id should match.');
assertTestSame('CRITICAL', $log4jEntry['cves'][0]->severity, 'First CVE severity should match.');

assertTestTrue($safeEntry !== null, 'CVE check data should include safe:lib entry.');
assertTestTrue(is_array($safeEntry['cves']), 'safe:lib CVEs should be an array (not null).');
assertTestSame(0, count($safeEntry['cves']), 'safe:lib should have 0 CVEs.');

// Total CVE count
$totalCves = array_sum(array_map(
    static fn (array $entry): int => is_array($entry['cves']) ? count($entry['cves']) : 0,
    $cveCheckData,
));
assertTestSame(2, $totalCves, 'Total CVE count should be 2.');

// ---------------------------------------------------------------------------
// CVE check: version specificity — different versions of the same component
// ---------------------------------------------------------------------------

$pdo      = createCveCheckTestPdo();
$repo     = new ComponentRepository($pdo);
$cveRepo  = new CveRepository($pdo);
$userRepo = new UserRepository($pdo);
$ownerId  = $userRepo->save('Dave', 'Brown', 'dave@example.com');

// Version 1.0 uses dep-x:1.0 (vulnerable), version 2.0 uses dep-x:2.0 (safe)
$componentId = $repo->save('multi-version', '1.0', $ownerId, 'proj', 'Python', [
    ['name' => 'dep-x', 'version' => '1.0'],
]);
$repo->update($componentId, 'multi-version', '2.0', $ownerId, 'proj', 'Python', [
    ['name' => 'dep-x', 'version' => '2.0'],
]);

$cveRepo->store('dep-x', '1.0', [new Cve('CVE-2020-00001', 'Old vulnerability', 'HIGH')]);
$cveRepo->store('dep-x', '2.0', []);

$comp = $repo->findByIdWithVersions($componentId);
assertTestSame(2, count($comp->versions), 'Component should have two versions.');

// Find the two versions
$ver10 = null;
$ver20 = null;
foreach ($comp->versions as $ver) {
    if ($ver->label === '1.0') {
        $ver10 = $ver;
    }
    if ($ver->label === '2.0') {
        $ver20 = $ver;
    }
}

assertTestTrue($ver10 !== null, 'Version 1.0 should exist.');
assertTestTrue($ver20 !== null, 'Version 2.0 should exist.');

// Check CVEs for version 1.0 (should find one vulnerability)
$data10 = [];
foreach ($ver10->dependencies as $dep) {
    $data10[] = ['dep' => $dep, 'cves' => $cveRepo->findByDependency($dep->name, $dep->version)];
}
assertTestSame(1, count($data10), 'Version 1.0 has one dependency.');
assertTestSame(1, count($data10[0]['cves']), 'dep-x:1.0 has one CVE.');

// Check CVEs for version 2.0 (should find no vulnerabilities)
$data20 = [];
foreach ($ver20->dependencies as $dep) {
    $data20[] = ['dep' => $dep, 'cves' => $cveRepo->findByDependency($dep->name, $dep->version)];
}
assertTestSame(1, count($data20), 'Version 2.0 has one dependency.');
assertTestSame(0, count($data20[0]['cves']), 'dep-x:2.0 has no CVEs.');

echo "CveCheck tests passed.\n";
