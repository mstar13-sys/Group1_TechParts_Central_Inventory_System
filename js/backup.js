/* /js/backup.js
 * Handles all Backup & Recovery UI interactions.
 * Requires: /admin/backup_action.php (JSON endpoint)
 * Depends on: #csrf-token hidden input on the page.
 *
 * Actions:
 *   createBackup()       — POST ?action=backup
 *   restoreBackup()      — POST ?action=restore  (from saved file)
 *   importWorkbench()    — POST ?action=import   (upload a .sql from Workbench)
 *   deleteBackup()       — POST ?action=delete
 *   fetchBackupList()    — GET  ?action=list
 */

(function () {
  'use strict';

  const ENDPOINT = '../admin/backup_action.php';

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const btnBackup    = document.getElementById('btn-backup');
  const btnRefresh   = document.getElementById('btn-refresh');
  const btnImport    = document.getElementById('btn-import');
  const fileInput    = document.getElementById('import-file-input');
  const alertBox     = document.getElementById('br-alert');
  const loadingEl    = document.getElementById('br-loading');
  const tableEl      = document.getElementById('br-table');
  const tbodyEl      = document.getElementById('br-tbody');
  const emptyEl      = document.getElementById('br-empty');
  const csrfToken    = () => document.getElementById('csrf-token')?.value ?? '';

  // ── Alert helper ──────────────────────────────────────────────────────────
  function showAlert(message, type = 'success') {
    alertBox.textContent   = message;
    alertBox.className     = `alert alert-${type}`;
    alertBox.style.display = 'block';
    alertBox.style.opacity = '1';
    clearTimeout(alertBox._timer);
    alertBox._timer = setTimeout(() => {
      alertBox.style.opacity = '0';
      setTimeout(() => {
        alertBox.style.display = 'none';
        alertBox.style.opacity = '1';
      }, 400);
    }, 6000);
  }

  /** Disable/re-enable a button and swap its label during async work. */
  function setBusy(btn, busy, originalText) {
    btn.disabled    = busy;
    btn.textContent = busy ? '⏳ Working…' : originalText;
  }

  // ── fetch wrapper ─────────────────────────────────────────────────────────
  async function apiPost(action, body) {
    const res  = await fetch(`${ENDPOINT}?action=${action}`, { method: 'POST', body });
    if (!res.ok && res.status !== 200) {
      throw new Error(`Server returned HTTP ${res.status}`);
    }
    return res.json();
  }

  async function apiGet(action) {
    const res = await fetch(`${ENDPOINT}?action=${action}`);
    return res.json();
  }

  // ── fetchBackupList ───────────────────────────────────────────────────────
  async function fetchBackupList() {
    loadingEl.style.display = 'block';
    tableEl.style.display   = 'none';
    emptyEl.style.display   = 'none';
    tbodyEl.innerHTML       = '';

    try {
      const data = await apiGet('list');
      renderTable(data.files || []);
    } catch {
      showAlert('Could not load backup list. Is the server running?', 'danger');
      loadingEl.style.display = 'none';
    }
  }

  // ── createBackup ──────────────────────────────────────────────────────────
  async function createBackup() {
    const orig = '💾 Create Backup Now';
    setBusy(btnBackup, true, orig);

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());

      const data = await apiPost('backup', body);
      showAlert(
        data.message ?? (data.success ? 'Backup created.' : 'Backup failed.'),
        data.success ? 'success' : 'danger'
      );
      if (data.success) fetchBackupList();
    } catch (err) {
      showAlert('Network error during backup: ' + err.message, 'danger');
    } finally {
      setBusy(btnBackup, false, orig);
    }
  }

  // ── restoreBackup ─────────────────────────────────────────────────────────
  // This OVERWRITES all current database data with the chosen backup.
  // Works because the backup now contains DROP TABLE IF EXISTS before every
  // CREATE TABLE, and the restore uses --force to continue past any errors.
  async function restoreBackup(filename, btnEl) {
    const confirmed = confirm(
      `⚠ RESTORE DATABASE FROM:\n"${filename}"\n\n` +
      'This will COMPLETELY OVERWRITE all current data.\n' +
      'Products, transactions, stock levels — everything will roll back\n' +
      'to the state when this backup was made.\n\n' +
      'This cannot be undone. Continue?'
    );
    if (!confirmed) return;

    const origText = btnEl?.textContent ?? '';
    if (btnEl) { btnEl.disabled = true; btnEl.textContent = '⏳ Restoring…'; }
    showAlert('Restoring database… do not close this page.', 'info');

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());
      body.append('file', filename);

      const data = await apiPost('restore', body);
      showAlert(
        data.message ?? (data.success ? 'Restore complete.' : 'Restore failed.'),
        data.success ? 'success' : 'danger'
      );
    } catch (err) {
      showAlert('Network error during restore: ' + err.message, 'danger');
    } finally {
      if (btnEl) { btnEl.disabled = false; btnEl.textContent = origText; }
    }
  }

  // ── importWorkbench ───────────────────────────────────────────────────────
  // Upload a .sql exported from MySQL Workbench (Server → Data Export) and
  // restore it. Mirrors Workbench "Data Import → Import from Self-Contained File".
  async function importWorkbench(file) {
    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.sql')) {
      showAlert('Only .sql files are accepted.', 'danger');
      return;
    }

    const confirmed = confirm(
      `⚠ IMPORT & RESTORE FROM:\n"${file.name}"\n\n` +
      'This will COMPLETELY OVERWRITE all current data with the contents\n' +
      'of the uploaded file.\n\n' +
      'This cannot be undone. Continue?'
    );
    if (!confirmed) {
      fileInput.value = '';
      return;
    }

    const orig = '📂 Import Workbench SQL';
    setBusy(btnImport, true, orig);
    showAlert('Uploading and importing SQL file… do not close this page.', 'info');

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());
      body.append('sqlfile', file);

      const data = await apiPost('import', body);
      showAlert(
        data.message ?? (data.success ? 'Import complete.' : 'Import failed.'),
        data.success ? 'success' : 'danger'
      );
      if (data.success) fetchBackupList();
    } catch (err) {
      showAlert('Network error during import: ' + err.message, 'danger');
    } finally {
      setBusy(btnImport, false, orig);
      fileInput.value = ''; // reset so the same file can be re-selected
    }
  }

  // ── deleteBackup ──────────────────────────────────────────────────────────
  async function deleteBackup(filename, rowEl) {
    if (!confirm(`Delete backup "${filename}"?\nThis cannot be undone.`)) return;

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());
      body.append('file', filename);

      const data = await apiPost('delete', body);
      if (data.success) {
        rowEl.remove();
        showAlert(data.message ?? 'Backup deleted.', 'success');
        if (tbodyEl.querySelectorAll('tr').length === 0) {
          tableEl.style.display = 'none';
          emptyEl.style.display = 'block';
        }
      } else {
        showAlert(data.message ?? 'Delete failed.', 'danger');
      }
    } catch (err) {
      showAlert('Network error during delete: ' + err.message, 'danger');
    }
  }

  // ── renderTable ───────────────────────────────────────────────────────────
  function renderTable(files) {
    loadingEl.style.display = 'none';
    tbodyEl.innerHTML       = '';

    if (files.length === 0) {
      emptyEl.style.display = 'block';
      tableEl.style.display = 'none';
      return;
    }

    tableEl.style.display = 'table';

    files.forEach(file => {
      const tr = document.createElement('tr');

      const label = file.name
        .replace('backup_manual_',    'Manual — ')
        .replace('backup_scheduled_', 'Scheduled — ')
        .replace(/^import_/, 'Imported — ')
        .replace('.sql', '')
        .replace(/_(\d{2})(\d{2})(\d{2})$/, ' $1:$2:$3');

      tr.innerHTML = `
        <td class="br-filename" title="${escHtml(file.name)}">${escHtml(label)}</td>
        <td>${escHtml(file.created_at)}</td>
        <td>${escHtml(file.size_human)}</td>
        <td class="br-actions">
          <button class="btn btn-sm btn-warning btn-restore" data-file="${escHtml(file.name)}">
            ♻ Restore
          </button>
          <button class="btn btn-sm btn-danger btn-delete" data-file="${escHtml(file.name)}">
            🗑 Delete
          </button>
        </td>
      `;

      tr.querySelector('.btn-restore')
        .addEventListener('click', (e) => restoreBackup(file.name, e.currentTarget));
      tr.querySelector('.btn-delete')
        .addEventListener('click', () => deleteBackup(file.name, tr));

      tbodyEl.appendChild(tr);
    });
  }

  /** Minimal XSS-safe HTML escape for server-returned strings. */
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Event wiring ──────────────────────────────────────────────────────────
  btnBackup?.addEventListener('click', createBackup);
  btnRefresh?.addEventListener('click', fetchBackupList);

  // Import button opens the hidden file picker
  btnImport?.addEventListener('click', () => fileInput?.click());

  // File picker change triggers the actual upload + restore
  fileInput?.addEventListener('change', () => {
    if (fileInput.files.length > 0) importWorkbench(fileInput.files[0]);
  });

  // Initial load
  fetchBackupList();
})();
