// Mobile sidebar toggle
const navToggle = document.getElementById('navToggle');
const nav = document.getElementById('nav');
if (navToggle && nav) {
  navToggle.addEventListener('click', () => nav.classList.toggle('hidden'));
}

// Auto-hide flash messages after 6 seconds
document.querySelectorAll('[data-flash]').forEach((el) => {
  setTimeout(() => el.remove(), 6000);
});

// OTP input: digits only, auto-submit when 6 digits are entered
const otp = document.getElementById('otp');
if (otp) {
  otp.addEventListener('input', () => {
    otp.value = otp.value.replace(/\D/g, '').slice(0, 6);
    if (otp.value.length === 6) otp.form.requestSubmit();
  });
}

// Resend-code countdown
const resend = document.getElementById('resendBtn');
if (resend) {
  let left = parseInt(resend.dataset.wait || '0', 10);
  const label = resend.textContent;
  const tick = () => {
    if (left > 0) {
      resend.disabled = true;
      resend.textContent = `${label} (${left}s)`;
      left--;
      setTimeout(tick, 1000);
    } else {
      resend.disabled = false;
      resend.textContent = label;
    }
  };
  tick();
}

// Confirm dangerous actions: <form data-confirm="Are you sure?">
document.querySelectorAll('form[data-confirm]').forEach((f) => {
  f.addEventListener('submit', (e) => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  });
});

// Client-side table filter: <input data-filter="#tableId">
document.querySelectorAll('input[data-filter]').forEach((input) => {
  const rows = () => document.querySelectorAll(input.dataset.filter + ' tbody tr');
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    rows().forEach((r) => (r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none'));
  });
});