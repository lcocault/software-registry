<?php
// Variables expected:
//   $component (Component) - the component whose dependencies are displayed

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-link"></i> Dependencies</h2>
        <a href="?" class="btn btn-cancel"><i class="fas fa-arrow-left"></i> Back to list</a>
    </div>

    <div class="deps-component-info">
        <span class="deps-component-name"><?= htmlspecialchars($component->name, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="deps-component-version"><?= htmlspecialchars($component->version, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="lang-badge">
            <i class="<?= htmlspecialchars($langIcons[$component->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
            <?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <?php if ($component->dependencies === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No dependencies registered for this component.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-cube"></i> Dependency</th>
                        <th><i class="fas fa-code-branch"></i> Version</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($component->dependencies as $dependency): ?>
                        <tr>
                            <td><?= htmlspecialchars($dependency->name, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($dependency->version, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="deps-footer"><?= count($component->dependencies) ?> <?= count($component->dependencies) === 1 ? 'dependency' : 'dependencies' ?></p>
    <?php endif; ?>
