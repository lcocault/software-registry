<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Cve.php';
require_once __DIR__ . '/../src/OsvClient.php';

// --- Cve model ---

$cve = new Cve('CVE-2021-44228', 'Remote code execution in Log4j', 'CRITICAL');
assertTestSame('CVE-2021-44228', $cve->id, 'Cve id should match constructor argument.');
assertTestSame('Remote code execution in Log4j', $cve->description, 'Cve description should match constructor argument.');
assertTestSame('CRITICAL', $cve->severity, 'Cve severity should match constructor argument.');

// Readonly — assigning to a readonly property must throw an error
$readonlyBlocked = false;
try {
    // @phpstan-ignore-next-line
    $cve->id = 'changed';
} catch (Error $e) {
    $readonlyBlocked = true;
}
assertTestTrue($readonlyBlocked, 'Cve::id must be readonly.');

// Empty severity is allowed
$cveNoSeverity = new Cve('GHSA-xxxx-xxxx-xxxx', 'Some vulnerability', '');
assertTestSame('', $cveNoSeverity->severity, 'Cve severity may be an empty string.');

// --- OsvClient::ecosystemForLanguage ---

assertTestSame('Maven', OsvClient::ecosystemForLanguage('Java'), 'Java should map to Maven ecosystem.');
assertTestSame('PyPI', OsvClient::ecosystemForLanguage('Python'), 'Python should map to PyPI ecosystem.');
assertTestSame('npm', OsvClient::ecosystemForLanguage('JavaScript'), 'JavaScript should map to npm ecosystem.');
assertTestNull(OsvClient::ecosystemForLanguage('Unknown'), 'Unknown language should return null.');
assertTestNull(OsvClient::ecosystemForLanguage(''), 'Empty language should return null.');

echo "Cve model and OsvClient tests passed.\n";
