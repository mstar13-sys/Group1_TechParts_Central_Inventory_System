<?php
// backup_engine.php
// handles creating and restoring database backups for TechParts POS
// uses mysqldump to create backups and PDO to restore them

require_once __DIR__ . '/config.php';

// set folder paths if not already defined in config
if (!defined('BACKUP_DIR'))           define('BACKUP_DIR',           __DIR__ . '/../backups/');
if (!defined('BACKUP_LOG_FILE'))      define('BACKUP_LOG_FILE',      __DIR__ . '/../logs/backup_audit.log');
if (!defined('BACKUP_MAX_FILES'))     define('BACKUP_MAX_FILES',     30);
if (!defined('XAMPP_MYSQLDUMP_PATH')) define('XAMPP_MYSQLDUMP_PATH', 'C:/xampp/mysql/bin/mysqldump.exe');


// creates a backup of the database using mysqldump
function take_database_backup($trigger = 'manual', $actor = 'system') {

    makeFolder(BACKUP_DIR);

    $timestamp = date('Ymd_His');
    $filename  = 'backup_' . $trigger . '_' . $timestamp . '.sql';
    $full_path = BACKUP_DIR . $filename;

    // use the xampp mysqldump if it exists, otherwise fall back to system PATH
    $dump_bin = file_exists(XAMPP_MYSQLDUMP_PATH) ? XAMPP_MYSQLDUMP_PATH : 'mysqldump';

    // only add the password flag if a password is actually set
    $pass_arg = (DB_PASS != '') ? '--password=' . escapeshellarg(DB_PASS) : '';

    // note: --set-gtid-purged=OFF was removed because MariaDB does not support it
    $command = '"' . escapeshellcmd($dump_bin) . '"'
        . ' --host='   . escapeshellarg(DB_HOST)
        . ' --user='   . escapeshellarg(DB_USER)
        . ' '          . $pass_arg
        . ' --default-auth=mysql_native_password'
        . ' --add-drop-database'
        . ' --add-drop-table'
        . ' --add-drop-trigger'
        . ' --complete-insert'
        . ' --single-transaction'
        . ' --routines'
        . ' --triggers'
        . ' --no-tablespaces'
        . ' --databases ' . escapeshellarg(DB_NAME)
        . ' > "' . $full_path . '" 2>&1';

    exec($command, $output, $return_var);

    // success = exit code 0 AND the file exists AND is not empty
    $success = ($return_var === 0 && file_exists($full_path) && filesize($full_path) > 0);

    writeLog(
        $success ? 'BACKUP_OK' : 'BACKUP_FAIL',
        $filename,
        $actor,
        $success ? '' : implode(' | ', $output)
    );

    if ($success) removeOldBackups();

    return $success;
}


// restores the database from one of our saved backup files
function restore_database_backup($filename, $actor = 'system') {

    // validate filename pattern before doing anything
    if (!preg_match('/^backup_(manual|scheduled)_\d{8}_\d{6}\.sql$/', $filename)) {
        return array('ok' => false, 'message' => 'Invalid backup filename.', 'warnings' => array());
    }

    $full_path = BACKUP_DIR . $filename;

    if (!file_exists($full_path)) {
        return array('ok' => false, 'message' => 'Backup file not found.', 'warnings' => array());
    }

    return runPdoRestore($full_path, $filename, $actor);
}


// restores from an uploaded .sql file exported from MySQL Workbench
function restore_workbench_sql($tmp_path, $label, $actor = 'system') {

    if (!file_exists($tmp_path) || filesize($tmp_path) === 0) {
        return array('ok' => false, 'message' => 'Uploaded file is missing or empty.', 'warnings' => array());
    }

    return runPdoRestore($tmp_path, $label, $actor);
}


