<?php
// Variables expected:
//   $catalogDepName     (string)                                                        - the dependency name being viewed
//   $catalogVersions    (array<array{version: string, usage_count: int, cve_count: int|null}>) - versions with usage and CVE counts
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-cube"></i> <?= htmlspecialchars($catalogDepName, ENT_QUOTES, 'UTF-8') ?></h2>
        <a href="?action=catalog" class="btn btn-cancel"><i class="fas fa-arrow-left"></i> Back to catalog</a>
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
