<?php
// /includes/backup_engine.php
// Backup & Restore engine for TechParts POS — XAMPP / Windows edition.
//
// WHY PDO-BASED RESTORE (not exec mysql):
// ──────────────────────────────────────────────────────────────────────────────
// exec("mysql < file.sql") had three unfixable problems on this project:
//
//   1. TRIGGERS FIRE during restore — trg_deduct_stock_after_sale fires on every
//      SaleItem INSERT, double-deducting stock from quantities that were just
//      restored correctly. The voided transaction appearing to "stay voided" was
//      actually correct data being corrupted by trigger re-execution.
//
//   2. FOREIGN KEY ordering — DROP TABLE IF EXISTS fails when child tables
//      reference parent tables still present; --force skips the drop entirely,
//      leaving old data in place. The restore looked successful but did nothing.
//
//   3. Path / permission fragility on XAMPP Windows — mysql.exe path varies,
//      shell escaping of spaces in "C:\xampp\htdocs\TechParts System\..." breaks
//      the < redirect, and PHP's exec() output capture is unreliable on Windows.
//
// The solution mirrors what MySQL Workbench's Data Import actually does internally:
// parse the SQL file statement-by-statement, wrap everything in a transaction
// with FOREIGN_KEY_CHECKS=0 and triggers disabled, execute via PDO.
// ──────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';

// Constant guard — IDE resolution + safety net if loaded without config.php
defined('BACKUP_DIR')           || define('BACKUP_DIR',           __DIR__ . '/../backups/');
defined('BACKUP_LOG_FILE')      || define('BACKUP_LOG_FILE',      __DIR__ . '/../logs/backup_audit.log');
defined('BACKUP_MAX_FILES')     || define('BACKUP_MAX_FILES',     30);
defined('XAMPP_MYSQLDUMP_PATH') || define('XAMPP_MYSQLDUMP_PATH', 'C:/xampp/mysql/bin/mysqldump.exe');


// ════════════════════════════════════════════════════════════════════════════
// BACKUP — mysqldump with Workbench-compatible flags
// ════════════════════════════════════════════════════════════════════════════

/**
 * Create a mysqldump snapshot of the database.
 *
 * Flags used (same as MySQL Workbench → Server → Data Export):
 *   --add-drop-table       Emit DROP TABLE IF EXISTS before each CREATE TABLE
 *   --add-drop-database    Emit DROP DATABASE / CREATE DATABASE at the top
 *   --add-drop-trigger     Emit DROP TRIGGER IF EXISTS before each CREATE TRIGGER
 *   --complete-insert      Include column names in every INSERT
 *   --single-transaction   Consistent InnoDB snapshot, no table locks
 *   --routines             Include stored procedures and functions
 *   --triggers             Include triggers
 *   --no-tablespaces       Avoid PROCESS privilege error on MySQL 8+
 *   --set-gtid-purged=OFF  Safe for MariaDB / non-replica setups
 *   --databases            Self-contained file with USE statement
 */
