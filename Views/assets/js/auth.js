/* ============================================================
   AUTH.JS — Login/Register flip card & form interactions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  const authCard = document.getElementById('auth-card');
  const showRegister = document.getElementById('show-register');
  const showLogin = document.getElementById('show-login');

  // --- Toggle Flip ---
  if (showRegister) {
    showRegister.addEventListener('click', (e) => {
      e.preventDefault();
      authCard.classList.add('flipped');
    });
  }
  
  if (showLogin) {
    showLogin.addEventListener('click', (e) => {
      e.preventDefault();
      authCard.classList.remove('flipped');
    });
  }

  // --- Password Strength Meter ---
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

  // --- Form Submit (prevent default for demo) ---
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');
  
  [loginForm, registerForm].forEach(form => {
    if (form) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const btn = form.querySelector('.auth-submit');
        const originalText = btn.textContent;
        btn.textContent = 'Loading...';
        btn.style.opacity = '0.7';
        btn.style.pointerEvents = 'none';
        
        setTimeout(() => {
          btn.textContent = '✓ Success!';
          btn.style.background = 'linear-gradient(135deg, #22C55E, #16A34A)';
          
          setTimeout(() => {
            window.location.href = 'profile.html';
          }, 800);
        }, 1500);
      });
    }
  });

  // --- Input focus animation ---
  document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('focus', () => {
      input.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', () => {
      input.parentElement.classList.remove('focused');
    });
  });
});
