<?php
require_once __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$appName = defined('APP_NAME') ? APP_NAME : 'TechParts POS';
$dbName = defined('DB_NAME') ? DB_NAME : 'TechParts2';
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : 'root';
$projectRoot = __DIR__;
$backupDir = $projectRoot . DIRECTORY_SEPARATOR . 'database_backups';
$recoveryAdminFile = $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'recovery_admin.json';
$backupFiles = [];

if (is_dir($backupDir)) {
    foreach (glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
        if (is_file($file)) {
            $backupFiles[] = [
                'name' => basename($file),
                'path' => $file,
                'modified' => filemtime($file),
                'size' => filesize($file),
            ];
        }
    }
}

usort($backupFiles, static fn ($a, $b) => $b['modified'] <=> $a['modified']);

$latestBackup = $backupFiles[0] ?? null;
$backupPath = $latestBackup['path'] ?? ($backupDir . DIRECTORY_SEPARATOR . 'your_backup_file.sql');
$manualRecoveryBackup = $backupDir . DIRECTORY_SEPARATOR . 'TechParts2_2026-05-21_11-01-49.sql';
$createCommand = 'mysql -u ' . $dbUser . ' -p -e "CREATE DATABASE IF NOT EXISTS `' . $dbName . '`;"';
$restoreCommand = 'mysql -u ' . $dbUser . ' -p ' . $dbName . ' < "' . $backupPath . '"';
$manualRecoveryCommand = 'mysql -u root -p ' . $dbName . ' < "' . $manualRecoveryBackup . '"';
$xamppCommand = 'C:\xampp\mysql\bin\mysql.exe -u ' . $dbUser . ' -p ' . $dbName . ' < "' . $backupPath . '"';
$restoreMessage = '';
$restoreMessageType = '';
$recoveryLoginError = '';

function recoveryAdminAccounts(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }

    return array_values(array_filter($data['admins'] ?? [], static function ($admin) {
        return is_array($admin)
            && !empty($admin['email'])
            && !empty($admin['password_hash']);
    }));
}

function recoveryCredentialsAreValid(string $email, string $password, array $admins): bool
{
    $email = strtolower(trim($email));

    foreach ($admins as $admin) {
        if (strtolower(trim((string) $admin['email'])) !== $email) {
            continue;
        }

        if (password_verify($password, (string) $admin['password_hash'])) {
            return true;
        }
    }

    return false;
}

function recoveryUserIsAuthorized(): bool
{
    if (!empty($_SESSION['recovery_admin_verified'])) {
        return true;
    }

    return !empty($_SESSION['logged_in'])
        && !empty($_SESSION['role'])
        && $_SESSION['role'] === 'Admin';
}

function recoverySqlStatements(string $sql): array
{
    $statements = [];
    $statement = '';
    $quote = null;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote === null && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($quote === null && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($quote === null && $char === '/' && $next === '*') {
            $statement .= $char . $next;
            $i += 2;
            while ($i < $length) {
                $statement .= $sql[$i];
                if ($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/') {
                    $statement .= '/';
                    $i++;
                    break;
                }
                $i++;
            }
            continue;
        }

        if (($char === "'" || $char === '"' || $char === '`') && ($i === 0 || $sql[$i - 1] !== '\\')) {
            if ($quote === null) {
                $quote = $char;
            } elseif ($quote === $char) {
                $quote = null;
            }
        }

        if ($char === ';' && $quote === null) {
            $trimmed = trim($statement);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $statement = '';
            continue;
        }

        $statement .= $char;
    }

    $trimmed = trim($statement);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

$recoveryAdmins = recoveryAdminAccounts($recoveryAdminFile);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'recovery_login') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (recoveryCredentialsAreValid($email, $password, $recoveryAdmins)) {
        session_regenerate_id(true);
        $_SESSION['recovery_admin_verified'] = true;
        $_SESSION['recovery_admin_email'] = $email;
        header('Location: /database_recovery.php');
        exit;
    }

    $recoveryLoginError = 'Invalid administrator recovery email or password.';
}

