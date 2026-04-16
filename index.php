<?php

declare(strict_types=1);

require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/DependencyParser.php';

$languages = ['Java', 'Python', 'JavaScript'];
$maxDependencyImportFileSize = 2 * 1024 * 1024;
$message = null;
$messageType = 'success';
$editComponent = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $componentId = (int) ($_POST['component_id'] ?? 0);
        if ($componentId <= 0) {
            $message = 'Invalid component ID.';
            $messageType = 'error';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $deleteStmt = $pdo->prepare('DELETE FROM components WHERE id = :id');
                $deleteStmt->execute(['id' => $componentId]);
                if ($deleteStmt->rowCount() === 0) {
                    $message = 'Component not found.';
                    $messageType = 'error';
                } else {
                    $message = 'Component deleted successfully.';
                    $messageType = 'success';
                }
            } catch (Throwable $exception) {
                $message = 'Unable to delete component: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update') {
        $componentId = (int) ($_POST['component_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        $project = trim($_POST['project'] ?? '');
        $language = trim($_POST['language'] ?? '');

        if ($componentId <= 0 || $name === '' || $version === '' || $owner === '' || $project === '' || !in_array($language, $languages, true)) {
            $message = 'All fields are required and language must be valid.';
            $messageType = 'error';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $pdo->beginTransaction();

                $projectStmt = $pdo->prepare(
                    'INSERT INTO projects(name) VALUES(:name) ON CONFLICT(name) DO UPDATE SET name = EXCLUDED.name RETURNING id'
                );
                $projectStmt->execute(['name' => $project]);
                $projectId = (int) $projectStmt->fetchColumn();

                $updateStmt = $pdo->prepare(
                    'UPDATE components SET name = :name, version = :version, owner = :owner, language = :language, project_id = :project_id WHERE id = :id'
                );
                $updateStmt->execute([
                    'name' => $name,
                    'version' => $version,
                    'owner' => $owner,
                    'language' => $language,
                    'project_id' => $projectId,
                    'id' => $componentId,
                ]);

                if ($updateStmt->rowCount() === 0) {
                    $pdo->rollBack();
                    $message = 'Component not found.';
                    $messageType = 'error';
                } else {
                    $upload = $_FILES['dependencies_file'] ?? null;
                    $dependenciesImported = null;
                    if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $size = (int) ($upload['size'] ?? 0);
                        if ($size > $maxDependencyImportFileSize) {
                            throw new RuntimeException('Dependency file is too large (max 2 MB).');
                        }

                        $content = file_get_contents($upload['tmp_name']);
                        $dependencies = [];
                        if ($content !== false && strlen($content) <= $maxDependencyImportFileSize) {
                            $dependencies = DependencyParser::parse($language, $content);
                        }

                        $deleteDepStmt = $pdo->prepare('DELETE FROM dependencies WHERE component_id = :component_id');
                        $deleteDepStmt->execute(['component_id' => $componentId]);

                        if ($dependencies !== []) {
                            $valueClauses = [];
                            $insertParams = [];
                            $validDependencies = [];

                            foreach ($dependencies as $dependency) {
                                if (
                                    strlen($dependency['name']) <= 255 &&
                                    strlen($dependency['version']) <= 100
                                ) {
                                    $validDependencies[] = $dependency;
                                }
                            }

                            foreach ($validDependencies as $index => $dependency) {
                                $valueClauses[] = '(:component_id_' . $index . ', :name_' . $index . ', :version_' . $index . ')';
                                $insertParams['component_id_' . $index] = $componentId;
                                $insertParams['name_' . $index] = $dependency['name'];
                                $insertParams['version_' . $index] = $dependency['version'];
                            }

                            if ($valueClauses !== []) {
                                $dependencyStmt = $pdo->prepare(
                                    'INSERT INTO dependencies(component_id, name, version) VALUES ' . implode(', ', $valueClauses)
                                );
                                $dependencyStmt->execute($insertParams);
                            }

                            $dependenciesImported = count($validDependencies);
                        } else {
                            $dependenciesImported = 0;
                        }
                    }

                    $pdo->commit();
                    if ($dependenciesImported !== null) {
                        $message = sprintf('Component updated successfully (%d dependencies imported).', $dependenciesImported);
                    } else {
                        $message = 'Component updated successfully.';
                    }
                    $messageType = 'success';
                }
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = 'Unable to update component: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        $project = trim($_POST['project'] ?? '');
        $language = trim($_POST['language'] ?? '');

        if ($name === '' || $version === '' || $owner === '' || $project === '' || !in_array($language, $languages, true)) {
            $message = 'All fields are required and language must be valid.';
            $messageType = 'error';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $pdo->beginTransaction();

                $projectStmt = $pdo->prepare(
                    'INSERT INTO projects(name) VALUES(:name) ON CONFLICT(name) DO UPDATE SET name = EXCLUDED.name RETURNING id'
                );
                $projectStmt->execute(['name' => $project]);
                $projectId = (int) $projectStmt->fetchColumn();

                $componentStmt = $pdo->prepare(
                    'INSERT INTO components(name, version, owner, language, project_id) VALUES(:name, :version, :owner, :language, :project_id) RETURNING id'
                );
                $componentStmt->execute([
                    'name' => $name,
                    'version' => $version,
                    'owner' => $owner,
                    'language' => $language,
                    'project_id' => $projectId,
                ]);
                $componentId = (int) $componentStmt->fetchColumn();

                $dependencies = [];
                $upload = $_FILES['dependencies_file'] ?? null;
                if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $size = (int) ($upload['size'] ?? 0);
                    if ($size > $maxDependencyImportFileSize) {
                        throw new RuntimeException('Dependency file is too large (max 2 MB).');
                    }

                    $content = file_get_contents($upload['tmp_name']);
                    if ($content !== false && strlen($content) <= $maxDependencyImportFileSize) {
                        $dependencies = DependencyParser::parse($language, $content);
                    }
                }

                if ($dependencies !== []) {
                    $valueClauses = [];
                    $insertParams = [];
                    $validDependencies = [];

                    foreach ($dependencies as $dependency) {
                        if (
                            strlen($dependency['name']) <= 255 &&
                            strlen($dependency['version']) <= 100
                        ) {
                            $validDependencies[] = $dependency;
                        }
                    }

                    foreach ($validDependencies as $index => $dependency) {
                        $valueClauses[] = '(:component_id_' . $index . ', :name_' . $index . ', :version_' . $index . ')';
                        $insertParams['component_id_' . $index] = $componentId;
                        $insertParams['name_' . $index] = $dependency['name'];
                        $insertParams['version_' . $index] = $dependency['version'];
                    }

                    if ($valueClauses !== []) {
                        $dependencyStmt = $pdo->prepare(
                            'INSERT INTO dependencies(component_id, name, version) VALUES ' . implode(', ', $valueClauses)
                        );
                        $dependencyStmt->execute($insertParams);
                    }

                    $dependencies = $validDependencies;
                }

                $pdo->commit();
                $message = sprintf('Component saved successfully (%d dependencies imported).', count($dependencies));
                $messageType = 'success';
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = 'Unable to save component: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        try {
            $pdo = getDatabaseConnection();
            $editStmt = $pdo->prepare(
                'SELECT c.id, c.name, c.version, c.owner, c.language, p.name AS project_name
                 FROM components c
                 JOIN projects p ON p.id = c.project_id
                 WHERE c.id = :id'
            );
            $editStmt->execute(['id' => $editId]);
            $editComponent = $editStmt->fetch();
            if (!$editComponent) {
                $message = 'Component not found.';
                $messageType = 'error';
            }
        } catch (Throwable $exception) {
            $message = 'Unable to load component: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

try {
    $pdo = getDatabaseConnection();
    $components = $pdo->query(
        'SELECT c.id, c.name, c.version, c.owner, c.language, p.name AS project_name
         FROM components c
         JOIN projects p ON p.id = c.project_id
         ORDER BY c.id DESC
         LIMIT 200'
    )->fetchAll();
    $dependenciesByComponent = [];
    if ($components !== []) {
        $componentIds = array_map(static fn (array $component): int => (int) $component['id'], $components);
        $placeholderTokens = array_map(static fn (int $index): string => ':id_' . $index, array_keys($componentIds));
        $dependencyStmt = $pdo->prepare(
            'SELECT component_id, name, version
             FROM dependencies
             WHERE component_id IN (' . implode(', ', $placeholderTokens) . ')
             ORDER BY name'
        );

        $dependencyParams = [];
        foreach ($componentIds as $index => $componentId) {
            $dependencyParams['id_' . $index] = $componentId;
        }
        $dependencyStmt->execute($dependencyParams);

        foreach ($dependencyStmt->fetchAll() as $dependencyRow) {
            $componentId = (int) $dependencyRow['component_id'];
            $dependenciesByComponent[$componentId][] = [
                'name' => $dependencyRow['name'],
                'version' => $dependencyRow['version'],
            ];
        }
    }
} catch (Throwable $exception) {
    $components = [];
    $dependenciesByComponent = [];
    if ($message === null) {
        $message = 'Unable to connect to database: ' . $exception->getMessage();
        $messageType = 'error';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Software registry</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; max-width: 700px; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        button { margin-top: 12px; padding: 10px 14px; }
        .message { padding: 10px; margin-bottom: 15px; max-width: 700px; }
        .message.success { background: #e6ffed; border: 1px solid #98d8a7; }
        .message.error { background: #ffecec; border: 1px solid #f3a3a3; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; text-align: left; }
        ul { margin: 0; padding-left: 20px; }
        .btn-edit { padding: 4px 10px; background: #f0f4ff; border: 1px solid #6699cc; color: #336; cursor: pointer; font-size: 0.9em; text-decoration: none; display: inline-block; }
        .btn-edit:hover { background: #ddeeff; }
        .btn-delete { padding: 4px 10px; background: #fff0f0; border: 1px solid #cc6666; color: #600; cursor: pointer; font-size: 0.9em; margin-top: 0; }
        .btn-delete:hover { background: #ffe0e0; }
        .btn-cancel { padding: 6px 12px; background: #f5f5f5; border: 1px solid #bbb; color: #333; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 12px; margin-left: 8px; }
        .btn-cancel:hover { background: #e8e8e8; }
        .form-actions { display: flex; align-items: center; }
    </style>
</head>
<body>
    <h1>Software component registry</h1>
    <p>Supported dependency import formats: <strong>mvn dependency:tree</strong> (Java), <strong>pip list</strong> (Python), <strong>package-lock.json</strong> (JavaScript).</p>

    <?php if ($message !== null): ?>
        <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php if ($editComponent !== null): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $editComponent['id'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <label>
            Name
            <input type="text" name="name" required value="<?= htmlspecialchars($editComponent['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Version
            <input type="text" name="version" required value="<?= htmlspecialchars($editComponent['version'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Owner
            <input type="text" name="owner" required value="<?= htmlspecialchars($editComponent['owner'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Project
            <input type="text" name="project" required value="<?= htmlspecialchars($editComponent['project_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Language
            <select name="language" required>
                <?php foreach ($languages as $language): ?>
                    <option value="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>"<?= ($editComponent !== null && $editComponent['language'] === $language) ? ' selected' : '' ?>><?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?></option>
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
                    <td><?= htmlspecialchars($component['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component['version'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component['owner'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component['project_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($component['language'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php $dependencies = $dependenciesByComponent[(int) $component['id']] ?? []; ?>
                        <?php if ($dependencies === []): ?>
                            No dependencies.
                        <?php else: ?>
                            <ul>
                                <?php foreach ($dependencies as $dependency): ?>
                                    <li><?= htmlspecialchars($dependency['name'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($dependency['version'], ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= htmlspecialchars((string) $component['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn-edit">Edit</a>
                        <form method="post" style="display:inline; margin-top:4px;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="component_id" value="<?= htmlspecialchars((string) $component['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-delete" onclick="return confirm('Delete this component?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
