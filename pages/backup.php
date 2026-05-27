<?php
// pages/backup.php
require_once __DIR__ . '/../includes/config.php';
requireLogin(['Admin']);

$pageTitle = 'Backup & Recovery';

$host = DB_HOST;
$user = DB_USER;
$password = DB_PASS;
$database = DB_NAME;
$backupFolder = __DIR__ . '/../database_backups';

$message = '';
$messageType = 'info';

if (!file_exists($backupFolder)) {
    mkdir($backupFolder, 0755, true);
}

if (isset($_GET['download'])) {
    $fileName = basename((string) $_GET['download']);
    $backupFile = $backupFolder . '/' . $fileName;

    if (!preg_match('/\.sql$/i', $fileName) || !is_file($backupFile)) {
        http_response_code(404);
        exit('Backup file not found.');
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($backupFile));
    readfile($backupFile);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        $backupFile = $backupFolder . '/' . $database . '_' . date('Y-m-d_H-i-s') . '.sql';
        $passwordPart = $password !== '' ? ' --password=' . escapeshellarg($password) : '';

        $command = 'mysqldump'
            . ' --host=' . escapeshellarg($host)
            . ' --user=' . escapeshellarg($user)
            . $passwordPart
            . ' ' . escapeshellarg($database)
            . ' > ' . escapeshellarg($backupFile);

        system($command, $output);

        if ($output === 0 && is_file($backupFile) && filesize($backupFile) > 0) {
            $message = 'Database backup created successfully!';
            $messageType = 'success';
        } else {
            if (is_file($backupFile) && filesize($backupFile) === 0) {
                unlink($backupFile);
            }
            $message = 'Backup failed!';
            $messageType = 'danger';
        }
    }

    if ($action === 'restore') {
        $fileName = basename((string) ($_POST['backup_file'] ?? ''));
        $backupFile = $backupFolder . '/' . $fileName;

        if (!preg_match('/\.sql$/i', $fileName) || !is_file($backupFile)) {
            $message = 'Selected backup file was not found.';
            $messageType = 'danger';
        } else {
            $passwordPart = $password !== '' ? ' --password=' . escapeshellarg($password) : '';

            $command = 'mysql'
                . ' --host=' . escapeshellarg($host)
                . ' --user=' . escapeshellarg($user)
                . $passwordPart
                . ' ' . escapeshellarg($database)
                . ' < ' . escapeshellarg($backupFile);

            system($command, $output);

            if ($output === 0) {
                $message = 'Database restored successfully!';
                $messageType = 'success';
            } else {
                $message = 'Restore failed!';
                $messageType = 'danger';
            }
        }
    }
}

$backupFiles = [];
foreach (glob($backupFolder . '/*.sql') ?: [] as $file) {
    $backupFiles[] = [
        'name' => basename($file),
        'size' => filesize($file),
        'modified' => filemtime($file),
    ];
}

usort($backupFiles, static fn ($a, $b) => $b['modified'] <=> $a['modified']);

include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card green">
    <div class="stat-label">Available Backups</div>
    <div class="stat-value"><?= count($backupFiles) ?></div>
    <div class="stat-sub">Stored in database_backups</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Database</div>
    <div class="stat-value" style="font-size:22px"><?= htmlspecialchars($database) ?></div>
    <div class="stat-sub"><?= htmlspecialchars($host) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-title">Create Backup</div>
  <form method="POST" data-confirm-submit data-confirm-title="Create Backup" data-confirm-message="Create a SQL backup of the current database now?" data-confirm-button="Create Backup">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="backup">
    <button type="submit" class="btn btn-primary">Create Backup</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Recovery</div>
  <?php if (empty($backupFiles)): ?>
    <p style="color:var(--text-muted)">No SQL backup files are available yet.</p>
  <?php else: ?>
    <form method="POST" data-confirm-submit data-confirm-title="Restore Database" data-confirm-message="Restore the selected SQL backup? Current database data may be replaced." data-confirm-button="Restore">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="restore">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Backup File</label>
          <select name="backup_file" class="form-control" required>
            <?php foreach ($backupFiles as $file): ?>
              <option value="<?= htmlspecialchars($file['name']) ?>">
                <?= htmlspecialchars($file['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end">
          <button type="submit" class="btn btn-danger">Restore Selected Backup</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">Backup Files</div>
  <?php if (empty($backupFiles)): ?>
    <p style="color:var(--text-muted)">No backup files yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>File</th>
            <th>Size</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($backupFiles as $file): ?>
            <tr>
              <td><?= htmlspecialchars($file['name']) ?></td>
              <td><?= number_format($file['size'] / 1024, 2) ?> KB</td>
              <td><?= date('M d, Y g:i A', (int) $file['modified']) ?></td>
              <td>
                <a class="btn btn-ghost btn-sm" href="/pages/backup.php?download=<?= urlencode($file['name']) ?>">Download</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
