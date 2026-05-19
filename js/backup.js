// backup.js
// handles all Backup & Recovery UI interactions
// talks to /admin/backup_action.php for all actions

var ENDPOINT = '../admin/backup_action.php';

// grab all the DOM elements we need
var btnBackup   = document.getElementById('btn-backup');
var btnRefresh  = document.getElementById('btn-refresh');
var btnImport   = document.getElementById('btn-import');
var fileInput   = document.getElementById('import-file-input');
var alertBox    = document.getElementById('br-alert');
var loadingEl   = document.getElementById('br-loading');
var tableEl     = document.getElementById('br-table');
var tbodyEl     = document.getElementById('br-tbody');
var emptyEl     = document.getElementById('br-empty');

// get the CSRF token from the hidden input on the page
function getCsrfToken() {
    var el = document.getElementById('csrf-token');
    return el ? el.value : '';
}

// shows an alert message at the top of the page and auto-hides it after 6 seconds
function showAlert(message, type) {
    if (!type) type = 'success';
    alertBox.textContent   = message;
    alertBox.className     = 'alert alert-' + type;
    alertBox.style.display = 'block';
    alertBox.style.opacity = '1';

    clearTimeout(alertBox._timer);
    alertBox._timer = setTimeout(function() {
        alertBox.style.opacity = '0';
        setTimeout(function() {
            alertBox.style.display = 'none';
            alertBox.style.opacity = '1';
        }, 400);
    }, 6000);
}

// disables a button and changes its text while waiting for a response
function setBusy(btn, busy, originalText) {
    btn.disabled    = busy;
    btn.textContent = busy ? '⏳ Working…' : originalText;
}

// sends a POST request to the endpoint and returns the parsed JSON response
async function apiPost(action, body) {
    var res = await fetch(ENDPOINT + '?action=' + action, { method: 'POST', body: body });
    if (!res.ok && res.status !== 200) {
        throw new Error('Server returned HTTP ' + res.status);
    }
    return res.json();
}

// sends a GET request and returns the parsed JSON response
async function apiGet(action) {
    var res = await fetch(ENDPOINT + '?action=' + action);
    return res.json();
}

// loads the list of backup files from the server and renders the table
async function fetchBackupList() {
    loadingEl.style.display = 'block';
    tableEl.style.display   = 'none';
    emptyEl.style.display   = 'none';
    tbodyEl.innerHTML       = '';

    try {
        var data = await apiGet('list');
        renderTable(data.files ? data.files : []);
    } catch (err) {
        showAlert('Could not load backup list. Is the server running?', 'danger');
        loadingEl.style.display = 'none';
    }
}

// sends a request to create a new backup
async function createBackup() {
    var orig = '💾 Create Backup Now';
    setBusy(btnBackup, true, orig);

    try {
        var body = new FormData();
        body.append('csrf_token', getCsrfToken());

        var data = await apiPost('backup', body);
        var msg  = data.message ? data.message : (data.success ? 'Backup created.' : 'Backup failed.');
        showAlert(msg, data.success ? 'success' : 'danger');

        if (data.success) fetchBackupList();

    } catch (err) {
        showAlert('Network error during backup: ' + err.message, 'danger');
    } finally {
        setBusy(btnBackup, false, orig);
    }
}

// sends a request to restore the database from a saved backup file
// warns the user first because this overwrites everything
async function restoreBackup(filename, btnEl) {
    var confirmed = confirm(
        '⚠ RESTORE DATABASE FROM:\n"' + filename + '"\n\n'
        + 'This will COMPLETELY OVERWRITE all current data.\n'
        + 'Products, transactions, stock levels — everything will roll back\n'
        + 'to the state when this backup was made.\n\n'
        + 'This cannot be undone. Continue?'
    );
    if (!confirmed) return;

    var origText = btnEl ? btnEl.textContent : '';
    if (btnEl) { btnEl.disabled = true; btnEl.textContent = '⏳ Restoring…'; }
    showAlert('Restoring database… do not close this page.', 'info');

    try {
        var body = new FormData();
        body.append('csrf_token', getCsrfToken());
        body.append('file', filename);

        var data = await apiPost('restore', body);
        var msg  = data.message ? data.message : (data.success ? 'Restore complete.' : 'Restore failed.');
        showAlert(msg, data.success ? 'success' : 'danger');

    } catch (err) {
        showAlert('Network error during restore: ' + err.message, 'danger');
    } finally {
        if (btnEl) { btnEl.disabled = false; btnEl.textContent = origText; }
    }
}

