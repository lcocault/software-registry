<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Cve.php';
require_once __DIR__ . '/../src/database/CveRepository.php';

/**
 * Creates an SQLite in-memory PDO instance with the CVE tables.
 */
function createCveTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE dependency_cve_fetches (
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
// findByDependency() — returns null when no fetch has been recorded
// ---------------------------------------------------------------------------

$repo = new CveRepository(createCveTestPdo());
$result = $repo->findByDependency('org.slf4j:slf4j-api', '2.0.13');
assertTestNull($result, 'findByDependency() should return null when CVEs have never been fetched.');

// ---------------------------------------------------------------------------
// store() + findByDependency() — fetch is recorded and CVEs are returned
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);

$cves = [
    new Cve('CVE-2021-44228', 'Remote code execution in Log4j', 'CRITICAL'),
    new Cve('CVE-2022-23302', 'JMSSink deserialization vulnerability', 'HIGH'),
];
$repo->store('log4j:log4j', '1.2.17', $cves);

$loaded = $repo->findByDependency('log4j:log4j', '1.2.17');
assertTestTrue($loaded !== null, 'findByDependency() should return an array after store().');
assertTestSame(2, count($loaded), 'findByDependency() should return 2 CVEs.');

// CVEs are ordered by cve_id
assertTestSame('CVE-2021-44228', $loaded[0]->id, 'First CVE id should match (ordered by id).');
assertTestSame('Remote code execution in Log4j', $loaded[0]->description, 'First CVE description should match.');
assertTestSame('CRITICAL', $loaded[0]->severity, 'First CVE severity should match.');
assertTestSame('CVE-2022-23302', $loaded[1]->id, 'Second CVE id should match.');

// Unrelated dependency is still unfetched
$notFetched = $repo->findByDependency('other:lib', '1.0.0');
assertTestNull($notFetched, 'findByDependency() should return null for a different dependency not yet fetched.');

// ---------------------------------------------------------------------------
// store() — empty CVE list is valid (no vulnerabilities found)
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);
$repo->store('safe:lib', '3.0.0', []);

$loaded = $repo->findByDependency('safe:lib', '3.0.0');
assertTestTrue($loaded !== null, 'findByDependency() should return an array (not null) when stored with empty list.');
assertTestSame(0, count($loaded), 'findByDependency() should return empty array when no CVEs were stored.');

// ---------------------------------------------------------------------------
// store() — calling store() again replaces the previous CVEs
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);

$repo->store('example:lib', '1.0.0', [
    new Cve('CVE-2020-00001', 'Old vulnerability', 'LOW'),
    new Cve('CVE-2020-00002', 'Another old one', 'MEDIUM'),
]);

// Re-store with only one (new) CVE
$repo->store('example:lib', '1.0.0', [
    new Cve('CVE-2023-99999', 'New vulnerability', 'HIGH'),
]);

$loaded = $repo->findByDependency('example:lib', '1.0.0');
assertTestTrue($loaded !== null, 'findByDependency() should return an array after second store().');
assertTestSame(1, count($loaded), 'store() should replace previous CVEs; only 1 CVE should remain.');
assertTestSame('CVE-2023-99999', $loaded[0]->id, 'Remaining CVE id should be the refreshed one.');

// ---------------------------------------------------------------------------
// findByDependency() — version specificity: same name, different version
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);

$repo->store('dep:x', '1.0.0', [new Cve('CVE-2021-11111', 'Vuln in 1.0.0', 'HIGH')]);

// Version 2.0.0 has not been fetched
assertTestNull($repo->findByDependency('dep:x', '2.0.0'), 'findByDependency() should return null for a different version not yet fetched.');

// Version 1.0.0 is loaded correctly
$loaded = $repo->findByDependency('dep:x', '1.0.0');
assertTestSame(1, count($loaded), 'findByDependency() should return 1 CVE for dep:x 1.0.0.');

// ---------------------------------------------------------------------------
// countByDependency() — returns null when no fetch has been recorded
// ---------------------------------------------------------------------------

$repo = new CveRepository(createCveTestPdo());
assertTestNull($repo->countByDependency('org.slf4j:slf4j-api', '2.0.13'), 'countByDependency() should return null when CVEs have never been fetched.');

