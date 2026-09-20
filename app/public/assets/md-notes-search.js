window.bindNoteSearch = (config, navigate) => {
    const dialog = document.getElementById('note-search');
    const input = document.getElementById('note-search-input');
    const results = document.getElementById('note-search-results');
    const status = document.getElementById('note-search-status');
    const t = config.searchTranslations;
    let timer, controller, sequence = 0, previousFocus;
    const resetRequest = () => { clearTimeout(timer); controller?.abort(); sequence++; };
    const open = () => { previousFocus = document.activeElement; dialog.showModal(); input.focus(); input.select(); input.dispatchEvent(new Event('input')); };
    document.getElementById('open-note-search').addEventListener('click', open);
    document.getElementById('close-note-search').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) { const bounds = dialog.getBoundingClientRect(); if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close(); } });
    dialog.addEventListener('close', () => { resetRequest(); previousFocus?.focus(); });
    document.addEventListener('keydown', (event) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); if (dialog.open) dialog.close(); else open(); } });
    input.addEventListener('input', () => {
        resetRequest();
        results.replaceChildren();
        const query = input.value.trim();
        if (query.length < 2) { status.textContent = t.hint; return; }
        status.textContent = t.loading;
        const requestId = sequence;
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const url = new URL(config.urls.search); url.searchParams.set('q', query);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || t.failed);
                if (requestId !== sequence || !dialog.open) return;
                status.textContent = data.truncated ? t.more : data.results.length ? '' : t.empty;
                for (const note of data.results) {
                    const link = document.createElement('a');
                    link.href = config.urls.notes + '/' + note.path.split('/').map(encodeURIComponent).join('/');
                    const title = document.createElement('strong'); title.textContent = note.title;
                    const path = document.createElement('small'); path.textContent = note.path;
                    const excerpt = document.createElement('span'); excerpt.textContent = note.excerpt;
                    link.append(title, path, excerpt); results.append(link);
                }
            } catch (error) { if (error.name !== 'AbortError' && requestId === sequence) status.textContent = error.message || t.failed; }
        }, 350);
    });
    dialog.addEventListener('keydown', (event) => {
        const links = [...results.querySelectorAll('a')];
        const index = links.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : links.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links[next]?.focus();
        } else if (event.key === 'Enter' && event.target === input && links[0]) { event.preventDefault(); links[0].click(); }
    });
    results.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); dialog.close(); navigate(link.href);
    });
};
