<?php
// Variables expected:
//   $users      (User[]) - list of registered users
//   $editUser   (User|null) - user being edited, or null
?>
    <div class="card-title-bar">
        <h2 class="card-title"><i class="fas fa-users"></i> Registered users</h2>
        <a href="?action=register_user" class="btn btn-primary" title="Add user"><i class="fas fa-plus"></i></a>
    </div>
    <?php if ($users === []): ?>
        <p class="empty-state"><i class="fas fa-inbox"></i> No users registered yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card"></i> First name</th>
                        <th><i class="fas fa-id-card"></i> Last name</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-gear"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user->firstname, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="actions">
                                <a href="?edit_user=<?= htmlspecialchars((string) $user->id, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                <form method="post">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $user->id, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-delete" title="Delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
