document.addEventListener('DOMContentLoaded', () => {
  const root = document.documentElement;
  const themeKey = 'vop-user-theme';
  const savedTheme = localStorage.getItem(themeKey);
  if (savedTheme === 'dark' || savedTheme === 'light') {
    root.setAttribute('data-theme', savedTheme);
  } else if (!root.getAttribute('data-theme')) {
    root.setAttribute('data-theme', 'dark');
  }

  document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', nextTheme);
      localStorage.setItem(themeKey, nextTheme);
    });
  });

  document.querySelectorAll('[data-animate-progress]').forEach((bar) => {
    const value = Number(bar.dataset.progress || 0);
    requestAnimationFrame(() => {
      bar.style.width = `${Math.max(0, Math.min(100, value))}%`;
    });
  });

  const registerPassword = document.getElementById('password');
  const registerConfirmPassword = document.getElementById('confirmPassword');
  const passwordMeter = document.getElementById('passwordMeter');

  if (registerPassword && passwordMeter) {
    registerPassword.addEventListener('input', () => {
      const value = registerPassword.value;
      let score = 0;
      if (value.length >= 8) score += 25;
      if (/[A-Z]/.test(value)) score += 25;
      if (/[0-9]/.test(value)) score += 25;
      if (/[^A-Za-z0-9]/.test(value)) score += 25;
      passwordMeter.dataset.progress = String(score);
      passwordMeter.style.width = `${score}%`;
    });
  }

  if (registerConfirmPassword) {
    registerConfirmPassword.addEventListener('input', () => {
      if (!registerPassword) return;
      const mismatch = registerConfirmPassword.value && registerPassword.value !== registerConfirmPassword.value;
      registerConfirmPassword.style.borderColor = mismatch ? '#ef4444' : '';
    });
  }

  document.querySelectorAll('[data-confirm-action]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const question = form.getAttribute('data-confirm-action') || 'Are you sure?';
      if (!window.confirm(question)) {
        event.preventDefault();
      }
    });
  });
});