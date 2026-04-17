<?php
// Variables expected:
//   $components (Component[]) - list of components with their dependencies

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-list-check"></i> Registered components</h2>
        <a href="?action=register" class="btn btn-primary"><i class="fas fa-plus"></i> Register component</a>
    </div>
    <?php if ($components === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No components registered yet.</p>
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
                        <th><i class="fas fa-link"></i> Dependencies</th>
                        <th><i class="fas fa-gear"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($components as $component): ?>
                        <tr>
                            <td>
                                <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="catalog-link">
                                    <?= htmlspecialchars($component->name, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($component->version, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($component->owner, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($component->projectName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="lang-badge">
                                    <i class="<?= htmlspecialchars($langIcons[$component->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php $depCount = count($component->dependencies); ?>
                                <?php if ($depCount === 0): ?>
                                    <span class="no-deps">None</span>
                                <?php else: ?>
                                    <span class="dep-count"><?= $depCount ?></span>
                                    <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-view"><i class="fas fa-eye"></i> View</a>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="?edit=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-edit"><i class="fas fa-pen"></i> Edit</a>
                                <form method="post">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-delete" onclick="return confirm('Delete this component?')"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
