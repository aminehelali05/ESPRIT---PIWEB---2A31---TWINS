document.addEventListener('DOMContentLoaded', () => {
    const forms = [
        document.getElementById('createContractForm'),
        ...Array.from(document.querySelectorAll('form')).filter((form) => {
            const action = (form.querySelector('input[name="action"]')?.value || '').toLowerCase().trim();
            return action === 'update_contract';
        })
    ].filter(Boolean);

    if (!forms.length) return;

    let lastAlertKey = '';
    let lastAlertAt = 0;

    const showError = (key, message) => {
        const now = Date.now();
        if (lastAlertKey === key && now - lastAlertAt < 2000) return;
        if (window.Swal && typeof window.Swal.isVisible === 'function' && window.Swal.isVisible()) return;
        lastAlertKey = key;
        lastAlertAt = now;

        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: message,
                confirmButtonColor: '#6366f1'
            });
        } else {
            alert(message);
        }
    };

    const clearWarnFlag = (field) => {
        if (!field) return;
        field.dataset.warnedInvalid = '';
    };

    const getLabel = (field) => {
        const label = field.closest('.ct-edit-grid > div, .field-grid > div, div')?.querySelector('.ct-edit-label, label');
        return (label?.textContent || field.name || 'Field').replace('*', '').trim();
    };

    const parseDate = (value) => {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const validateField = (form, field, mode = 'submit') => {
        if (!field || field.type === 'hidden') return true;
        const name = field.name;
        const label = getLabel(field);
        const value = (field.value || '').trim();
        const isBlur = mode === 'blur';
        const hasCreateAction = (form.querySelector('input[name="action"]')?.value || '').toLowerCase() === 'create_contract';

        const fail = (message) => {
            const key = `${form.id || 'contractForm'}:${name}:${message}`;
            if (isBlur && field.dataset.warnedInvalid === '1') return false;
            if (isBlur) field.dataset.warnedInvalid = '1';
            showError(key, message);
            return false;
        };

        const detailRequired = new Set(['amount', 'terms', 'payment_details']);
        const createOnlyRequired = new Set([]);

        const isRequired = (detailRequired.has(name) && !!form.querySelector(`[name="${name}"]`))
            || (hasCreateAction && createOnlyRequired.has(name));

        if (isRequired && value === '') {
            return fail(`${label} is required.`);
        }

        if (value === '') return true;

        if (name === 'amount') {
            const amount = Number(value);
            if (!Number.isFinite(amount) || amount <= 0) return fail('Amount must be greater than 0.');
            if (amount > 10000000) return fail('Amount is too high.');
        }

        if (name === 'terms') {
            if (value.length < 40 || value.length > 4000) return fail('Terms must be between 40 and 4000 characters.');
            if (!/^[A-ZÀ-ÖØ-Þ]/.test(value)) return fail('Terms must start with an uppercase letter.');
        }

        if (name === 'starts_at' || name === 'ends_at') {
            if (!parseDate(value)) return fail(`${label} is invalid.`);
        }

        return true;
    };

    const validateFormRelations = (form) => {
        const starts = form.querySelector('[name="starts_at"]')?.value?.trim() || '';
        const ends = form.querySelector('[name="ends_at"]')?.value?.trim() || '';

        const dStart = starts ? parseDate(starts) : null;
        const dEnd = ends ? parseDate(ends) : null;

        if (dStart && dEnd && dEnd <= dStart) {
            showError(`${form.id}:end-before-start`, 'End date/time must be after start date/time.');
            return false;
        }

        return true;
    };

    forms.forEach((form) => {
        const fields = form.querySelectorAll('input, textarea, select');

        fields.forEach((field) => {
            if (field.type !== 'hidden') {
                field.setAttribute('required', 'required');
            }

            field.addEventListener('input', () => clearWarnFlag(field));
            field.addEventListener('change', () => clearWarnFlag(field));
            field.addEventListener('blur', () => {
                validateField(form, field, 'blur');
            });
        });

        form.addEventListener('submit', (event) => {
            for (const field of fields) {
                if (!validateField(form, field, 'submit')) {
                    event.preventDefault();
                    event.stopPropagation();
                    field.focus();
                    return;
                }
            }

            if (!validateFormRelations(form)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });
});