// uploads and restores a .sql file exported from MySQL Workbench
async function importWorkbench(file) {
    if (!file) return;

    if (file.name.toLowerCase().indexOf('.sql') === -1) {
        showAlert('Only .sql files are accepted.', 'danger');
        return;
    }

    var confirmed = confirm(
        '⚠ IMPORT & RESTORE FROM:\n"' + file.name + '"\n\n'
        + 'This will COMPLETELY OVERWRITE all current data with the contents\n'
        + 'of the uploaded file.\n\n'
        + 'This cannot be undone. Continue?'
    );
    if (!confirmed) {
        fileInput.value = '';
        return;
    }

    var orig = '📂 Import Workbench SQL';
    setBusy(btnImport, true, orig);
    showAlert('Uploading and importing SQL file… do not close this page.', 'info');

    try {
        var body = new FormData();
        body.append('csrf_token', getCsrfToken());
        body.append('sqlfile', file);

        var data = await apiPost('import', body);
        var msg  = data.message ? data.message : (data.success ? 'Import complete.' : 'Import failed.');
        showAlert(msg, data.success ? 'success' : 'danger');

        if (data.success) fetchBackupList();

    } catch (err) {
        showAlert('Network error during import: ' + err.message, 'danger');
    } finally {
        setBusy(btnImport, false, orig);
        fileInput.value = ''; // reset so the same file can be re-selected
    }
}

// deletes a single backup file and removes the row from the table
async function deleteBackup(filename, rowEl) {
    if (!confirm('Delete backup "' + filename + '"?\nThis cannot be undone.')) return;

    try {
        var body = new FormData();
        body.append('csrf_token', getCsrfToken());
        body.append('file', filename);

        var data = await apiPost('delete', body);

        if (data.success) {
            rowEl.remove();
            var msg = data.message ? data.message : 'Backup deleted.';
            showAlert(msg, 'success');

            // if the table is now empty, show the empty state message
            if (tbodyEl.querySelectorAll('tr').length === 0) {
                tableEl.style.display = 'none';
                emptyEl.style.display = 'block';
            }
        } else {
            showAlert(data.message ? data.message : 'Delete failed.', 'danger');
        }

    } catch (err) {
        showAlert('Network error during delete: ' + err.message, 'danger');
    }
}

// builds the backup table rows from the file list returned by the server
function renderTable(files) {
    loadingEl.style.display = 'none';
    tbodyEl.innerHTML       = '';

    if (files.length === 0) {
        emptyEl.style.display = 'block';
        tableEl.style.display = 'none';
        return;
    }

    tableEl.style.display = 'table';

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var tr   = document.createElement('tr');

        // make the filename more readable for display
        var label = file.name
            .replace('backup_manual_',    'Manual — ')
            .replace('backup_scheduled_', 'Scheduled — ')
            .replace(/^import_/,          'Imported — ')
            .replace('.sql', '')
            .replace(/_(\\d{2})(\\d{2})(\\d{2})$/, ' $1:$2:$3');

        tr.innerHTML = ''
            + '<td class="br-filename" title="' + escHtml(file.name) + '">' + escHtml(label) + '</td>'
            + '<td>' + escHtml(file.created_at) + '</td>'
            + '<td>' + escHtml(file.size_human) + '</td>'
            + '<td class="br-actions">'
            +   '<button class="btn btn-sm btn-warning btn-restore" data-file="' + escHtml(file.name) + '">&#9851; Restore</button>'
            +   '<button class="btn btn-sm btn-danger btn-delete"  data-file="' + escHtml(file.name) + '">&#128465; Delete</button>'
            + '</td>';

        // attach click handlers using a closure to capture the current file/row
        (function(f, row) {
            row.querySelector('.btn-restore').addEventListener('click', function(e) {
                restoreBackup(f.name, e.currentTarget);
            });
            row.querySelector('.btn-delete').addEventListener('click', function() {
                deleteBackup(f.name, row);
            });
        })(file, tr);

        tbodyEl.appendChild(tr);
    }
}

// escapes special HTML characters to prevent XSS from server-returned strings
function escHtml(str) {
    return String(str)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;');
}

// wire up the buttons
if (btnBackup)  btnBackup.addEventListener('click', createBackup);
if (btnRefresh) btnRefresh.addEventListener('click', fetchBackupList);

// the import button just opens the hidden file picker
if (btnImport)  btnImport.addEventListener('click', function() {
    if (fileInput) fileInput.click();
});

// when a file is picked, start the upload+restore
if (fileInput)  fileInput.addEventListener('change', function() {
    if (fileInput.files.length > 0) importWorkbench(fileInput.files[0]);
});

// load the backup list as soon as the page is ready
fetchBackupList();
