<?php

declare(strict_types=1);

require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/DependencyParser.php';

$languages = ['Java', 'Python', 'JavaScript'];
$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $content = file_get_contents($upload['tmp_name']);
                if ($content !== false) {
                    $dependencies = DependencyParser::parse($language, $content);
                }
            }

            if ($dependencies !== []) {
                $valueClauses = [];
                $insertParams = [];

                foreach ($dependencies as $index => $dependency) {
                    $valueClauses[] = '(:component_id_' . $index . ', :name_' . $index . ', :version_' . $index . ')';
                    $insertParams['component_id_' . $index] = $componentId;
                    $insertParams['name_' . $index] = $dependency['name'];
                    $insertParams['version_' . $index] = $dependency['version'];
                }

                $dependencyStmt = $pdo->prepare(
                    'INSERT INTO dependencies(component_id, name, version) VALUES ' . implode(', ', $valueClauses)
                );
                $dependencyStmt->execute($insertParams);
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

try {
    $pdo = getDatabaseConnection();
    $components = $pdo->query(
        'SELECT c.id, c.name, c.version, c.owner, c.language, p.name AS project_name
         FROM components c
         JOIN projects p ON p.id = c.project_id
         ORDER BY c.id DESC'
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

        foreach ($componentIds as $index => $componentId) {
            $dependencyStmt->bindValue(':id_' . $index, $componentId, PDO::PARAM_INT);
        }
        $dependencyStmt->execute();

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
        <label>
            Name
            <input type="text" name="name" required>
        </label>
        <label>
            Version
            <input type="text" name="version" required>
        </label>
        <label>
            Owner
            <input type="text" name="owner" required>
        </label>
        <label>
            Project
            <input type="text" name="project" required>
        </label>
        <label>
            Language
            <select name="language" required>
                <?php foreach ($languages as $language): ?>
                    <option value="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Dependency file (optional)
            <input type="file" name="dependencies_file">
        </label>
        <button type="submit">Register component</button>
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
            </tr>
        </thead>
        <tbody>
        <?php if ($components === []): ?>
            <tr><td colspan="6">No component registered yet.</td></tr>
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
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
