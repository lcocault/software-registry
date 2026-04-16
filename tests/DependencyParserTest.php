<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/DependencyParser.php';

function assertDependenciesEqual(array $expected, array $actual, string $message): void
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

assertDependenciesEqual(
    [
        ['name' => 'org.slf4j:slf4j-api', 'version' => '2.0.13'],
        ['name' => 'org.junit.jupiter:junit-jupiter', 'version' => '5.10.2'],
    ],
    DependencyParser::parse('Java', $javaInput),
    'Java parser should parse mvn dependency tree output.'
);

$javaInputWithoutInfoPrefix = <<<TXT
fr.codemap:cli:jar:0.0.1-SNAPSHOT
+- fr.codemap:engine:jar:0.0.1-SNAPSHOT:compile
|  \\- com.github.javaparser:javaparser-symbol-solver-core:jar:3.25.3:compile
|     +- com.github.javaparser:javaparser-core:jar:3.25.3:compile
|     +- org.javassist:javassist:jar:3.29.2-GA:compile
|     \\- com.google.guava:guava:jar:31.1-jre:compile
|        +- com.google.guava:failureaccess:jar:1.0.1:compile
|        +- com.google.guava:listenablefuture:jar:9999.0-empty-to-avoid-conflict-with-guava:compile
|        +- com.google.code.findbugs:jsr305:jar:3.0.2:compile
|        +- org.checkerframework:checker-qual:jar:3.12.0:compile
|        +- com.google.errorprone:error_prone_annotations:jar:2.11.0:compile
|        \\- com.google.j2objc:j2objc-annotations:jar:1.3:compile
+- commons-cli:commons-cli:jar:1.5.0:compile
\\- org.junit.jupiter:junit-jupiter-engine:jar:5.4.2:test
   +- org.apiguardian:apiguardian-api:jar:1.0.0:test
   +- org.junit.platform:junit-platform-engine:jar:1.4.2:test
   |  +- org.opentest4j:opentest4j:jar:1.1.1:test
   |  \\- org.junit.platform:junit-platform-commons:jar:1.4.2:test
   \\- org.junit.jupiter:junit-jupiter-api:jar:5.4.2:test
TXT;

assertDependenciesEqual(
    [
        ['name' => 'fr.codemap:engine', 'version' => '0.0.1-SNAPSHOT'],
        ['name' => 'com.github.javaparser:javaparser-symbol-solver-core', 'version' => '3.25.3'],
        ['name' => 'com.github.javaparser:javaparser-core', 'version' => '3.25.3'],
        ['name' => 'org.javassist:javassist', 'version' => '3.29.2-GA'],
        ['name' => 'com.google.guava:guava', 'version' => '31.1-jre'],
        ['name' => 'com.google.guava:failureaccess', 'version' => '1.0.1'],
        ['name' => 'com.google.guava:listenablefuture', 'version' => '9999.0-empty-to-avoid-conflict-with-guava'],
        ['name' => 'com.google.code.findbugs:jsr305', 'version' => '3.0.2'],
        ['name' => 'org.checkerframework:checker-qual', 'version' => '3.12.0'],
        ['name' => 'com.google.errorprone:error_prone_annotations', 'version' => '2.11.0'],
        ['name' => 'com.google.j2objc:j2objc-annotations', 'version' => '1.3'],
        ['name' => 'commons-cli:commons-cli', 'version' => '1.5.0'],
        ['name' => 'org.junit.jupiter:junit-jupiter-engine', 'version' => '5.4.2'],
        ['name' => 'org.apiguardian:apiguardian-api', 'version' => '1.0.0'],
        ['name' => 'org.junit.platform:junit-platform-engine', 'version' => '1.4.2'],
        ['name' => 'org.opentest4j:opentest4j', 'version' => '1.1.1'],
        ['name' => 'org.junit.platform:junit-platform-commons', 'version' => '1.4.2'],
        ['name' => 'org.junit.jupiter:junit-jupiter-api', 'version' => '5.4.2'],
    ],
    DependencyParser::parse('Java', $javaInputWithoutInfoPrefix),
    'Java parser should parse mvn dependency tree output without [INFO] prefix.'
);

$pythonInput = <<<TXT
Package    Version
---------- -------
requests   2.32.3
urllib3==2.2.1
TXT;

assertDependenciesEqual(
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

assertDependenciesEqual(
    [
        ['name' => 'react', 'version' => '18.3.1'],
        ['name' => 'lodash', 'version' => '4.17.21'],
    ],
    DependencyParser::parse('JavaScript', $javascriptInput),
    'JavaScript parser should parse package-lock.json.'
);

echo "DependencyParser tests passed.\n";