$recoveryAuthorized = recoveryUserIsAuthorized();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'recover_database') {
    verifyCsrf();

    if (!$recoveryAuthorized) {
        http_response_code(403);
        $restoreMessage = 'Only an administrator can recover the database.';
        $restoreMessageType = 'danger';
    } elseif (!is_file($manualRecoveryBackup)) {
        $restoreMessage = 'Backup file not found: ' . $manualRecoveryBackup;
        $restoreMessageType = 'danger';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,
                ]
            );

            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $pdo->exec('USE `' . str_replace('`', '``', $dbName) . '`');
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            $sql = file_get_contents($manualRecoveryBackup);
            if ($sql === false) {
                throw new RuntimeException('Could not read the backup file.');
            }

            foreach (recoverySqlStatements($sql) as $statement) {
                $pdo->exec($statement);
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $restoreMessage = 'Database recovered successfully from the latest Techparts2 Database Backup';
            $restoreMessageType = 'success';
        } catch (Throwable $e) {
            $restoreMessage = 'Recovery failed. ' . $e->getMessage();
            $restoreMessageType = 'danger';
        }
    }
}

function recoveryFormatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Recovery - <?= htmlspecialchars($appName) ?></title>
  <link rel="icon" href="/assets/images/logo.png" type="image/png">
  <link rel="stylesheet" href="/css/recovery_page.css">
  </style>
</head>
<body>
  <?php if (!$recoveryAuthorized): ?>
  <main class="recovery-shell">
    <section class="hero">
      <div>
        <div class="brand">
          <img src="/assets/images/logo.png" alt="">
          <span><?= htmlspecialchars($appName) ?></span>
        </div>
        <h1>Database Connection Problem</h1>
        <p class="muted">The system cannot connect to the database. Please contact the administrator to recover and restore the system.</p>
      </div>
      <div class="hero-side">
        <div class="status-pill">Database Down</div>
      </div>
    </section>

    <div class="grid">
      <section>
        <div class="card">
          <div class="card-title">System Notice</div>
          <p class="muted">Recovery tools are only available to authorized administrators. Regular users should wait until the database is restored.</p>
          <div class="actions">
            <a class="btn btn-primary" href="/pages/login.php">Back to Login</a>
          </div>
        </div>
      </section>

      <aside>
        <div class="card">
          <div class="card-title">Administrator Access</div>
          <?php if ($recoveryLoginError): ?>
            <div class="notice danger"><?= htmlspecialchars($recoveryLoginError) ?></div>
          <?php endif; ?>
          <?php if (empty($recoveryAdmins)): ?>
            <div class="notice danger">No recovery administrator file was found. Contact the system owner.</div>
          <?php else: ?>
            <form method="POST" action="/database_recovery.php">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="recovery_login">
              <div style="display:grid;gap:10px">
                <input class="btn" style="justify-content:flex-start;text-align:left;font-weight:400" type="email" name="email" placeholder="Admin email" required>
                <input class="btn" style="justify-content:flex-start;text-align:left;font-weight:400" type="password" name="password" placeholder="Admin password" required>
                <button type="submit" class="btn btn-primary">Unlock Recovery</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </main>
