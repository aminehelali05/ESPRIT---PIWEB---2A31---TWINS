(() => {
  const showInlineError = (field, message) => {
    field.style.borderColor = 'rgba(225, 29, 72, 0.55)';
    
    const group = field.closest('.uf-group') || field.parentElement;
    group.classList.add('has-error');

    let errorSpan = group.querySelector('.field-error');
    if (!errorSpan) {
        errorSpan = document.createElement('span');
        errorSpan.className = 'field-error';
        group.appendChild(errorSpan);
    }
    errorSpan.textContent = message;
  };

  const clearInlineError = (field) => {
    field.style.borderColor = '';
    const group = field.closest('.uf-group') || field.parentElement;
    group.classList.remove('has-error');

    const errorSpan = group.querySelector('.field-error');
    if (errorSpan) {
        errorSpan.remove();
    }
  };

  const validateField = (field, silent = false) => {
    if (!field || field.disabled) return true;

    const key = (field.name || field.id || '').toLowerCase();
    const value = String(field.value || '').trim();
    let message = '';

    const isGibberish = (text) => {
      if (/(.)\1{4,}/.test(text)) return true; // Repeated chars
      if (/[^aeiouyAEIOUY\s\d\W]{6,}/.test(text)) return true; // Consonant clusters
      if (/[0-9]{5,}/.test(text)) return true; // 5+ consecutive digits
      return false;
    };

    const hasProfanity = (text) => {
      const blacklist = ['badword1', 'badword2', 'merde', 'putain', 'salope', 'connard'];
      const lowered = text.toLowerCase();
      return blacklist.some(word => lowered.includes(word));
    };

    if (key === 'title' || key === 'formtitle') {
      if (!value) {
          message = 'Ce champ ne peut pas être vide.';
      } else if (value.length < 10) {
        message = 'Title must be at least 10 characters.';
      } else if (value.length > 200) {
        message = 'Title is too long (max 200 chars).';
      } else if ((value.match(/[a-zA-Z]/g) || []).length < 5) {
        message = 'Title must contain at least 5 letters.';
      } else if (isGibberish(value)) {
        message = 'Title seems to contain invalid patterns (gibberish).';
      } else if (hasProfanity(value)) {
        message = 'Title contains inappropriate language.';
      }
    }

    if (key === 'description' || key === 'formdescription') {
      if (!value) {
         message = 'Ce champ ne peut pas être vide.';
      } else if (value.length < 20) {
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
      if (!silent) {
        showInlineError(field, message);
      } else {
        field.style.borderColor = 'rgba(225, 29, 72, 0.55)';
      }
      return false;
    }

    field.dataset.invalid = '0';
    clearInlineError(field);
    return true;
  };

  const validateForm = (form) => {
    if (!form) return true;
    const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter((f) => {
      return f.type !== 'hidden' && f.type !== 'button' && f.type !== 'submit';
    });

    let isValid = true;
    let firstInvalidField = null;

    for (const field of fields) {
      if (!validateField(field, false)) {
        isValid = false;
        if (!firstInvalidField) firstInvalidField = field;
      }
    }
    
    if (!isValid && firstInvalidField) {
        firstInvalidField.focus();
    }
    return isValid;
  };

  const attachLiveValidation = (form) => {
    if (!form) return;
    const fields = form.querySelectorAll('input, select, textarea');
    fields.forEach((field) => {
      field.addEventListener('blur', () => validateField(field, false));
      field.addEventListener('input', () => {
         if (field.dataset.invalid === '1') {
             validateField(field, true);
         }
      });
      field.addEventListener('change', () => validateField(field, false));
      
      // CRITICAL: Stop browser bubbles for native validation
      field.addEventListener('invalid', (e) => {
          e.preventDefault();
      }, true);
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
