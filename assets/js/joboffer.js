document.addEventListener('DOMContentLoaded', () => {
    const forms = [
        document.getElementById('createOfferForm'),
        document.getElementById('editOfferForm')
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
        const label = field.closest('.jo-fg, div')?.querySelector('.jo-fl, label');
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

        const fail = (message) => {
            const key = `${form.id}:${name}:${message}`;
            if (isBlur && field.dataset.warnedInvalid === '1') return false;
            if (isBlur) field.dataset.warnedInvalid = '1';
            showError(key, message);
            return false;
        };

        const requiredNames = new Set([
            'title', 'description', 'budget', 'skills_required', 'location',
            'experience_level', 'project_type', 'status', 'deadline_at'
        ]);

        if (requiredNames.has(name) && value === '') {
            return fail(`${label} is required.`);
        }

        if (name === 'client_id') {
            if (!field.closest('form')?.querySelector('select[name="client_id"]')) return true;
            if (value === '' || Number(value) <= 0) return fail('Client is required.');
        }

        if (value === '') return true;

        if (name === 'title') {
            if (value.length < 10 || value.length > 120) return fail('Title must be between 10 and 120 characters.');
            if (!/^[A-ZÀ-ÖØ-Þ]/.test(value)) return fail('Title must start with an uppercase letter.');
            if (!/^[A-Za-zÀ-ÖØ-öø-ÿ0-9 ,.!?:;()'"\/-]+$/.test(value)) return fail('Title contains invalid characters.');
        }

        if (name === 'description') {
            if (value.length < 40 || value.length > 3500) return fail('Description must be between 40 and 3500 characters.');
            if (!/^[A-ZÀ-ÖØ-Þ]/.test(value)) return fail('Description must start with an uppercase letter.');
        }

        if (name === 'skills_required') {
            if (value.length < 3 || value.length > 255) return fail('Skills must be between 3 and 255 characters.');
            if (!/^[A-Za-zÀ-ÖØ-öø-ÿ0-9,+.#()\/-\s]+$/.test(value)) return fail('Skills contains invalid characters.');
        }

        if (name === 'location') {
            if (value.length < 2 || value.length > 120) return fail('Location must be between 2 and 120 characters.');
            if (!/^[A-Za-zÀ-ÖØ-öø-ÿ0-9,.'\-\s]+$/.test(value)) return fail('Location contains invalid characters.');
        }

        if (name === 'budget') {
            const budget = Number(value);
            if (!Number.isFinite(budget) || budget <= 0) return fail('Budget must be greater than 0.');
            if (budget > 10000000) return fail('Budget is too high.');
        }

        if (name === 'deadline_at') {
            const date = parseDate(value);
            if (!date) return fail('Deadline date/time is invalid.');
            if (date <= new Date()) return fail('Deadline must be in the future.');
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
        });
    });
});
