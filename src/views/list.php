<?php
// Variables expected:
//   $components   (Component[]) - list of components with their versions and dependencies
//   $allCveCounts (array)       - [dep_name][dep_version] => int|absent for fetched CVE counts

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-list-check"></i> Registered components</h2>
        <a href="?action=register" class="btn btn-primary" title="Register component"><i class="fas fa-plus"></i></a>
    </div>
    <?php if ($components === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No components registered yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-tag"></i> Name</th>
                        <th><i class="fas fa-code-branch"></i> Versions</th>
                        <th><i class="fas fa-user"></i> Owner</th>
                        <th><i class="fas fa-folder"></i> Project</th>
                        <th><i class="fas fa-code"></i> Language</th>
                        <th><i class="fas fa-link"></i> Dependencies</th>
                        <th><i class="fas fa-shield-halved"></i> CVEs</th>
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
                            <td>
                                <?php if ($component->versions === []): ?>
                                    <span class="no-deps">None</span>
                                <?php else: ?>
                                    <?php foreach ($component->versions as $ver): ?>
                                        <span class="dep-count"><?= htmlspecialchars($ver->label, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($component->owner, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($component->projectName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="lang-badge">
                                    <i class="<?= htmlspecialchars($langIcons[$component->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php $depCount = array_sum(array_map(static fn ($v) => count($v->dependencies), $component->versions)); ?>
                                <?php if ($depCount === 0): ?>
                                    <span class="no-deps">None</span>
                                <?php else: ?>
                                    <span class="dep-count"><?= $depCount ?></span>
                                    <a href="?deps=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-view" title="View dependencies"><i class="fas fa-eye"></i></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $componentCveTotal = 0;
                                    $componentAnyFetched = false;
                                    foreach ($component->versions as $ver) {
                                        foreach ($ver->dependencies as $dep) {
                                            $cveCount = $allCveCounts[$dep->name][$dep->version] ?? null;
                                            if ($cveCount !== null) {
                                                $componentAnyFetched = true;
                                                $componentCveTotal += $cveCount;
                                            }
                                        }
                                    }
                                ?>
                                <?php if ($depCount === 0): ?>
                                    <span class="no-deps">—</span>
                                <?php elseif (!$componentAnyFetched): ?>
                                    <span class="cve-count-badge cve-count-unknown">?</span>
                                <?php elseif ($componentCveTotal === 0): ?>
                                    <span class="cve-count-badge cve-count-zero">0</span>
                                <?php else: ?>
                                    <span class="cve-count-badge cve-count-vuln"><?= $componentCveTotal ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="?edit=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                <form method="post">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-delete" title="Delete" onclick="return confirm('Delete this component?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
