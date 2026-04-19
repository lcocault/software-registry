<?php
// Variables expected:
//   $catalogDepName     (string)                                                        - the dependency name being viewed
//   $catalogVersions    (array<array{version: string, usage_count: int, cve_count: int|null}>) - versions with usage and CVE counts
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-cube"></i> <?= htmlspecialchars($catalogDepName, ENT_QUOTES, 'UTF-8') ?></h2>
        <a href="?action=catalog" class="btn btn-cancel" title="Back to 3rd party"><i class="fas fa-arrow-left"></i></a>
    </div>
    <?php if ($catalogVersions === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No versions found for this dependency.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-code-branch"></i> Version</th>
                        <th><i class="fas fa-cubes"></i> Using components</th>
                        <th><i class="fas fa-shield-halved"></i> Known CVEs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogVersions as $ver): ?>
                        <tr>
                            <td>
                                <a href="?action=catalog&amp;catalog_dep=<?= urlencode($catalogDepName) ?>&amp;catalog_version=<?= urlencode($ver['version']) ?>" class="catalog-link">
                                    <?= htmlspecialchars($ver['version'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td>
                                <span class="dep-count"><?= $ver['usage_count'] ?></span>
                            </td>
                            <td>
                                <?php if ($ver['cve_count'] === null): ?>
                                    <span class="cve-unknown" title="CVEs not yet checked"><i class="fas fa-circle-question"></i></span>
                                <?php elseif ($ver['cve_count'] === 0): ?>
                                    <span class="cve-none"><i class="fas fa-circle-check"></i> 0</span>
                                <?php else: ?>
                                    <span class="dep-count cve-count-badge"><?= $ver['cve_count'] ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="deps-add-version-section">
        <h3 class="deps-version-label"><i class="fas fa-plus-circle"></i> Add new version</h3>
        <form method="post" class="deps-add-dep-form">
            <input type="hidden" name="action" value="add_catalog_version">
            <input type="hidden" name="catalog_name" value="<?= htmlspecialchars($catalogDepName, ENT_QUOTES, 'UTF-8') ?>">
            <div class="deps-inline-form">
                <div class="form-group">
                    <label for="catalog-new-version"><i class="fas fa-code-branch"></i> Version label</label>
                    <input id="catalog-new-version" type="text" name="catalog_version" placeholder="e.g. 6.1.0" value="<?= htmlspecialchars($_POST['catalog_version'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="deps-inline-form-action">
                    <button type="submit" class="btn btn-primary" title="Add version"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </form>
    </div>
