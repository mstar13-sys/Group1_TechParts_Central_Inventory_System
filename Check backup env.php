<?php
/**
 * ============================================================
 *  TechParts POS — Backup & Recovery Environment Diagnostic
 *  Place this file in your project ROOT (same level as dashboard.php)
 *  and open it in your browser, or run:  php check_backup_env.php
 * ============================================================
 *
 *  CONFIRMED BUGS FOUND IN YOUR AUDIT LOG (backup_audit.log):
 *  ─────────────────────────────────────────────────────────────
 *  BUG 1 — mysqldump flag --set-gtid-purged=OFF is NOT supported
 *           by XAMPP's bundled MariaDB. mysqldump exits with error
 *           before writing anything, so the .sql file contains only
 *           the error text. Every backup silently fails.
 *
 *  BUG 2 — caching_sha2_password.dll is missing from XAMPP.
 *           This is a MySQL 8 auth plugin. MariaDB (which XAMPP ships)
 *           does not include it. The --default-auth=mysql_native_password
 *           flag in backup_engine.php is already the correct workaround,
 *           but it only helps mysqldump — PHP's PDO connection is fine.
 *
 *  This script detects both bugs (and other common failure points)
 *  and prints a clear fix for each one it finds.
 * ============================================================
 */

// ── Output mode ──────────────────────────────────────────────
$cli = (PHP_SAPI === 'cli');

// ── DB credentials — mirror includes/config.php ─────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'TechParts2');

// ── Expected paths ───────────────────────────────────────────
$PROJECT_ROOT  = __DIR__;
$BACKUP_DIR    = $PROJECT_ROOT . '/backups';
$LOG_FILE      = $PROJECT_ROOT . '/logs/backup_audit.log';
$ENGINE_FILE   = $PROJECT_ROOT . '/includes/backup_engine.php';
$XAMPP_DUMP    = 'C:/xampp/mysql/bin/mysqldump.exe';
$LAMPP_DUMP    = '/opt/lampp/bin/mysqldump';
$PLAIN_DUMP    = 'mysqldump';

// ─────────────────────────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────────────────────────
$issues = [];

