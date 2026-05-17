<?php
// /admin/backup_action.php
// JSON endpoint consumed exclusively by backup.js via fetch().
// All state-changing actions require a valid CSRF token.
//
// Supported actions (via ?action=...):
//   list     — GET  — returns JSON list of saved backup files
//   backup   — POST — creates a new mysqldump snapshot
//   restore  — POST — restores from a saved backup file (overwrites all data)
//   import   — POST — restores from an uploaded .sql (Workbench export compat)
//   delete   — POST — deletes a saved backup file

session_start();
header('Content-Type: application/json');

// ── Security gate: Admin only ─────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/backup_engine.php';

$action = $_GET['action'] ?? '';
// Try every key your login.php might use for the username
$actor  = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'unknown';

// ── list: read-only, no CSRF needed ──────────────────────────────────────────
if ($action === 'list') {
    echo json_encode(['success' => true, 'files' => list_backup_files()]);
    exit();
}

// ── All write actions require a valid CSRF token ──────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit();
}

// ── backup ────────────────────────────────────────────────────────────────────
if ($action === 'backup') {
    $ok = take_database_backup('manual', $actor);
    echo json_encode([
        'success' => $ok,
        'message' => $ok
            ? 'Backup created successfully.'
            : 'Backup failed. Check XAMPP error log (php_error.log).',
    ]);
    exit();
}

// ── restore: from a saved backup file ────────────────────────────────────────
if ($action === 'restore') {
    $filename = basename($_POST['file'] ?? '');
    $result   = restore_database_backup($filename, $actor);
    echo json_encode([
        'success'  => $result['ok'],
        'message'  => $result['message'],
        'warnings' => $result['warnings'] ?? [],
    ]);
    exit();
}

// ── import: from an uploaded .sql file (MySQL Workbench export compatible) ───
// This allows admins to upload a .sql file exported from Workbench
// (Server → Data Export) and restore it directly through the system UI,
// mirroring what Workbench's "Data Import" does.
if ($action === 'import') {
    // Validate upload
    if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing PHP temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        $err_code = $_FILES['sqlfile']['error'] ?? UPLOAD_ERR_NO_FILE;
        $err_msg  = $upload_errors[$err_code] ?? 'Unknown upload error.';
        echo json_encode(['success' => false, 'message' => $err_msg]);
        exit();
    }

    // Validate file extension
    $original_name = $_FILES['sqlfile']['name'];
    if (strtolower(pathinfo($original_name, PATHINFO_EXTENSION)) !== 'sql') {
        echo json_encode(['success' => false, 'message' => 'Only .sql files are accepted.']);
        exit();
    }

    // Validate MIME / content — must start with a SQL comment or SET statement
    $fh      = fopen($_FILES['sqlfile']['tmp_name'], 'r');
    $header  = fread($fh, 256);
    fclose($fh);
    if (!preg_match('/^(--|\/\*|SET|CREATE|DROP|INSERT|USE)/i', ltrim($header))) {
        echo json_encode(['success' => false, 'message' => 'File does not appear to be a valid SQL dump.']);
        exit();
    }

    // Move upload to backups/ with a timestamped name so it appears in the list
    $safe_label = 'import_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
    $dest_path  = BACKUP_DIR . $safe_label;

    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0750, true);
    }

    if (!move_uploaded_file($_FILES['sqlfile']['tmp_name'], $dest_path)) {
        echo json_encode(['success' => false, 'message' => 'Could not save uploaded file.']);
        exit();
    }

    $result = restore_workbench_sql($dest_path, $safe_label, $actor);
    echo json_encode(['success' => $result['ok'], 'message' => $result['message']]);
    exit();
}

// ── delete ────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $filename = basename($_POST['file'] ?? '');
    $result   = delete_backup_file($filename, $actor);
    echo json_encode(['success' => $result['ok'], 'message' => $result['message']]);
    exit();
}

// ── Unknown action ────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
