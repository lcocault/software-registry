<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DependencyParser.php';

function assertSameDependencies(array $expected, array $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . PHP_EOL . 'Expected: ' . json_encode($expected) . PHP_EOL . 'Actual: ' . json_encode($actual));
    }
}

$javaInput = <<<TXT
[INFO] com.example:demo:jar:1.0-SNAPSHOT
[INFO] +- org.slf4j:slf4j-api:jar:2.0.13:compile
[INFO] \\- org.junit.jupiter:junit-jupiter:jar:5.10.2:test
TXT;

assertSameDependencies(
    [
        ['name' => 'org.slf4j:slf4j-api', 'version' => '2.0.13'],
        ['name' => 'org.junit.jupiter:junit-jupiter', 'version' => '5.10.2'],
    ],
    DependencyParser::parse('Java', $javaInput),
    'Java parser should parse mvn dependency tree output.'
);

$pythonInput = <<<TXT
Package    Version
---------- -------
requests   2.32.3
urllib3==2.2.1
TXT;

assertSameDependencies(
    [
        ['name' => 'requests', 'version' => '2.32.3'],
        ['name' => 'urllib3', 'version' => '2.2.1'],
    ],
    DependencyParser::parse('Python', $pythonInput),
    'Python parser should parse pip list output.'
);

$javascriptInput = json_encode([
    'name' => 'demo',
    'packages' => [
        '' => ['name' => 'demo', 'version' => '1.0.0'],
        'node_modules/react' => ['version' => '18.3.1'],
        'node_modules/lodash' => ['version' => '4.17.21'],
    ],
], JSON_THROW_ON_ERROR);

assertSameDependencies(
    [
        ['name' => 'react', 'version' => '18.3.1'],
        ['name' => 'lodash', 'version' => '4.17.21'],
    ],
    DependencyParser::parse('JavaScript', $javascriptInput),
    'JavaScript parser should parse package-lock.json.'
);

echo "DependencyParser tests passed.\n";
