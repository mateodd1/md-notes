const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { createHash, webcrypto } = require('node:crypto');

const hash = value => createHash('sha256').update(value).digest('hex');
const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
};

async function harness() {
    const notes = new Map(), remote = new Map([['Note.md', 'Original']]), sent = [];
    const controls = { nextWrite: null, response: null, failures: new Map() };
    const db = {
        activate: async () => false,
        all: async () => [...notes.values()].map(note => structuredClone(note)),
        note: async (_account, notePath, update) => {
            if (update && controls.nextWrite) {
                const gate = controls.nextWrite; controls.nextWrite = null;
                gate.started.resolve(); await gate.release.promise;
            }
            const previous = notes.has(notePath) ? structuredClone(notes.get(notePath)) : null;
            const result = update ? update(previous) : previous;
            if (update) {
                if (!result || result.path !== notePath) notes.delete(notePath);
                if (result) notes.set(result.path, structuredClone(result));
            }
            return result;
        },
    };
    const window = { MdNotesOfflineDB: db, isSecureContext: true, indexedDB: {}, addEventListener() {}, dispatchEvent() {} };
    const config = { account: 'a'.repeat(64), worker: '/worker', session: '/session', sync: '/sync', translations: { pending: ':count pending', not_cached: 'Not cached', other_tab: 'Another tab', error: 'Sync error', login_required: 'Sign in again', account_changed: 'Account changed' } };
    const context = vm.createContext({
        window, document: { addEventListener() {} }, navigator: { onLine: true, serviceWorker: { register: async () => {}, ready: Promise.resolve(), addEventListener() {} } },
        setTimeout, clearTimeout, setInterval() {}, TextEncoder, crypto: webcrypto, AbortSignal,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        fetch: async (url, options) => {
            if (url === config.session) return { ok: true, json: async () => ({ account: config.account, csrf: 'test-only' }) };
            assert.equal(url, config.sync);
            const data = JSON.parse(options.body); sent.push(data);
            const failure = controls.failures.get(data.path);
            if (failure) return { ok: false, status: failure.status, json: async () => failure };
            const gate = controls.response; controls.response = null;
            if (gate) { gate.started.resolve(); await gate.release.promise; }
            const conflict = hash(remote.get(data.path) ?? '') !== data.revision && remote.get(data.path) !== data.content;
            const destination = conflict ? 'Recovered.md' : data.path;
            remote.set(destination, data.content);
            return { ok: true, json: async () => ({ path: destination, revision: hash(data.content), conflict, savedAt: 'now' }) };
        },
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/assets/md-notes-offline.js'), 'utf8'), context);
    const manager = new window.MdNotesOffline(config);
    await manager.ready;
    await manager.remember('Note.md', 'Original', hash('Original'));
    const gate = () => ({ started: deferred(), release: deferred() });
    const anotherTab = async () => {
        const tab = new window.MdNotesOffline(config);
        await tab.ready;
        return tab;
    };
    return { manager, db, remote, sent, controls, gate, anotherTab };
}

test('opening the original note again saves there and preserves the recovered copy', async () => {
    const { manager, remote } = await harness();
    remote.set('Note.md', 'Changed elsewhere');
    await manager.save('Note.md', 'Recovered draft', true);
    await manager.remember('Note.md', 'Changed elsewhere', hash('Changed elsewhere'));

    await manager.save('Note.md', 'Edit after reopening', true);

    assert.equal(remote.get('Note.md'), 'Edit after reopening');
    assert.equal(remote.get('Recovered.md'), 'Recovered draft');
    assert.equal(await manager.isPending('Note.md'), false);
});

test('typing during an acknowledgement saves the newest edit without a false conflict', async () => {
    const { manager, remote, controls, gate } = await harness();
    await manager.queue('Note.md', 'First request');
    const response = controls.response = gate();
    const syncing = manager.sync();
    await response.started.promise;
    const write = controls.nextWrite = gate();
    const typing = manager.queue('Note.md', 'Newer edit');
    await write.started.promise;
    response.release.resolve();
    await new Promise(setImmediate);
    write.release.resolve();
    await Promise.all([typing, syncing]);

    await manager.sync();

    assert.equal(remote.get('Note.md'), 'Newer edit');
    assert.equal(remote.has('Recovered.md'), false);
    assert.equal(await manager.isPending('Note.md'), false);
});

test('a conflict acknowledgement keeps edits typed while the request was in flight', async () => {
    const { manager, remote, controls, gate } = await harness();
    remote.set('Note.md', 'Changed elsewhere');
    await manager.queue('Note.md', 'Draft being sent');
    const response = controls.response = gate();
    const syncing = manager.sync();
    await response.started.promise;
    const write = controls.nextWrite = gate();
    const typing = manager.queue('Note.md', 'Draft with newer edits');
    await write.started.promise;
    response.release.resolve();
    await new Promise(setImmediate);
    write.release.resolve();
    await Promise.all([typing, syncing]);

    await manager.sync();

    assert.equal(remote.get('Note.md'), 'Changed elsewhere');
    assert.equal(remote.get('Recovered.md'), 'Draft with newer edits');
    assert.equal(await manager.isPending('Recovered.md'), false);
});

test('edits from another tab keep their protection against being silently overwritten', async () => {
    const { manager, db } = await harness();
    await db.note('a'.repeat(64), 'Note.md', note => ({ ...note, content: 'Other tab draft', dirty: true, changeId: 'other-tab' }));

    await assert.rejects(manager.queue('Note.md', 'This tab draft'), /Another tab/);

    assert.equal((await db.all())[0].content, 'Other tab draft');
});

test('a draft acknowledged by another tab supplies the revision for the next edit', async () => {
    const { manager, db, remote } = await harness();
    await manager.queue('Note.md', 'First edit');
    remote.set('Note.md', 'First edit');
    await db.note('a'.repeat(64), 'Note.md', note => ({ ...note, baseContent: 'First edit', revision: hash('First edit'), dirty: false }));

    await manager.save('Note.md', 'Next edit', true);

    assert.equal(remote.get('Note.md'), 'Next edit');
    assert.equal(remote.has('Recovered.md'), false);
});

test('a stale editor preserves changes saved and reopened in another tab', async () => {
    const { manager, remote, anotherTab } = await harness();
    const second = await anotherTab();
    await second.remember('Note.md', 'Original', hash('Original'));
    await second.save('Note.md', 'Saved in another tab', true);
    await second.remember('Note.md', 'Saved in another tab', hash('Saved in another tab'));

    await manager.save('Note.md', 'Edit from stale tab', true);

    assert.equal(remote.get('Note.md'), 'Saved in another tab');
    assert.equal(remote.get('Recovered.md'), 'Edit from stale tab');
});

for (const status of [409, 422]) {
    test(`a note rejected with ${status} does not block other drafts and can be retried`, async () => {
        const { manager, remote, controls } = await harness();
        remote.set('Second.md', 'Second original');
        await manager.remember('Second.md', 'Second original', hash('Second original'));
        await manager.queue('Note.md', 'Retained draft');
        await manager.queue('Second.md', 'Valid edit');
        controls.failures.set('Note.md', { status, message: 'Rejected note' });

        await manager.sync();

        assert.equal(remote.get('Note.md'), 'Original');
        assert.equal(remote.get('Second.md'), 'Valid edit');
        assert.equal(await manager.isPending('Note.md'), true);
        assert.equal(await manager.isPending('Second.md'), false);
        assert.equal(manager.lastError, 'Rejected note');

        controls.failures.clear();
        await manager.sync();

        assert.equal(remote.get('Note.md'), 'Retained draft');
        assert.equal(await manager.isPending('Note.md'), false);
        assert.equal(manager.lastError, '');
    });
}

for (const failure of [
    { status: 401 }, { status: 403 }, { status: 419 }, { status: 429 }, { status: 503 },
    { status: 409, code: 'account_changed' },
]) {
    test(`a global sync failure ${failure.status} ${failure.code || ''} stops sending and keeps every draft`, async () => {
        const { manager, remote, controls, sent } = await harness();
        remote.set('Second.md', 'Second original');
        await manager.remember('Second.md', 'Second original', hash('Second original'));
        await manager.queue('Note.md', 'First draft');
        await manager.queue('Second.md', 'Second draft');
        controls.failures.set('Note.md', { ...failure, message: 'Cannot sync now' });

        await manager.sync();

        assert.equal(sent.length, 1);
        assert.equal(await manager.isPending('Note.md'), true);
        assert.equal(await manager.isPending('Second.md'), true);
        assert.equal(remote.get('Note.md'), 'Original');
        assert.equal(remote.get('Second.md'), 'Second original');
        assert.ok(manager.lastError);
    });
}
