/* Shared by pages and the service worker. Private data is scoped to one active account. */
((root) => {
    const DB_NAME = 'md-notes-offline-v1';
    let opening;
    const open = () => opening ||= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            request.result.createObjectStore('meta');
            request.result.createObjectStore('notes', { keyPath: 'path' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => { opening = null; reject(request.error); };
    });
    async function transaction(stores, mode, run) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(stores, mode);
            let result, failure;
            tx.oncomplete = () => resolve(result);
            tx.onabort = tx.onerror = () => reject(failure || tx.error || new Error('Storage failed'));
            const done = value => { result = value; };
            const fail = error => { failure = error; tx.abort(); };
            try { run(tx, done, fail); } catch (error) { fail(error); }
        });
    }
    const meta = () => transaction(['meta'], 'readonly', (tx, done) => {
        const request = tx.objectStore('meta').get('account');
        request.onsuccess = () => done(request.result || null);
    });
    const all = () => transaction(['notes'], 'readonly', (tx, done) => {
        const request = tx.objectStore('notes').getAll();
        request.onsuccess = () => done(request.result);
    });
    const clear = () => transaction(['meta', 'notes'], 'readwrite', tx => {
        tx.objectStore('meta').clear(); tx.objectStore('notes').clear();
    });
    const activate = config => transaction(['meta', 'notes'], 'readwrite', (tx, done) => {
        const metaStore = tx.objectStore('meta');
        const request = metaStore.get('account');
        request.onsuccess = () => {
            const changed = request.result && request.result.account !== config.account;
            if (changed) tx.objectStore('notes').clear();
            metaStore.put(config, 'account'); done(changed);
        };
    });
    const note = (account, path, update) => transaction(['meta', 'notes'], update ? 'readwrite' : 'readonly', (tx, done, fail) => {
        const current = tx.objectStore('meta').get('account');
        current.onsuccess = () => {
            if (current.result?.account !== account) { fail(new Error('account_changed')); return; }
            const store = tx.objectStore('notes');
            const request = store.get(path);
            request.onsuccess = () => {
                try {
                    const result = update ? update(request.result || null) : request.result;
                    if (update) {
                        if (!result || result.path !== path) store.delete(path);
                        if (result) store.put(result);
                    }
                    done(result);
                } catch (error) { fail(error); }
            };
        };
    });
    root.MdNotesOfflineDB = { open, meta, all, activate, note, clear };
})(typeof self !== 'undefined' ? self : globalThis);
