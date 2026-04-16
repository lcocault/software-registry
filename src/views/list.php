<?php
// Variables expected:
//   $components (Component[]) - list of components with their dependencies
?>
    <h2>Registered components</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Version</th>
                <th>Owner</th>
                <th>Project</th>
                <th>Language</th>
                <th>Dependencies</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($components === []): ?>
            <tr><td colspan="7">No components registered yet.</td></tr>
        <?php else: ?>
            <?php foreach ($components as $component): ?>
                <tr>
                    <td><?= htmlspecialchars($component->name, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component->version, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component->owner, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component->projectName, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component->language, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($component->dependencies === []): ?>
                            No dependencies.
                        <?php else: ?>
                            <ul>
                                <?php foreach ($component->dependencies as $dependency): ?>
                                    <li><?= htmlspecialchars($dependency->name, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($dependency->version, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>" class="btn-edit">Edit</a>
                        <form method="post" style="display:inline; margin-top:4px;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component->id, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-delete" onclick="return confirm('Delete this component?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