</body>
</html>
<?php exit; ?>
  <?php endif; ?>

  <main class="recovery-shell">
    <section class="hero">
      <div>
        <div class="brand">
          <img src="/assets/images/logo.png" alt="">
          <span><?= htmlspecialchars($appName) ?></span>
        </div>
        <h1>Database Connection Problem</h1>
        <p class="muted">The system cannot connect to <strong><?= htmlspecialchars($dbName) ?></strong>. Use the steps below to restore the database from a SQL backup - if the recovery button is not available.</p>
      </div>
      <div class="hero-side">
        <div class="status-pill">Database Down</div>
        <form method="POST" action="/database_recovery.php" id="recover-database-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="recover_database">
          <button type="submit" class="btn btn-danger">Recover Database</button>
        </form>
      </div>
    </section>

    <div class="grid">
      <section>
        <div class="card">
          <div class="card-title">Recommended Recovery Steps</div>
          <div class="steps">
            <div class="step">
              <span class="step-number">1</span>
              <div>
                <strong>Start MySQL</strong>
                <p class="muted">Open XAMPP or your MySQL service manager and make sure MySQL is running.</p>
              </div>
            </div>

            <div class="step">
              <span class="step-number">2</span>
              <div>
                <strong>Open Command Prompt</strong>
                <p class="muted">Run CMD on the computer where MySQL is installed.</p>
              </div>
            </div>

            <div class="step">
              <span class="step-number">3</span>
              <div>
                <strong>Create the database if it was deleted</strong>
                <pre><?= htmlspecialchars($createCommand) ?></pre>
              </div>
            </div>

            <div class="step">
              <span class="step-number">4</span>
              <div>
                <strong>Restore the latest SQL backup</strong>
                <pre><?= htmlspecialchars($restoreCommand) ?></pre>
                <!-- <p class="muted">One-click recovery button command:</p>
                <pre><?= htmlspecialchars($manualRecoveryCommand) ?></pre> -->
                <p class="muted">If MySQL is not available in CMD, use the XAMPP full path:</p>
                <pre><?= htmlspecialchars($xamppCommand) ?></pre>
              </div>
            </div>

            <div class="step">
              <span class="step-number">5</span>
              <div>
                <strong>Refresh the system</strong>
                <p class="muted">After the restore finishes, open the login page again and sign in as Admin.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">phpMyAdmin Option</div>
          <p class="muted">You can also open phpMyAdmin, create/select <strong><?= htmlspecialchars($dbName) ?></strong>, choose Import, then upload one of the SQL files from <strong>database_backups</strong>.</p>
        </div>
      </section>

      <aside>
        <div class="card">
          <div class="card-title">Detected Backup Files</div>
          <?php if (empty($backupFiles)): ?>
            <p class="muted">No SQL backups were found in database_backups.</p>
          <?php else: ?>
            <div class="file-list">
              <?php foreach (array_slice($backupFiles, 0, 6) as $file): ?>
                <div class="file-item">
                  <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                  <div class="file-meta">
                    <?= date('M d, Y g:i A', (int) $file['modified']) ?> · <?= recoveryFormatBytes((int) $file['size']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-title">Important</div>
          <div class="note">
            Restoring a SQL backup can replace current database data. Use the newest trusted backup file unless your admin specifically needs an older copy.
          </div>
          <div class="actions">
            <a class="btn btn-primary" href="/pages/login.php">Try Login Again</a>
            <a class="btn" href="/">Go to Start</a>
          </div>
        </div>
      </aside>
    </div>
  </main>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.23.0/sweetalert2.all.min.js"></script>
  <script>
    const recoverForm = document.getElementById('recover-database-form');

    if (recoverForm) {
      recoverForm.addEventListener('submit', function(event) {
        if (recoverForm.dataset.confirmed === '1') {
          recoverForm.dataset.confirmed = '';
          return;
        }

        event.preventDefault();

        if (!window.Swal) {
          if (confirm('Recover the database from the current Techparts2 database backup? This can replace current data.')) {
            recoverForm.dataset.confirmed = '1';
            recoverForm.submit();
          }
          return;
        }

        Swal.fire({
          icon: 'warning',
          title: 'Recover Database?',
          text: 'This will restore the latest techparts database backup and can replace current data.',
          showCancelButton: true,
          confirmButtonText: 'Recover',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#ef4444',
          cancelButtonColor: '#64748b'
        }).then(result => {
          if (!result.isConfirmed) return;
          recoverForm.dataset.confirmed = '1';
          recoverForm.requestSubmit();
        });
      });
    }

    <?php if ($restoreMessage): ?>
      if (window.Swal) {
        Swal.fire({
          icon: '<?= $restoreMessageType === 'danger' ? 'error' : 'success' ?>',
          title: '<?= $restoreMessageType === 'danger' ? 'Recovery Failed' : 'Recovery Complete' ?>',
          text: <?= json_encode($restoreMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          confirmButtonColor: '#f97316'
        });
      } else {
        alert(<?= json_encode($restoreMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
      }
    <?php endif; ?>
  </script>
</body>
</html>
