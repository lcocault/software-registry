<?php

declare(strict_types=1);

require_once __DIR__ . '/src/models/Dependency.php';
require_once __DIR__ . '/src/models/ComponentVersion.php';
require_once __DIR__ . '/src/models/Component.php';
require_once __DIR__ . '/src/models/User.php';
require_once __DIR__ . '/src/models/Cve.php';
require_once __DIR__ . '/src/database/Connection.php';
require_once __DIR__ . '/src/database/ComponentRepository.php';
require_once __DIR__ . '/src/database/UserRepository.php';
require_once __DIR__ . '/src/database/CveRepository.php';
require_once __DIR__ . '/src/DependencyParser.php';
require_once __DIR__ . '/src/OsvClient.php';

$languages = ['Java', 'Python', 'JavaScript'];
$maxDependencyImportFileSize = 2 * 1024 * 1024;
$message = null;
$messageType = 'success';
$editComponent = null;
$editUser = null;
$catalogDeps = null;
$catalogDepName = null;
$catalogVersions = null;
$catalogDepVersion = null;
$catalogUsing = null;
$catalogCves = null;
$viewCveCheckComponent = null;
$viewCveCheckVersion = null;
$viewCveCheckData = null;
$allCveCounts = [];

$repository = null;
$userRepository = null;
$cveRepository = null;
try {
    $pdo = getDatabaseConnection();
    $repository = new ComponentRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $cveRepository = new CveRepository($pdo);
} catch (Throwable $exception) {
    $message = 'Unable to connect to database: ' . $exception->getMessage();
    $messageType = 'error';
}

