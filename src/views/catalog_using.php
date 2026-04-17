<?php
// Variables expected:
//   $catalogDepName     (string)      - the dependency name
//   $catalogDepVersion  (string)      - the version being viewed
//   $catalogUsing       (Component[]) - components that use this dependency version
//   $catalogCves        (Cve[])       - known CVEs for this dependency version

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];

$depLanguage = $catalogUsing !== [] ? $catalogUsing[0]->language : '';
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

    <div class="cve-section">
        <div class="cve-section-header">
            <p class="cve-section-title"><i class="fas fa-shield-halved"></i> Known vulnerabilities (CVE)</p>
            <form method="post" action="?action=catalog&amp;catalog_dep=<?= urlencode($catalogDepName) ?>&amp;catalog_version=<?= urlencode($catalogDepVersion) ?>">
                <input type="hidden" name="action" value="refresh_cves">
                <input type="hidden" name="dep_name" value="<?= htmlspecialchars($catalogDepName, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="dep_version" value="<?= htmlspecialchars($catalogDepVersion, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="dep_language" value="<?= htmlspecialchars($depLanguage, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-edit"><i class="fas fa-rotate"></i> Refresh CVEs</button>
            </form>
        </div>
        <?php if ($catalogCves === null || $catalogCves === []): ?>
            <p class="cve-none"><i class="fas fa-circle-check"></i> No known vulnerabilities found for this version.</p>
        <?php else: ?>
            <?php $knownSeverities = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']; ?>
            <ul class="cve-list">
                <?php foreach ($catalogCves as $cve): ?>
                    <li class="cve-item">
                        <span class="cve-id"><?= htmlspecialchars($cve->id, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($cve->severity !== ''): ?>
                            <?php $severityClass = in_array($cve->severity, $knownSeverities, true) ? $cve->severity : 'UNKNOWN'; ?>
                            <span class="cve-severity cve-severity-<?= htmlspecialchars($severityClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($cve->severity, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($cve->description !== ''): ?>
                            <span class="cve-description"><?= htmlspecialchars($cve->description, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

