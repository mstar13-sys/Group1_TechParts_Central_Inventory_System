/* app.js - shared JS for TechParts POS */

function sweetTitle(type, title) {
  if (title) return title;
  if (type === 'success') return 'Success';
  if (type === 'warning') return 'Please Confirm';
  if (type === 'error') return 'Error';
  return 'Notice';
}

function normalizeSweetType(type) {
  if (type === 'danger') return 'error';
  if (['success', 'warning', 'error', 'info'].includes(type)) return type;
  return 'info';
}

function showSweetAlert(type, message, title = '', options = {}) {
  const normalizedType = normalizeSweetType(type || 'info');

  if (window.Swal) {
    return Swal.fire({
      icon: normalizedType,
      title: sweetTitle(normalizedType, title),
      text: message || '',
      showCancelButton: !!options.showCancel,
      confirmButtonText: options.confirmText || 'OK',
      cancelButtonText: options.cancelText || 'Cancel',
      allowOutsideClick: options.closeOnBackdrop !== false,
      timer: options.timer || undefined,
      confirmButtonColor: '#f97316',
      cancelButtonColor: '#64748b'
    }).then(result => result.isConfirmed);
  }

  return Promise.resolve(confirm(message || sweetTitle(normalizedType, title)));
}

function confirmDelete(msg, form) {
  showSweetAlert('warning', msg || 'Are you sure?', 'Confirm Action', {
    showCancel: true,
    confirmText: 'Yes'
  }).then(ok => {
    if (ok && form) form.submit();
  });
}

function alertTypeFromElement(el) {
  if (el.classList.contains('alert-success')) return 'success';
  if (el.classList.contains('alert-danger') || el.classList.contains('error')) return 'error';
  if (el.classList.contains('alert-warning')) return 'warning';
  return 'info';
}

window.showSweetAlert = showSweetAlert;
window.confirmDelete = confirmDelete;

function initRequiredFieldAlerts() {
  let alertOpen = false;

  document.addEventListener('invalid', event => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
    if (alertOpen) return;

    alertOpen = true;
    showSweetAlert(
      'warning',
      field.validationMessage || 'Please fill in all required fields before continuing.',
      'Missing Required Field'
    ).then(() => {
      alertOpen = false;
      field.focus();
    });
  }, true);
}

function initConfirmSubmitForms() {
  document.querySelectorAll('form[data-confirm-submit]').forEach(form => {
    if (form.dataset.confirmSubmitReady === '1') return;
    form.dataset.confirmSubmitReady = '1';

    form.addEventListener('submit', event => {
      if (form.dataset.confirmed === '1') {
        form.dataset.confirmed = '';
        return;
      }

      event.preventDefault();
      showSweetAlert('warning', form.dataset.confirmMessage || 'Are you sure?', form.dataset.confirmTitle || 'Confirm Action', {
        showCancel: true,
        confirmText: form.dataset.confirmButton || 'Confirm'
      }).then(ok => {
        if (!ok) return;
        form.dataset.confirmed = '1';
        form.requestSubmit();
      });
    });
  });
}

function initLogoutConfirmation() {
  document.querySelectorAll('[data-confirm-logout]').forEach(link => {
    if (link.dataset.logoutConfirmReady === '1') return;
    link.dataset.logoutConfirmReady = '1';

    link.addEventListener('click', event => {
      event.preventDefault();
      showSweetAlert('warning', 'Are you sure you want to logout?', 'Confirm Logout', {
        showCancel: true,
        confirmText: 'Logout'
      }).then(ok => {
        if (ok) window.location.href = link.href;
      });
    });
  });
}

// Live clock
const clock = document.getElementById('live-clock');
if (clock) {
  function tick() {
    const now = new Date();
    clock.textContent = now.toLocaleDateString('en-PH', {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    }) + ' ' + now.toLocaleTimeString('en-PH');
  }
  tick();
  setInterval(tick, 1000);
}

function initSweetAlerts() {
  initRequiredFieldAlerts();
  initConfirmSubmitForms();
  initLogoutConfirmation();

  if (window.TechPartsFlash) {
    showSweetAlert(
      window.TechPartsFlash.type || 'info',
      window.TechPartsFlash.message || '',
      window.TechPartsFlash.title || ''
    );
    window.TechPartsFlash = null;
  }

  document.querySelectorAll('.alert, .error[data-swal], .done-alert[data-swal]').forEach(el => {
    if (el.dataset.swalShown === '1') return;
    el.dataset.swalShown = '1';
    const message = (el.dataset.message || el.textContent || '').replace(/^[!×✓\s]+/, '').trim();
    if (message) {
      showSweetAlert(alertTypeFromElement(el), message, el.dataset.title || '');
    }
    el.style.display = 'none';
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSweetAlerts);
} else {
  initSweetAlerts();
}
