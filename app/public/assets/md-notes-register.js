(() => {
    const form = document.getElementById('register-form');
    if (!form) return;

    let toastTimer;
    let removalTimer;
    const closeIcon = '<svg class="icon close-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6 18 18M18 6 6 18"/></svg>';

    const dismissToast = (toast) => {
        clearTimeout(toastTimer);
        toast.classList.remove('is-entering');
        toast.classList.add('hiding');
        clearTimeout(removalTimer);
        removalTimer = setTimeout(() => toast.remove(), 220);
    };

    const bindToast = (toast) => {
        toast.querySelector('button')?.addEventListener('click', () => dismissToast(toast));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => dismissToast(toast), 3000);
    };

    const showToast = (message) => {
        clearTimeout(removalTimer);
        let toast = document.getElementById('registration-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'registration-toast';
            document.body.append(toast);
        }

        toast.className = 'toast error auth-registration-toast';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        const copy = document.createElement('div');
        copy.className = 'auth-toast-copy';
        const text = document.createElement('p');
        text.textContent = message;
        copy.append(text);
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', form.dataset.closeLabel);
        close.innerHTML = closeIcon;
        toast.replaceChildren(copy, close);
        void toast.offsetWidth;
        toast.classList.add('is-entering');
        bindToast(toast);
    };

    const initialToast = document.getElementById('registration-toast');
    if (initialToast) {
        bindToast(initialToast);
        form.querySelector('[aria-invalid="true"]')?.focus({ preventScroll: true });
    }

    form.addEventListener('submit', (event) => {
        const invalidField = Array.from(form.elements)
            .find((field) => field instanceof HTMLInputElement && !field.checkValidity());
        if (!invalidField) return;

        event.preventDefault();
        invalidField.setAttribute('aria-invalid', 'true');
        invalidField.focus();
        showToast(invalidField.validationMessage || form.dataset.invalidMessage);
    });

    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.checkValidity()) {
            event.target.removeAttribute('aria-invalid');
        }
    });
})();
