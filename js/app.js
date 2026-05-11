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

// Auto-dismiss alerts after 4s
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => el.style.opacity = '0', 3800);
  setTimeout(() => el.remove(), 4200);
  el.style.transition = 'opacity .4s';
});
