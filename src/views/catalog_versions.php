<?php
// Variables expected:
//   $catalogDepName     (string)                              - the dependency name being viewed
//   $catalogVersions    (array<array{version: string, usage_count: int}>) - versions with usage counts
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
