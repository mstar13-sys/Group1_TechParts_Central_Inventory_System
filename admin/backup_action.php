<?php
// backup_action.php
// JSON endpoint for the Backup & Recovery page.
// Called by backup.js using fetch() for all backup-related actions.
//
// Actions (via ?action=...):
//   list    - GET  - returns a list of saved backup files
//   backup  - POST - creates a new database backup using mysqldump
//   restore - POST - restores from a saved backup file
//   import  - POST - restores from an uploaded .sql file (MySQL Workbench export)
//   delete  - POST - deletes a saved backup file

session_start();
header('Content-Type: application/json');

// only admins are allowed here
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Unauthorized.'));
    exit();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/backup_engine.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// try the different session keys that login.php might use for the username
$actor = isset($_SESSION['user_name'])  ? $_SESSION['user_name']
       : (isset($_SESSION['username']) ? $_SESSION['username']
       : (isset($_SESSION['name'])     ? $_SESSION['name'] : 'unknown'));


// ── list: no CSRF needed since it is read-only ──────────────────────────────
if ($action == 'list') {
    echo json_encode(array('success' => true, 'files' => list_backup_files()));
    exit();
}


// ── all other actions need a valid CSRF token ───────────────────────────────
$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Invalid security token.'));
    exit();
}


// ── backup ──────────────────────────────────────────────────────────────────
if ($action == 'backup') {
    $ok = take_database_backup('manual', $actor);
    echo json_encode(array(
        'success' => $ok,
        'message' => $ok
            ? 'Backup created successfully.'
            : 'Backup failed. Check XAMPP error log (php_error.log).'
    ));
    exit();
}


// ── restore: from a saved backup file ───────────────────────────────────────
if ($action == 'restore') {
    $filename = basename(isset($_POST['file']) ? $_POST['file'] : '');
    $result   = restore_database_backup($filename, $actor);
    echo json_encode(array(
        'success'  => $result['ok'],
        'message'  => $result['message'],
        'warnings' => isset($result['warnings']) ? $result['warnings'] : array()
    ));
    exit();
}


// ── import: from an uploaded .sql file (MySQL Workbench export compatible) ──
if ($action == 'import') {

    // check the upload actually arrived without errors
    if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {

        // map PHP upload error codes to readable messages
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing PHP temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.'
        );

        $err_code = isset($_FILES['sqlfile']['error']) ? $_FILES['sqlfile']['error'] : UPLOAD_ERR_NO_FILE;
        $err_msg  = isset($upload_errors[$err_code]) ? $upload_errors[$err_code] : 'Unknown upload error.';

        echo json_encode(array('success' => false, 'message' => $err_msg));
        exit();
    }

    // only accept .sql files
    $original_name = $_FILES['sqlfile']['name'];
    if (strtolower(pathinfo($original_name, PATHINFO_EXTENSION)) !== 'sql') {
        echo json_encode(array('success' => false, 'message' => 'Only .sql files are accepted.'));
        exit();
    }

    // peek at the first 256 bytes to confirm it looks like a SQL file
    $fh     = fopen($_FILES['sqlfile']['tmp_name'], 'r');
    $header = fread($fh, 256);
    fclose($fh);

    if (!preg_match('/^(--|\\/\\*|SET|CREATE|DROP|INSERT|USE)/i', ltrim($header))) {
        echo json_encode(array('success' => false, 'message' => 'File does not appear to be a valid SQL dump.'));
        exit();
    }

    // save the uploaded file into backups/ with a timestamped name
    $safe_label = 'import_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
    $dest_path  = BACKUP_DIR . $safe_label;

    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0750, true);

    if (!move_uploaded_file($_FILES['sqlfile']['tmp_name'], $dest_path)) {
        echo json_encode(array('success' => false, 'message' => 'Could not save uploaded file.'));
        exit();
    }

    $result = restore_workbench_sql($dest_path, $safe_label, $actor);
    echo json_encode(array('success' => $result['ok'], 'message' => $result['message']));
    exit();
}


// ── delete ───────────────────────────────────────────────────────────────────
if ($action == 'delete') {
    $filename = basename(isset($_POST['file']) ? $_POST['file'] : '');
    $result   = delete_backup_file($filename, $actor);
    echo json_encode(array('success' => $result['ok'], 'message' => $result['message']));
    exit();
}


// ── unknown action ───────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(array('success' => false, 'message' => 'Unknown action.'));
