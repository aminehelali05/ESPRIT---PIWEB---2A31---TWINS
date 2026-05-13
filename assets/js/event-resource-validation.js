(() => {
    // --- Helpers ---
    const isGibberish = (text) => {
        if (!text) return false;
        if (/(.)\1{4,}/.test(text)) return true; // Repeated chars
        if (/[^aeiouyAEIOUY\s\d\W]{6,}/.test(text)) return true; // Consonant clusters
        return false;
    };

    const hasProfanity = (text) => {
        if (!text) return false;
        const blacklist = ['badword1', 'badword2', 'merde', 'putain', 'salope', 'connard', 'fuck', 'shit'];
        const lowered = text.toLowerCase();
        return blacklist.some(word => lowered.includes(word));
    };

    const showInlineError = (field, message) => {
        const group = field.closest('.uf-group') || field.closest('.res-field') || field.closest('.activity-slot') || field.closest('.rule-input-row') || field.parentElement;
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
        const group = field.closest('.uf-group') || field.closest('.res-field') || field.closest('.activity-slot') || field.closest('.rule-input-row') || field.parentElement;
        group.classList.remove('has-error');

        const errorSpan = group.querySelector('.field-error');
        if (errorSpan) {
            errorSpan.remove();
        }
    };

    const fieldKey = (field) => {
        const key = (field.name || field.id || '').trim();
        return key.toLowerCase();
    };

    // --- Core Logic ---
    const validateField = (field, context = 'generic', silent = false) => {
        if (!field || field.disabled) return true;

        const key = fieldKey(field);
        const value = String(field.value || '').trim();
        let message = '';

        // Validation Rules
        if (key === 'title') {
            if (value === "") message = 'Ce champ ne peut pas être vide.';
            else if (value.length < 5) message = 'The title must be at least 5 characters long.';
            else if (!/[a-zA-Z]/.test(value)) message = 'Title must contain letters.';
            else if (isGibberish(value) || hasProfanity(value)) message = 'Title contains invalid or inappropriate patterns.';
        }

        if (key === 'event_date') {
            if (value === "") message = 'Ce champ ne peut pas être vide (sélectionnez une date).';
            else {
                const selectedDate = new Date(value);
                const now = new Date();
                if (selectedDate < now) message = 'The date cannot be in the past.';
            }
        }

        if (key === 'location') {
            if (value === "") message = 'Ce champ ne peut pas être vide.';
        }

        if (key === 'description') {
            const minDescLen = (context === 'resource') ? 10 : 20;
            if (value === "") message = 'Ce champ ne peut pas être vide.';
            else if (value.length < minDescLen) message = `The description should be at least ${minDescLen} characters long.`;
            else if (!/[a-zA-Z]/.test(value)) message = 'Description must contain letters.';
            else if (isGibberish(value) || hasProfanity(value)) message = 'Description contains invalid or inappropriate patterns.';
        }

        if (key === 'status' && context === 'resource') {
            if (value === "") message = 'Status is required.';
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

    const validateForm = (form, context = 'generic') => {
        if (!form) return true;

        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter((field) => {
            const key = fieldKey(field);
            return key !== '' && field.type !== 'hidden' && field.type !== 'button' && field.type !== 'submit';
        });

        let isValid = true;
        let firstInvalidField = null;

        for (const field of fields) {
            if (!validateField(field, context, false)) {
                isValid = false;
                if (!firstInvalidField) firstInvalidField = field;
            }
        }
        
        if (!isValid && firstInvalidField) {
            firstInvalidField.focus();
        }

        return isValid;
    };

    const attachLiveValidation = (form, context = 'generic') => {
        if (!form) return;

        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach((field) => {
            // Live validation on focus loss
            field.addEventListener('blur', () => {
                validateField(field, context, false);
            });

            // Re-validate on input silently to remove error styles early
            field.addEventListener('input', () => {
                if (field.dataset.invalid === '1') {
                    validateField(field, context, true);
                }
            });

            field.addEventListener('change', () => {
                validateField(field, context, false);
            });

            // CRITICAL: Stop browser bubbles for native validation
            field.addEventListener('invalid', (e) => {
                e.preventDefault();
            }, true);
        });
    };

    const initValidation = () => {
        console.log("Initializing Event/Resource Validation...");
        const eventForms = [
            document.getElementById('eventForm'),
            document.getElementById('eventCreateForm'),
            document.getElementById('eventEditForm')
        ];

        const resourceForms = [
            document.getElementById('resourceAdminForm')
        ];

        eventForms.forEach((form) => {
            if (form) {
                form.setAttribute('novalidate', 'novalidate');
                attachLiveValidation(form, 'event');
                form.addEventListener('submit', (e) => {
                    if (!validateForm(form, 'event')) {
                        e.preventDefault();
                    }
                });
            }
        });

        resourceForms.forEach((form) => {
            if (form) {
                form.setAttribute('novalidate', 'novalidate');
                attachLiveValidation(form, 'resource');
                form.addEventListener('submit', (e) => {
                    if (!validateForm(form, 'resource')) {
                        e.preventDefault();
                    }
                });
            }
        });
    };

    window.EventResourceValidation = {
        validateField,
        validateForm,
        attachLiveValidation,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initValidation);
    } else {
        initValidation();
    }
})();
