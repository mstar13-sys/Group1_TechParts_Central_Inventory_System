<?php
// /admin/backup_action.php
// JSON endpoint consumed exclusively by backup.js via fetch().
// All state-changing actions require a valid CSRF token.

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
$actor  = $_SESSION['username'] ?? 'unknown';

// ── list: return all backup files (read-only, no CSRF needed) ─────────────────
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

// ── backup: create a new manual snapshot ─────────────────────────────────────
if ($action === 'backup') {
    $ok = take_database_backup('manual', $actor);
    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Backup created successfully.' : 'Backup failed. Check server error log.',
    ]);
    exit();
}

// ── restore: load a chosen .sql file back into the database ──────────────────
if ($action === 'restore') {
    $filename = basename($_POST['file'] ?? '');
    $result   = restore_database_backup($filename, $actor);
    echo json_encode(['success' => $result['ok'], 'message' => $result['message']]);
    exit();
}

// ── delete: permanently remove a backup file ─────────────────────────────────
if ($action === 'delete') {
    $filename = basename($_POST['file'] ?? '');
    $result   = delete_backup_file($filename, $actor);
    echo json_encode(['success' => $result['ok'], 'message' => $result['message']]);
    exit();
}

// ── Unknown action ────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
