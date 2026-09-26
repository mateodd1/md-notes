/* Local drafts are written before sending, and acknowledged by mutation ID, never by timing. */
(() => {
    const db = window.MdNotesOfflineDB;
    window.renderOfflineMarkdown = (content, workspaceUrl) => {
        const clean = DOMPurify.sanitize(marked.parse(content, { breaks: true }), {
            USE_PROFILES: { html: true }, FORBID_TAGS: ['form', 'style', 'button', 'textarea', 'select', 'iframe', 'object', 'embed'],
            FORBID_ATTR: ['style', 'id', 'name'],
        });
        const template = document.createElement('template'); template.innerHTML = clean;
        const base = new URL(workspaceUrl).pathname.replace(/\/$/, '');
        template.content.querySelectorAll('[src], [href]').forEach(element => {
            const attribute = element.hasAttribute('src') ? 'src' : 'href';
            try {
                const url = new URL(element.getAttribute(attribute), location.origin);
                if ([location.hostname, 'md.mateo.ovh', 'mdnotes.net', 'app.mdnotes.net'].includes(url.hostname) && /^\/(?:app\/)?media\/[a-z0-9]{24}\.[a-z0-9]{1,10}$/.test(url.pathname)) {
                    element.setAttribute(attribute, base + '/media/' + url.pathname.split('/').pop());
                }
            } catch (_) {}
        });
        return template.innerHTML;
    };
    class OfflineNotes {
        constructor(config) {
            this.config = config;
            this.t = config.translations;
            this.known = new Map();
            this.aliases = new Map();
            this.available = false;
            this.lastError = '';
            this.syncing = null;
            this.ready = this.start();
        }
        async start() {
            try {
                if (!('indexedDB' in window) || !('serviceWorker' in navigator) || !window.isSecureContext) throw Error();
                if (await db.activate(this.config)) await this.clearMedia();
                await navigator.serviceWorker.register(this.config.worker, { scope: '/', updateViaCache: 'none' });
                let readinessTimer;
                try {
                    await Promise.race([navigator.serviceWorker.ready, new Promise((_, reject) => { readinessTimer = setTimeout(() => reject(Error('Worker unavailable')), 15000); })]);
                } finally { clearTimeout(readinessTimer); }
                this.available = true;
                navigator.storage?.persist?.().catch(() => {});
                window.addEventListener('online', () => { this.sync(); setTimeout(() => this.sync(), 1500); });
                window.addEventListener('offline', () => this.notify());
                document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') this.sync(); });
                // Some browsers miss the online event after an offline reload; poll pending work too.
                setInterval(() => { if (document.visibilityState === 'visible') this.sync(); }, 5000);
                navigator.serviceWorker.addEventListener('message', event => {
                    if (event.data?.type === 'OFFLINE_CLEARED') { this.available = false; window.location.replace(this.config.login); }
                });
                this.notify();
            } catch (_) {
                this.lastError = this.t.unavailable;
                this.notify();
            }
            return this.available;
        }
        resolve(path) {
            const seen = new Set();
            while (this.aliases.has(path) && !seen.has(path)) { seen.add(path); path = this.aliases.get(path); }
            return path;
        }
        async prune(paths) {
            if (!await this.ready) return;
            const valid = new Set(paths);
            for (const note of await db.all()) {
                if (!valid.has(note.path)) await db.note(this.config.account, note.path, current => current?.dirty ? current : null);
            }
            const notes = await db.all();
            const filenames = new Set(notes.flatMap(note => note.content.match(/[a-z0-9]{24}\.[a-z0-9]{1,10}/g) || []));
            const cache = await caches.open('md-notes-media-' + this.config.account);
            for (const key of await cache.keys()) if (!filenames.has(new URL(key.url).pathname.split('/').pop())) await cache.delete(key);
        }
        async relocate(source, destination) {
            if (!this.available || source === destination) return;
            for (const note of await db.all()) {
                if (note.path !== source && !note.path.startsWith(source + '/')) continue;
                const target = destination + note.path.slice(source.length);
                await db.note(this.config.account, note.path, current => current ? { ...current, path: target } : null);
                this.aliases.set(note.path, target);
                if (this.known.has(note.path)) this.known.set(target, this.known.get(note.path));
            }
        }
        async remember(path, content, revision) {
            if (!await this.ready) return null;
            const note = await db.note(this.config.account, path, previous => previous?.dirty ? previous : {
                path, content, baseContent: content, revision, dirty: false, changeId: null, snapshot: false, updatedAt: Date.now(),
            });
            this.known.set(path, { changeId: note.changeId, revision: note.revision, baseContent: note.baseContent });
            return note;
        }
        async load(path) {
            if (!await this.ready) return null;
            const note = await db.note(this.config.account, this.resolve(path));
            if (note) this.known.set(note.path, { changeId: note.changeId, revision: note.revision, baseContent: note.baseContent });
            return note;
        }
        async queue(path, content, snapshot = false) {
            if (!await this.ready) throw Error(this.t.unavailable);
            path = this.resolve(path);
            if (new TextEncoder().encode(content).length > 5 * 1024 * 1024) throw Error(this.t.too_large);
            const known = this.known.get(path);
            try {
                const note = await db.note(this.config.account, path, previous => {
                    if (!previous || !known) throw Error(this.t.not_cached);
                    if (previous.dirty && previous.changeId !== known.changeId && previous.content !== content) throw Error(this.t.other_tab);
                    if (previous.content === content && (!snapshot || previous.snapshot)) return previous;
                    return {
                        ...previous, content, revision: known.revision, baseContent: known.baseContent,
                        dirty: content !== known.baseContent || snapshot,
                        snapshot: snapshot || previous.snapshot,
                        changeId: crypto.randomUUID(), updatedAt: Date.now(),
                    };
                });
                this.known.set(path, { changeId: note.changeId, revision: note.revision, baseContent: note.baseContent });
                this.notify();
                return note;
            } catch (error) {
                if (['QuotaExceededError', 'UnknownError', 'AbortError'].includes(error.name)) throw Error(this.t.storage_failed);
                if (error.message === 'account_changed') throw Error(this.t.account_changed);
                throw error;
            }
        }
        async save(path, content, snapshot) {
            await this.queue(path, content, snapshot);
            await this.sync();
        }
        async sync() {
            if (!await this.ready || !navigator.onLine) { this.notify(); return; }
            if (this.syncing) return this.syncing;
            const execute = () => this.drain();
            this.syncing = navigator.locks ? navigator.locks.request('md-notes-offline-sync', execute) : execute();
            this.notify();
            try { await this.syncing; }
            catch (_) { this.lastError = this.t.storage_failed; }
            finally { this.syncing = null; this.notify(); }
        }
        async drain() {
            const pending = (await db.all()).filter(note => note.dirty);
            if (!pending.length) { this.lastError = ''; return; }
            try {
                const session = await fetch(this.config.session, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(12000) });
                if ([401, 403, 419].includes(session.status)) throw Error(this.t.login_required);
                if (!session.ok) throw Error(this.t.error);
                const identity = await session.json();
                if (identity.account !== this.config.account) throw Error(this.t.account_changed);
                for (const sent of pending) {
                    const current = await db.note(this.config.account, sent.path);
                    if (!current?.dirty || current.changeId !== sent.changeId) continue;
                    const response = await fetch(this.config.sync, {
                        method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(20000),
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': identity.csrf },
                        body: JSON.stringify({ account: this.config.account, path: sent.path, content: sent.content, revision: sent.revision, change_id: sent.changeId, snapshot: sent.snapshot }),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw Error([401, 403, 419].includes(response.status) ? this.t.login_required : result.message || this.t.error);
                    if (!result.revision || !result.path || !result.savedAt) throw Error(this.t.error);
                    const acknowledged = await db.note(this.config.account, sent.path, latest => {
                        if (!latest) return null;
                        const dirty = latest.changeId !== sent.changeId;
                        return { ...latest, path: result.path, baseContent: sent.content, revision: result.revision, dirty, snapshot: dirty && latest.snapshot };
                    });
                    if (acknowledged && this.known.get(sent.path)?.changeId === acknowledged.changeId) {
                        this.known.set(result.path, { changeId: acknowledged.changeId, revision: result.revision, baseContent: sent.content });
                    }
                    if (result.conflict) {
                        this.aliases.set(sent.path, result.path);
                        this.known.delete(sent.path);
                        window.dispatchEvent(new CustomEvent('md-offline-conflict', { detail: { source: sent.path, ...result } }));
                    }
                    window.dispatchEvent(new CustomEvent('md-offline-synced', { detail: { source: sent.path, ...result } }));
                }
                this.lastError = '';
            } catch (error) {
                this.lastError = error.message === 'account_changed' ? this.t.account_changed
                    : ['TypeError', 'TimeoutError', 'AbortError'].includes(error.name) ? this.t.error : error.message;
            }
        }
        async notify() {
            let paths = [];
            try { paths = (await db.all()).filter(note => note.dirty).map(note => note.path); } catch (_) {}
            const count = paths.length;
            const message = this.lastError || (this.syncing ? this.t.syncing : count ? this.t.pending.replace(':count', count) : navigator.onLine ? this.t.ready : this.t.offline);
            window.dispatchEvent(new CustomEvent('md-offline-state', { detail: { message, count, paths, error: this.lastError, available: this.available } }));
        }
        async isPending(path) {
            if (!await this.ready) return false;
            return Boolean((await db.note(this.config.account, this.resolve(path)))?.dirty);
        }
        async clearMedia() {
            if (!('caches' in window)) return;
            for (const name of await caches.keys()) if (name.startsWith('md-notes-media-')) await caches.delete(name);
        }
        async cacheMedia(urls) {
            if (!await this.ready || !navigator.onLine) return;
            const cache = await caches.open('md-notes-media-' + this.config.account);
            for (const value of [...new Set(urls)].slice(0, 30)) {
                try {
                    const url = new URL(value, location.origin);
                    if (url.origin !== location.origin || !/\/(?:app\/)?media\/[a-z0-9]{24}\.[a-z0-9]{1,10}$/.test(url.pathname)) continue;
                    url.search = ''; url.hash = '';
                    if (await cache.match(url.href)) continue;
                    // Keep media bounded; note drafts are never evicted by the application.
                    const keys = await cache.keys();
                    let bytes = 0;
                    for (const key of keys) bytes += Number((await cache.match(key)).headers.get('X-Offline-Bytes') || 0);
                    if (bytes >= 40 * 1024 * 1024) break;
                    const response = await fetch(url.href, { cache: 'no-store' });
                    if (!response.ok || response.redirected) continue;
                    const blob = await response.blob();
                    if (blob.size > 10 * 1024 * 1024 || bytes + blob.size > 50 * 1024 * 1024) continue;
                    if ((await db.meta())?.account !== this.config.account) return;
                    const headers = new Headers(response.headers); headers.set('X-Offline-Bytes', String(blob.size));
                    await cache.put(url.href, new Response(blob, { headers }));
                    if ((await db.meta())?.account !== this.config.account) { await caches.delete('md-notes-media-' + this.config.account); return; }
                } catch (_) { /* Media cache failure must never interrupt saving a note. */ }
            }
        }
    }
    window.MdNotesOffline = OfflineNotes;
    if (window.mdNotesOfflineConfig) {
        window.mdOffline = new OfflineNotes(window.mdNotesOfflineConfig);
        // Applies on profile pages too. Never discard pending drafts on logout or account deletion.
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.offlineCleared) return;
            const url = new URL(form.action);
            const logout = url.pathname.endsWith('/logout');
            const deleting = url.pathname.endsWith('/settings') && form.querySelector('[name="_method"]')?.value === 'DELETE';
            if (!logout && !deleting) return;
            if (!window.mdOffline.available) return;
            event.preventDefault(); event.stopImmediatePropagation();
            await window.mdOffline.sync();
            if ((await db.all()).some(note => note.dirty)) {
                window.dispatchEvent(new CustomEvent('md-offline-warning', { detail: window.mdOffline.t.logout_pending }));
                let warning = document.getElementById('offline-logout-warning');
                if (!warning) { warning = document.createElement('div'); warning.id = 'offline-logout-warning'; warning.className = 'toast error'; warning.setAttribute('role', 'alert'); document.body.append(warning); }
                warning.textContent = window.mdOffline.t.logout_pending;
                setTimeout(() => warning.remove(), 6000);
                return;
            }
            await db.clear(); await window.mdOffline.clearMedia();
            form.dataset.offlineCleared = 'true';
            HTMLFormElement.prototype.submit.call(form);
        }, true);
    }
})();