if ($repository !== null && $userRepository !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } elseif ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $message = 'Invalid user ID.';
            $messageType = 'error';
        } else {
            try {
                if (!$userRepository->delete($userId)) {
                    $message = 'User not found.';
                    $messageType = 'error';
                } else {
                    $message = 'User deleted successfully.';
                    $messageType = 'success';
                }
            } catch (Throwable $exception) {
                $message = 'Unable to delete user: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'create_user') {
        $firstname = trim($_POST['firstname'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($firstname === '' || $name === '' || $email === '') {
            $message = 'All user fields are required.';
            $messageType = 'error';
        } else {
            try {
                $userRepository->save($firstname, $name, $email);
                $message = 'User added successfully.';
                $messageType = 'success';
            } catch (Throwable $exception) {
                $message = 'Unable to add user: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_user') {
        $userId    = (int) ($_POST['user_id'] ?? 0);
        $firstname = trim($_POST['firstname'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($userId <= 0 || $firstname === '' || $name === '' || $email === '') {
            $message = 'All user fields are required.';
            $messageType = 'error';
        } else {
            try {
                if (!$userRepository->update($userId, $firstname, $name, $email)) {
                    $message = 'User not found.';
                    $messageType = 'error';
                } else {
                    $message = 'User updated successfully.';
                    $messageType = 'success';
                }
            } catch (Throwable $exception) {
                $message = 'Unable to update user: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update') {
        $componentId = (int) ($_POST['component_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $ownerId = (int) ($_POST['owner_id'] ?? 0);
        $project = trim($_POST['project'] ?? '');
        $language = trim($_POST['language'] ?? '');

        if ($componentId <= 0 || $name === '' || $version === '' || $ownerId <= 0 || $project === '' || !in_array($language, $languages, true)) {
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

                if (!$repository->update($componentId, $name, $version, $ownerId, $project, $language, $dependencies)) {
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
    } elseif ($action === 'refresh_cves' && $cveRepository !== null) {
        $depName     = trim($_POST['dep_name'] ?? '');
        $depVersion  = trim($_POST['dep_version'] ?? '');
        $depLanguage = trim($_POST['dep_language'] ?? '');

        if ($depName === '' || $depVersion === '' || $depLanguage === '') {
            $message = 'Invalid dependency information for CVE refresh.';
            $messageType = 'error';
        } else {
            try {
                $freshCves = (new OsvClient())->getVulnerabilities($depName, $depVersion, $depLanguage);
                $cveRepository->store($depName, $depVersion, $freshCves);
                header(
                    'Location: ?action=catalog&catalog_dep=' . urlencode($depName)
                    . '&catalog_version=' . urlencode($depVersion)
                );
                exit;
            } catch (Throwable $exception) {
                $message = 'Unable to refresh CVE data: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'refresh_version_cves' && $repository !== null && $cveRepository !== null) {
        $componentId = (int) ($_POST['component_id'] ?? 0);
        $versionId   = (int) ($_POST['version_id'] ?? 0);

        if ($componentId <= 0 || $versionId <= 0) {
            $message = 'Invalid component or version for CVE refresh.';
            $messageType = 'error';
        } else {
            try {
                $comp = $repository->findByIdWithVersions($componentId);
                if ($comp === null) {
                    $message = 'Component not found.';
                    $messageType = 'error';
                } else {
                    $matchedVersion = null;
                    foreach ($comp->versions as $ver) {
                        if ($ver->id === $versionId) {
                            $matchedVersion = $ver;
                            break;
                        }
                    }
                    if ($matchedVersion === null) {
                        $message = 'Version not found.';
                        $messageType = 'error';
                    } else {
                        foreach ($matchedVersion->dependencies as $dep) {
                            $freshCves = (new OsvClient())->getVulnerabilities($dep->name, $dep->version, $comp->language);
                            $cveRepository->store($dep->name, $dep->version, $freshCves);
                        }
                        header('Location: ?deps=' . $componentId . '&check_cves=' . $versionId);
                        exit;
                    }
                }
            } catch (Throwable $exception) {
                $message = 'Unable to refresh CVEs: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    } else {
        $name    = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $ownerId = (int) ($_POST['owner_id'] ?? 0);
        $project = trim($_POST['project'] ?? '');
        $language = trim($_POST['language'] ?? '');

        if ($name === '' || $version === '' || $ownerId <= 0 || $project === '' || !in_array($language, $languages, true)) {
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

                $repository->save($name, $version, $ownerId, $project, $language, $dependencies);
                $message = sprintf('Component saved successfully (%d dependencies imported).', count($dependencies));
                $messageType = 'success';
            } catch (Throwable $exception) {
                $message = 'Unable to save component: ' . $exception->getMessage();
                $messageType = 'error';
            }
        }
    }
}

if ($userRepository !== null && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_user'])) {
    $editUserId = (int) $_GET['edit_user'];
    if ($editUserId > 0) {
        try {
            $editUser = $userRepository->findById($editUserId);
            if ($editUser === null) {
                $message = 'User not found.';
                $messageType = 'error';
            }
        } catch (Throwable $exception) {
            $message = 'Unable to load user: ' . $exception->getMessage();
            $messageType = 'error';
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

$viewDepsComponent = null;
if ($repository !== null && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['deps']) && !isset($_GET['check_cves'])) {
    $depsId = (int) $_GET['deps'];
    if ($depsId > 0) {
        try {
            $viewDepsComponent = $repository->findByIdWithVersions($depsId);
            if ($viewDepsComponent === null) {
                $message = 'Component not found.';
                $messageType = 'error';
            } elseif ($cveRepository !== null) {
                $allCveCounts = $cveRepository->getAllCounts();
            }
        } catch (Throwable $exception) {
            $message = 'Unable to load component: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

if ($repository !== null && $cveRepository !== null && $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['deps'], $_GET['check_cves'])) {
    $depsId         = (int) $_GET['deps'];
    $checkVersionId = (int) $_GET['check_cves'];
    if ($depsId > 0 && $checkVersionId > 0) {
        try {
            $viewCveCheckComponent = $repository->findByIdWithVersions($depsId);
            if ($viewCveCheckComponent === null) {
                $message = 'Component not found.';
                $messageType = 'error';
            } else {
                foreach ($viewCveCheckComponent->versions as $ver) {
                    if ($ver->id === $checkVersionId) {
                        $viewCveCheckVersion = $ver;
                        break;
                    }
                }
                if ($viewCveCheckVersion === null) {
                    $message = 'Version not found.';
                    $messageType = 'error';
                } else {
                    $viewCveCheckData = [];
                    foreach ($viewCveCheckVersion->dependencies as $dep) {
                        $cves = $cveRepository->findByDependency($dep->name, $dep->version);
                        if ($cves === null) {
                            try {
                                $cves = (new OsvClient())->getVulnerabilities(
                                    $dep->name,
                                    $dep->version,
                                    $viewCveCheckComponent->language,
                                );
                                $cveRepository->store($dep->name, $dep->version, $cves);
                            } catch (Throwable) {
                                $cves = null;
                            }
                        }
                        $viewCveCheckData[] = ['dep' => $dep, 'cves' => $cves];
                    }
                }
            }
        } catch (Throwable $exception) {
            $message = 'Unable to load CVE data: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$showCatalogSection = isset($_GET['action']) && $_GET['action'] === 'catalog';

if ($repository !== null && $showCatalogSection && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $catalogDepName    = isset($_GET['catalog_dep']) ? trim($_GET['catalog_dep']) : null;
    $catalogDepVersion = isset($_GET['catalog_version']) ? trim($_GET['catalog_version']) : null;

    try {
        if ($catalogDepName !== null && $catalogDepName !== '' && $catalogDepVersion !== null && $catalogDepVersion !== '') {
            $catalogUsing = $repository->listComponentsUsingDependency($catalogDepName, $catalogDepVersion);

            // Load CVEs from the database; only call the OSV API if they have never been fetched.
            // CVE lookup uses the language of the first component as the OSV ecosystem.
            // All components sharing the same dependency are expected to use the same language.
            if ($cveRepository !== null) {
                $catalogCves = $cveRepository->findByDependency($catalogDepName, $catalogDepVersion);
                if ($catalogCves === null) {
                    $language = $catalogUsing !== [] ? $catalogUsing[0]->language : '';
                    if ($language !== '') {
                        try {
                            $catalogCves = (new OsvClient())->getVulnerabilities($catalogDepName, $catalogDepVersion, $language);
                            $cveRepository->store($catalogDepName, $catalogDepVersion, $catalogCves);
                        } catch (Throwable) {
                            $catalogCves = [];
                        }
                    } else {
                        $catalogCves = [];
                    }
                }
            } else {
                $catalogCves = [];
            }
        } elseif ($catalogDepName !== null && $catalogDepName !== '') {
            $catalogVersions = $repository->listDependencyVersions($catalogDepName);
            if ($cveRepository !== null) {
                $catalogVersions = array_map(
                    static fn (array $ver): array => $ver + [
                        'cve_count' => $cveRepository->countByDependency($catalogDepName, $ver['version']),
                    ],
                    $catalogVersions,
                );
            } else {
                $catalogVersions = array_map(
                    static fn (array $ver): array => $ver + ['cve_count' => null],
                    $catalogVersions,
                );
            }
        } else {
            $catalogDepName = null;
            $catalogDeps    = $repository->listDependencyNames();
        }
    } catch (Throwable $exception) {
        $message = 'Unable to load catalog: ' . $exception->getMessage();
        $messageType = 'error';
    }
}

$components = [];
if ($repository !== null && $viewDepsComponent === null && $viewCveCheckData === null && !$showCatalogSection) {
    try {
        $components = $repository->listAll();
        if ($cveRepository !== null) {
            $allCveCounts = $cveRepository->getAllCounts();
        }
    } catch (Throwable $exception) {
        if ($message === null) {
            $message = 'Unable to load components: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$users = [];
if ($userRepository !== null) {
    try {
        $users = $userRepository->listAll();
    } catch (Throwable $exception) {
        if ($message === null) {
            $message = 'Unable to load users: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

$showUsersSection = (isset($_GET['action']) && $_GET['action'] === 'users')
    || (isset($_GET['action']) && $_GET['action'] === 'register_user')
    || $editUser !== null
    || (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && in_array($_POST['action'] ?? '', ['create_user', 'update_user', 'delete_user'], true)
    );

$isFailedFormSubmission = $_SERVER['REQUEST_METHOD'] === 'POST'
    && !in_array($_POST['action'] ?? 'create', ['delete', 'delete_user', 'create_user', 'update_user', 'refresh_cves', 'refresh_version_cves'], true)
    && $messageType === 'error';

$showUserForm = $editUser !== null
    || (isset($_GET['action']) && $_GET['action'] === 'register_user')
    || (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && in_array($_POST['action'] ?? '', ['create_user', 'update_user'], true)
        && $messageType === 'error'
    );

$showForm = $editComponent !== null
    || (isset($_GET['action']) && in_array($_GET['action'], ['register'], true))
    || $isFailedFormSubmission;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Registry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* ── CSS custom properties ─────────────────────────────────────── */
        :root {
            --bg-primary:       #f0f4f8;
            --bg-card:          #ffffff;
            --text-primary:     #1a202c;
            --text-secondary:   #718096;
            --border-color:     #e2e8f0;
            --header-bg:        #1a1a2e;
            --header-text:      #ffffff;
            --accent:           #4361ee;
            --accent-hover:     #3451d1;
            --success-bg:       #f0fff4;
            --success-border:   #9ae6b4;
            --success-text:     #276749;
            --error-bg:         #fff5f5;
            --error-border:     #fc8181;
            --error-text:       #9b2c2c;
            --th-bg:            #1a1a2e;
            --th-text:          #e2e8f0;
            --tr-hover:         #ebf4ff;
            --tr-stripe:        #f7fafc;
            --input-bg:         #ffffff;
            --input-border:     #cbd5e0;
            --input-focus:      #4361ee;
            --btn-primary-bg:   #4361ee;
            --btn-primary-text: #ffffff;
            --btn-primary-hover:#3451d1;
            --btn-edit-bg:      #ebf8ff;
            --btn-edit-border:  #90cdf4;
            --btn-edit-text:    #2b6cb0;
            --btn-edit-hover:   #bee3f8;
            --btn-del-bg:       #fff5f5;
            --btn-del-border:   #fc8181;
            --btn-del-text:     #c53030;
            --btn-del-hover:    #fed7d7;
            --btn-cancel-bg:    #f7fafc;
            --btn-cancel-border:#cbd5e0;
            --btn-cancel-text:  #4a5568;
            --btn-cancel-hover: #edf2f7;
            --shadow:           0 1px 3px rgba(0,0,0,.12), 0 1px 2px rgba(0,0,0,.08);
            --radius:           8px;
        }

        /* Auto dark mode (system preference) */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg-primary:       #0d1117;
                --bg-card:          #161b22;
                --text-primary:     #c9d1d9;
                --text-secondary:   #8b949e;
                --border-color:     #30363d;
                --header-bg:        #010409;
                --header-text:      #c9d1d9;
                --accent:           #58a6ff;
                --accent-hover:     #79b8ff;
                --success-bg:       #0d2a17;
                --success-border:   #196c2e;
                --success-text:     #56d364;
                --error-bg:         #2d0f0f;
                --error-border:     #8b2020;
                --error-text:       #f85149;
                --th-bg:            #010409;
                --th-text:          #c9d1d9;
                --tr-hover:         #1c2333;
                --tr-stripe:        #131920;
                --input-bg:         #0d1117;
                --input-border:     #30363d;
                --input-focus:      #58a6ff;
                --btn-primary-bg:   #238636;
                --btn-primary-text: #ffffff;
                --btn-primary-hover:#2ea043;
                --btn-edit-bg:      #1b2a4a;
                --btn-edit-border:  #1f6feb;
                --btn-edit-text:    #58a6ff;
                --btn-edit-hover:   #234780;
                --btn-del-bg:       #2d0f0f;
                --btn-del-border:   #6e1a1a;
                --btn-del-text:     #f85149;
                --btn-del-hover:    #3d1515;
                --btn-cancel-bg:    #21262d;
                --btn-cancel-border:#30363d;
                --btn-cancel-text:  #8b949e;
                --btn-cancel-hover: #292e36;
                --shadow:           0 1px 3px rgba(0,0,0,.4), 0 1px 2px rgba(0,0,0,.3);
            }
        }

        /* Manual dark mode override */
        [data-theme="dark"] {
            --bg-primary:       #0d1117;
            --bg-card:          #161b22;
            --text-primary:     #c9d1d9;
            --text-secondary:   #8b949e;
            --border-color:     #30363d;
            --header-bg:        #010409;
            --header-text:      #c9d1d9;
            --accent:           #58a6ff;
            --accent-hover:     #79b8ff;
            --success-bg:       #0d2a17;
            --success-border:   #196c2e;
            --success-text:     #56d364;
            --error-bg:         #2d0f0f;
            --error-border:     #8b2020;
            --error-text:       #f85149;
            --th-bg:            #010409;
            --th-text:          #c9d1d9;
            --tr-hover:         #1c2333;
            --tr-stripe:        #131920;
            --input-bg:         #0d1117;
            --input-border:     #30363d;
            --input-focus:      #58a6ff;
            --btn-primary-bg:   #238636;
            --btn-primary-text: #ffffff;
            --btn-primary-hover:#2ea043;
            --btn-edit-bg:      #1b2a4a;
            --btn-edit-border:  #1f6feb;
            --btn-edit-text:    #58a6ff;
            --btn-edit-hover:   #234780;
            --btn-del-bg:       #2d0f0f;
            --btn-del-border:   #6e1a1a;
            --btn-del-text:     #f85149;
            --btn-del-hover:    #3d1515;
            --btn-cancel-bg:    #21262d;
            --btn-cancel-border:#30363d;
            --btn-cancel-text:  #8b949e;
            --btn-cancel-hover: #292e36;
            --shadow:           0 1px 3px rgba(0,0,0,.4), 0 1px 2px rgba(0,0,0,.3);
        }

        /* ── Reset & base ──────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background .25s, color .25s;
            min-height: 100vh;
        }

        /* ── Header ────────────────────────────────────────────────────── */
        header {
            background: var(--header-bg);
            color: var(--header-text);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,.35);
        }

        .site-title {
            margin: 0;
            font-size: 1.25em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: .01em;
        }

        .site-title i { color: #4361ee; font-size: 1.1em; }

        /* ── Dark-mode toggle ──────────────────────────────────────────── */
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            background: transparent;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 20px;
            color: var(--header-text);
            font-size: .85em;
            cursor: pointer;
            transition: background .2s, border-color .2s;
        }

        .theme-toggle:hover { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.45); }

        /* ── Main content ──────────────────────────────────────────────── */
        main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 24px;
        }

        .subtitle {
            color: var(--text-secondary);
            margin: 0 0 24px;
            font-size: .93em;
            line-height: 1.6;
        }

        .subtitle code {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 1px 5px;
            border-radius: 4px;
            font-size: .9em;
        }

        /* ── Alert messages ────────────────────────────────────────────── */
        .message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: .95em;
        }

        .message.success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .message.error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        /* ── Card ──────────────────────────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .card-title {
            margin: 0 0 20px;
            font-size: 1.05em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .card-title i { color: var(--accent); }

        /* ── Form ──────────────────────────────────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 620px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }

        label {
            font-size: .8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-secondary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        input[type="text"],
        input[type="file"],
        select {
            width: 100%;
            padding: 8px 11px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: .95em;
            transition: border-color .2s, box-shadow .2s;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(67,97,238,.15);
        }

        input[type="file"] { padding: 6px 8px; }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        /* ── Buttons ───────────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: .9em;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            transition: background .18s, border-color .18s, color .18s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border-color: var(--btn-primary-bg);
        }
        .btn-primary:hover { background: var(--btn-primary-hover); border-color: var(--btn-primary-hover); }

        .btn-edit {
            background: var(--btn-edit-bg);
            border-color: var(--btn-edit-border);
            color: var(--btn-edit-text);
        }
        .btn-edit:hover { background: var(--btn-edit-hover); }

        .btn-delete {
            background: var(--btn-del-bg);
            border-color: var(--btn-del-border);
            color: var(--btn-del-text);
        }
        .btn-delete:hover { background: var(--btn-del-hover); }

        .btn-cancel {
            background: var(--btn-cancel-bg);
            border-color: var(--btn-cancel-border);
            color: var(--btn-cancel-text);
        }
        .btn-cancel:hover { background: var(--btn-cancel-hover); }

        /* ── Table ─────────────────────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }

        table {
            border-collapse: collapse;
            width: 100%;
            font-size: .92em;
        }

        thead th {
            background: var(--th-bg);
            color: var(--th-text);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        thead th i { margin-right: 5px; opacity: .75; }

        tbody tr:nth-child(even) { background: var(--tr-stripe); }
        tbody tr:hover { background: var(--tr-hover); }

        td {
            border-bottom: 1px solid var(--border-color);
            padding: 10px 12px;
            vertical-align: top;
        }

        ul { margin: 0; padding-left: 18px; }
        li { margin: 2px 0; }

        .lang-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .empty-state {
            color: var(--text-secondary);
            text-align: center;
            padding: 32px 16px;
            font-size: .95em;
        }

        .empty-state i { font-size: 2em; display: block; margin-bottom: 10px; opacity: .4; }

        .no-deps { color: var(--text-secondary); font-style: italic; font-size: .9em; }

        .cve-count-badge {
            display: inline-block;
            border-radius: 12px;
            padding: 1px 8px;
            font-size: .85em;
            font-weight: 600;
            white-space: nowrap;
        }
        .cve-count-zero { background: #e8f5e9; border: 1px solid #68d391; color: #1b5e20; }
        .cve-count-vuln { background: #ffe4e4; border: 1px solid #fc8181; color: #9b2c2c; }
        .cve-count-unknown { background: var(--tr-stripe); border: 1px solid var(--border-color); color: var(--text-secondary); font-style: italic; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .cve-count-zero { background: #0d2a17; border-color: #065f46; color: #6ee7b7; }
            :root:not([data-theme="light"]) .cve-count-vuln { background: #3d0e0e; border-color: #7f1d1d; color: #f87171; }
        }
        [data-theme="dark"] .cve-count-zero { background: #0d2a17; border-color: #065f46; color: #6ee7b7; }
        [data-theme="dark"] .cve-count-vuln { background: #3d0e0e; border-color: #7f1d1d; color: #f87171; }

        .dep-count {
            display: inline-block;
            background: var(--btn-edit-bg);
            border: 1px solid var(--btn-edit-border);
            color: var(--btn-edit-text);
            border-radius: 12px;
            padding: 1px 8px;
            font-size: .85em;
            font-weight: 600;
            margin-right: 6px;
        }

        .btn-view {
            background: var(--btn-edit-bg);
            border-color: var(--btn-edit-border);
            color: var(--btn-edit-text);
        }
        .btn-view:hover { background: var(--btn-edit-hover); }

        .catalog-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .catalog-link:hover { text-decoration: underline; }

        .deps-component-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: .95em;
            flex-wrap: wrap;
        }
        .deps-component-name { font-weight: 700; font-size: 1.05em; }
        .deps-component-version {
            color: var(--text-secondary);
            background: var(--tr-stripe);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 1px 7px;
            font-size: .9em;
        }

        .deps-footer {
            color: var(--text-secondary);
            font-size: .85em;
            text-align: right;
            margin-top: 8px;
        }

        .deps-version-section {
            margin-bottom: 24px;
        }
        .deps-version-label {
            font-size: .95em;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-hint {
            display: block;
            margin-top: 4px;
            font-size: .82em;
            color: var(--text-secondary);
        }

        .actions { white-space: nowrap; }
        .actions form { display: inline; }

        .card-title-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .card-title-bar .card-title { margin-bottom: 0; }

        .nav-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .nav-bar a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 16px;
            border-radius: var(--radius);
            font-size: .9em;
            text-decoration: none;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            transition: background .15s, border-color .15s;
        }
        .nav-bar a:hover { background: var(--tr-hover); border-color: var(--accent); }
        .nav-bar a.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* ── CVE / Vulnerability styles ────────────────────────────────── */
        .cve-section { margin-top: 28px; }

        .cve-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .cve-section-title {
            font-size: 1em;
            font-weight: 700;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--text-primary);
        }

        .cve-none {
            color: var(--text-secondary);
            font-size: .9em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cve-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }

        .cve-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 12px 14px;
            background: var(--bg-card);
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 10px;
        }

        .cve-id {
            font-weight: 700;
            font-size: .9em;
            white-space: nowrap;
            font-family: monospace;
            color: var(--text-primary);
        }

        .cve-description {
            flex: 1;
            font-size: .9em;
            color: var(--text-secondary);
            min-width: 0;
        }

        .cve-severity {
            font-size: .78em;
            font-weight: 700;
            border-radius: 4px;
            padding: 2px 8px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .cve-severity-CRITICAL { background: #ffe4e4; color: #9b2c2c; border: 1px solid #fc8181; }
        .cve-severity-HIGH     { background: #fff3e0; color: #7b4f0e; border: 1px solid #f6ad55; }
        .cve-severity-MEDIUM   { background: #fffde7; color: #744210; border: 1px solid #f6e05e; }
        .cve-severity-LOW      { background: #e8f5e9; color: #1b5e20; border: 1px solid #68d391; }
        .cve-severity-UNKNOWN  { background: var(--tr-stripe); color: var(--text-secondary); border: 1px solid var(--border-color); }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .cve-severity-CRITICAL { background: #3d0e0e; color: #f87171; border-color: #7f1d1d; }
            :root:not([data-theme="light"]) .cve-severity-HIGH     { background: #3d2000; color: #fbbf24; border-color: #92400e; }
            :root:not([data-theme="light"]) .cve-severity-MEDIUM   { background: #3d3000; color: #fde68a; border-color: #92400e; }
            :root:not([data-theme="light"]) .cve-severity-LOW      { background: #0d2a17; color: #6ee7b7; border-color: #065f46; }
        }
    </style>
</head>
<body>
    <header>
        <h1 class="site-title"><i class="fas fa-cubes"></i> Software Registry</h1>
        <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon" id="toggle-icon"></i>
            <span id="toggle-label">Dark mode</span>
        </button>
    </header>

    <main>
        <nav class="nav-bar">
            <a href="." class="<?= !$showUsersSection && !$showCatalogSection ? 'active' : '' ?>"><i class="fas fa-cubes"></i> Components</a>
            <a href="?action=catalog" class="<?= $showCatalogSection ? 'active' : '' ?>"><i class="fas fa-book"></i> 3rd party</a>
            <a href="?action=users" class="<?= $showUsersSection ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a>
        </nav>

        <p class="subtitle">
            Supported dependency import formats:
            <code><i class="fab fa-java"></i> mvn dependency:tree</code>
            &nbsp;·&nbsp;
            <code><i class="fab fa-python"></i> pip list</code>
            &nbsp;·&nbsp;
            <code><i class="fab fa-js"></i> package-lock.json</code>
        </p>

        <?php if ($message !== null): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($showUsersSection): ?>

        <?php if ($showUserForm): ?>
        <div class="card">
            <h2 class="card-title">
                <i class="fas <?= $editUser !== null ? 'fa-pen-to-square' : 'fa-plus-circle' ?>"></i>
                <?= $editUser !== null ? 'Edit user' : 'Add user' ?>
            </h2>
            <?php include __DIR__ . '/src/views/user_form.php'; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <?php include __DIR__ . '/src/views/users.php'; ?>
        </div>

        <?php elseif ($showCatalogSection): ?>

        <div class="card">
            <?php if ($catalogUsing !== null): ?>
                <?php include __DIR__ . '/src/views/catalog_using.php'; ?>
            <?php elseif ($catalogVersions !== null): ?>
                <?php include __DIR__ . '/src/views/catalog_versions.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/src/views/catalog.php'; ?>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <?php if ($showForm): ?>
        <div class="card">
            <h2 class="card-title">
                <i class="fas <?= $editComponent !== null ? 'fa-pen-to-square' : 'fa-plus-circle' ?>"></i>
                <?= $editComponent !== null ? 'Edit component' : 'Register component' ?>
            </h2>
            <?php include __DIR__ . '/src/views/form.php'; ?>
        </div>
        <?php endif; ?>

        <?php if ($viewCveCheckData !== null): ?>
        <div class="card">
            <?php include __DIR__ . '/src/views/cve_check.php'; ?>
        </div>
        <?php elseif ($viewDepsComponent !== null): ?>
        <div class="card">
            <?php $component = $viewDepsComponent; include __DIR__ . '/src/views/dependencies.php'; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <?php include __DIR__ . '/src/views/list.php'; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </main>

    <script>
        (function () {
            var html   = document.documentElement;
            var btn    = document.getElementById('theme-toggle');
            var icon   = document.getElementById('toggle-icon');
            var label  = document.getElementById('toggle-label');

            function isDark() {
                var theme = html.getAttribute('data-theme');
                if (theme === 'dark') return true;
                if (theme === 'light') return false;
                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            function applyTheme(dark) {
                html.setAttribute('data-theme', dark ? 'dark' : 'light');
                icon.className  = dark ? 'fas fa-sun' : 'fas fa-moon';
                label.textContent = dark ? 'Light mode' : 'Dark mode';
            }

            // Restore saved preference
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                applyTheme(saved === 'dark');
            } else {
                applyTheme(isDark());
            }

            btn.addEventListener('click', function () {
                var next = !isDark();
                applyTheme(next);
                localStorage.setItem('theme', next ? 'dark' : 'light');
            });
        })();
    </script>
</body>
</html>
