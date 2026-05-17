/* app.js - shared JS for TechParts POS */

function ensureSweetAlertStyles() {
  if (document.getElementById('tp-swal-styles')) return;

  const style = document.createElement('style');
  style.id = 'tp-swal-styles';
  style.textContent = `
    .tp-swal-backdrop {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 18px;
      background: rgba(0,0,0,.55);
      opacity: 0;
      pointer-events: none;
      transition: opacity .16s ease;
    }
    .tp-swal-backdrop.open {
      opacity: 1;
      pointer-events: auto;
    }
    .tp-swal-box {
      width: min(390px, 96vw);
      background: #151820;
      color: #e2e8f0;
      border: 1px solid #2a3050;
      border-radius: 12px;
      box-shadow: 0 18px 60px rgba(0,0,0,.45);
      padding: 24px;
      text-align: center;
      transform: translateY(10px) scale(.98);
      transition: transform .16s ease;
      font-family: 'DM Sans', Segoe UI, sans-serif;
    }
    .tp-swal-backdrop.open .tp-swal-box {
      transform: translateY(0) scale(1);
    }
    .tp-swal-icon {
      width: 54px;
      height: 54px;
      margin: 0 auto 14px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      font-size: 28px;
      font-weight: 800;
    }
    .tp-swal-success { background: rgba(34,197,94,.14); color: #22c55e; border: 1px solid rgba(34,197,94,.35); }
    .tp-swal-error { background: rgba(239,68,68,.14); color: #ef4444; border: 1px solid rgba(239,68,68,.35); }
    .tp-swal-warning { background: rgba(234,179,8,.14); color: #eab308; border: 1px solid rgba(234,179,8,.35); }
    .tp-swal-info { background: rgba(59,130,246,.14); color: #3b82f6; border: 1px solid rgba(59,130,246,.35); }
    .tp-swal-title {
      font-size: 20px;
      font-weight: 800;
      margin-bottom: 7px;
    }
    .tp-swal-message {
      color: #a8b3c7;
      font-size: 14px;
      line-height: 1.45;
      word-break: break-word;
    }
    .tp-swal-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
    }
    .tp-swal-btn {
      border: 0;
      border-radius: 8px;
      padding: 9px 18px;
      cursor: pointer;
      font-weight: 700;
      background: #f97316;
      color: #fff;
    }
    .tp-swal-cancel {
      background: transparent;
      color: #a8b3c7;
      border: 1px solid #2a3050;
    }
  `;
  document.head.appendChild(style);
}

function sweetIcon(type) {
  if (type === 'success') return '✓';
  if (type === 'warning') return '!';
  if (type === 'error') return '×';
  return 'i';
}

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
  ensureSweetAlertStyles();

  const normalizedType = normalizeSweetType(type || 'info');
  const backdrop = document.createElement('div');
  backdrop.className = 'tp-swal-backdrop';
  backdrop.innerHTML = `
    <div class="tp-swal-box" role="dialog" aria-modal="true">
      <div class="tp-swal-icon tp-swal-${normalizedType}">${sweetIcon(normalizedType)}</div>
      <div class="tp-swal-title"></div>
      <div class="tp-swal-message"></div>
      <div class="tp-swal-actions">
        ${options.showCancel ? '<button type="button" class="tp-swal-btn tp-swal-cancel">Cancel</button>' : ''}
        <button type="button" class="tp-swal-btn">${options.confirmText || 'OK'}</button>
      </div>
    </div>
  `;

  backdrop.querySelector('.tp-swal-title').textContent = sweetTitle(normalizedType, title);
  backdrop.querySelector('.tp-swal-message').textContent = message || '';
  document.body.appendChild(backdrop);
  requestAnimationFrame(() => backdrop.classList.add('open'));

  return new Promise(resolve => {
    const close = result => {
      backdrop.classList.remove('open');
      setTimeout(() => backdrop.remove(), 180);
      resolve(result);
    };

    backdrop.querySelector('.tp-swal-btn:not(.tp-swal-cancel)').addEventListener('click', () => close(true));
    const cancel = backdrop.querySelector('.tp-swal-cancel');
    if (cancel) cancel.addEventListener('click', () => close(false));
    backdrop.addEventListener('click', e => {
      if (e.target === backdrop && options.closeOnBackdrop !== false) close(false);
    });
    if (options.timer) setTimeout(() => close(true), options.timer);
  });
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
