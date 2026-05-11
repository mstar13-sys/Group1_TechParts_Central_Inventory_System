/* app.js — shared JS for TechParts POS */

// Live clock
const clock = document.getElementById('live-clock');
if (clock) {
  function tick() {
    const now = new Date();
    clock.textContent = now.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' })
      + ' ' + now.toLocaleTimeString('en-PH');
  }
  tick(); setInterval(tick, 1000);
}

// Modal helpers
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// Confirm delete
function confirmDelete(msg, form) {
  if (confirm(msg || 'Are you sure?')) form.submit();
}

// Auto-dismiss alerts after 4s
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 3800);
  setTimeout(() => el.remove(), 4200);
  el.style.transition = 'opacity .4s';
});
