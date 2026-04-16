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
        <div class="form-grid">
            <div class="form-group">
                <label for="field-name"><i class="fas fa-tag"></i> Name</label>
                <input id="field-name" type="text" name="name" required value="<?= htmlspecialchars($editComponent?->name ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="field-version"><i class="fas fa-code-branch"></i> Version</label>
                <input id="field-version" type="text" name="version" required value="<?= htmlspecialchars($editComponent?->version ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="field-owner"><i class="fas fa-user"></i> Owner</label>
                <input id="field-owner" type="text" name="owner" required value="<?= htmlspecialchars($editComponent?->owner ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="field-project"><i class="fas fa-folder"></i> Project</label>
                <input id="field-project" type="text" name="project" required value="<?= htmlspecialchars($editComponent?->projectName ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="field-language"><i class="fas fa-code"></i> Language</label>
                <select id="field-language" name="language" required>
                    <?php foreach ($languages as $language): ?>
                        <option value="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>"<?= ($editComponent !== null && $editComponent->language === $language) ? ' selected' : '' ?>><?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="field-deps"><i class="fas fa-file-import"></i> Dependency file (<?= $editComponent !== null ? 'optional, replaces existing' : 'optional' ?>)</label>
                <input id="field-deps" type="file" name="dependencies_file">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas <?= $editComponent !== null ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
                <?= $editComponent !== null ? 'Update component' : 'Register component' ?>
            </button>
            <?php if ($editComponent !== null): ?>
                <a href="." class="btn btn-cancel"><i class="fas fa-xmark"></i> Cancel</a>
            <?php endif; ?>
        </div>
    </form>
