<?php
// Variables expected:
//   $component    (Component)     - the component whose versions and dependencies are displayed
//   $allCveCounts (array)         - [dep_name][dep_version] => int|absent for fetched CVE counts

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-link"></i> Dependencies</h2>
        <a href="?" class="btn btn-cancel" title="Back to list"><i class="fas fa-arrow-left"></i></a>
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
                        <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>&amp;check_cves=<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-edit" title="Check CVEs">
                            <i class="fas fa-shield-halved"></i>
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
                                    <th><i class="fas fa-shield-halved"></i> CVEs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($version->dependencies as $dependency): ?>
                                    <?php $cveCount = $allCveCounts[$dependency->name][$dependency->version] ?? null; ?>
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
                                        <td>
                                            <?php if ($cveCount === null): ?>
                                                <span class="cve-count-badge cve-count-unknown">—</span>
                                            <?php elseif ($cveCount === 0): ?>
                                                <span class="cve-count-badge cve-count-zero">0</span>
                                            <?php else: ?>
                                                <span class="cve-count-badge cve-count-vuln"><?= $cveCount ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="deps-footer"><?= count($version->dependencies) ?> <?= count($version->dependencies) === 1 ? 'dependency' : 'dependencies' ?></p>
                <?php endif; ?>
                <form method="post" class="deps-add-dep-form">
                    <input type="hidden" name="action" value="add_dependency">
                    <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="version_id" value="<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="deps-inline-form">
                        <div class="form-group">
                            <label for="dep-name-<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-cube"></i> Dependency name</label>
                            <input id="dep-name-<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>" type="text" name="dep_name" placeholder="e.g. org.slf4j:slf4j-api">
                        </div>
                        <div class="form-group">
                            <label for="dep-version-<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-code-branch"></i> Version</label>
                            <input id="dep-version-<?= htmlspecialchars((string) $version->id, ENT_QUOTES, 'UTF-8') ?>" type="text" name="dep_version" placeholder="e.g. 2.0.13">
                        </div>
                        <div class="deps-inline-form-action">
                            <button type="submit" class="btn btn-primary" title="Add dependency"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="deps-add-version-section">
        <h3 class="deps-version-label"><i class="fas fa-plus-circle"></i> Add new version</h3>
        <form method="post" class="deps-add-dep-form">
            <input type="hidden" name="action" value="add_version">
            <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
            <div class="deps-inline-form">
                <div class="form-group">
                    <label for="new-version-label"><i class="fas fa-code-branch"></i> Version label</label>
                    <input id="new-version-label" type="text" name="version_label" placeholder="e.g. 2.0.0">
                </div>
                <div class="deps-inline-form-action">
                    <button type="submit" class="btn btn-primary" title="Add version"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </form>
    </div>