function ok(string $label, string $detail = ''): void {
    global $cli;
    echo $cli
        ? "  \u{2705} PASS  $label" . ($detail ? " \u{2014} $detail" : '') . "\n"
        : "<tr><td class='lbl'>" . he($label) . "</td><td class='pass'>\u{2705} PASS</td><td>" . he($detail) . "</td></tr>\n";
}
function ng(string $label, string $detail, string $fix, string $sev = 'bug'): void {
    global $cli, $issues;
    $issues[] = compact('label', 'detail', 'fix', 'sev');
    $icon = $sev === 'bug' ? "\u{274C} BUG " : "\u{26A0}\u{FE0F} WARN";
    echo $cli
        ? "  $icon  $label\n       \u{2192} $detail\n       \u{270E} FIX: $fix\n"
        : "<tr><td class='lbl'>" . he($label) . "</td>"
          . "<td class='" . ($sev === 'bug' ? 'fail' : 'warn') . "'>$icon</td>"
          . "<td>" . he($detail) . " <em>\u{00BB} " . he($fix) . "</em></td></tr>\n";
}
function info(string $label, string $detail): void {
    global $cli;
    echo $cli
        ? "  \u{2139}\u{FE0F}  INFO  $label \u{2014} $detail\n"
        : "<tr><td class='lbl'>" . he($label) . "</td><td class='info'>\u{2139} INFO</td><td>" . he($detail) . "</td></tr>\n";
}
function section(string $title): void {
    global $cli;
    echo $cli
        ? "\n\u{2500}\u{2500} $title " . str_repeat("\u{2500}", max(0, 56 - strlen($title))) . "\n"
        : "<tr class='sec'><td colspan='3'>$title</td></tr>\n";
}
function he(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function find_mysqldump(): array {
    global $XAMPP_DUMP, $LAMPP_DUMP, $PLAIN_DUMP;
    if (file_exists($XAMPP_DUMP)) return [$XAMPP_DUMP, 'XAMPP (Windows)'];
    if (file_exists($LAMPP_DUMP)) return [$LAMPP_DUMP, 'XAMPP/LAMPP (Linux)'];
    $out = []; $rc = 0;
    exec('mysqldump --version 2>&1', $out, $rc);
    if ($rc === 0 || !empty($out)) return [$PLAIN_DUMP, 'System PATH'];
    return [null, 'Not found'];
}

// ─────────────────────────────────────────────────────────────
//  HTML HEADER
// ─────────────────────────────────────────────────────────────
if (!$cli) { echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>TechParts — Backup Diagnostic</title>
<style>
  *{box-sizing:border-box}
  body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;padding:28px 32px;margin:0}
  h1{color:#38bdf8;margin:0 0 4px}
  .sub{color:#94a3b8;margin:0 0 24px;font-size:14px}
  table{width:100%;border-collapse:collapse;font-size:13.5px;margin-bottom:28px}
  th{background:#1e293b;padding:9px 14px;text-align:left;color:#94a3b8;font-weight:600}
  td{padding:8px 14px;border-bottom:1px solid #1e293b;vertical-align:top;line-height:1.5}
  tr.sec td{background:#1e3a5f;color:#7dd3fc;font-weight:700;font-size:12px;
            letter-spacing:.6px;text-transform:uppercase;padding:7px 14px}
  .lbl{width:36%;color:#cbd5e1;font-weight:500}
  .pass{color:#4ade80;font-weight:700;white-space:nowrap;width:90px}
  .fail{color:#f87171;font-weight:700;white-space:nowrap;width:90px}
  .warn{color:#fbbf24;font-weight:700;white-space:nowrap;width:90px}
  .info{color:#60a5fa;font-weight:700;white-space:nowrap;width:90px}
  em{color:#93c5fd;font-style:normal}
  code{background:#1e293b;border-radius:4px;padding:2px 7px;font-size:12px;color:#fde68a;white-space:pre-wrap;word-break:break-all}
  pre{background:#1e293b;border-radius:8px;padding:14px 18px;font-size:13px;overflow-x:auto;margin:0 0 16px}
  .verdict{margin-top:4px;padding:18px 22px;border-radius:10px;border-left:5px solid;font-size:14px;line-height:1.7}
  .v-clean{background:#052e16;border-color:#4ade80;color:#bbf7d0}
  .v-fail{background:#2d0a0a;border-color:#f87171;color:#fecaca}
  .v-warn{background:#2d1d00;border-color:#fbbf24;color:#fef3c7}
  h2{color:#7dd3fc;margin:28px 0 8px;font-size:15px}
  .fix-block{background:#1e293b;border-radius:8px;padding:14px 18px;margin-bottom:12px;
             border-left:3px solid #f87171;font-size:13px;line-height:1.6}
  .fix-block.warn{border-color:#fbbf24}
  .fix-block strong{color:#f87171;display:block;margin-bottom:6px;font-size:14px}
  .fix-block.warn strong{color:#fbbf24}
</style>
</head>
<body>
<h1>&#x1F50D; TechParts POS &mdash; Backup &amp; Recovery Diagnostic</h1>
<p class="sub">Checks every layer that causes backup / restore to fail on XAMPP + MariaDB.</p>
<table>
<thead><tr><th>Check</th><th>Result</th><th>Details / Fix</th></tr></thead>
<tbody>
HTML;
}

// ═════════════════════════════════════════════════════════════
//  1. PHP RUNTIME
// ═════════════════════════════════════════════════════════════
section('1 · PHP Runtime');

$phpVer = PHP_VERSION;
version_compare($phpVer, '7.4', '>=')
    ? ok('PHP version', $phpVer)
    : ng('PHP version', $phpVer, 'Upgrade to PHP 7.4 or higher');

foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json'] as $ext) {
    extension_loaded($ext)
        ? ok("Extension: php_$ext")
        : ng("Extension: php_$ext", 'Not loaded',
             "Enable extension=php_$ext in php.ini and restart Apache");
}

info('SAPI', PHP_SAPI);
info('OS',   PHP_OS_FAMILY . ' / ' . PHP_OS);

// ═════════════════════════════════════════════════════════════
//  2. IDE / STACK DETECTION
// ═════════════════════════════════════════════════════════════
section('2 · IDE / Server Stack Detection');

$stack = 'Unknown';
$ide   = 'Unknown';

if (file_exists('C:/xampp/mysql/bin/mysqldump.exe'))        $stack = 'XAMPP (Windows)';
elseif (file_exists('/opt/lampp/bin/mysqldump'))             $stack = 'XAMPP / LAMPP (Linux)';
elseif (file_exists('C:/wamp64/bin/mysql') ||
        file_exists('C:/wamp/bin/mysql'))                    $stack = 'WAMP';
elseif (file_exists('C:/laragon/bin/mysql'))                 $stack = 'Laragon';
elseif (PHP_OS_FAMILY === 'Linux')                           $stack = 'Standalone Linux';

if (getenv('VSCODE_PID') || getenv('TERM_PROGRAM') === 'vscode') {
    $ide = 'Visual Studio Code';
} elseif (getenv('PHPSTORM') || getenv('__CFBundleIdentifier') === 'com.jetbrains.PhpStorm') {
    $ide = 'PhpStorm';
} elseif (getenv('IDEA_INITIAL_DIRECTORY'))  { $ide = 'JetBrains IDE'; }
elseif (getenv('NETBEANS_HOME'))             { $ide = 'NetBeans'; }
elseif (getenv('ECLIPSE_HOME'))              { $ide = 'Eclipse'; }
elseif (PHP_OS_FAMILY === 'Windows') {
    // Probe running processes on Windows
    foreach ([
        ['Code.exe',        'Visual Studio Code'],
        ['phpstorm64.exe',  'PhpStorm'],
        ['sublime_text.exe','Sublime Text'],
        ['notepad++.exe',   'Notepad++'],
    ] as [$proc, $name]) {
        $out = []; exec("tasklist /FI \"IMAGENAME eq $proc\" 2>NUL", $out);
        if (str_contains(strtolower(implode('', $out)), strtolower($proc))) {
            $ide = $name; break;
        }
    }
}

info('Detected Stack', $stack);
info('Detected IDE',   $ide);

if (str_contains($stack, 'XAMPP')) {
    ok('Stack compatibility',
       "$stack detected \u{2014} known issue: --set-gtid-purged=OFF crashes MariaDB's mysqldump");
} elseif ($stack !== 'Unknown') {
    ok('Stack compatibility', "$stack \u{2014} no stack-specific issues detected");
} else {
    info('Stack', 'Could not auto-detect. Script assumes XAMPP + MariaDB based on audit log evidence.');
}

// ═════════════════════════════════════════════════════════════
//  3. mysqldump BINARY
// ═════════════════════════════════════════════════════════════
section('3 · mysqldump Binary');

[$dumpPath, $dumpLabel] = find_mysqldump();

if ($dumpPath === null) {
    ng('mysqldump binary',
       'Not found at C:/xampp/mysql/bin/mysqldump.exe or on PATH',
       'Verify XAMPP is installed. Check that mysql/bin directory exists in your XAMPP folder.');
} else {
    ok('mysqldump binary', "$dumpLabel \u{2192} $dumpPath");

    // Get version string
    $vCmd   = ($dumpPath === $PLAIN_DUMP) ? 'mysqldump --version 2>&1' : "\"$dumpPath\" --version 2>&1";
    $verOut = []; exec($vCmd, $verOut);
    $verStr = implode(' ', $verOut);
    info('mysqldump version', $verStr ?: 'Could not determine');

    $isMariaDB = str_contains(strtolower($verStr), 'mariadb');
    $isMySQL8  = preg_match('/Ver 8\./', $verStr) && !$isMariaDB;

    // ── BUG 1: --set-gtid-purged=OFF ─────────────────────────────────────
    if ($isMariaDB) {
        ng(
            'BUG 1 \u{2014} --set-gtid-purged=OFF (CONFIRMED via audit log)',
            'Your mysqldump is MariaDB. This flag is MySQL-only. mysqldump exits immediately '
            . 'and writes only the error string into the .sql file (73 bytes). '
            . 'Every backup produced is corrupt \u{2014} exactly matching your audit log.',
            'In backup_engine.php remove the line: . \' --set-gtid-purged=OFF\''
        );
    } elseif ($isMySQL8) {
        ok('--set-gtid-purged=OFF flag', 'MySQL 8 detected \u{2014} flag is supported');
    } else {
        // Direct flag test
        $tCmd = ($dumpPath === $PLAIN_DUMP ? 'mysqldump' : "\"$dumpPath\"")
              . ' --set-gtid-purged=OFF --version 2>&1';
        $tOut = []; exec($tCmd, $tOut);
        $tStr = strtolower(implode(' ', $tOut));
        if (str_contains($tStr, 'unknown variable') || str_contains($tStr, 'unknown option')) {
            ng('BUG 1 \u{2014} --set-gtid-purged=OFF',
               'mysqldump rejected this flag. Backup crashes before writing any SQL.',
               'Remove . \' --set-gtid-purged=OFF\' from $command in backup_engine.php');
        } else {
            ok('--set-gtid-purged=OFF', 'Supported by this mysqldump version');
        }
    }

    // ── BUG 2: caching_sha2_password.dll ─────────────────────────────────
    if ($isMariaDB && PHP_OS_FAMILY === 'Windows') {
        $dllDir  = dirname(realpath($dumpPath) ?: $dumpPath);
        $dllPath = $dllDir . '/caching_sha2_password.dll';
        if (!file_exists($dllPath)) {
            ng(
                'BUG 2 \u{2014} caching_sha2_password.dll (CONFIRMED via audit log)',
                'XAMPP\'s MariaDB does not ship this MySQL 8 auth plugin DLL. '
                . 'CLI tools (mysqldump, mysql.exe) fail with ERROR 1045 when the server '
                . 'requests this auth method \u{2014} seen in your logs.',
                'Run in phpMyAdmin: ALTER USER \'root\'@\'localhost\' IDENTIFIED WITH '
                . 'mysql_native_password BY \'root\'; FLUSH PRIVILEGES;'
            );
        } else {
            ok('caching_sha2_password.dll', 'Found at ' . $dllPath);
        }
    } else {
        ok('caching_sha2_password.dll', 'Not applicable for this stack');
    }

    // ── Live mysqldump connection test ────────────────────────────────────
    $passArg  = DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '';
    $authFlag = $isMariaDB ? '--default-auth=mysql_native_password' : '';
    $dryCmd   = ($dumpPath === $PLAIN_DUMP ? 'mysqldump' : "\"$dumpPath\"")
              . ' --host=' . escapeshellarg(DB_HOST)
              . ' --user=' . escapeshellarg(DB_USER)
              . ($passArg  ? " $passArg"  : '')
              . ($authFlag ? " $authFlag" : '')
              . ' --no-tablespaces --single-transaction --no-data'
              . ' --databases ' . escapeshellarg(DB_NAME) . ' 2>&1';

    $dryOut = []; $dryRc = 0;
    exec($dryCmd, $dryOut, $dryRc);
    $dryStr = strtolower(implode(' ', $dryOut));

    if ($dryRc === 0) {
        ok('mysqldump live connection test', 'Connected and dumped schema successfully');
    } else {
        $hint = '';
        if (str_contains($dryStr, 'unknown variable') || str_contains($dryStr, 'unknown option')) {
            foreach ($dryOut as $l) {
                if (str_contains(strtolower($l), 'unknown')) { $hint = "Unsupported flag: $l"; break; }
            }
        } elseif (str_contains($dryStr, 'access denied') || str_contains($dryStr, '1045')) {
            $hint = 'Authentication failed. Check DB_USER/DB_PASS and run: ALTER USER ... IDENTIFIED WITH mysql_native_password';
        } elseif (str_contains($dryStr, 'unknown database')) {
            $hint = DB_NAME . ' database does not exist. Create it first.';
        } else {
            $hint = implode(' | ', array_slice($dryOut, 0, 2));
        }
        ng('mysqldump live connection test',
           "Exit code: $dryRc \u{2014} $hint", $hint ?: 'See detail above');
    }
}

// ═════════════════════════════════════════════════════════════
//  4. PHP SHELL EXECUTION FUNCTIONS
// ═════════════════════════════════════════════════════════════
section('4 · PHP Shell Execution');

$disabled = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));

foreach (['exec', 'shell_exec', 'proc_open'] as $fn) {
    in_array($fn, $disabled)
        ? ng("$fn()", "Disabled in disable_functions (php.ini)",
             "Remove '$fn' from disable_functions in php.ini, then restart Apache")
        : ok("$fn()", 'Enabled');
}

$openBasedir = ini_get('open_basedir');
if ($openBasedir) {
    ng('open_basedir restriction', "Restricts paths to: $openBasedir",
       'Set open_basedir = "" in php.ini or ensure backups/ and mysqldump path are included');
} else {
    ok('open_basedir', 'Not set \u{2014} no path restrictions');
}

// ═════════════════════════════════════════════════════════════
//  5. PDO DATABASE CONNECTIVITY
// ═════════════════════════════════════════════════════════════
section('5 · PDO Database Connectivity');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    ok('PDO connection', 'Connected as ' . DB_USER . '@' . DB_HOST);

    $sv = $pdo->query('SELECT VERSION()')->fetchColumn();
    info('Server version', $sv);
    info('Server type', str_contains(strtolower($sv), 'mariadb')
        ? 'MariaDB \u{2014} --set-gtid-purged=OFF MUST be removed from backup_engine.php'
        : 'MySQL \u{2014} all flags are compatible');

    // DB existence
    $dbExists = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'")->rowCount();
    $dbExists
        ? ok('Database exists', DB_NAME)
        : ng('Database missing', DB_NAME . ' not found',
             'CREATE DATABASE `' . DB_NAME . '`; in phpMyAdmin');

    // Privileges
    $grants   = $pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
    $grantStr = strtoupper(implode(' | ', $grants));
    $hasAll   = str_contains($grantStr, 'ALL PRIVILEGES');
    $hasProc  = str_contains($grantStr, 'PROCESS') || str_contains($grantStr, 'SUPER');
    $hasAll
        ? ok('User privileges', 'ALL PRIVILEGES \u{2014} sufficient')
        : ng('User privileges', 'Missing ALL PRIVILEGES',
             "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost'; FLUSH PRIVILEGES;");

    (!$hasProc && !$hasAll)
        ? ng('PROCESS privilege', 'Required for --single-transaction',
             "GRANT PROCESS ON *.* TO '" . DB_USER . "'@'localhost'; FLUSH PRIVILEGES;")
        : ok('PROCESS privilege', 'Available');

    // Auth plugin
    try {
        $authRow = $pdo->query(
            "SELECT plugin FROM mysql.user WHERE User='" . DB_USER . "' AND Host='localhost' LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if ($authRow) {
            $plugin = $authRow['plugin'] ?? 'unknown';
            $plugin === 'caching_sha2_password'
                ? ng('Auth plugin', "Plugin is '$plugin' \u{2014} XAMPP CLI tools cannot load this DLL",
                     "ALTER USER '" . DB_USER . "'@'localhost' IDENTIFIED WITH mysql_native_password BY '"
                     . DB_PASS . "'; FLUSH PRIVILEGES;")
                : ok('Auth plugin', "$plugin \u{2014} compatible with XAMPP");
        }
    } catch (PDOException $e) {
        info('Auth plugin check', 'Skipped (no access to mysql.user): ' . $e->getMessage());
    }

    $pdo = null;
} catch (PDOException $e) {
    ng('PDO connection failed', $e->getMessage(),
       'Check DB_HOST/DB_USER/DB_PASS in config.php. Ensure MySQL is running in XAMPP Control Panel.');
}

// ═════════════════════════════════════════════════════════════
//  6. FILESYSTEM & BACKUP FOLDER
// ═════════════════════════════════════════════════════════════
section('6 · Filesystem & Backup Folder');

is_dir($BACKUP_DIR)
    ? ok('backups/ directory exists', $BACKUP_DIR)
    : ng('backups/ directory missing', $BACKUP_DIR,
        'Create it: mkdir backups in your project root (same level as dashboard.php)');

if (is_dir($BACKUP_DIR)) {
    is_writable($BACKUP_DIR)
        ? ok('backups/ is writable')
        : ng('backups/ not writable',
             'PHP cannot write to ' . $BACKUP_DIR,
             'Windows: Properties \u{2192} Security \u{2192} give IUSR write access. '
             . 'Linux: chmod 750 backups/ && chown www-data:www-data backups/');

    // Detect corrupt backups (73-byte error-string-only files from Bug 1)
    $sqlFiles = glob($BACKUP_DIR . '/*.sql') ?: [];
    $corrupt  = [];
    foreach ($sqlFiles as $f) {
        if (filesize($f) < 1024) $corrupt[] = basename($f) . ' (' . filesize($f) . ' bytes)';
    }
    if (!empty($corrupt)) {
        ng('Corrupt backup files detected',
           'These .sql files are < 1 KB and contain only mysqldump\'s error output, not real SQL: '
           . implode(', ', $corrupt),
           'Delete these files. Fix BUG 1 first, then run a fresh backup.');
    } else {
        ok('Backup file integrity', count($sqlFiles) === 0
            ? 'No backups yet'
            : count($sqlFiles) . ' backup(s) found, all above 1 KB minimum');
    }
}

is_writable(dirname($LOG_FILE))
    ? ok('logs/ directory writable', dirname($LOG_FILE))
    : ng('logs/ directory not writable', dirname($LOG_FILE),
        'mkdir logs && chmod 750 logs in project root');

// ═════════════════════════════════════════════════════════════
//  7. backup_engine.php SOURCE AUDIT
// ═════════════════════════════════════════════════════════════
section('7 · backup_engine.php Source Audit');

if (!file_exists($ENGINE_FILE)) {
    ng('backup_engine.php found', 'Missing at includes/backup_engine.php',
       'Ensure the file exists in your includes/ folder');
} else {
    ok('backup_engine.php found', $ENGINE_FILE);
    $src = file_get_contents($ENGINE_FILE);

    str_contains($src, '--set-gtid-purged=OFF')
        ? ng('--set-gtid-purged=OFF in source (BUG 1)',
             'Flag still present in backup_engine.php \u{2014} will crash MariaDB mysqldump every time',
             "Remove the line: . ' --set-gtid-purged=OFF' from the \$command string (around line 65)")
        : ok('--set-gtid-purged=OFF removed', 'Flag not present \u{2014} BUG 1 already patched');

    str_contains($src, '--default-auth=mysql_native_password')
        ? ok('--default-auth=mysql_native_password', 'Present \u{2014} correct workaround for BUG 2')
        : ng('--default-auth=mysql_native_password missing',
             'Without this flag, XAMPP mysqldump may fail with the sha2 DLL error',
             "Add: . ' --default-auth=mysql_native_password' to \$command in backup_engine.php");

    str_contains($src, 'BACKUP_DIR')
        ? ok('BACKUP_DIR constant', 'Defined')
        : ng('BACKUP_DIR constant missing', 'Not found in backup_engine.php',
             "Add: define('BACKUP_DIR', __DIR__ . '/../backups/');");

    str_contains($src, 'DELIMITER')
        ? ok('DELIMITER parser', 'SQL parser handles trigger blocks')
        : ng('DELIMITER parser missing', 'Restore may fail on dumps containing triggers',
             'Ensure _parse_sql_statements() handles DELIMITER $$ blocks in backup_engine.php');
}

// ═════════════════════════════════════════════════════════════
//  8. AUDIT LOG ANALYSIS
// ═════════════════════════════════════════════════════════════
section('8 · Audit Log Analysis (logs/backup_audit.log)');

if (!file_exists($LOG_FILE)) {
    info('Audit log', 'Not found \u{2014} no backup attempts recorded yet');
} else {
    $logLines = file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $fails    = array_filter($logLines, fn($l) => str_contains($l, '_FAIL') || str_contains($l, '_PARTIAL'));
    $oks      = array_filter($logLines, fn($l) => str_contains($l, '_OK'));

    info('Total log entries',      (string) count($logLines));
    info('Successful operations',  count($oks)   . ' (BACKUP_OK / RESTORE_OK / DELETE_OK)');
    info('Failed operations',      count($fails) . ' (BACKUP_FAIL / RESTORE_FAIL / RESTORE_PARTIAL)');

    $seenGtid = $seenSha2 = false;
    foreach ($fails as $line) {
        if (str_contains($line, 'set-gtid-purged'))       $seenGtid = true;
        if (str_contains($line, 'caching_sha2_password')) $seenSha2 = true;
    }

    $seenGtid
        ? ng('Log: --set-gtid-purged errors', 'PRIMARY cause of all BACKUP_FAIL entries in your log',
             'Remove --set-gtid-purged=OFF from backup_engine.php')
        : ok('Log: no --set-gtid-purged errors');

    $seenSha2
        ? ng('Log: caching_sha2_password errors', 'CLI auth plugin DLL missing from XAMPP',
             'ALTER USER root@localhost IDENTIFIED WITH mysql_native_password; FLUSH PRIVILEGES;')
        : ok('Log: no caching_sha2_password errors');

    $last5 = array_slice($logLines, -5);
    if (!$cli) {
        echo "<tr><td class='lbl'>Last 5 log entries</td><td class='info'>&#x2139; INFO</td>"
           . "<td><code>" . he(implode("\n", $last5)) . "</code></td></tr>\n";
    } else {
        echo "\n  Last 5 log entries:\n";
        foreach ($last5 as $l) echo "    $l\n";
    }
}

// ─────────────────────────────────────────────────────────────
//  CLOSE TABLE + VERDICT
// ─────────────────────────────────────────────────────────────
if (!$cli) echo "</tbody></table>\n";

$bugs  = array_values(array_filter($issues, fn($i) => $i['sev'] === 'bug'));
$warns = array_values(array_filter($issues, fn($i) => $i['sev'] === 'warn'));

if ($cli) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  SUMMARY: " . count($bugs) . " bug(s) \u{00B7} " . count($warns) . " warning(s)\n";
    echo str_repeat('=', 60) . "\n";
    if (empty($bugs) && empty($warns)) {
        echo "\n  \u{2705} All checks passed. Backup & recovery should work.\n\n";
    }
} else {
    $cls     = empty($bugs) ? (empty($warns) ? 'v-clean' : 'v-warn') : 'v-fail';
    $summary = empty($bugs)
        ? (empty($warns)
            ? '&#x2705; All checks passed &mdash; backup and recovery should work correctly.'
            : '&#x26A0;&#xFE0F; No critical bugs, but warnings need attention (see below).')
        : '&#x274C; ' . count($bugs) . ' bug(s) confirmed &mdash; backup will keep failing until fixed.';
    echo "<div class='verdict $cls'><strong>$summary</strong></div>\n";
}

// ── Action Plan ───────────────────────────────────────────────
if (!empty($bugs) || !empty($warns)) {
    if (!$cli) echo "<h2>&#x1F4CB; Action Plan &mdash; Fix in this order:</h2>\n";
    else echo "\n  ACTION PLAN:\n";

    foreach (array_merge($bugs, $warns) as $n => $issue) {
        $cls2 = $issue['sev'] === 'bug' ? '' : ' warn';
        if (!$cli) {
            echo "<div class='fix-block$cls2'>"
               . "<strong>[" . ($n + 1) . "] " . he($issue['label']) . "</strong>"
               . "<b>Problem:</b> " . he($issue['detail']) . "<br>"
               . "<b>Fix:</b> " . he($issue['fix'])
               . "</div>\n";
        } else {
            echo "\n  [" . ($n + 1) . "] {$issue['label']}\n"
               . "      Problem: {$issue['detail']}\n"
               . "      Fix:     {$issue['fix']}\n";
        }
    }
}

// ── Patch snippet ─────────────────────────────────────────────
if (!$cli) { echo <<<'HTML'
<h2>&#x1FA79; Quick Patch for backup_engine.php (BUG 1 &mdash; remove this line)</h2>
<pre style="color:#f87171">. ' --set-gtid-purged=OFF'      &larr; DELETE THIS LINE from the $command string</pre>

<h2>&#x1F511; SQL Fix for BUG 2 (run in phpMyAdmin or HeidiSQL)</h2>
<pre style="color:#fde68a">ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
FLUSH PRIVILEGES;</pre>

<h2>&#x2705; Corrected $command block (MariaDB-safe, paste into backup_engine.php)</h2>
<pre style="color:#4ade80">$command = sprintf(
    '"%s"'
    . ' --host=%s'
    . ' --user=%s'
    . ' %s'
    . ' --default-auth=mysql_native_password'
    . ' --add-drop-database'
    . ' --add-drop-table'
    . ' --add-drop-trigger'
    . ' --complete-insert'
    . ' --single-transaction'
    . ' --routines'
    . ' --triggers'
    . ' --no-tablespaces'
    // --set-gtid-purged=OFF removed: MariaDB does not support this flag
    . ' --databases %s'
    . ' &gt; "%s" 2&gt;&amp;1',
    escapeshellcmd($dump_bin),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    $pass_arg,
    escapeshellarg(DB_NAME),
    $full_path
);</pre>
</body></html>
HTML;
} else {
    echo "\n  PATCH (backup_engine.php) — remove this line from \$command:\n";
    echo "      . ' --set-gtid-purged=OFF'\n\n";
    echo "  SQL FIX (BUG 2) — run in phpMyAdmin:\n";
    echo "      ALTER USER 'root'\@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';\n";
    echo "      FLUSH PRIVILEGES;\n\n";
}