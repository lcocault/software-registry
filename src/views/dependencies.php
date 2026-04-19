<?php
// Variables expected:
//   $component (Component) - the component whose versions and dependencies are displayed

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
        <span class="lang-badge">
            <i class="<?= htmlspecialchars($langIcons[$component->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
            <?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <?php if ($component->versions === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No versions registered for this component.</p>
    <?php else: ?>
        <?php foreach ($component->versions as $version): ?>
            <div class="deps-version-section">
                <div class="card-title-bar" style="margin-bottom:10px">
                    <h3 class="deps-version-label" style="margin-bottom:0">
                        <i class="fas fa-code-branch"></i>
                        <span class="deps-component-version"><?= htmlspecialchars($version->label, ENT_QUOTES, 'UTF-8') ?></span>
                    </h3>
                    <?php if ($version->dependencies !== []): ?>
                        <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>&amp;check_cves=<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-edit">
                            <i class="fas fa-shield-halved"></i> Check CVEs
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($version->dependencies === []): ?>
                    <p class="empty-state"><i class="fas fa-inbox"></i> No dependencies registered for this version.</p>
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
                                <?php foreach ($version->dependencies as $dependency): ?>
                                    <tr>
                                        <td>
                                            <a href="?action=catalog&amp;catalog_dep=<?= urlencode($dependency->name) ?>" class="catalog-link">
                                                <?= htmlspecialchars($dependency->name, ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="?action=catalog&amp;catalog_dep=<?= urlencode($dependency->name) ?>&amp;catalog_version=<?= urlencode($dependency->version) ?>" class="catalog-link">
                                                <?= htmlspecialchars($dependency->version, ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="deps-footer"><?= count($version->dependencies) ?> <?= count($version->dependencies) === 1 ? 'dependency' : 'dependencies' ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
