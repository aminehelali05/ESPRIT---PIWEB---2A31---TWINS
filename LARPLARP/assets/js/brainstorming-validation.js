(() => {
  const state = {
    lastAlertKey: '',
  };

  const pushAlert = (message, key = '') => {
    const currentKey = `${key}::${message}`;
    if (state.lastAlertKey === currentKey) return;
    state.lastAlertKey = currentKey;

    if (window.Swal) {
      window.Swal.fire({
        toast: true,
        icon: 'error',
        title: message,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
        background: '#1e293b',
        color: '#f8fafc',
        customClass: { container: 'uf-swal-front' },
      });
      return;
    }

    const fallback = document.createElement('div');
    fallback.textContent = message;
    fallback.style.cssText = [
      'position:fixed',
      'top:14px',
      'right:14px',
      'z-index:12000',
      'background:#1e293b',
      'color:#f8fafc',
      'border:1px solid rgba(239,68,68,0.45)',
      'padding:10px 12px',
      'border-radius:12px',
      'font-size:12px',
      'box-shadow:0 10px 26px rgba(15,23,42,0.35)',
    ].join(';');

    document.body.appendChild(fallback);
    setTimeout(() => fallback.remove(), 2600);
  };

  const validateField = (field, silent = false) => {
    if (!field || field.disabled) return true;

    const key = (field.name || field.id || '').toLowerCase();
    const value = String(field.value || '').trim();
    let message = '';

    const isGibberish = (text) => {
      if (/(.)\1{4,}/.test(text)) return true; // Repeated chars
      if (/[^aeiouyAEIOUY\s\d\W]{6,}/.test(text)) return true; // Consonant clusters
      return false;
    };

    const hasProfanity = (text) => {
      const blacklist = ['badword1', 'badword2', 'merde', 'putain', 'salope', 'connard'];
      const lowered = text.toLowerCase();
      return blacklist.some(word => lowered.includes(word));
    };

    if (key === 'title' || key === 'formtitle') {
      if (value.length < 10) {
        message = 'Title must be at least 10 characters.';
      } else if (value.length > 200) {
        message = 'Title is too long (max 200 chars).';
      } else if (!/[a-zA-Z]/.test(value)) {
        message = 'Title must contain letters (cannot be only numbers).';
      } else if (isGibberish(value)) {
        message = 'Title seems to contain invalid patterns (gibberish).';
      } else if (hasProfanity(value)) {
        message = 'Title contains inappropriate language.';
      }
    }

    if (key === 'description' || key === 'formdescription') {
      if (value.length < 20) {
        message = 'Description must be at least 20 characters.';
      } else if (!/[a-zA-Z]/.test(value)) {
        message = 'Description must contain letters.';
      } else if (isGibberish(value)) {
        message = 'Description contains invalid patterns.';
      } else if (hasProfanity(value)) {
        message = 'Description contains inappropriate language.';
      }
    }

    if (key === 'content' || key === 'formcontent') {
        if (!value) {
            message = 'Idea content cannot be empty.';
        }
    }

    if (message) {
      field.dataset.invalid = '1';
      field.style.borderColor = 'rgba(225, 29, 72, 0.55)';
      if (!silent) {
        pushAlert(message, key);
      }
      return false;
    }

    field.dataset.invalid = '0';
    field.style.borderColor = '';
    return true;
  };

  const validateForm = (form) => {
    if (!form) return true;
    const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter((f) => {
      return f.type !== 'hidden' && f.type !== 'button' && f.type !== 'submit';
    });

    for (const field of fields) {
      if (!validateField(field, false)) {
        field.focus();
        return false;
      }
    }
    return true;
  };

  const attachLiveValidation = (form) => {
    if (!form) return;
    const fields = form.querySelectorAll('input, select, textarea');
    fields.forEach((field) => {
      const run = () => validateField(field, false);
      field.addEventListener('blur', run);
      field.addEventListener('change', run);
    });
  };

  const init = () => {
    const forms = document.querySelectorAll('#brainstormingForm, #ideaForm, #adminBrainstormingForm');
    forms.forEach((form) => {
      form.setAttribute('novalidate', 'novalidate');
      attachLiveValidation(form);
      form.addEventListener('submit', (e) => {
        if (!validateForm(form)) {
          e.preventDefault();
        }
      });
    });
  };

  document.addEventListener('DOMContentLoaded', init);

  window.BrainstormingValidation = {
      validateField,
      validateForm,
      attachLiveValidation
  };
})();
