import './bootstrap';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const forms = Array.from(document.querySelectorAll('form')).filter((form) => {
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        return method !== 'GET'
            && !form.hasAttribute('data-no-dirty-check')
            && form.id !== 'form-logout-automatico';
    });

    const serialize = (form) => Array.from(new FormData(form).entries())
        .map(([key, value]) => `${key}=${value instanceof File ? value.name : value}`)
        .join('&');

    forms.forEach((form) => {
        form.dataset.initialState = serialize(form);
        form.dataset.submitting = 'false';

        form.addEventListener('submit', () => {
            form.dataset.submitting = 'true';
        });
    });

    window.addEventListener('beforeunload', (event) => {
        const hasDirtyForm = forms.some((form) => {
            if (form.dataset.submitting === 'true') {
                return false;
            }

            return serialize(form) !== form.dataset.initialState;
        });

        if (!hasDirtyForm) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
});
