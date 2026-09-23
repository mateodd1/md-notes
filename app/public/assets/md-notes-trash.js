(() => {
    const dialog = document.getElementById('trash-preview');
    if (!dialog) return;

    const title = document.getElementById('trash-preview-title');
    const path = document.getElementById('trash-preview-path');
    const body = dialog.querySelector('.trash-preview-body');
    const content = dialog.querySelector('.trash-preview-content');
    const status = dialog.querySelector('.trash-preview-status');
    let request = null;
    let opener = null;
    let url = '';
    let closeTimer = null;
    let backdropPressed = false;

    const cancelRequest = () => {
        request?.abort();
        request = null;
    };

    const load = async () => {
        cancelRequest();
        const pending = new AbortController();
        request = pending;
        content.replaceChildren();
        content.hidden = true;
        status.hidden = false;
        status.classList.remove('is-error');
        status.textContent = dialog.dataset.loading;
        body.setAttribute('aria-busy', 'true');
        body.scrollTop = 0;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: pending.signal,
            });
            if (!response.ok) throw new Error('Could not load trash preview');
            const data = await response.json();
            if (typeof data.rendered !== 'string' || typeof data.title !== 'string' || typeof data.path !== 'string') {
                throw new Error('Invalid trash preview');
            }
            if (request !== pending || !dialog.open) return;
            title.textContent = data.title;
            path.textContent = data.path;
            // HTML is rendered and sanitised by the authenticated Markdown endpoint.
            content.innerHTML = data.rendered;
            content.hidden = false;
            status.hidden = true;
        } catch (error) {
            if (request !== pending || error.name === 'AbortError' || !dialog.open) return;
            status.textContent = dialog.dataset.error;
            status.classList.add('is-error');
        } finally {
            if (request === pending) {
                body.setAttribute('aria-busy', 'false');
                request = null;
            }
        }
    };

    const finishClose = () => {
        clearTimeout(closeTimer);
        dialog.classList.remove('is-closing');
        if (dialog.open) dialog.close();
    };

    const close = () => {
        if (!dialog.open || dialog.classList.contains('is-closing')) return;
        cancelRequest();
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finishClose();
            return;
        }
        dialog.classList.add('is-closing');
        closeTimer = setTimeout(finishClose, 180);
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-trash-preview-url]');
        if (!button) return;
        opener = button;
        url = button.dataset.trashPreviewUrl;
        title.textContent = button.dataset.trashPreviewTitle;
        path.textContent = '';
        clearTimeout(closeTimer);
        dialog.classList.remove('is-closing');
        document.body.classList.add('trash-preview-open');
        if (!dialog.open) dialog.showModal();
        load();
    });

    dialog.querySelector('[data-trash-preview-close]').addEventListener('click', close);
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('close', () => {
        if (dialog.open) return;
        cancelRequest();
        clearTimeout(closeTimer);
        dialog.classList.remove('is-closing');
        document.body.classList.remove('trash-preview-open');
        content.replaceChildren();
        content.hidden = true;
        body.setAttribute('aria-busy', 'false');
        opener?.focus({ preventScroll: true });
    });
    const isOutside = event => {
        const bounds = dialog.getBoundingClientRect();
        return event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom;
    };
    dialog.addEventListener('pointerdown', event => { backdropPressed = event.target === dialog && isOutside(event); });
    dialog.addEventListener('click', event => {
        if (backdropPressed && event.target === dialog && isOutside(event)) close();
        backdropPressed = false;
    });
    dialog.addEventListener('animationend', event => {
        if (event.target === dialog && event.animationName === 'md-notes-modal-card-out') finishClose();
    });
})();
