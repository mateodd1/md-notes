const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const sandbox = { module: { exports: {} } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/md-notes-saver.js'), 'utf8'), sandbox);
const Saver = sandbox.module.exports;
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; };

test('workspace assets are valid classic JavaScript', () => {
    for (const name of ['md-notes-saver.js', 'md-notes-search.js', 'md-notes-workspace.js', 'md-notes-trash.js']) {
        assert.doesNotThrow(() => new vm.Script(fs.readFileSync(path.join(__dirname, '../../public/assets', name), 'utf8')));
    }
});

test('typing while saving does not mark unsent content as saved', async () => {
    let content = 'First'; const request = deferred(); const sent = [];
    const saver = new Saver(() => content, async value => { sent.push(value); await request.promise; }, '');
    const saving = saver.save(); content = 'Second'; request.resolve();
    assert.equal(await saving, true); assert.equal(saver.saved, 'First'); assert.equal(saver.dirty, true);
    await saver.save(); assert.deepEqual(sent, ['First', 'Second']); assert.equal(saver.dirty, false);
});

test('save on navigation waits for autosave and flushes the latest text with a snapshot', async () => {
    let content = 'First'; const request = deferred(); const sent = []; let active = 0, maxActive = 0;
    const saver = new Saver(() => content, async (value, snapshot) => {
        active++; maxActive = Math.max(maxActive, active); sent.push([value, snapshot]); await request.promise; active--;
    }, '');
    const auto = saver.save(); content = 'Second'; const navigation = saver.save(true); const click = saver.save(true);
    request.resolve(); assert.deepEqual(await Promise.all([auto, navigation, click]), [true, true, true]);
    assert.equal(maxActive, 1); assert.equal(saver.saved, 'Second'); assert.equal(saver.snapshot, 'Second');
    assert.deepEqual(sent, [['First', false], ['Second', true]]);
});

test('reverting to an old snapshot still saves when the server holds a newer autosave', async () => {
    let content = 'Changed'; const sent = [];
    const saver = new Saver(() => content, async value => { sent.push(value); }, 'Original');
    await saver.save(); content = 'Original'; await saver.save(true);
    assert.deepEqual(sent, ['Changed', 'Original']);
});

test('failed saves remain dirty and can be retried', async () => {
    let fail = true; const saver = new Saver(() => 'New', async () => { if (fail) throw Error('Offline'); }, 'Old');
    assert.equal(await saver.save(true), false); assert.equal(saver.dirty, true); assert.equal(saver.saved, 'Old');
    fail = false; assert.equal(await saver.save(true), true); assert.equal(saver.snapshot, 'New');
});

test('explicit save flushes edits typed during the request', async () => {
    let content = 'First'; const request = deferred(); const sent = [];
    const saver = new Saver(() => content, async value => { sent.push(value); await request.promise; }, '');
    const save = saver.save(true); content = 'Second'; request.resolve();
    assert.equal(await save, true); assert.deepEqual(sent, ['First', 'Second']); assert.equal(saver.dirty, false);
});
