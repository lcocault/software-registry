<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHelpers.php';
require_once __DIR__ . '/../src/models/Dependency.php';
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

// --- Component model (no dependencies) ---

$component = new Component(1, 'my-lib', '1.0.0', 5, 'Alice Smith', 'Java', 'my-project');
assertTestSame(1, $component->id, 'Component id should match.');
assertTestSame('my-lib', $component->name, 'Component name should match.');
assertTestSame('1.0.0', $component->version, 'Component version should match.');
assertTestSame(5, $component->ownerId, 'Component ownerId should match.');
assertTestSame('Alice Smith', $component->owner, 'Component owner should match.');
assertTestSame('Java', $component->language, 'Component language should match.');
assertTestSame('my-project', $component->projectName, 'Component projectName should match.');
assertTestSame([], $component->dependencies, 'Component dependencies should default to empty array.');

// --- Component model (with dependencies) ---

$deps = [
    new Dependency('org.slf4j:slf4j-api', '2.0.13'),
    new Dependency('org.junit.jupiter:junit-jupiter', '5.10.2'),
];
$componentWithDeps = new Component(2, 'my-app', '2.3.0', 7, 'Bob Jones', 'Java', 'my-project', $deps);
assertTestSame($deps, $componentWithDeps->dependencies, 'Component dependencies should match constructor argument.');
assertTestSame(2, count($componentWithDeps->dependencies), 'Component should have 2 dependencies.');
assertTestSame('org.slf4j:slf4j-api', $componentWithDeps->dependencies[0]->name, 'First dependency name should match.');
assertTestSame('2.0.13', $componentWithDeps->dependencies[0]->version, 'First dependency version should match.');

echo "Component and Dependency model tests passed.\n";
