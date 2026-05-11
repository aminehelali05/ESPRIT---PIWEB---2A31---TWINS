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
  const particleCanvas = document.getElementById('particleCanvas');
  const themeToggleAuth = document.getElementById('theme-toggle-auth');
  const glassCard = document.querySelector('.auth-integrated-body .glass-card');

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

  const setTheme = (theme) => {
    document.documentElement.setAttribute('data-theme', theme);
    if (themeToggleAuth) {
      const icon = themeToggleAuth.querySelector('i');
      if (icon) {
        icon.setAttribute('data-lucide', theme === 'dark' ? 'moon' : 'sun');
      }
    }
    if (window.lucide?.createIcons) {
      window.lucide.createIcons();
    }
  };

  try {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light' || savedTheme === 'dark') {
      setTheme(savedTheme);
    }
  } catch (error) {
    console.warn('Unable to read saved theme.', error);
  }

  if (themeToggleAuth) {
    themeToggleAuth.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
      const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
      setTheme(nextTheme);
      try {
        localStorage.setItem('theme', nextTheme);
      } catch (error) {
        console.warn('Unable to persist theme.', error);
      }
    });
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

  const animateParticles = () => {
    if (!particleCanvas) {
      return;
    }
    const ctx = particleCanvas.getContext('2d');
    if (!ctx) {
      return;
    }

    let particles = [];
    const particleCount = Math.min(90, Math.floor(window.innerWidth / 18));

    const resize = () => {
      particleCanvas.width = window.innerWidth;
      particleCanvas.height = window.innerHeight;
      particles = Array.from({ length: particleCount }, () => ({
        x: Math.random() * particleCanvas.width,
        y: Math.random() * particleCanvas.height,
        vx: (Math.random() - 0.5) * 0.28,
        vy: (Math.random() - 0.5) * 0.28,
        size: Math.random() * 2 + 0.6
      }));
    };

    const draw = () => {
      ctx.clearRect(0, 0, particleCanvas.width, particleCanvas.height);

      particles.forEach((particle) => {
        particle.x += particle.vx;
        particle.y += particle.vy;

        if (particle.x <= 0 || particle.x >= particleCanvas.width) particle.vx *= -1;
        if (particle.y <= 0 || particle.y >= particleCanvas.height) particle.vy *= -1;

        ctx.beginPath();
        ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(96, 165, 250, 0.55)';
        ctx.fill();
      });

      for (let i = 0; i < particles.length; i += 1) {
        for (let j = i + 1; j < particles.length; j += 1) {
          const dx = particles[i].x - particles[j].x;
          const dy = particles[i].y - particles[j].y;
          const dist = Math.hypot(dx, dy);
          if (dist < 120) {
            const alpha = (1 - dist / 120) * 0.2;
            ctx.beginPath();
            ctx.moveTo(particles[i].x, particles[i].y);
            ctx.lineTo(particles[j].x, particles[j].y);
            ctx.strokeStyle = `rgba(56, 189, 248, ${alpha})`;
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }

      requestAnimationFrame(draw);
    };

    resize();
    window.addEventListener('resize', resize);
    draw();
  };

  animateNetwork();
  animateParticles();

  if (glassCard) {
    document.addEventListener('mousemove', (event) => {
      const rect = glassCard.getBoundingClientRect();
      const insideX = event.clientX - rect.left;
      const insideY = event.clientY - rect.top;

      if (insideX < 0 || insideY < 0 || insideX > rect.width || insideY > rect.height) {
        glassCard.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg)';
        return;
      }

      const rotateY = ((insideX / rect.width) - 0.5) * 7;
      const rotateX = (0.5 - (insideY / rect.height)) * 7;
      glassCard.style.transform = `perspective(900px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg)`;
    });

    document.addEventListener('mouseleave', () => {
      glassCard.style.transform = 'perspective(900px) rotateX(0deg) rotateY(0deg)';
    });
  }

  if (window.lucide?.createIcons) {
    window.lucide.createIcons();
  }
});
