<?php
// Variables expected:
//   $component          (Component)                            - the component whose high-level dependencies are displayed
//   $allDependencyNames (array<array{name:string}>|null)       - list of available 3rd party dependency names

$langIcons = [
    'Java'       => 'fab fa-java',
    'Python'     => 'fab fa-python',
    'JavaScript' => 'fab fa-js',
];
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-layer-group"></i> High-Level Dependencies</h2>
        <a href="?" class="btn btn-cancel"><i class="fas fa-arrow-left"></i> Back to list</a>
    </div>

    <div class="deps-component-info">
        <span class="deps-component-name"><?= htmlspecialchars($component->name, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="lang-badge">
            <i class="<?= htmlspecialchars($langIcons[$component->language] ?? 'fas fa-code', ENT_QUOTES, 'UTF-8') ?>"></i>
            <?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <?php if ($component->highLevelDependencies === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No high-level dependencies registered for this component.</p>
    <?php else: ?>
        <?php foreach ($component->highLevelDependencies as $hld): ?>
            <div class="deps-version-section">
                <div class="card-title-bar" style="margin-bottom:10px">
                    <h3 class="deps-version-label" style="margin-bottom:0">
                        <i class="fas fa-layer-group"></i>
                        <span class="deps-component-version"><?= htmlspecialchars($hld->name, ENT_QUOTES, 'UTF-8') ?></span>
                    </h3>
                    <form method="post" style="margin:0">
                        <input type="hidden" name="action" value="delete_high_level_dep">
                        <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="high_level_dep_id" value="<?= htmlspecialchars((string) $hld->id, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-delete" onclick="return confirm('Delete this high-level dependency?')"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </div>

                <div style="margin-bottom:12px">
                    <div class="form-group" style="margin-bottom:8px">
                        <strong><i class="fas fa-comment"></i> Reuse justification:</strong>
                        <?php if ($hld->reuseJustification !== ''): ?>
                            <p style="margin:4px 0 0 0; white-space:pre-wrap"><?= htmlspecialchars($hld->reuseJustification, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <p style="margin:4px 0 0 0"><span class="no-deps">Not specified</span></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="margin-bottom:8px">
                        <strong><i class="fas fa-puzzle-piece"></i> Integration strategy:</strong>
                        <?php if ($hld->integrationStrategy !== ''): ?>
                            <p style="margin:4px 0 0 0; white-space:pre-wrap"><?= htmlspecialchars($hld->integrationStrategy, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <p style="margin:4px 0 0 0"><span class="no-deps">Not specified</span></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="margin-bottom:8px">
                        <strong><i class="fas fa-flask"></i> Validation strategy:</strong>
                        <?php if ($hld->validationStrategy !== ''): ?>
                            <p style="margin:4px 0 0 0; white-space:pre-wrap"><?= htmlspecialchars($hld->validationStrategy, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <p style="margin:4px 0 0 0"><span class="no-deps">Not specified</span></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-bottom:12px">
                    <strong><i class="fas fa-cubes"></i> 3rd party dependencies:</strong>
                    <?php if ($hld->thirdPartyDependencies === []): ?>
                        <p class="empty-state" style="margin-top:4px"><i class="fas fa-inbox"></i> None linked yet.</p>
                    <?php else: ?>
                        <div class="table-wrapper" style="margin-top:6px">
                            <table>
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-cube"></i> Dependency name</th>
                                        <th><i class="fas fa-gear"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hld->thirdPartyDependencies as $depName): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($depName, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="actions">
                                                <form method="post">
                                                    <input type="hidden" name="action" value="delete_high_level_dep_third_party">
                                                    <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="high_level_dep_id" value="<?= htmlspecialchars((string) $hld->id, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="dep_name" value="<?= htmlspecialchars($depName, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-delete" onclick="return confirm('Remove this 3rd party dependency?')"><i class="fas fa-trash"></i> Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="deps-add-dep-form" style="margin-top:8px">
                        <input type="hidden" name="action" value="add_high_level_dep_third_party">
                        <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="high_level_dep_id" value="<?= htmlspecialchars((string) $hld->id, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="deps-inline-form">
                            <div class="form-group">
                                <label for="dep-name-<?= htmlspecialchars((string) $hld->id, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-cube"></i> 3rd party component</label>
                                <?php
                                    $linkedNames   = $hld->thirdPartyDependencies;
                                    $linkedIndex   = array_flip($linkedNames);
                                    $availableNames = array_filter(
                                        $allDependencyNames ?? [],
                                        static fn (array $entry): bool => !isset($linkedIndex[$entry['name']]),
                                    );
                                ?>
                                <?php if (empty($availableNames)): ?>
                                    <p class="empty-state" style="margin:4px 0"><i class="fas fa-info-circle"></i> No additional 3rd party components available.</p>
                                <?php else: ?>
                                    <select id="dep-name-<?= htmlspecialchars((string) $hld->id, ENT_QUOTES, 'UTF-8') ?>" name="dep_name" required>
                                        <option value="" disabled selected>— Select a 3rd party component —</option>
                                        <?php foreach ($availableNames as $entry): ?>
                                            <option value="<?= htmlspecialchars($entry['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($entry['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($availableNames)): ?>
                            <div class="deps-inline-form-action">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Link dependency</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="deps-add-version-section">
        <h3 class="deps-version-label"><i class="fas fa-plus-circle"></i> Add new high-level dependency</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_high_level_dep">
            <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="hld-name"><i class="fas fa-layer-group"></i> Name</label>
                <input id="hld-name" type="text" name="hld_name" placeholder="e.g. Logging" required>
            </div>
            <div class="form-group">
                <label for="hld-reuse-justification"><i class="fas fa-comment"></i> Reuse justification</label>
                <textarea id="hld-reuse-justification" name="reuse_justification" rows="3" placeholder="Why is this dependency needed from the component perspective?"></textarea>
            </div>
            <div class="form-group">
                <label for="hld-integration-strategy"><i class="fas fa-puzzle-piece"></i> Integration strategy</label>
                <textarea id="hld-integration-strategy" name="integration_strategy" rows="3" placeholder="How is this dependency integrated at the component level?"></textarea>
            </div>
            <div class="form-group">
                <label for="hld-validation-strategy"><i class="fas fa-flask"></i> Validation strategy</label>
                <textarea id="hld-validation-strategy" name="validation_strategy" rows="3" placeholder="How is this dependency validated in the component?"></textarea>
            </div>
            <div style="margin-top:8px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add high-level dependency</button>
            </div>
        </form>
    </div>
