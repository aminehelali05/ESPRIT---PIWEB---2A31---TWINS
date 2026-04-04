(function () {
  function initValidation(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    form.addEventListener('submit', function (event) {
      let valid = true;
      const fields = form.querySelectorAll('[data-validate]');

      fields.forEach(function (field) {
        const checks = String(field.dataset.validate || '').split('|');
        const value = (field.value || '').trim();
        let fieldValid = true;

        checks.forEach(function (check) {
          if (check === 'required' && value.length === 0) fieldValid = false;
          if (check === 'email' && value.length > 0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) fieldValid = false;
          if (check === 'password' && value.length > 0 && value.length < 6) fieldValid = false;
        });

        const error = form.querySelector('[data-error-for="' + field.name + '"]');
        if (!fieldValid) {
          valid = false;
          field.style.borderColor = 'rgba(251,113,133,.65)';
          field.style.boxShadow = '0 0 0 3px rgba(251,113,133,.14)';
          if (error) error.classList.add('show');
        } else {
          field.style.borderColor = 'rgba(255,255,255,.14)';
          field.style.boxShadow = 'none';
          if (error) error.classList.remove('show');
        }
      });

      if (!valid) {
        event.preventDefault();
      }
    });
  }

  function initDeleteConfirm(formSelector, message) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    form.addEventListener('submit', function (event) {
      if (!window.confirm(message || 'Are you sure?')) {
        event.preventDefault();
      }
    });
  }

  function initModal(openSelector, modalSelector, closeSelector) {
    const modal = document.querySelector(modalSelector);
    if (!modal) return;

    document.querySelectorAll(openSelector).forEach(function (button) {
      button.addEventListener('click', function () {
        modal.classList.add('show');
      });
    });

    document.querySelectorAll(closeSelector).forEach(function (button) {
      button.addEventListener('click', function () {
        modal.classList.remove('show');
      });
    });

    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        modal.classList.remove('show');
      }
    });
  }

  function initLiveSearch(inputSelector, rowSelector) {
    const input = document.querySelector(inputSelector);
    if (!input) return;

    input.addEventListener('input', function () {
      const q = input.value.trim().toLowerCase();
      document.querySelectorAll(rowSelector).forEach(function (row) {
        const content = row.textContent.toLowerCase();
        row.style.display = content.indexOf(q) >= 0 ? '' : 'none';
      });
    });
  }

  function initToasts() {
    const toast = document.querySelector('.us-toast');
    if (!toast) return;
    const text = toast.dataset.message || '';
    if (!text) return;

    toast.textContent = text;
    toast.classList.add('show');
    setTimeout(function () {
      toast.classList.remove('show');
    }, 2600);
  }

  function initPasswordMeter(inputSelector, targetSelector) {
    const input = document.querySelector(inputSelector);
    const target = document.querySelector(targetSelector);
    if (!input || !target) return;

    input.addEventListener('input', function () {
      const value = input.value || '';
      let score = 0;
      if (value.length >= 6) score++;
      if (/[A-Z]/.test(value)) score++;
      if (/[0-9]/.test(value)) score++;
      if (/[^a-zA-Z0-9]/.test(value)) score++;

      const levels = ['Very weak', 'Weak', 'Medium', 'Strong', 'Excellent'];
      const colors = ['#fda4af', '#fb7185', '#facc15', '#60a5fa', '#34d399'];
      target.textContent = value.length === 0 ? '' : levels[score];
      target.style.color = colors[score];
    });
  }

  window.UserSystem = {
    initValidation: initValidation,
    initDeleteConfirm: initDeleteConfirm,
    initModal: initModal,
    initLiveSearch: initLiveSearch,
    initToasts: initToasts,
    initPasswordMeter: initPasswordMeter
  };
})();
