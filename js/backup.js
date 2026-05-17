/* /js/backup.js
 * Handles all Backup & Recovery UI interactions.
 * Requires: /admin/backup_action.php (JSON endpoint)
 * Depends on: #csrf-token hidden input on the page.
 */

(function () {
  'use strict';

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const btnBackup  = document.getElementById('btn-backup');
  const btnRefresh = document.getElementById('btn-refresh');
  const alertBox   = document.getElementById('br-alert');
  const loadingEl  = document.getElementById('br-loading');
  const tableEl    = document.getElementById('br-table');
  const tbodyEl    = document.getElementById('br-tbody');
  const emptyEl    = document.getElementById('br-empty');
  const csrfToken  = () => document.getElementById('csrf-token')?.value ?? '';

  // ── Helpers ───────────────────────────────────────────────────────────────

  /** Show a temporary status alert. type: 'success' | 'danger' | 'info' */
  function showAlert(message, type = 'success') {
    alertBox.textContent = message;
    alertBox.className   = `alert alert-${type}`;
    alertBox.style.display = 'block';
    clearTimeout(alertBox._timer);
    alertBox._timer = setTimeout(() => {
      alertBox.style.opacity = '0';
      setTimeout(() => { alertBox.style.display = 'none'; alertBox.style.opacity = '1'; }, 400);
    }, 5000);
  }

  /** Disable / re-enable a button during async work. */
  function setLoading(btn, loading, originalText) {
    btn.disabled   = loading;
    btn.textContent = loading ? '⏳ Working…' : originalText;
  }

  // ── API calls ─────────────────────────────────────────────────────────────

  /** GET /admin/backup_action.php?action=list */
  async function fetchBackupList() {
    loadingEl.style.display = 'block';
    tableEl.style.display   = 'none';
    emptyEl.style.display   = 'none';

    try {
      const res  = await fetch('../admin/backup_action.php?action=list');
      const data = await res.json();
      renderTable(data.files || []);
    } catch (err) {
      showAlert('Could not load backup list. Check your connection.', 'danger');
      loadingEl.style.display = 'none';
    }
  }

  /** POST /admin/backup_action.php?action=backup */
  async function createBackup() {
    const originalText = '💾 Create Backup Now';
    setLoading(btnBackup, true, originalText);

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());

      const res  = await fetch('../admin/backup_action.php?action=backup', { method: 'POST', body });
      const data = await res.json();

      showAlert(data.message ?? (data.success ? 'Backup created.' : 'Backup failed.'),
                data.success ? 'success' : 'danger');

      if (data.success) fetchBackupList();
    } catch (err) {
      showAlert('Network error during backup.', 'danger');
    } finally {
      setLoading(btnBackup, false, originalText);
    }
  }

  /** POST /admin/backup_action.php?action=restore  (with file param) */
  async function restoreBackup(filename, btnEl) {
    const confirmed = confirm(
      `⚠ Restore from "${filename}"?\n\n` +
      'This will OVERWRITE all current data with this backup.\n' +
      'This action cannot be undone. Continue?'
    );
    if (!confirmed) return;

    // Disable the restore button to prevent duplicate requests
    const origText = btnEl ? btnEl.textContent : '';
    if (btnEl) { btnEl.disabled = true; btnEl.textContent = '⏳ Restoring…'; }

    showAlert('Restoring database… please wait.', 'info');

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());
      body.append('file', filename);

      const res  = await fetch('../admin/backup_action.php?action=restore', { method: 'POST', body });
      const data = await res.json();

      showAlert(data.message ?? (data.success ? 'Restore complete.' : 'Restore failed.'),
                data.success ? 'success' : 'danger');
    } catch (err) {
      showAlert('Network error during restore.', 'danger');
    } finally {
      if (btnEl) { btnEl.disabled = false; btnEl.textContent = origText; }
    }
  }

  /** POST /admin/backup_action.php?action=delete  (with file param) */
  async function deleteBackup(filename, rowEl) {
    const confirmed = confirm(`Delete backup "${filename}"? This cannot be undone.`);
    if (!confirmed) return;

    try {
      const body = new FormData();
      body.append('csrf_token', csrfToken());
      body.append('file', filename);

      const res  = await fetch('../admin/backup_action.php?action=delete', { method: 'POST', body });
      const data = await res.json();

      if (data.success) {
        rowEl.remove();
        showAlert(data.message ?? 'Backup deleted.', 'success');
        // Show empty state if no rows remain
        if (tbodyEl.querySelectorAll('tr').length === 0) {
          tableEl.style.display = 'none';
          emptyEl.style.display = 'block';
        }
      } else {
        showAlert(data.message ?? 'Delete failed.', 'danger');
      }
    } catch (err) {
      showAlert('Network error during delete.', 'danger');
    }
  }

  // ── Rendering ─────────────────────────────────────────────────────────────

  function renderTable(files) {
    loadingEl.style.display = 'none';
    tbodyEl.innerHTML       = '';

    if (files.length === 0) {
      emptyEl.style.display = 'block';
      return;
    }

    tableEl.style.display = 'table';

    files.forEach(file => {
      const tr = document.createElement('tr');

      // Format filename as a nicer label
      const label = file.name
        .replace('backup_manual_', 'Manual — ')
        .replace('backup_scheduled_', 'Scheduled — ')
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

      // Attach row-level listeners
      tr.querySelector('.btn-restore').addEventListener('click', (e) => restoreBackup(file.name, e.currentTarget));
      tr.querySelector('.btn-delete').addEventListener('click', () => deleteBackup(file.name, tr));

      tbodyEl.appendChild(tr);
    });
  }

  /** Minimal HTML-escape to prevent XSS from server-returned filenames. */
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

  // Load list on page ready
  fetchBackupList();
})();
