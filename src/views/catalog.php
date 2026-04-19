<?php
// Variables expected:
//   $catalogDeps (array<array{name: string, usage_count: int}>) - list of dependency names with usage counts
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-book"></i> 3rd party components</h2>
    </div>
    <?php if ($catalogDeps === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No 3rd party components found yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-cube"></i> Component</th>
                        <th><i class="fas fa-cubes"></i> Using components</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogDeps as $dep): ?>
                        <tr>
                            <td>
                                <a href="?action=catalog&amp;catalog_dep=<?= urlencode($dep['name']) ?>" class="catalog-link">
                                    <?= htmlspecialchars($dep['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td>
                                <span class="dep-count"><?= $dep['usage_count'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
