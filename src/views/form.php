<?php
// Variables expected:
//   $editComponent (Component|null) - component being edited, or null for creation
//   $languages     (string[])       - list of supported languages
?>
    <form method="post" enctype="multipart/form-data">
        <?php if ($editComponent !== null): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $editComponent->id, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <label>
            Name
            <input type="text" name="name" required value="<?= htmlspecialchars($editComponent?->name ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Version
            <input type="text" name="version" required value="<?= htmlspecialchars($editComponent?->version ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Owner
            <input type="text" name="owner" required value="<?= htmlspecialchars($editComponent?->owner ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Project
            <input type="text" name="project" required value="<?= htmlspecialchars($editComponent?->projectName ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Language
            <select name="language" required>
                <?php foreach ($languages as $language): ?>
                    <option value="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>"<?= ($editComponent !== null && $editComponent->language === $language) ? ' selected' : '' ?>><?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Dependency file (<?= $editComponent !== null ? 'optional, replaces existing dependencies' : 'optional' ?>)
            <input type="file" name="dependencies_file">
        </label>
        <div class="form-actions">
            <button type="submit"><?= $editComponent !== null ? 'Update component' : 'Register component' ?></button>
            <?php if ($editComponent !== null): ?>
                <a href="." class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