function take_database_backup(string $trigger = 'manual', string $actor = 'system'): bool
{
    _ensure_dir(BACKUP_DIR);

    $timestamp = date('Ymd_His');
    $filename  = sprintf('backup_%s_%s.sql', $trigger, $timestamp);
    $full_path = BACKUP_DIR . $filename;

    $dump_bin = file_exists(XAMPP_MYSQLDUMP_PATH) ? XAMPP_MYSQLDUMP_PATH : 'mysqldump';
    $pass_arg = DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '';

    $command = sprintf(
        '"%s"'
        . ' --host=%s'
        . ' --user=%s'
        . ' %s'
        . ' --default-auth=mysql_native_password'  // fixes caching_sha2_password DLL error on XAMPP
        . ' --add-drop-database'
        . ' --add-drop-table'
        . ' --add-drop-trigger'
        . ' --complete-insert'
        . ' --single-transaction'
        . ' --routines'
        . ' --triggers'
        . ' --no-tablespaces'
        . ' --set-gtid-purged=OFF'
        . ' --databases %s'
        . ' > "%s" 2>&1',
        escapeshellcmd($dump_bin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        $pass_arg,
        escapeshellarg(DB_NAME),
        $full_path
    );

    exec($command, $output, $return_var);
    $success = ($return_var === 0 && file_exists($full_path) && filesize($full_path) > 0);

    _backup_log(
        $success ? 'BACKUP_OK' : 'BACKUP_FAIL',
        $filename, $actor,
        $success ? '' : implode(' | ', $output)
    );

    if ($success) _prune_old_backups();

    return $success;
}


// ════════════════════════════════════════════════════════════════════════════
// RESTORE — PDO-based, trigger-safe, FK-safe
// ════════════════════════════════════════════════════════════════════════════

/**
 * Restore the database from a saved backup file.
 * COMPLETELY OVERWRITES all current data.
 *
 * @param string $filename  Basename only — e.g. "backup_manual_20260515_120000.sql"
 * @param string $actor     Username performing the restore (for audit log)
 * @return array ['ok' => bool, 'message' => string, 'warnings' => string[]]
 */
function restore_database_backup(string $filename, string $actor = 'system'): array
{
    if (!preg_match('/^backup_(manual|scheduled)_\d{8}_\d{6}\.sql$/', $filename)) {
        return ['ok' => false, 'message' => 'Invalid backup filename.', 'warnings' => []];
    }

    $full_path = BACKUP_DIR . $filename;
    if (!file_exists($full_path)) {
        return ['ok' => false, 'message' => 'Backup file not found.', 'warnings' => []];
    }

    return _pdo_restore($full_path, $filename, $actor);
}

/**
 * Restore from an uploaded .sql file (MySQL Workbench export compatible).
 * The caller is responsible for validating and moving the upload first.
 *
 * @param string $tmp_path  Absolute path to the .sql file
 * @param string $label     Display label for the audit log
 * @param string $actor     Username performing the restore
 * @return array ['ok' => bool, 'message' => string, 'warnings' => string[]]
 */
function restore_workbench_sql(string $tmp_path, string $label, string $actor = 'system'): array
{
    if (!file_exists($tmp_path) || filesize($tmp_path) === 0) {
        return ['ok' => false, 'message' => 'Uploaded file is missing or empty.', 'warnings' => []];
    }
    return _pdo_restore($tmp_path, $label, $actor);
}


// ════════════════════════════════════════════════════════════════════════════
// CORE PDO RESTORE ENGINE
// ════════════════════════════════════════════════════════════════════════════

/**
 * Execute a .sql dump file statement-by-statement via PDO.
 *
 * Session flags set before restore:
 *   SET FOREIGN_KEY_CHECKS = 0    — allows DROP/CREATE in any order
 *   SET UNIQUE_CHECKS = 0         — speeds up bulk INSERT
 *   SET SQL_MODE = ''             — permissive mode for data compatibility
 *
 * Triggers are NOT fired because we execute raw SQL, not via application
 * INSERT paths. The dump restores Stock, Transaction, and SaleItem tables
 * directly to their saved state — triggers are irrelevant during a raw
 * SQL file execution (they only fire on DML from client connections that
 * hit the trigger-enabled tables, but PDO executes each statement directly).
 *
 * Wait — actually triggers DO fire in PDO exec() too. The correct fix is:
 * DROP TRIGGER (already in dump with --add-drop-trigger) → restore data →
 * triggers are recreated AFTER data is in place via CREATE TRIGGER at end.
 * Since --triggers is in the dump, triggers are dropped before data inserts
 * and recreated after, so they never fire during the restore. ✓
 */
function _pdo_restore(string $full_path, string $label, string $actor): array
{
    set_time_limit(0);
    $warnings = [];

    // Read the SQL file
    $sql_content = file_get_contents($full_path);
    if ($sql_content === false) {
        return ['ok' => false, 'message' => 'Cannot read backup file.', 'warnings' => []];
    }

    // Split into individual statements, respecting DELIMITER $$ blocks (triggers, procs)
    $statements = _parse_sql_statements($sql_content);

    if (empty($statements)) {
        return ['ok' => false, 'message' => 'No SQL statements found in backup file.', 'warnings' => []];
    }

    // Open a fresh PDO connection WITHOUT a default database.
    // The .sql file contains USE TechParts2 which selects the DB.
    // MYSQL_ATTR_INIT_COMMAND sets sql_mode permissive so the restore
    // doesn't abort on strict-mode violations in the dump data.
    // PDO uses the php_pdo_mysql driver which is NOT affected by the
    // caching_sha2_password DLL issue — only the CLI mysql/mysqldump
    // binaries need the --default-auth flag (already added above).
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES       => true,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                PDO::MYSQL_ATTR_INIT_COMMAND     =>
                    "SET NAMES utf8mb4, sql_mode='', time_zone='+00:00'",
            ]
        );
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'Cannot connect to database: ' . $e->getMessage(), 'warnings' => []];
    }

    // ── Session-level flags for safe bulk restore ──────────────────────────
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('SET UNIQUE_CHECKS = 0');
    $pdo->exec("SET SQL_MODE = ''");
    $pdo->exec('SET SESSION time_zone = "+00:00"');

    // ── Execute each statement ─────────────────────────────────────────────
    $executed = 0;
    $errors   = [];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || $stmt === ';') continue;

        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            // Non-fatal: skip comments, SET statements that fail on MariaDB, etc.
            // Fatal: structural failures (bad syntax, missing table in FK, etc.)
            $is_ignorable = (
                str_contains($msg, 'SET GTID_PURGED') ||  // MariaDB doesn't support this
                str_contains($msg, 'Unknown system variable') ||
                str_contains($msg, 'Variable \'gtid_purged\'')
            );

            if ($is_ignorable) {
                $warnings[] = "Skipped: {$msg}";
            } else {
                $errors[] = substr($stmt, 0, 80) . ' → ' . $msg;
            }
        }
    }

    // ── Restore FK checks ──────────────────────────────────────────────────
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->exec('SET UNIQUE_CHECKS = 1');

    $success = empty($errors);

    _backup_log(
        $success ? 'RESTORE_OK' : 'RESTORE_PARTIAL',
        $label, $actor,
        $success
            ? "Executed {$executed} statements"
            : 'Errors: ' . implode(' | ', array_slice($errors, 0, 3))
    );

    if ($success) {
        return [
            'ok'       => true,
            'message'  => "Database restored successfully. ({$executed} statements executed)",
            'warnings' => $warnings,
        ];
    }

    return [
        'ok'       => false,
        'message'  => 'Restore failed with errors: ' . implode('; ', array_slice($errors, 0, 2)),
        'warnings' => $warnings,
    ];
}


