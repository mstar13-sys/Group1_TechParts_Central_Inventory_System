<?php
// /pages/backup_recovery.php
require_once '../includes/config.php';
requireLogin(['Admin']);

$pageTitle = 'Backup & Recovery';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../css/backup_recovery.css">

<div class="br-container">

  <!-- ── Status banner (JS-populated) ───────────────────────────────────── -->
  <div id="br-alert" class="alert" style="display:none"></div>

  <!-- ── Top action bar ─────────────────────────────────────────────────── -->
  <div class="card br-card">
    <div class="br-header">
      <div>
        <h2 class="br-title">🛡 Database Backup &amp; Recovery</h2>
        <p class="br-subtitle">
          Create a point-in-time snapshot of <strong><?= htmlspecialchars(DB_NAME) ?></strong>
          or restore it from an earlier backup.
        </p>
      </div>
      <button id="btn-backup" class="btn btn-primary">
        <span class="btn-icon">💾</span> Create Backup Now
      </button>
    </div>
  </div>

  <!-- ── Backup file table ──────────────────────────────────────────────── -->
  <div class="card br-card">
    <div class="br-table-header">
      <h3>Saved Backups</h3>
      <button id="btn-refresh" class="btn btn-sm btn-secondary" title="Refresh list">↻ Refresh</button>
    </div>

    <div id="br-loading" class="br-loading">Loading backups…</div>

    <table class="br-table" id="br-table" style="display:none">
      <thead>
        <tr>
          <th>Filename</th>
          <th>Created</th>
          <th>Size</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="br-tbody">
        <!-- rows injected by backup.js -->
      </tbody>
    </table>

    <div id="br-empty" class="br-empty" style="display:none">
      No backups found. Click <em>Create Backup Now</em> to make the first one.
    </div>
  </div>

</div><!-- .br-container -->

<!-- Hidden CSRF field — read by backup.js for all POST requests -->
<input type="hidden" id="csrf-token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

<script src="../js/backup.js"></script>

<?php require_once '../includes/footer.php'; ?>