// ---------------------------------------------------------------------------
// countByDependency() — returns 0 when fetched but no CVEs found
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);
$repo->store('safe:lib', '3.0.0', []);
assertTestSame(0, $repo->countByDependency('safe:lib', '3.0.0'), 'countByDependency() should return 0 when stored with empty list.');

// ---------------------------------------------------------------------------
// countByDependency() — returns the correct count after store()
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);
$repo->store('log4j:log4j', '1.2.17', [
    new Cve('CVE-2021-44228', 'Remote code execution in Log4j', 'CRITICAL'),
    new Cve('CVE-2022-23302', 'JMSSink deserialization vulnerability', 'HIGH'),
]);
assertTestSame(2, $repo->countByDependency('log4j:log4j', '1.2.17'), 'countByDependency() should return 2 for log4j:log4j 1.2.17.');

// Unrelated dependency should still return null
assertTestNull($repo->countByDependency('other:lib', '1.0.0'), 'countByDependency() should return null for an unfetched dependency.');

// ---------------------------------------------------------------------------
// countByDependency() — version specificity
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);
$repo->store('dep:x', '1.0.0', [new Cve('CVE-2021-11111', 'Vuln in 1.0.0', 'HIGH')]);

assertTestNull($repo->countByDependency('dep:x', '2.0.0'), 'countByDependency() should return null for a different version not yet fetched.');
assertTestSame(1, $repo->countByDependency('dep:x', '1.0.0'), 'countByDependency() should return 1 for dep:x 1.0.0.');

// ---------------------------------------------------------------------------
// getAllCounts() — returns empty array when nothing has been fetched
// ---------------------------------------------------------------------------

$repo = new CveRepository(createCveTestPdo());
assertTestSame([], $repo->getAllCounts(), 'getAllCounts() should return an empty array when no CVEs have been fetched.');

// ---------------------------------------------------------------------------
// getAllCounts() — returns counts for all fetched dependencies
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);
$repo->store('log4j:log4j', '1.2.17', [
    new Cve('CVE-2021-44228', 'Remote code execution in Log4j', 'CRITICAL'),
    new Cve('CVE-2022-23302', 'JMSSink deserialization', 'HIGH'),
]);
$repo->store('safe:lib', '3.0.0', []);
$repo->store('dep:x', '1.0.0', [new Cve('CVE-2021-11111', 'Vuln in 1.0.0', 'HIGH')]);

$counts = $repo->getAllCounts();

assertTestSame(2, $counts['log4j:log4j']['1.2.17'], 'getAllCounts() should return 2 for log4j:log4j 1.2.17.');
assertTestSame(0, $counts['safe:lib']['3.0.0'], 'getAllCounts() should return 0 for safe:lib 3.0.0 (fetched but no CVEs).');
assertTestSame(1, $counts['dep:x']['1.0.0'], 'getAllCounts() should return 1 for dep:x 1.0.0.');

// Unfetched version is absent from the result
assertTestTrue(!isset($counts['dep:x']['2.0.0']), 'getAllCounts() should not contain an entry for an unfetched version.');
// Unfetched dependency is absent from the result
assertTestTrue(!isset($counts['other:lib']), 'getAllCounts() should not contain an entry for an unfetched dependency.');

// ---------------------------------------------------------------------------
// getAllCounts() — updated after a second store() call
// ---------------------------------------------------------------------------

$pdo = createCveTestPdo();
$repo = new CveRepository($pdo);
$repo->store('example:lib', '1.0.0', [
    new Cve('CVE-2020-00001', 'Old vuln', 'LOW'),
    new Cve('CVE-2020-00002', 'Another old', 'MEDIUM'),
]);

$counts = $repo->getAllCounts();
assertTestSame(2, $counts['example:lib']['1.0.0'], 'getAllCounts() should return 2 before refresh.');

$repo->store('example:lib', '1.0.0', [new Cve('CVE-2023-99999', 'New vuln', 'HIGH')]);

$counts = $repo->getAllCounts();
assertTestSame(1, $counts['example:lib']['1.0.0'], 'getAllCounts() should return 1 after refresh replaces CVEs.');

echo "CveRepository tests passed.\n";
