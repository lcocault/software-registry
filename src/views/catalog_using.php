<?php
// Variables expected:
//   $catalogDepName     (string)      - the dependency name
//   $catalogDepVersion  (string)      - the version being viewed
//   $catalogUsing       (Component[]) - components that use this dependency version

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];
?>
    <div class="card-title-bar">
        <h2 class="card-title">
            <i class="fas fa-cubes"></i>
            Using
            <span class="deps-component-name"><?= htmlspecialchars($catalogDepName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="deps-component-version"><?= htmlspecialchars($catalogDepVersion, ENT_QUOTES, 'UTF-8') ?></span>
        </h2>
        <a href="?action=catalog&amp;catalog_dep=<?= urlencode($catalogDepName) ?>" class="btn btn-cancel"><i class="fas fa-arrow-left"></i> Back to versions</a>
    </div>
    <?php if ($catalogUsing === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No components are currently using this version.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-tag"></i> Name</th>
                        <th><i class="fas fa-code-branch"></i> Version</th>
                        <th><i class="fas fa-user"></i> Owner</th>
                        <th><i class="fas fa-folder"></i> Project</th>
                        <th><i class="fas fa-code"></i> Language</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogUsing as $component): ?>
                        <tr>
                            <td>
                                <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="catalog-link">
                                    <?= htmlspecialchars($component->name, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td>
                                <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="catalog-link">
                                    <?= htmlspecialchars($component->version, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($component->owner, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($component->projectName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="lang-badge">
                                    <i class="<?= htmlspecialchars($langIcons[$component->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="deps-footer"><?= count($catalogUsing) ?> <?= count($catalogUsing) === 1 ? 'component' : 'components' ?></p>
    <?php endif; ?>