// the actual restore logic - reads the SQL file and runs each statement via PDO
// we use PDO instead of exec(mysql) because exec had problems with:
//   1. triggers firing during restore and corrupting stock counts
//   2. foreign key errors blocking DROP TABLE
//   3. path/permission issues with mysql.exe on Windows XAMPP
function runPdoRestore($full_path, $label, $actor) {

    set_time_limit(0); // large databases take a while

    $warnings = array();

    $sql_content = file_get_contents($full_path);
    if ($sql_content === false) {
        return array('ok' => false, 'message' => 'Cannot read backup file.', 'warnings' => array());
    }

    $statements = parseSqlStatements($sql_content);

    if (empty($statements)) {
        return array('ok' => false, 'message' => 'No SQL statements found in backup file.', 'warnings' => array());
    }

    // connect without a default database - the SQL file has a USE statement that picks it
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            array(
                PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES       => true,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                PDO::MYSQL_ATTR_INIT_COMMAND     => "SET NAMES utf8mb4, sql_mode='', time_zone='+00:00'"
            )
        );
    } catch (PDOException $e) {
        return array('ok' => false, 'message' => 'Cannot connect to database: ' . $e->getMessage(), 'warnings' => array());
    }

    // turn off FK checks so tables can drop and recreate in any order
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('SET UNIQUE_CHECKS = 0');
    $pdo->exec("SET SQL_MODE = ''");
    $pdo->exec('SET SESSION time_zone = "+00:00"');

    $executed = 0;
    $errors   = array();

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);

        if ($stmt == '' || $stmt == ';') continue;

        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();

            // MariaDB does not support GTID variables so we skip those errors
            $is_ignorable = (strpos($msg, 'SET GTID_PURGED') !== false
                          || strpos($msg, 'Unknown system variable') !== false
                          || strpos($msg, "Variable 'gtid_purged'") !== false);

            if ($is_ignorable) {
                $warnings[] = 'Skipped: ' . $msg;
            } else {
                $errors[] = substr($stmt, 0, 80) . ' -> ' . $msg;
            }
        }
    }

    // re-enable FK checks when done
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->exec('SET UNIQUE_CHECKS = 1');

    $success = empty($errors);

    writeLog(
        $success ? 'RESTORE_OK' : 'RESTORE_PARTIAL',
        $label,
        $actor,
        $success
            ? 'Executed ' . $executed . ' statements'
            : 'Errors: ' . implode(' | ', array_slice($errors, 0, 3))
    );

    if ($success) {
        return array(
            'ok'       => true,
            'message'  => 'Database restored successfully. (' . $executed . ' statements executed)',
            'warnings' => $warnings
        );
    }

    return array(
        'ok'       => false,
        'message'  => 'Restore failed with errors: ' . implode('; ', array_slice($errors, 0, 2)),
        'warnings' => $warnings
    );
}


// splits a full SQL dump file into individual statements
// handles DELIMITER $$ blocks used by mysqldump for triggers and stored procedures
function parseSqlStatements($sql) {

    $statements = array();
    $current    = '';
    $delimiter  = ';';

    // normalize Windows line endings
    $sql   = str_replace("\r\n", "\n", $sql);
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = rtrim($line);

        // skip blank lines and comment lines
        if ($trimmed == '' || substr($trimmed, 0, 2) == '--' || substr($trimmed, 0, 1) == '#') {
            continue;
        }

        // handle DELIMITER changes (mysqldump uses DELIMITER $$ for triggers)
        if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmed, $m)) {
            $delimiter = $m[1];
            continue;
        }

        $current .= $line . "\n";

        // check if the buffer ends with the current delimiter
        if (substr(rtrim($current), -strlen($delimiter)) === $delimiter) {

            $stmt = rtrim($current);

            // strip the delimiter off the end
            $stmt = ($delimiter != ';')
                ? rtrim(substr($stmt, 0, -strlen($delimiter)))
                : rtrim(substr($stmt, 0, -1));

            if (trim($stmt) != '') {
                $statements[] = $stmt;
            }

            $current = '';
        }
    }

    // flush anything left over at the end of the file
    if (trim($current) != '') {
        $statements[] = rtrim($current, "; \n");
    }

    return $statements;
}


// returns a list of saved backup files sorted newest first
function list_backup_files() {

    if (!is_dir(BACKUP_DIR)) return array();

    $files = glob(BACKUP_DIR . 'backup_*.sql');
    if (!$files) $files = array();

    // sort newest first
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $result = array();
    foreach ($files as $path) {
        $bytes    = filesize($path);
        $result[] = array(
            'name'       => basename($path),
            'size_bytes' => $bytes,
            'size_human' => humanFilesize($bytes),
            'created_at' => date('Y-m-d H:i:s', filemtime($path))
        );
    }

    return $result;
}


// deletes a single backup file
function delete_backup_file($filename, $actor = 'system') {

    if (!preg_match('/^backup_(manual|scheduled)_\d{8}_\d{6}\.sql$/', $filename)) {
        return array('ok' => false, 'message' => 'Invalid backup filename.');
    }

    $full_path = BACKUP_DIR . $filename;

    if (!file_exists($full_path)) {
        return array('ok' => false, 'message' => 'File not found.');
    }

    $ok = unlink($full_path);

    writeLog($ok ? 'DELETE_OK' : 'DELETE_FAIL', $filename, $actor, '');

    return array(
        'ok'      => $ok,
        'message' => $ok ? 'Backup deleted.' : 'Could not delete backup file.'
    );
}


// deletes the oldest backup files when we go over the max limit
function removeOldBackups() {

    $files = glob(BACKUP_DIR . 'backup_*.sql');
    if (!$files) $files = array();

    // sort oldest first so we remove from the front
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    while (count($files) > BACKUP_MAX_FILES) {
        $oldest = array_shift($files);
        unlink($oldest);
        writeLog('PRUNED', basename($oldest), 'system', 'Auto-pruned to maintain limit');
    }
}


// creates a folder if it does not already exist
function makeFolder($dir) {
    if (!is_dir($dir)) mkdir($dir, 0750, true);
}


// appends one line to the audit log
function writeLog($event, $file, $actor, $detail) {
    makeFolder(dirname(BACKUP_LOG_FILE));
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $event
          . ' | file='  . $file
          . ' | actor=' . $actor
          . ' | '       . $detail . "\n";
    file_put_contents(BACKUP_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}


// converts bytes into a readable string like 1.2 MB
function humanFilesize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}
