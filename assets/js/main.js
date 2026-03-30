/**
 * Resource Hub — Shared JavaScript Utilities
 * assets/js/main.js
 */

// Toggle password visibility
function togglePassword(inputId, btnId) {
  const input = document.getElementById(inputId);
  const btn   = document.getElementById(btnId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    if (btn) btn.textContent = '🙈';
  } else {
    input.type = 'password';
    if (btn) btn.textContent = '👁️';
  }
}

// Password strength checker
function checkPasswordStrength(val, barId, labelId) {
  const bar   = document.getElementById(barId);
  const label = document.getElementById(labelId);
  if (!bar || !label) return;

  let score = 0;
  if (val.length >= 8)            score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { w: '0%',   bg: 'transparent', text: 'Enter a password' },
    { w: '25%',  bg: '#f87171',     text: 'Weak' },
    { w: '50%',  bg: '#fb923c',     text: 'Fair' },
    { w: '75%',  bg: '#facc15',     text: 'Good' },
    { w: '100%', bg: '#4ade80',     text: 'Strong ✓' },
  ];
  const l = val.length === 0 ? levels[0] : levels[score];
  bar.style.width      = l.w;
  bar.style.background = l.bg;
  label.textContent    = l.text;
  label.style.color    = l.bg === 'transparent' ? 'var(--muted)' : l.bg;
}

// File size formatter
function formatFileSize(bytes) {
  if (bytes < 1024)      return bytes + ' B';
  if (bytes < 1048576)   return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

// Auto-dismiss alerts after 4 seconds
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert-success, .msg').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
});