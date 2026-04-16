<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Dependency.php';
require_once __DIR__ . '/src/models/Component.php';
require_once __DIR__ . '/src/database/Connection.php';
require_once __DIR__ . '/src/database/ComponentRepository.php';
require_once __DIR__ . '/src/DependencyParser.php';

$languages = ['Java', 'Python', 'JavaScript'];
$maxDependencyImportFileSize = 2 * 1024 * 1024;
$message = null;
$messageType = 'success';
$editComponent = null;

$repository = null;
try {
    $repository = new ComponentRepository(getDatabaseConnection());
} catch (Throwable $exception) {
    $message = 'Unable to connect to database: ' . $exception->getMessage();
    $messageType = 'error';
}

if ($repository !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $componentId = (int) ($_POST['component_id'] ?? 0);
        if ($componentId <= 0) {
            $message = 'Invalid component ID.';
            $messageType = 'error';
        } else {
            try {
                if (!$repository->delete($componentId)) {
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
                $dependencies = null;
                $dependenciesImported = null;
                $upload = $_FILES['dependencies_file'] ?? null;
                if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $size = (int) ($upload['size'] ?? 0);
                    if ($size > $maxDependencyImportFileSize) {
                        throw new RuntimeException('Dependency file is too large (max 2 MB).');
                    }

                    $content = file_get_contents($upload['tmp_name']);
                    $parsed = [];
                    if ($content !== false && strlen($content) <= $maxDependencyImportFileSize) {
                        $parsed = DependencyParser::parse($language, $content);
                    }

                    $dependencies = array_values(array_filter(
                        $parsed,
                        static fn (array $d): bool => strlen($d['name']) <= 255 && strlen($d['version']) <= 100,
                    ));
                    $dependenciesImported = count($dependencies);
                }

                if (!$repository->update($componentId, $name, $version, $owner, $project, $language, $dependencies)) {
                    $message = 'Component not found.';
                    $messageType = 'error';
                } else {
                    if ($dependenciesImported !== null) {
                        $message = sprintf('Component updated successfully (%d dependencies imported).', $dependenciesImported);
                    } else {
                        $message = 'Component updated successfully.';
                    }
                    $messageType = 'success';
                }
            } catch (Throwable $exception) {
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
                $dependencies = [];
                $upload = $_FILES['dependencies_file'] ?? null;
                if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $size = (int) ($upload['size'] ?? 0);
                    if ($size > $maxDependencyImportFileSize) {
                        throw new RuntimeException('Dependency file is too large (max 2 MB).');
                    }

                    $content = file_get_contents($upload['tmp_name']);
                    if ($content !== false && strlen($content) <= $maxDependencyImportFileSize) {
                        $parsed = DependencyParser::parse($language, $content);
                        $dependencies = array_values(array_filter(
                            $parsed,
                            static fn (array $d): bool => strlen($d['name']) <= 255 && strlen($d['version']) <= 100,
                        ));
                    }
                }

                $repository->save($name, $version, $owner, $project, $language, $dependencies);
                $message = sprintf('Component saved successfully (%d dependencies imported).', count($dependencies));
                $messageType = 'success';
            } catch (Throwable $exception) {
                $message = 'Unable to save component: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    }
}

if ($repository !== null && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        try {
            $editComponent = $repository->findById($editId);
            if ($editComponent === null) {
                $message = 'Component not found.';
                $messageType = 'error';
            }
        } catch (Throwable $exception) {
            $message = 'Unable to load component: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$components = [];
if ($repository !== null) {
    try {
        $components = $repository->listAll();
    } catch (Throwable $exception) {
        if ($message === null) {
            $message = 'Unable to load components: ' . $exception->getMessage();
            $messageType = 'error';
        }
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

    <?php include __DIR__ . '/src/views/form.php'; ?>
    <?php include __DIR__ . '/src/views/list.php'; ?>
</body>
</html>
