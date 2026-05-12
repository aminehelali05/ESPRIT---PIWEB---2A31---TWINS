document.addEventListener('DOMContentLoaded', () => {
  const STYLE_ID = 'mvc-inline-validation-style';

  if (!document.getElementById(STYLE_ID)) {
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      .field-error,
      .error-message {
        display: block;
        min-height: 1.08rem;
        margin-top: 6px;
        padding-left: 2px;
        font-size: 0.72rem;
        line-height: 1.35;
        font-weight: 500;
        color: #be123c;
        white-space: pre-line;
        opacity: 1;
        transition: opacity 160ms ease, color 160ms ease;
      }
      .field-error.is-empty,
      .error-message.is-empty {
        visibility: hidden;
        opacity: 0;
      }
      input.is-invalid,
      select.is-invalid,
      textarea.is-invalid {
        border-color: rgba(225, 29, 72, 0.68) !important;
        box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.14) !important;
      }
      input.is-valid,
      select.is-valid,
      textarea.is-valid {
        border-color: rgba(59, 130, 246, 0.55) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
      }
      .uf-group.is-invalid .uf-label,
      .ct-modal-fg.is-invalid .ct-modal-fl,
      .ct-edit-grid > div.is-invalid .ct-edit-label,
      .jo-fg.is-invalid .jo-fl,
      .pj-fg.is-invalid .pj-fl,
      .field-grid > div.is-invalid label,
      .form-panel > div.is-invalid label,
      .action-stack.is-invalid label,
      .bo-signature-wrap.is-invalid label,
      .ct-signature-wrap.is-invalid .ct-modal-fl {
        color: #e11d48 !important;
      }
      button[type="submit"]:disabled,
      input[type="submit"]:disabled {
        opacity: 0.56;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
      }
      .mvc-signature-error {
        display: block;
        min-height: 1.08rem;
        margin-top: 6px;
        font-size: 0.72rem;
        line-height: 1.35;
        font-weight: 500;
        color: #be123c;
        white-space: pre-line;
        opacity: 1;
        transition: opacity 160ms ease;
      }
      .mvc-signature-error.is-empty {
        visibility: hidden;
        opacity: 0;
      }
    `;
    document.head.appendChild(style);
  }

  const toText = (value) => String(value ?? '').trim();
  const normalize = (value) => toText(value).replace(/\s+/g, ' ');
  const uppercaseStartPattern = /^[A-ZÀ-ÖØ-Þ]/;
  const textPattern = /^[A-Za-zÀ-ÖØ-öø-ÿ0-9 ,.!?:;()'"/&\-+]+$/;
  const techPattern = /^[A-Za-zÀ-ÖØ-öø-ÿ0-9 ,.+#()/_\-]+$/;
  const locationPattern = /^[A-Za-zÀ-ÖØ-öø-ÿ0-9,.'\-\s]+$/;
  const attachmentExtPattern = /\.(pdf|doc|docx|ppt|pptx|zip|rar|7z|png|jpg|jpeg|webp)$/i;
  const attachmentMimeSet = new Set([
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/x-rar-compressed',
    'application/x-7z-compressed',
    'image/png',
    'image/jpeg',
    'image/webp',
  ]);

  const allowed = {
    offerStatus: new Set(['open', 'in_progress', 'closed', 'archived']),
    offerType: new Set(['Fixed Price', 'Hourly', 'Retainer', 'Long-term']),
    offerExperience: new Set(['Junior', 'Mid', 'Senior', 'Expert']),
    projectStatus: new Set(['planning', 'active', 'completed', 'on_hold', 'archived']),
    projectVisibility: new Set(['team', 'public', 'private']),
    taskStatus: new Set(['todo', 'in_progress', 'blocked', 'done']),
  };

  const parseDate = (value) => {
    const text = toText(value);
    if (!text) return null;
    const date = new Date(`${text}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
  };

  const parseDateTime = (value) => {
    const text = toText(value);
    if (!text) return null;
    const date = new Date(text);
    return Number.isNaN(date.getTime()) ? null : date;
  };

  const normalizeNumber = (value) => {
    const raw = toText(value);
    if (raw === '') return null;
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : null;
  };

  const getFieldContainer = (field) =>
    field.closest('.ct-modal-fg, .ct-edit-grid > div, .jo-fg, .pj-fg, .field-grid > div, .form-panel > div, .action-stack, .bo-signature-wrap, .ct-signature-wrap, .field-grid, .form-panel')
    || field.parentElement;

  const isVisibleField = (field) => {
    if (!field) return false;
    if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return false;
    return true;
  };

  const getLabel = (field) => {
    const container = getFieldContainer(field);
    const label = container?.querySelector('label');
    return normalize(label?.textContent || field.name || 'Field').replace(/\*+$/, '').trim();
  };

  const ensureErrorNode = (field) => {
    const container = getFieldContainer(field);
    if (!container) return null;

    let node = field.nextElementSibling;
    while (node && !node.classList?.contains('field-error')) {
      node = node.nextElementSibling;
    }

    if (!node) {
      node = document.createElement('span');
      node.className = 'field-error error-message is-empty';
      node.textContent = ' ';
      node.setAttribute('aria-live', 'polite');
      field.insertAdjacentElement('afterend', node);
    }

    return node;
  };

  const ensureSignatureNode = (container) => {
    if (!container) return null;
    let node = container.querySelector(':scope > .mvc-signature-error');
    if (!node) {
      node = document.createElement('div');
      node.className = 'mvc-signature-error is-empty';
      node.textContent = ' ';
      node.setAttribute('aria-live', 'polite');
      container.appendChild(node);
    }
    return node;
  };

  const setFieldError = (field, messages, showState = true) => {
    const list = Array.isArray(messages) ? messages.filter(Boolean) : [];
    const node = ensureErrorNode(field);
    const container = getFieldContainer(field);
    const shouldMarkValid = showState && list.length === 0 && (field.required || toText(field.value) !== '' || field.type === 'file');

    if (node) {
      node.textContent = list.length > 0 ? list.join('\n') : ' ';
      node.classList.toggle('is-empty', list.length === 0);
    }

    field.classList.toggle('is-invalid', showState && list.length > 0);
    field.classList.toggle('is-valid', shouldMarkValid);
    field.setAttribute('aria-invalid', list.length > 0 ? 'true' : 'false');
    field.dataset.mvcInvalid = list.length > 0 ? '1' : '0';

    if (container) {
      container.classList.toggle('is-invalid', showState && list.length > 0);
      container.classList.toggle('is-valid', shouldMarkValid);
    }

    const ufGroup = field.closest('.uf-group');
    if (ufGroup) {
      ufGroup.classList.toggle('is-invalid', showState && list.length > 0);
      ufGroup.classList.toggle('is-valid', shouldMarkValid);
    }
  };

  const setSignatureError = (container, messages) => {
    const node = ensureSignatureNode(container);
    if (!node) return messages.length === 0;
    const list = Array.isArray(messages) ? messages.filter(Boolean) : [];
    node.textContent = list.length > 0 ? list.join('\n') : ' ';
    node.classList.toggle('is-empty', list.length === 0);
    container.classList.toggle('is-invalid', list.length > 0);
    return list.length === 0;
  };

  const getFiles = (field) => {
    if (!field || field.type !== 'file') return [];
    return Array.from(field.files || []);
  };

  const validateOfferField = (form, field) => {
    const name = field.name;
    const value = normalize(field.value);
    const messages = [];

    if (name === 'client_id' && toText(field.value) === '') {
      messages.push('Client is required.');
      return messages;
    }

    if (name === 'title') {
      if (value === '') messages.push('Job title is required.');
      if (value !== '' && (value.length < 10 || value.length > 140)) messages.push('Title must be between 10 and 140 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Title must start with an uppercase letter.');
      if (value !== '' && !textPattern.test(value)) messages.push('Title contains invalid characters.');
    }

    if (name === 'description') {
      if (value === '') messages.push('Description is required.');
      if (value !== '' && (value.length < 40 || value.length > 3500)) messages.push('Description must be between 40 and 3500 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Description must start with an uppercase letter.');
    }

    if (name === 'budget') {
      const budget = normalizeNumber(field.value);
      if (toText(field.value) === '') messages.push('Budget is required.');
      if (toText(field.value) !== '' && (budget === null || budget <= 0)) messages.push('Budget must be greater than 0.');
      if (budget !== null && budget > 10000000) messages.push('Budget is too high.');
    }

    if (name === 'skills_required' && value !== '') {
      if (value.length < 3 || value.length > 255) messages.push('Skills must be between 3 and 255 characters.');
      if (!techPattern.test(value)) messages.push('Skills contain invalid characters.');
    }

    if (name === 'location' && value !== '') {
      if (value.length < 2 || value.length > 120) messages.push('Location must be between 2 and 120 characters.');
      if (!locationPattern.test(value)) messages.push('Location contains invalid characters.');
    }

    if (name === 'deadline_at' && value !== '') {
      const deadline = parseDateTime(value);
      if (!deadline) messages.push('Deadline is invalid.');
      if (deadline && deadline <= new Date()) messages.push('Deadline must be in the future.');
    }

    if (name === 'status' && value !== '' && !allowed.offerStatus.has(value)) messages.push('Invalid offer status.');
    if (name === 'experience_level' && value !== '' && !allowed.offerExperience.has(value)) messages.push('Invalid experience level.');
    if (name === 'project_type' && value !== '' && !allowed.offerType.has(value)) messages.push('Invalid project type.');

    return messages;
  };

  const validateProjectField = (form, field) => {
    const name = field.name;
    const value = normalize(field.value);
    const messages = [];

    if (name === 'owner_id' && toText(field.value) === '') {
      messages.push('Owner is required.');
      return messages;
    }

    if (name === 'title') {
      if (value === '') messages.push('Project title is required.');
      if (value !== '' && (value.length < 10 || value.length > 140)) messages.push('Project title must be between 10 and 140 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Project title must start with an uppercase letter.');
      if (value !== '' && !textPattern.test(value)) messages.push('Project title contains invalid characters.');
    }

    if (name === 'short_description' && value !== '') {
      if (value.length < 15 || value.length > 220) messages.push('Short description must be between 15 and 220 characters.');
      if (!uppercaseStartPattern.test(value)) messages.push('Short description must start with an uppercase letter.');
    }

    if (name === 'description') {
      if (value === '') messages.push('Full description is required.');
      if (value !== '' && (value.length < 60 || value.length > 5000)) messages.push('Description must be between 60 and 5000 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Description must start with an uppercase letter.');
    }

    if (name === 'technologies' && value !== '') {
      if (value.length < 3 || value.length > 255) messages.push('Technologies must be between 3 and 255 characters.');
      if (!techPattern.test(value)) messages.push('Technologies contain invalid characters.');
    }

    if (name === 'budget' && toText(field.value) !== '') {
      const budget = normalizeNumber(field.value);
      if (budget === null || budget < 0) messages.push('Budget must be a valid positive number.');
      if (budget !== null && budget > 10000000) messages.push('Budget is too high.');
    }

    if (name === 'progress_percent' && toText(field.value) !== '') {
      const progress = normalizeNumber(field.value);
      if (!Number.isInteger(progress) || progress < 0 || progress > 100) messages.push('Progress must be an integer between 0 and 100.');
    }

    if (name === 'status' && value !== '' && !allowed.projectStatus.has(value)) messages.push('Invalid project status.');
    if (name === 'visibility' && value !== '' && !allowed.projectVisibility.has(value)) messages.push('Invalid visibility.');

    if (name === 'due_date' && value !== '') {
      const dueDate = parseDate(value);
      if (!dueDate) messages.push('Due date is invalid.');
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      if (dueDate && dueDate < today) messages.push('Due date cannot be in the past.');
    }

    return messages;
  };

  const validateTaskField = (form, field) => {
    const name = field.name;
    const value = normalize(field.value);
    const messages = [];

    if (['projet_id', 'project_id'].includes(name) && toText(field.value) === '') {
      messages.push('Project is required.');
      return messages;
    }

    if (name === 'title') {
      if (value === '') messages.push('Task title is required.');
      if (value !== '' && (value.length < 5 || value.length > 255)) messages.push('Task title must be between 5 and 255 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Task title must start with an uppercase letter.');
      if (value !== '' && !textPattern.test(value)) messages.push('Task title contains invalid characters.');
    }

    if (name === 'description') {
      if (value === '') messages.push('Task description is required.');
      if (value !== '' && (value.length < 20 || value.length > 4000)) messages.push('Task description must be between 20 and 4000 characters.');
    }

    if (name === 'status' && value !== '' && !allowed.taskStatus.has(value)) messages.push('Invalid task status.');

    if (name === 'deadline') {
      if (value === '') messages.push('Deadline is required.');
      if (value !== '') {
        const deadline = parseDate(value);
        if (!deadline) messages.push('Deadline is invalid.');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (deadline && deadline <= today) messages.push('Deadline must be after today.');
      }
    }

    return messages;
  };

  const validateContractField = (form, field) => {
    const name = field.name;
    const value = normalize(field.value);
    const messages = [];
    const startsAt = parseDateTime(form.querySelector('input[name="starts_at"]')?.value || '');
    const endsAt = parseDateTime(form.querySelector('input[name="ends_at"]')?.value || '');

    if (['application_pair', 'offer_id', 'client_id', 'freelancer_id'].includes(name) && toText(field.value) === '') {
      messages.push(`${getLabel(field)} is required.`);
      return messages;
    }

    if (name === 'amount') {
      const amount = normalizeNumber(field.value);
      if (toText(field.value) === '') messages.push('Amount is required.');
      if (toText(field.value) !== '' && (amount === null || amount <= 0)) messages.push('Amount must be greater than 0.');
      if (amount !== null && amount > 10000000) messages.push('Amount is too high.');
    }

    if (name === 'terms') {
      if (value === '') messages.push('Contract terms are required.');
      if (value !== '' && (value.length < 20 || value.length > 4000)) messages.push('Contract terms must be between 20 and 4000 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Contract terms must start with an uppercase letter.');
    }

    if (name === 'payment_details') {
      if (value === '') messages.push('Payment details are required.');
      if (value !== '' && (value.length < 5 || value.length > 2000)) messages.push('Payment details must be between 5 and 2000 characters.');
    }

    if (name === 'starts_at' && value !== '') {
      if (!parseDateTime(value)) messages.push('Start date is invalid.');
    }

    if (name === 'ends_at' && value !== '') {
      if (!parseDateTime(value)) messages.push('End date is invalid.');
      if (startsAt && endsAt && endsAt <= startsAt) messages.push('End date must be after the start date.');
    }

    return messages;
  };

  const validateRulesField = (form, field) => {
    const name = field.name;
    const value = normalize(field.value);
    const messages = [];

    if (name === 'contract_id' && toText(field.value) === '') {
      messages.push('Contract is required.');
      return messages;
    }

    if (name === 'rules_terms') {
      if (value === '') messages.push('Rules terms are required.');
      if (value !== '' && (value.length < 20 || value.length > 4000)) messages.push('Rules terms must be between 20 and 4000 characters.');
      if (value !== '' && !uppercaseStartPattern.test(value)) messages.push('Rules terms must start with an uppercase letter.');
    }

    if (name === 'rules_deadline') {
      if (value === '') messages.push('Rules deadline is required.');
      if (value !== '') {
        const deadline = parseDate(value);
        if (!deadline) messages.push('Rules deadline is invalid.');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (deadline && deadline <= today) messages.push('Rules deadline must be after today.');
      }
    }

    if (name === 'rules_payment_terms') {
      if (value === '') messages.push('Payment terms are required.');
      if (value !== '' && (value.length < 5 || value.length > 2000)) messages.push('Payment terms must be between 5 and 2000 characters.');
    }

    if (name === 'rules_penalties') {
      if (value === '') messages.push('Penalties are required.');
      if (value !== '' && (value.length < 5 || value.length > 2000)) messages.push('Penalties must be between 5 and 2000 characters.');
    }

    return messages;
  };

  const validateCandidatureField = (form, field) => {
    const name = field.name;
    const value = normalize(field.value);
    const messages = [];

    if (name === 'cover_letter' || name === 'message') {
      if (value === '') messages.push('Application message is required.');
      if (value !== '' && (value.length < 20 || value.length > 3000)) messages.push('Application message must be between 20 and 3000 characters.');
    }

    if (name === 'proposed_budget') {
      const budget = normalizeNumber(field.value);
      if (toText(field.value) === '') messages.push('Proposed budget is required.');
      if (toText(field.value) !== '' && (budget === null || budget <= 0)) messages.push('Proposed budget must be greater than 0.');
      if (budget !== null && budget > 10000000) messages.push('Proposed budget is too high.');
    }

    if (name === 'estimated_delivery_days') {
      const days = normalizeNumber(field.value);
      if (toText(field.value) === '') messages.push('Estimated delivery time is required.');
      if (!Number.isInteger(days) || days <= 0) messages.push('Estimated delivery time must be a whole number of days.');
      if (Number.isInteger(days) && days > 3650) messages.push('Estimated delivery time is too long.');
    }

    if (name === 'skills_experience') {
      if (value === '') messages.push('Skills and experience are required.');
      if (value !== '' && (value.length < 20 || value.length > 2000)) messages.push('Skills and experience must be between 20 and 2000 characters.');
    }

    if (field.type === 'file') {
      const files = getFiles(field);
      if (files.length > 3) messages.push('You can upload up to 3 attachments.');
      files.forEach((file) => {
        const fileName = String(file.name || '').trim();
        const fileType = String(file.type || '').trim();
        if (fileName && !attachmentExtPattern.test(fileName) && fileType && !attachmentMimeSet.has(fileType)) {
          messages.push(`Unsupported attachment: ${fileName}.`);
        }
        if (file.size > 10 * 1024 * 1024) {
          messages.push(`${fileName || 'Attachment'} must be smaller than 10 MB.`);
        }
      });
    }

    return messages;
  };

  const specs = [
    {
      matcher: (form) => ['createOfferForm', 'editOfferForm'].includes(form.id),
      validator: validateOfferField,
    },
    {
      matcher: (form) => ['createProjectForm', 'editProjectForm'].includes(form.id),
      validator: validateProjectField,
    },
    {
      matcher: (form) => ['createTaskForm', 'editTaskForm'].includes(form.id),
      validator: validateTaskField,
    },
    {
      matcher: (form) => form.id === 'applyOfferForm',
      validator: validateCandidatureField,
    },
    {
      matcher: (form) => form.id === 'createContractForm' || (toText(form.querySelector('input[name="action"]')?.value) === 'update_contract' && !!form.querySelector('textarea[name="terms"]')),
      validator: validateContractField,
    },
    {
      matcher: (form) => form.id === 'contractRulesForm' || toText(form.querySelector('input[name="action"]')?.value) === 'save_rules',
      validator: validateRulesField,
    },
  ];

  const getSpec = (form) => specs.find((spec) => spec.matcher(form)) || null;

  const fieldsForForm = (form) => Array.from(form.querySelectorAll('input, textarea, select')).filter((field) => isVisibleField(field) || field.type === 'file');

  const validateField = (form, field, spec, options = {}) => {
    const { forceShow = false, showState = true } = options;
    const touched = field.dataset.mvcTouched === '1' || form.dataset.mvcSubmitted === '1';
    const shouldShow = forceShow || touched || toText(field.value) !== '' || field.type === 'file';
    const messages = spec.validator(form, field);
    setFieldError(field, shouldShow ? messages : [], showState && shouldShow);
    return messages.length === 0;
  };

  const refreshFormValidity = (form, spec, options = {}) => {
    const { forceShow = false } = options;
    const fields = fieldsForForm(form);
    let formValid = true;

    fields.forEach((field) => {
      const isValid = validateField(form, field, spec, { forceShow });
      if (!isValid) {
        formValid = false;
      }
    });

    const submitButtons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
    submitButtons.forEach((button) => {
      button.disabled = !formValid;
      button.setAttribute('aria-disabled', formValid ? 'false' : 'true');
    });

    form.dataset.mvcValid = formValid ? '1' : '0';
    return formValid;
  };

  const forms = Array.from(document.querySelectorAll('form')).filter((form) => getSpec(form));

  forms.forEach((form) => {
    const spec = getSpec(form);
    if (!spec) return;

    form.setAttribute('novalidate', 'novalidate');

    const fields = fieldsForForm(form);
    fields.forEach((field) => {
      ensureErrorNode(field);

      const markTouched = () => {
        field.dataset.mvcTouched = '1';
      };

      const inputHandler = () => {
        markTouched();
        validateField(form, field, spec, { forceShow: true });
        refreshFormValidity(form, spec);
      };

      field.addEventListener('input', inputHandler);
      field.addEventListener('change', inputHandler);
      field.addEventListener('blur', () => {
        markTouched();
        validateField(form, field, spec, { forceShow: true });
        refreshFormValidity(form, spec);
      });
    });

    refreshFormValidity(form, spec);

    form.addEventListener('submit', (event) => {
      form.dataset.mvcSubmitted = '1';
      const valid = refreshFormValidity(form, spec, { forceShow: true });
      if (!valid) {
        event.preventDefault();
        event.stopPropagation();
        const firstInvalid = fields.find((field) => field.dataset.mvcInvalid === '1');
        firstInvalid?.focus();
      }
    });
  });

  window.MVCInlineValidation = {
    normalize,
    parseDate,
    parseDateTime,
    setSignatureError,
    ensureSignatureNode,
  };
});
