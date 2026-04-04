document.addEventListener('DOMContentLoaded', () => {
  const toastEl = document.getElementById('auth-toast');
  const flashEl = document.getElementById('serverFlash');
  const authCard = document.getElementById('auth-card');
  const showRegister = document.getElementById('show-register');
  const showLogin = document.getElementById('show-login');
  const demoBtn = document.getElementById('demo-login-btn');
  const faceLoginBtn = document.getElementById('face-login-btn');
  const loginEmail = document.getElementById('login-email');
  const loginPassword = document.getElementById('login-password');
  const humanCheck = document.getElementById('human-check');
  const networkCanvas = document.getElementById('auth-network-canvas');

  const showToast = (message, type = 'success') => {
    if (!toastEl || !message) {
      return;
    }
    toastEl.textContent = message;
    toastEl.className = `auth-toast show ${type}`;
    window.setTimeout(() => toastEl.classList.remove('show'), 2800);
  };

  if (flashEl) {
    const errorMsg = (flashEl.dataset.error || '').trim();
    const successMsg = (flashEl.dataset.success || '').trim();
    if (errorMsg) {
      showToast(errorMsg, 'error');
    } else if (successMsg) {
      showToast(successMsg, 'success');
    }
  }

  if (showRegister) {
    showRegister.addEventListener('click', (e) => {
      e.preventDefault();
      authCard.classList.add('flipped');
      history.replaceState(null, '', 'auth.php?mode=register');
    });
  }

  if (showLogin) {
    showLogin.addEventListener('click', (e) => {
      e.preventDefault();
      authCard.classList.remove('flipped');
      history.replaceState(null, '', 'auth.php?mode=login');
    });
  }

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.dataset.togglePassword;
      const targetInput = targetId ? document.getElementById(targetId) : null;
      if (!targetInput) {
        return;
      }
      const isPassword = targetInput.type === 'password';
      targetInput.type = isPassword ? 'text' : 'password';
      const icon = button.querySelector('i');
      if (icon) {
        icon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
      }
      if (window.lucide?.createIcons) {
        window.lucide.createIcons();
      }
    });
  });

  if (demoBtn && loginEmail && loginPassword) {
    demoBtn.addEventListener('click', () => {
      loginEmail.value = 'admin';
      loginPassword.value = 'admin';
      showToast('Demo credentials filled.', 'success');
      loginEmail.focus();
      if (humanCheck) {
        humanCheck.classList.add('human-check-pass');
      }
    });
  }

  if (faceLoginBtn) {
    faceLoginBtn.addEventListener('click', () => {
      showToast('Face login module will be connected next.', 'success');
    });
  }

  const regPassword = document.getElementById('reg-password');
  const strengthBar = document.querySelector('.strength-bar');

  if (regPassword && strengthBar) {
    regPassword.addEventListener('input', () => {
      const val = regPassword.value;
      strengthBar.classList.remove('weak', 'medium', 'strong');

      if (val.length === 0) {
        strengthBar.style.width = '0%';
        return;
      }

      let score = 0;
      if (val.length >= 8) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      if (score <= 1) strengthBar.classList.add('weak');
      else if (score <= 2) strengthBar.classList.add('medium');
      else strengthBar.classList.add('strong');
    });
  }

  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');
  const confirmPassword = document.getElementById('confirm-password');

  [loginForm, registerForm].forEach((form) => {
    if (form) {
      form.addEventListener('submit', (event) => {
        if (form.id === 'register-form' && regPassword && confirmPassword) {
          if (regPassword.value !== confirmPassword.value) {
            event.preventDefault();
            showToast('Passwords do not match.', 'error');
            confirmPassword.focus();
            return;
          }
        }

        const btn = form.querySelector('.auth-submit');
        if (btn) {
          btn.classList.add('loading');
          btn.disabled = true;
        }

        if (form.id === 'login-form' && humanCheck) {
          humanCheck.classList.add('human-check-pass');
        }
      });
    }
  });

  document.querySelectorAll('.form-input').forEach((input) => {
    input.addEventListener('focus', () => {
      input.closest('.form-group')?.classList.add('focused');
    });
    input.addEventListener('blur', () => {
      input.closest('.form-group')?.classList.remove('focused');
    });
  });

  const animateNetwork = () => {
    if (!networkCanvas) {
      return;
    }
    const ctx = networkCanvas.getContext('2d');
    if (!ctx) {
      return;
    }

    const points = [];
    const targetCount = Math.min(48, Math.floor(window.innerWidth / 35));

    const resize = () => {
      networkCanvas.width = window.innerWidth;
      networkCanvas.height = window.innerHeight;
      while (points.length < targetCount) {
        points.push({
          x: Math.random() * networkCanvas.width,
          y: Math.random() * networkCanvas.height,
          vx: (Math.random() - 0.5) * 0.35,
          vy: (Math.random() - 0.5) * 0.35
        });
      }
    };

    const tick = () => {
      ctx.clearRect(0, 0, networkCanvas.width, networkCanvas.height);

      for (let i = 0; i < points.length; i += 1) {
        const point = points[i];
        point.x += point.vx;
        point.y += point.vy;

        if (point.x < 0 || point.x > networkCanvas.width) point.vx *= -1;
        if (point.y < 0 || point.y > networkCanvas.height) point.vy *= -1;

        ctx.beginPath();
        ctx.arc(point.x, point.y, 1.7, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(148, 197, 255, 0.75)';
        ctx.fill();

        for (let j = i + 1; j < points.length; j += 1) {
          const pair = points[j];
          const dx = point.x - pair.x;
          const dy = point.y - pair.y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 130) {
            const opacity = 1 - dist / 130;
            ctx.beginPath();
            ctx.moveTo(point.x, point.y);
            ctx.lineTo(pair.x, pair.y);
            ctx.strokeStyle = `rgba(56, 189, 248, ${opacity * 0.16})`;
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }

      requestAnimationFrame(tick);
    };

    resize();
    window.addEventListener('resize', resize);
    tick();
  };

  animateNetwork();

  if (window.lucide?.createIcons) {
    window.lucide.createIcons();
  }
});
