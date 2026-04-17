<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Dependency.php';
require_once __DIR__ . '/../src/models/ComponentVersion.php';
require_once __DIR__ . '/../src/models/Component.php';

// --- Dependency model ---

$dep = new Dependency('org.slf4j:slf4j-api', '2.0.13');
assertTestSame('org.slf4j:slf4j-api', $dep->name, 'Dependency name should match constructor argument.');
assertTestSame('2.0.13', $dep->version, 'Dependency version should match constructor argument.');

// Readonly — assigning to a readonly property must throw an error
$readonlyBlocked = false;
try {
    // @phpstan-ignore-next-line
    $dep->name = 'changed'; // @phpstan-ignore-line
} catch (Error $e) {
    $readonlyBlocked = true;
}
assertTestTrue($readonlyBlocked, 'Dependency::name must be readonly.');

// --- ComponentVersion model (no dependencies) ---

$ver = new ComponentVersion(10, '1.0.0');
assertTestSame(10, $ver->id, 'ComponentVersion id should match constructor argument.');
assertTestSame('1.0.0', $ver->label, 'ComponentVersion label should match constructor argument.');
assertTestSame([], $ver->dependencies, 'ComponentVersion dependencies should default to empty array.');

// --- ComponentVersion model (with dependencies) ---

$deps = [
    new Dependency('org.slf4j:slf4j-api', '2.0.13'),
    new Dependency('org.junit.jupiter:junit-jupiter', '5.10.2'),
];
$verWithDeps = new ComponentVersion(11, '2.0.0', $deps);
assertTestSame($deps, $verWithDeps->dependencies, 'ComponentVersion dependencies should match constructor argument.');
assertTestSame(2, count($verWithDeps->dependencies), 'ComponentVersion should have 2 dependencies.');
assertTestSame('org.slf4j:slf4j-api', $verWithDeps->dependencies[0]->name, 'First dependency name should match.');
assertTestSame('2.0.13', $verWithDeps->dependencies[0]->version, 'First dependency version should match.');

// --- Component model (no versions) ---

$component = new Component(1, 'my-lib', 5, 'Alice Smith', 'Java', 'my-project');
assertTestSame(1, $component->id, 'Component id should match.');
assertTestSame('my-lib', $component->name, 'Component name should match.');
assertTestSame(5, $component->ownerId, 'Component ownerId should match.');
assertTestSame('Alice Smith', $component->owner, 'Component owner should match.');
assertTestSame('Java', $component->language, 'Component language should match.');
assertTestSame('my-project', $component->projectName, 'Component projectName should match.');
assertTestSame([], $component->versions, 'Component versions should default to empty array.');

// --- Component model (with versions) ---

$versions = [
    new ComponentVersion(10, '1.0.0'),
    new ComponentVersion(11, '2.0.0', $deps),
];
$componentWithVersions = new Component(2, 'my-app', 7, 'Bob Jones', 'Java', 'my-project', $versions);
assertTestSame($versions, $componentWithVersions->versions, 'Component versions should match constructor argument.');
assertTestSame(2, count($componentWithVersions->versions), 'Component should have 2 versions.');
assertTestSame('1.0.0', $componentWithVersions->versions[0]->label, 'First version label should match.');
assertTestSame('2.0.0', $componentWithVersions->versions[1]->label, 'Second version label should match.');
assertTestSame(2, count($componentWithVersions->versions[1]->dependencies), 'Second version should have 2 dependencies.');

echo "Component, ComponentVersion, and Dependency model tests passed.\n";
