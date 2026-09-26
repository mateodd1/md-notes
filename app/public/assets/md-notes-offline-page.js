(() => {
    const db = window.MdNotesOfflineDB;
    const page = window.mdNotesOfflinePage;
    const byId = id => document.getElementById(id);
    let t = page.translations, manager, activePath, editing = false, timer, writing = Promise.resolve(), dirtyInput = false, needsSnapshot = false;
    const editor = byId('offline-editor'), reader = byId('offline-reader');
    const message = text => { byId('offline-message').textContent = text; };
    const theme = () => {
        let selected = 'system';
        try { selected = localStorage.getItem('md-notes-theme') || 'system'; } catch (_) {}
        document.documentElement.classList.toggle('dark', selected === 'dark' || (selected === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
    };
    theme();
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', theme);
    byId('offline-theme').addEventListener('click', () => {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        try { localStorage.setItem('md-notes-theme', next); } catch (_) {}
        document.documentElement.classList.toggle('dark', next === 'dark');
    });
    const render = () => { reader.innerHTML = window.renderOfflineMarkdown(editor.value, manager?.config.notes || page.notes); };
    const list = async () => {
        const query = byId('offline-search').value.toLocaleLowerCase();
        const notes = (await db.all()).sort((a, b) => a.path.localeCompare(b.path));
        byId('offline-list').replaceChildren();
        for (const note of notes) {
            if (query && !(note.path + '\n' + note.content).toLocaleLowerCase().includes(query)) continue;
            const button = document.createElement('button'); button.type = 'button';
            button.className = 'offline-note' + (note.path === activePath ? ' active' : '');
            const title = document.createElement('strong'); title.textContent = note.path.split('/').pop();
            const detail = document.createElement('small'); detail.textContent = note.path + (note.dirty ? ' · ' + t.local_saved : '');
            button.append(title, detail); button.addEventListener('click', () => open(note.path)); byId('offline-list').append(button);
        }
    };
    const persist = async (snapshot = false) => {
        clearTimeout(timer);
        if (!activePath || !manager) return true;
        if (!dirtyInput && !(snapshot && needsSnapshot)) return true;
        const path = activePath, content = editor.value;
        const operation = writing.then(() => manager.queue(path, content, snapshot));
        writing = operation.catch(() => {});
        try {
            await operation;
            if (editor.value === content) { dirtyInput = false; if (snapshot) needsSnapshot = false; }
            return true;
        } catch (error) { message(error.message || t.storage_failed); return false; }
    };
    const mode = value => {
        editing = value; editor.hidden = !editing; reader.hidden = editing;
        byId('offline-edit').textContent = editing ? t.read : t.edit;
        byId('offline-save').hidden = !editing;
        if (editing) editor.focus(); else render();
    };
    async function open(path) {
        if (!await persist(true)) return;
        const note = await manager.load(path);
        if (!note) { message(t.not_cached); return; }
        activePath = note.path;
        editor.value = note.content; dirtyInput = false; needsSnapshot = note.dirty && !note.snapshot;
        byId('offline-title').textContent = note.path.split('/').pop();
        document.title = note.path.split('/').pop() + ' · md-notes';
        byId('offline-edit').hidden = byId('offline-download').hidden = false;
        const url = new URL('/offline', location.origin); url.searchParams.set('note', note.path);
        history.replaceState({}, '', url);
        document.body.classList.remove('show-notes'); mode(false); await list();
        manager.notify();
    }
    editor.addEventListener('input', () => { dirtyInput = true; needsSnapshot = true; clearTimeout(timer); timer = setTimeout(() => persist(), 350); });
    byId('offline-edit').addEventListener('click', async () => { if (await persist()) mode(!editing); });
    byId('offline-save').addEventListener('click', async () => {
        if (await persist(true)) { message(t.local_saved); await list(); manager.sync(); }
    });
    byId('offline-sync').addEventListener('click', async () => { if (await persist(true)) await manager?.sync(); });
    byId('offline-download').addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([editor.value], { type: 'text/markdown;charset=utf-8' }));
        const link = document.createElement('a'); link.href = url; link.download = activePath.split('/').pop(); link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    byId('offline-show-notes').addEventListener('click', async () => { if (await persist(true)) document.body.classList.add('show-notes'); });
    byId('offline-search').addEventListener('input', list);
    window.addEventListener('beforeunload', event => { if (dirtyInput) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('md-offline-state', event => {
        message(event.detail.message);
        if (event.detail.error === t.login_required) {
            const link = document.createElement('a'); link.href = manager.config.login; link.textContent = t.login; byId('offline-message').append(link);
        }
    });
    window.addEventListener('md-offline-conflict', event => {
        if (activePath === event.detail.source) {
            activePath = event.detail.path; byId('offline-title').textContent = activePath;
            const url = new URL('/offline', location.origin); url.searchParams.set('note', activePath); history.replaceState({}, '', url);
        }
        const warning = document.createElement('div'); warning.className = 'offline-message'; warning.setAttribute('role', 'alert');
        warning.textContent = t.conflict.replace(':path', event.detail.path); byId('offline-message').after(warning);
    });
    window.addEventListener('md-offline-synced', () => list());
    (async () => {
        try {
            const config = await db.meta();
            if (!config) { document.body.classList.add('show-notes'); message(t.empty); return; }
            t = config.translations; manager = window.mdOffline = new window.MdNotesOffline(config);
            if (!await manager.ready) { message(t.unavailable); return; }
            await list();
            const requested = new URL(location.href).searchParams.get('note');
            const base = new URL(config.notes).pathname.replace(/\/$/, '');
            const path = requested || (location.pathname.endsWith('.md') ? decodeURIComponent(location.pathname.slice(base.length + 1)) : null);
            if (path) await open(path); else document.body.classList.add('show-notes');
            await manager.sync();
        } catch (_) { message(t.unavailable); }
    })();
})();
