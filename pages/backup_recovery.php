<?php
// backup_recovery.php
// the main Backup & Recovery page - only accessible by Admins
require_once '../includes/config.php';
requireLogin(array('Admin'));

$pageTitle = 'Backup & Recovery';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../css/backup_recovery.css">

<div class="br-container">

    <!-- show a success or error message here (hidden until backup.js sets it) -->
    <div id="br-alert" class="alert" style="display:none"></div>

    <!-- top card: title, buttons, and the workbench tip -->
    <div class="card br-card">
        <div class="br-header">
            <div>
                <h2 class="br-title">🛡 Database Backup &amp; Recovery</h2>
                <p class="br-subtitle">
                    Snapshot and restore <strong><?= htmlspecialchars(DB_NAME) ?></strong>.
                    Backups are compatible with MySQL Workbench
                    <em>(Server &rarr; Data Export / Data Import)</em>.
                </p>
            </div>
            <div class="br-btn-group">
                <button id="btn-backup" class="btn btn-primary">
                    💾 Create Backup Now
                </button>
                <button id="btn-import" class="btn btn-secondary" title="Upload a .sql file exported from MySQL Workbench">
                    📂 Import Workbench SQL
                </button>
                <!-- this file input is hidden - btn-import click opens it -->
                <input type="file" id="import-file-input" accept=".sql" style="display:none">
            </div>
        </div>

        <!-- tip box explaining how to use MySQL Workbench with this system -->
        <div class="br-tip">
            <strong>💡 MySQL Workbench workflow:</strong>
            <span>Export &rarr; <em>Server &rarr; Data Export &rarr; select TechParts2 &rarr; Export to Self-Contained File (.sql)</em></span>
            &nbsp;|&nbsp;
            <span>Import &rarr; click <em>Import Workbench SQL</em> above, or use <em>Server &rarr; Data Import</em> in Workbench</span>
        </div>
    </div>

    <!-- bottom card: the list of saved backup files -->
    <div class="card br-card">
        <div class="br-table-header">
            <h3>Saved Backups</h3>
            <button id="btn-refresh" class="btn btn-sm btn-secondary" title="Refresh list">&#8635; Refresh</button>
        </div>

        <!-- shown while backup.js is fetching the list -->
        <div id="br-loading" class="br-loading">Loading backups&hellip;</div>

        <!-- filled in by backup.js renderTable() -->
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
                <!-- rows are injected by backup.js -->
            </tbody>
        </table>

        <!-- shown when there are no backup files yet -->
        <div id="br-empty" class="br-empty" style="display:none">
            No backups found. Click <em>Create Backup Now</em> to make the first one.
        </div>
    </div>

</div>

<!-- CSRF token read by backup.js - must be above the script tag -->
<input type="hidden" id="csrf-token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

<script src="../js/backup.js"></script>

<?php require_once '../includes/footer.php'; ?>
