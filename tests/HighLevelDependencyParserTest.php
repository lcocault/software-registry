<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/HighLevelDependencyParser.php';

// ---------------------------------------------------------------------------
// parse() — empty dependencies array
// ---------------------------------------------------------------------------

$result = HighLevelDependencyParser::parse('{"highLevelDependencies":[]}');
assertTestSame([], $result, 'Empty highLevelDependencies array should return empty result.');

// ---------------------------------------------------------------------------
// parse() — single entry with all fields
// ---------------------------------------------------------------------------

$json = json_encode([
    'highLevelDependencies' => [
        [
            'name'                   => 'Logging',
            'license'                => 'MIT License',
            'reuseJustification'     => 'Need structured logging.',
            'integrationStrategy'    => 'Use SLF4J facade.',
            'validationStrategy'     => 'Unit tests cover all log calls.',
            'thirdPartyDependencies' => ['ch.qos.logback:logback-classic', 'org.slf4j:slf4j-api'],
        ],
    ],
]);
$result = HighLevelDependencyParser::parse($json);
assertTestSame(1, count($result), 'Should parse 1 high-level dependency.');
assertTestSame('Logging', $result[0]['name'], 'Name should match.');
assertTestSame('MIT License', $result[0]['license'], 'License should match.');
assertTestSame('Need structured logging.', $result[0]['reuseJustification'], 'Reuse justification should match.');
assertTestSame('Use SLF4J facade.', $result[0]['integrationStrategy'], 'Integration strategy should match.');
assertTestSame('Unit tests cover all log calls.', $result[0]['validationStrategy'], 'Validation strategy should match.');
assertTestSame(['ch.qos.logback:logback-classic', 'org.slf4j:slf4j-api'], $result[0]['thirdPartyDependencies'], 'Third-party deps should match.');

// ---------------------------------------------------------------------------
// parse() — optional fields default to empty
// ---------------------------------------------------------------------------

$json = json_encode([
    'highLevelDependencies' => [
        ['name' => 'Security'],
    ],
]);
$result = HighLevelDependencyParser::parse($json);
assertTestSame(1, count($result), 'Should parse 1 high-level dependency with minimal fields.');
assertTestSame('Security', $result[0]['name'], 'Name should match.');
assertTestSame('', $result[0]['license'], 'License should default to empty string.');
assertTestSame('', $result[0]['reuseJustification'], 'Reuse justification should default to empty string.');
assertTestSame('', $result[0]['integrationStrategy'], 'Integration strategy should default to empty string.');
assertTestSame('', $result[0]['validationStrategy'], 'Validation strategy should default to empty string.');
assertTestSame([], $result[0]['thirdPartyDependencies'], 'Third-party deps should default to empty array.');

// ---------------------------------------------------------------------------
// parse() — multiple entries
// ---------------------------------------------------------------------------

$json = json_encode([
    'highLevelDependencies' => [
        ['name' => 'Logging'],
        ['name' => 'Metrics'],
        ['name' => 'Tracing'],
    ],
]);
$result = HighLevelDependencyParser::parse($json);
assertTestSame(3, count($result), 'Should parse 3 high-level dependencies.');
assertTestSame('Logging', $result[0]['name'], 'First entry name should match.');
assertTestSame('Metrics', $result[1]['name'], 'Second entry name should match.');
assertTestSame('Tracing', $result[2]['name'], 'Third entry name should match.');

// ---------------------------------------------------------------------------
// parse() — name is trimmed
// ---------------------------------------------------------------------------

$json = json_encode([
    'highLevelDependencies' => [
        ['name' => '  Logging  '],
    ],
]);
$result = HighLevelDependencyParser::parse($json);
assertTestSame('Logging', $result[0]['name'], 'Name should be trimmed.');

// ---------------------------------------------------------------------------
// parse() — empty license value is accepted
// ---------------------------------------------------------------------------

$json = json_encode([
    'highLevelDependencies' => [
        ['name' => 'Cache', 'license' => ''],
    ],
]);
$result = HighLevelDependencyParser::parse($json);
assertTestSame('', $result[0]['license'], 'Empty license string should be accepted.');

// ---------------------------------------------------------------------------
// parse() — invalid JSON throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    HighLevelDependencyParser::parse('not json');
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Invalid JSON should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — JSON root not an object throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    HighLevelDependencyParser::parse('[1, 2, 3]');
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'JSON array root should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — missing highLevelDependencies key throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    HighLevelDependencyParser::parse('{"other": []}');
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Missing highLevelDependencies key should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — highLevelDependencies not an array throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    HighLevelDependencyParser::parse('{"highLevelDependencies": "oops"}');
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Non-array highLevelDependencies should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — missing name throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    HighLevelDependencyParser::parse('{"highLevelDependencies": [{"license": "MIT License"}]}');
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Entry without name should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — empty name throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    HighLevelDependencyParser::parse('{"highLevelDependencies": [{"name": "   "}]}');
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Entry with whitespace-only name should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — invalid license throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    $json = json_encode(['highLevelDependencies' => [['name' => 'Lib', 'license' => 'Fake License']]]);
    HighLevelDependencyParser::parse($json);
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Invalid license value should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — duplicate name throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    $json = json_encode(['highLevelDependencies' => [['name' => 'Logging'], ['name' => 'Logging']]]);
    HighLevelDependencyParser::parse($json);
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Duplicate name should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — name over 255 chars throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    $json = json_encode(['highLevelDependencies' => [['name' => str_repeat('a', 256)]]]);
    HighLevelDependencyParser::parse($json);
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Name over 255 characters should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — thirdPartyDependencies not an array throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    $json = json_encode(['highLevelDependencies' => [['name' => 'Lib', 'thirdPartyDependencies' => 'not-array']]]);
    HighLevelDependencyParser::parse($json);
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Non-array thirdPartyDependencies should throw a RuntimeException.');

// ---------------------------------------------------------------------------
// parse() — thirdPartyDependency with empty string throws RuntimeException
// ---------------------------------------------------------------------------

$threw = false;
try {
    $json = json_encode(['highLevelDependencies' => [['name' => 'Lib', 'thirdPartyDependencies' => ['']]]]);
    HighLevelDependencyParser::parse($json);
} catch (RuntimeException $e) {
    $threw = true;
}
assertTestTrue($threw, 'Empty string in thirdPartyDependencies should throw a RuntimeException.');

echo "HighLevelDependencyParser tests passed.\n";
