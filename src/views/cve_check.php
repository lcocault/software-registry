<?php
// Variables expected:
//   $viewCveCheckComponent (Component)        - the component being checked
//   $viewCveCheckVersion   (ComponentVersion) - the specific version being checked
//   $viewCveCheckData      (array)            - array of ['dep' => Dependency, 'cves' => Cve[]|null]

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];

$knownSeverities = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

$totalCves = array_sum(array_map(
    static fn (array $entry): int => is_array($entry['cves']) ? count($entry['cves']) : 0,
    $viewCveCheckData,
));
?>
    <div class="card-title-bar">
        <h2 class="card-title">
            <i class="fas fa-shield-halved"></i>
            CVE Check &mdash;
            <span class="deps-component-name"><?= htmlspecialchars($viewCveCheckComponent->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="deps-component-version"><?= htmlspecialchars($viewCveCheckVersion->label, ENT_QUOTES, 'UTF-8') ?></span>
        </h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <form method="post">
                <input type="hidden" name="action" value="refresh_version_cves">
                <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $viewCveCheckComponent->id, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="version_id" value="<?= htmlspecialchars((string) $viewCveCheckVersion->id, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-edit" title="Refresh all CVEs"><i class="fas fa-rotate"></i></button>
            </form>
            <a href="?deps=<?= htmlspecialchars((string) $viewCveCheckComponent->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-cancel" title="Back to dependencies">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <div class="deps-component-info">
        <span class="deps-component-name"><?= htmlspecialchars($viewCveCheckComponent->name, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="deps-component-version"><?= htmlspecialchars($viewCveCheckVersion->label, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="lang-badge">
            <i class="<?= htmlspecialchars($langIcons[$viewCveCheckComponent->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
            <?= htmlspecialchars($viewCveCheckComponent->language, ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <?php if ($viewCveCheckData === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No dependencies registered for this version.</p>
    <?php else: ?>
        <?php if ($totalCves === 0): ?>
            <p class="cve-none"><i class="fas fa-circle-check"></i> No known vulnerabilities found for any dependency in this version.</p>
        <?php endif; ?>

        <?php foreach ($viewCveCheckData as $entry): ?>
            <?php $dep = $entry['dep']; $cves = $entry['cves']; ?>
            <div class="deps-version-section">
                <h3 class="deps-version-label">
                    <i class="fas fa-cube"></i>
                    <a href="?action=catalog&amp;catalog_dep=<?= urlencode($dep->name) ?>&amp;catalog_version=<?= urlencode($dep->version) ?>" class="catalog-link">
                        <?= htmlspecialchars($dep->name, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <span class="deps-component-version"><?= htmlspecialchars($dep->version, ENT_QUOTES, 'UTF-8') ?></span>
                </h3>
                <?php if ($cves === null): ?>
                    <p class="cve-none"><i class="fas fa-triangle-exclamation"></i> CVE data unavailable for this dependency.</p>
                <?php elseif ($cves === []): ?>
                    <p class="cve-none"><i class="fas fa-circle-check"></i> No known vulnerabilities.</p>
                <?php else: ?>
                    <ul class="cve-list">
                        <?php foreach ($cves as $cve): ?>
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
        <?php endforeach; ?>
        <p class="deps-footer">
            <?= count($viewCveCheckData) ?> <?= count($viewCveCheckData) === 1 ? 'dependency' : 'dependencies' ?> checked<?= $totalCves > 0 ? ', ' . $totalCves . ' ' . ($totalCves === 1 ? 'vulnerability' : 'vulnerabilities') . ' found' : '' ?>
        </p>
    <?php endif; ?>
