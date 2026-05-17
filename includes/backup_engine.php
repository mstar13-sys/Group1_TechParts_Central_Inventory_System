<?php
// /includes/backup_engine.php
// Core backup and restore engine for TechParts POS.
// Consumed by admin/backup_action.php — never called directly by the browser.

require_once __DIR__ . '/config.php';

// ── Constant guard ────────────────────────────────────────────────────────────
// Provides IDE type-resolution (eliminates "undefined constant" red underlines)
// and acts as a safety net if this file is ever loaded without config.php.
defined('BACKUP_DIR')           || define('BACKUP_DIR',           __DIR__ . '/../backups/');
defined('BACKUP_LOG_FILE')      || define('BACKUP_LOG_FILE',      __DIR__ . '/../logs/backup_audit.log');
defined('BACKUP_MAX_FILES')     || define('BACKUP_MAX_FILES',     30);
defined('XAMPP_MYSQLDUMP_PATH') || define('XAMPP_MYSQLDUMP_PATH', 'C:/xampp/mysql/bin/mysqldump.exe');
defined('XAMPP_MYSQL_PATH')     || define('XAMPP_MYSQL_PATH',     'C:/xampp/mysql/bin/mysql.exe');
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────
// Take a mysqldump snapshot and save it to BACKUP_DIR.
// $trigger : 'manual' | 'scheduled'
// $actor   : username of the person who triggered it (for the log)
// Returns  : true on success, false on failure.
// ─────────────────────────────────────────────
function take_database_backup(string $trigger = 'manual', string $actor = 'system'): bool
{
    // Ensure the backup directory exists and is writable
    if (!is_dir(BACKUP_DIR)) {
        if (!mkdir(BACKUP_DIR, 0750, true)) {
            error_log('[Backup] Cannot create BACKUP_DIR: ' . BACKUP_DIR);
            return false;
        }
    }

    $timestamp = date('Ymd_His');
    $filename  = sprintf('backup_%s_%s.sql', $trigger, $timestamp);
    $full_path = BACKUP_DIR . $filename;

    // Locate mysqldump — prefer the XAMPP path, fall back to PATH
    $dump_bin = file_exists(XAMPP_MYSQLDUMP_PATH) ? XAMPP_MYSQLDUMP_PATH : 'mysqldump';

    // Build the shell command safely
    $pass_arg = DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '';
    $command  = sprintf(
        '"%s" --host=%s --user=%s %s --single-transaction --routines --triggers %s > "%s" 2>&1',
        escapeshellcmd($dump_bin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        $pass_arg,
        escapeshellarg(DB_NAME),
        $full_path
    );

    exec($command, $output, $return_var);

    $success = ($return_var === 0 && file_exists($full_path) && filesize($full_path) > 0);

    // Audit log
    _backup_log($success ? 'BACKUP_OK' : 'BACKUP_FAIL', $filename, $actor, implode(' | ', $output));

    // Auto-prune: keep only the newest BACKUP_MAX_FILES files
    if ($success) {
        _prune_old_backups();
    }

    return $success;
}

// ─────────────────────────────────────────────
// Restore the database from a previously saved .sql file.
// $filename : basename only (e.g. "backup_manual_20260515_120000.sql")
// $actor    : username performing the restore
// Returns   : ['ok' => bool, 'message' => string]
// ─────────────────────────────────────────────
function restore_database_backup(string $filename, string $actor = 'system'): array
{
    set_time_limit(0); // Must be lifted at function entry — before any I/O or exec()

    // Strict filename validation — only allow our own naming convention
    if (!preg_match('/^backup_(manual|scheduled)_\d{8}_\d{6}\.sql$/', $filename)) {
        return ['ok' => false, 'message' => 'Invalid backup filename.'];
    }

    $full_path = BACKUP_DIR . $filename;
    if (!file_exists($full_path)) {
        return ['ok' => false, 'message' => 'Backup file not found.'];
    }

    // Locate mysql client — prefer XAMPP path, fall back to PATH
    $mysql_bin = file_exists(XAMPP_MYSQL_PATH) ? XAMPP_MYSQL_PATH : 'mysql';

    $pass_arg = DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '';
    $command  = sprintf(
        '"%s" --host=%s --user=%s %s %s < "%s" 2>&1',
        escapeshellcmd($mysql_bin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        $pass_arg,
        escapeshellarg(DB_NAME),
        $full_path
    );

    exec($command, $output, $return_var);

    $success = ($return_var === 0);
    $detail  = implode(' | ', $output);

    _backup_log($success ? 'RESTORE_OK' : 'RESTORE_FAIL', $filename, $actor, $detail);

    return [
        'ok'      => $success,
        'message' => $success ? 'Database restored successfully.' : 'Restore failed: ' . $detail,
    ];
}

// ─────────────────────────────────────────────
// Return a sorted list of available backup files (newest first).
// Each entry: ['name', 'size_bytes', 'size_human', 'created_at']
// ─────────────────────────────────────────────
function list_backup_files(): array
{
    if (!is_dir(BACKUP_DIR)) {
        return [];
    }

    $files = glob(BACKUP_DIR . 'backup_*.sql') ?: [];

    // Sort newest → oldest
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

    return array_map(function ($path) {
        $bytes = filesize($path);
        return [
            'name'       => basename($path),
            'size_bytes' => $bytes,
            'size_human' => _human_filesize($bytes),
            'created_at' => date('Y-m-d H:i:s', filemtime($path)),
        ];
    }, $files);
}

// ─────────────────────────────────────────────
// Delete a single backup file by basename.
// Returns ['ok' => bool, 'message' => string]
// ─────────────────────────────────────────────
function delete_backup_file(string $filename, string $actor = 'system'): array
{
    if (!preg_match('/^backup_(manual|scheduled)_\d{8}_\d{6}\.sql$/', $filename)) {
        return ['ok' => false, 'message' => 'Invalid backup filename.'];
    }

    $full_path = BACKUP_DIR . $filename;
    if (!file_exists($full_path)) {
        return ['ok' => false, 'message' => 'File not found.'];
    }

    $ok = unlink($full_path);
    _backup_log($ok ? 'DELETE_OK' : 'DELETE_FAIL', $filename, $actor, '');

    return ['ok' => $ok, 'message' => $ok ? 'Backup deleted.' : 'Could not delete backup file.'];
}

// ─────────────────────────────────────────────
// Internal helpers
// ─────────────────────────────────────────────

/** Remove oldest backups once count exceeds BACKUP_MAX_FILES. */
function _prune_old_backups(): void
{
    $files = glob(BACKUP_DIR . 'backup_*.sql') ?: [];
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b)); // oldest first

    while (count($files) > BACKUP_MAX_FILES) {
        $oldest = array_shift($files);
        unlink($oldest);
        _backup_log('PRUNED', basename($oldest), 'system', 'Auto-pruned to maintain limit');
    }
}

/** Append a single line to the flat-file audit log. */
function _backup_log(string $event, string $file, string $actor, string $detail): void
{
    $line = sprintf(
        "[%s] %s | file=%s | actor=%s | %s\n",
        date('Y-m-d H:i:s'),
        $event,
        $file,
        $actor,
        $detail
    );
    file_put_contents(BACKUP_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/** Format bytes to a human-readable string. */
function _human_filesize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}
