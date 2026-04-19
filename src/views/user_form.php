<?php
// Variables expected:
//   $editUser (User|null) - user being edited, or null for creation
?>
    <form method="post">
        <?php if ($editUser !== null): ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $editUser->id, ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create_user">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="field-firstname"><i class="fas fa-id-card"></i> First name</label>
                <input id="field-firstname" type="text" name="firstname" required value="<?= htmlspecialchars($editUser?->firstname ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="field-name"><i class="fas fa-id-card"></i> Last name</label>
                <input id="field-name" type="text" name="name" required value="<?= htmlspecialchars($editUser?->name ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label for="field-email"><i class="fas fa-envelope"></i> Email address</label>
                <input id="field-email" type="email" name="email" required value="<?= htmlspecialchars($editUser?->email ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" title="<?= $editUser !== null ? 'Update user' : 'Add user' ?>">
                <i class="fas <?= $editUser !== null ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
            </button>
            <a href="?action=users" class="btn btn-cancel" title="Cancel"><i class="fas fa-xmark"></i></a>
        </div>
    </form>
