document.addEventListener('DOMContentLoaded', () => {
    const forms = [
        document.getElementById('createProjectForm'),
        document.getElementById('editProjectForm')
    ].filter(Boolean);

    if (!forms.length) return;

    let lastAlertKey = '';
    let lastAlertAt = 0;

    const allowedStatus = new Set(['planning', 'active', 'completed', 'on_hold', 'archived']);
    const allowedVisibility = new Set(['team', 'public', 'private']);

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
        const label = field.closest('.pj-fg, div')?.querySelector('.pj-fl, label');
        return (label?.textContent || field.name || 'Field').replace('*', '').trim();
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
            'title', 'short_description', 'description', 'technologies', 'status',
            'visibility', 'budget', 'due_date', 'progress_percent'
        ]);

        if (requiredNames.has(name) && value === '') {
            return fail(`${label} is required.`);
        }

        if (name === 'owner_id') {
            if (!form.querySelector('select[name="owner_id"]')) return true;
            if (value === '' || Number(value) <= 0) return fail('Owner is required.');
        }

        if (value === '') return true;

        if (name === 'title') {
            if (value.length < 10 || value.length > 140) return fail('Project title must be between 10 and 140 characters.');
            if (!/^[A-ZÀ-ÖØ-Þ]/.test(value)) return fail('Project title must start with an uppercase letter.');
            if (!/^[A-Za-zÀ-ÖØ-öø-ÿ0-9 ,.!?:;()'"\/-]+$/.test(value)) return fail('Project title contains invalid characters.');
        }

        if (name === 'short_description') {
            if (value.length < 15 || value.length > 220) return fail('Short description must be between 15 and 220 characters.');
            if (!/^[A-ZÀ-ÖØ-Þ]/.test(value)) return fail('Short description must start with an uppercase letter.');
        }

        if (name === 'description') {
            if (value.length < 60 || value.length > 5000) return fail('Description must be between 60 and 5000 characters.');
            if (!/^[A-ZÀ-ÖØ-Þ]/.test(value)) return fail('Description must start with an uppercase letter.');
        }

        if (name === 'technologies') {
            if (value.length < 3 || value.length > 255) return fail('Technologies must be between 3 and 255 characters.');
            if (!/^[A-Za-zÀ-ÖØ-öø-ÿ0-9,+.#()\/-\s]+$/.test(value)) return fail('Technologies contains invalid characters.');
        }

        if (name === 'budget') {
            const budget = Number(value);
            if (!Number.isFinite(budget) || budget <= 0) return fail('Budget must be greater than 0.');
            if (budget > 10000000) return fail('Budget is too high.');
        }

        if (name === 'progress_percent') {
            const progress = Number(value);
            if (!Number.isInteger(progress) || progress < 0 || progress > 100) {
                return fail('Progress must be an integer between 0 and 100.');
            }
        }

        if (name === 'status' && !allowedStatus.has(value)) {
            return fail('Invalid status selected.');
        }

        if (name === 'visibility' && !allowedVisibility.has(value)) {
            return fail('Invalid visibility selected.');
        }

        if (name === 'due_date') {
            const dueDate = new Date(`${value}T00:00:00`);
            if (Number.isNaN(dueDate.getTime())) return fail('Due date is invalid.');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (dueDate < today) return fail('Due date cannot be in the past.');
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
