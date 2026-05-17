<?php
// /pages/backup_recovery.php
require_once '../includes/config.php';
requireLogin(['Admin']);

$pageTitle = 'Backup & Recovery';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../css/backup_recovery.css">

<div class="br-container">

  <!-- ── Status banner ───────────────────────────────────────────────────── -->
  <div id="br-alert" class="alert" style="display:none"></div>

  <!-- ── Action bar ──────────────────────────────────────────────────────── -->
  <div class="card br-card">
    <div class="br-header">
      <div>
        <h2 class="br-title">🛡 Database Backup &amp; Recovery</h2>
        <p class="br-subtitle">
          Snapshot and restore <strong><?= htmlspecialchars(DB_NAME) ?></strong>.
          Backups are compatible with MySQL Workbench
          <em>(Server → Data Export / Data Import)</em>.
        </p>
      </div>
      <div class="br-btn-group">
        <button id="btn-backup" class="btn btn-primary">
          💾 Create Backup Now
        </button>
        <button id="btn-import" class="btn btn-secondary" title="Upload a .sql file exported from MySQL Workbench">
          📂 Import Workbench SQL
        </button>
        <!-- Hidden file input — triggered by btn-import click -->
        <input type="file" id="import-file-input" accept=".sql" style="display:none">
      </div>
    </div>

    <!-- ── Workbench tip ─────────────────────────────────────────────────── -->
    <div class="br-tip">
      <strong>💡 MySQL Workbench workflow:</strong>
      <span>Export → <em>Server → Data Export → select TechParts2 → Export to Self-Contained File (.sql)</em></span>
      &nbsp;|&nbsp;
      <span>Import → click <em>Import Workbench SQL</em> above, or use <em>Server → Data Import</em> in Workbench</span>
    </div>
  </div>

  <!-- ── Backup file table ───────────────────────────────────────────────── -->
  <div class="card br-card">
    <div class="br-table-header">
      <h3>Saved Backups</h3>
      <button id="btn-refresh" class="btn btn-sm btn-secondary" title="Refresh list">↻ Refresh</button>
    </div>

    <div id="br-loading" class="br-loading">Loading backups…</div>

    <table class="br-table" id="br-table" style="display:none">
      <thead>
        <tr>
          <th>Backup</th>
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

<!-- CSRF token — must appear before backup.js -->
<input type="hidden" id="csrf-token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

<script src="../js/backup.js"></script>

<?php require_once '../includes/footer.php'; ?>