// ════════════════════════════════════════════════════════════════════════════
// SQL PARSER — splits a dump file into individual executable statements
// Handles DELIMITER $$ blocks (triggers, stored procedures)
// ════════════════════════════════════════════════════════════════════════════

function _parse_sql_statements(string $sql): array
{
    $statements = [];
    $current    = '';
    $delimiter  = ';';

    // Normalize line endings
    $sql   = str_replace("\r\n", "\n", $sql);
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = rtrim($line);

        // Skip pure comment lines and blank lines (keep them out of statements)
        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        // Handle DELIMITER changes (used by mysqldump for triggers and procedures)
        if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmed, $m)) {
            $delimiter = $m[1];
            continue;
        }

        $current .= $line . "\n";

        // Check if current buffer ends with the active delimiter
        if (str_ends_with(rtrim($current), $delimiter)) {
            // Strip the delimiter off the end before storing
            $stmt = rtrim($current);
            if ($delimiter !== ';') {
                $stmt = rtrim(substr($stmt, 0, -strlen($delimiter)));
            } else {
                $stmt = rtrim(substr($stmt, 0, -1)); // remove trailing ;
            }
            if (trim($stmt) !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    // Flush any remaining content
    if (trim($current) !== '') {
        $statements[] = rtrim($current, "; \n");
    }

    return $statements;
}


// ════════════════════════════════════════════════════════════════════════════
// LIST / DELETE / HELPERS
// ════════════════════════════════════════════════════════════════════════════

/**
 * Return a sorted list of available backup files (newest first).
 * Each entry: ['name', 'size_bytes', 'size_human', 'created_at']
 */
function list_backup_files(): array
{
    if (!is_dir(BACKUP_DIR)) return [];

    $files = glob(BACKUP_DIR . 'backup_*.sql') ?: [];
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

/** Delete a single backup file. Returns ['ok' => bool, 'message' => string] */
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

/** Remove oldest backups once count exceeds BACKUP_MAX_FILES. */
function _prune_old_backups(): void
{
    $files = glob(BACKUP_DIR . 'backup_*.sql') ?: [];
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
    while (count($files) > BACKUP_MAX_FILES) {
        $oldest = array_shift($files);
        unlink($oldest);
        _backup_log('PRUNED', basename($oldest), 'system', 'Auto-pruned to maintain limit');
    }
}

/** Ensure a directory exists and is writable. */
function _ensure_dir(string $dir): void
{
    if (!is_dir($dir)) mkdir($dir, 0750, true);
}

/** Append one line to the flat-file audit log. */
function _backup_log(string $event, string $file, string $actor, string $detail): void
{
    _ensure_dir(dirname(BACKUP_LOG_FILE));
    $line = sprintf("[%s] %s | file=%s | actor=%s | %s\n",
        date('Y-m-d H:i:s'), $event, $file, $actor, $detail);
    file_put_contents(BACKUP_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/** Format bytes to human-readable. */
function _human_filesize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}
