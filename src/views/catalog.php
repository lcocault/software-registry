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
    <div class="deps-add-version-section">
        <h3 class="deps-version-label"><i class="fas fa-plus-circle"></i> Add new 3rd party component</h3>
        <form method="post" class="deps-add-dep-form">
            <input type="hidden" name="action" value="add_catalog_entry">
            <div class="deps-inline-form">
                <div class="form-group">
                    <label for="catalog-entry-name"><i class="fas fa-cube"></i> Component name</label>
                    <input id="catalog-entry-name" type="text" name="catalog_name" placeholder="e.g. org.springframework:spring-core" value="<?= htmlspecialchars($_POST['catalog_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="catalog-entry-version"><i class="fas fa-code-branch"></i> Version</label>
                    <input id="catalog-entry-version" type="text" name="catalog_version" placeholder="e.g. 6.1.0" value="<?= htmlspecialchars($_POST['catalog_version'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="deps-inline-form-action">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>
        </form>
    </div>
