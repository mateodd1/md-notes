const { test } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const modulePath = process.env.MDNOTES_PLAYWRIGHT;
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
async function eventually(check, label = 'condition') {
    const deadline = Date.now() + 12000;
    while (Date.now() < deadline) {
        if (await check()) return;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.fail('Timed out: ' + label);
}
const assets = ['/offline', '/assets/md-notes-offline.css', '/assets/md-notes-offline-db.js', '/assets/md-notes-offline.js', '/assets/md-notes-offline-page.js', '/assets/md-notes-viewport.js', '/assets/md-notes-base.css', '/assets/md-notes-google.css', '/assets/vendor/marked.js', '/assets/vendor/purify.js'];
const mediaPath = '/app/media/1234567890abcdef12345678.png';

test('offline reload, local persistence, reconnection, conflicts, sanitization and account isolation', { skip: !modulePath, timeout: 90000 }, async () => {
    const { chromium } = require(modulePath);
    const template = fs.readFileSync('/tmp/md-notes-offline-workspace.html', 'utf8');
    const shell = fs.readFileSync('/tmp/md-notes-offline-shell.html', 'utf8');
    const config = JSON.parse(template.match(/window\.mdNotesOfflineConfig = (.*?);<\/script>/)[1]);
    let account = config.account, remote = '# Cached note\n\nOriginal', sent = [], origin, failSync = false, authenticated = true, requests = [];
    let holdResponse = false, releaseResponse;
    const rewrite = html => html.replaceAll('https://mdnotes.net', origin).replaceAll('https:\\/\\/mdnotes.net', origin.replaceAll('/', '\\/'));
    const server = http.createServer(async (req, res) => {
        try {
            const url = new URL(req.url, origin);
            requests.push(url.pathname);
            const json = (value, status = 200) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(value)); };
            if (url.pathname === mediaPath) {
                res.writeHead(200, { 'Content-Type': 'image/png', 'Content-Disposition': 'inline; filename="example.png"' });
                return res.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jhyoAAAAASUVORK5CYII=', 'base64'));
            }
            if (url.pathname === '/logout') { res.writeHead(302, { Location: '/login' }); return res.end(); }
            if (url.pathname === '/login') { res.writeHead(200, { 'Content-Type': 'text/html' }); return res.end('<h1>Sign in</h1><script src="/assets/md-notes-offline-db.js"></script>'); }
            if (url.pathname === '/app/offline/session') return authenticated ? json({ account, csrf: 'current-token' }) : json({ message: 'Unauthenticated' }, 401);
            if (url.pathname === '/app/offline/sync') {
                let body = ''; for await (const chunk of req) body += chunk;
                const data = JSON.parse(body);
                if (failSync) return json({ message: 'Quota exceeded' }, 422);
                if (!authenticated) return json({}, 401);
                if (data.account !== account) return json({}, 409);
                assert.equal(req.headers['x-csrf-token'], 'current-token');
                sent.push(data);
                if (holdResponse) { holdResponse = false; await new Promise(resolve => { releaseResponse = resolve; }); }
                const conflict = hash(remote) !== data.revision && remote !== data.content;
                if (!conflict) remote = data.content;
                return json({ path: conflict ? 'Recovered.md' : data.path, revision: hash(data.content), savedAt: 'now', conflict });
            }
            if (url.pathname === '/app/quota') return json({ used_human: '1 KB', limit_human: '100 MB', percentage: 0 });
            if (url.pathname === '/offline-worker.js') {
                res.writeHead(200, { 'Content-Type': 'application/javascript', 'Service-Worker-Allowed': '/' });
                return res.end('const OFFLINE_CONFIG = ' + JSON.stringify({ version: 'browser-test', assets, base: '/app' }) + ';\n' + fs.readFileSync(path.join(__dirname, '../../public/assets/md-notes-offline-worker.js')));
            }
            if (url.pathname === '/offline') { res.writeHead(200, { 'Content-Type': 'text/html' }); return res.end(rewrite(shell)); }
            if (url.pathname === '/app/Note.md' || url.pathname === '/app') {
                res.writeHead(200, { 'Content-Type': 'text/html' });
                return res.end(rewrite(template).replace(config.account, account));
            }
            if (url.pathname.startsWith('/assets/')) {
                const file = path.resolve(__dirname, '../../public', '.' + url.pathname);
                res.writeHead(200, { 'Content-Type': file.endsWith('.css') ? 'text/css' : 'application/javascript' });
                return res.end(fs.readFileSync(file));
            }
            res.writeHead(404); res.end();
        } catch (error) { res.writeHead(500); res.end(String(error)); }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    origin = 'http://127.0.0.1:' + server.address().port;
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(origin + '/app/Note.md');
        await page.waitForFunction(() => window.mdOffline?.available && navigator.serviceWorker.controller);
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.all()).length === 1));
        await page.evaluate(url => mdOffline.cacheMedia([url]), origin + mediaPath);
        assert.equal(await page.locator('#editor').inputValue(), '# Cached note\n\nOriginal');
        await context.setOffline(true);
        await page.locator('#edit-note').click();
        await page.locator('#editor').fill('# Offline edit\n\n**Saved locally**');
        await page.locator('#save-note').click();
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.all())[0]?.dirty));
        await page.reload();
        await page.locator('#offline-edit').waitFor({ state: 'visible' });
        assert.match(await page.locator('#offline-reader').innerText(), /Saved locally/);
        assert.equal(await page.evaluate(async url => (await fetch(url)).status, origin + mediaPath), 200);
        await page.locator('#offline-edit').click();
        await page.locator('#offline-editor').fill('# Updated offline\n\nFinal version');
        await page.locator('#offline-save').click();
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.all())[0]?.content.includes('Final version')));
        await page.reload();
        await page.locator('#offline-edit').waitFor({ state: 'visible' });
        assert.match(await page.locator('#offline-reader').innerText(), /Final version/);
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all())[0]?.dirty), true, 'pending before reconnection');
        await page.evaluate(() => { window.onlineEvents = 0; addEventListener('online', () => onlineEvents++); });
        await context.setOffline(false);
        await eventually(() => remote === '# Updated offline\n\nFinal version', 'sync after reconnect').catch(async error => {
            throw Error(error.message + JSON.stringify({requests, state: await page.evaluate(() => ({online: navigator.onLine, events: window.onlineEvents, available: mdOffline.available, syncing: Boolean(mdOffline.syncing), error: mdOffline.lastError}))}));
        });
        assert.equal(remote, '# Updated offline\n\nFinal version');
        assert(sent.length >= 1);

        // Acknowledging one payload must not erase newer edits typed during its request.
        await page.evaluate(() => mdOffline.syncing);
        holdResponse = true;
        await page.locator('#offline-edit').click();
        await page.locator('#offline-editor').fill('First request in flight');
        await page.locator('#offline-save').click();
        await eventually(() => Boolean(releaseResponse));
        await page.locator('#offline-editor').fill('Typed while syncing');
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.all())[0].content === 'Typed while syncing'));
        releaseResponse(); releaseResponse = null;
        await eventually(() => remote === 'First request in flight');
        await page.evaluate(() => mdOffline.syncing);
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all())[0].dirty), true);
        await page.locator('#offline-sync').click();
        await eventually(() => remote === 'Typed while syncing');
        await page.locator('#offline-edit').click();

        // Two tabs must not silently replace each other's pending local drafts.
        const second = await context.newPage();
        await second.goto(origin + '/offline?note=Note.md');
        await second.locator('#offline-edit').waitFor({ state: 'visible' });
        await context.setOffline(true);
        await page.locator('#offline-edit').click();
        await page.locator('#offline-editor').fill('Draft from first tab');
        await page.locator('#offline-save').click();
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.all())[0].content === 'Draft from first tab'));
        await second.locator('#offline-edit').click();
        await second.locator('#offline-editor').fill('Different draft in second tab');
        await second.locator('#offline-save').click();
        await second.waitForFunction(() => document.getElementById('offline-message').textContent.includes('another tab'));
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all())[0].content), 'Draft from first tab');
        await second.close();
        await context.setOffline(false);
        await page.locator('#offline-sync').click();
        await eventually(() => remote === 'Draft from first tab');
        await page.locator('#offline-edit').click();

        // Failed validation and expired sessions retain the draft for later retry.
        failSync = true;
        await page.locator('#offline-edit').click();
        await page.locator('#offline-editor').fill('Retained after quota error');
        await page.locator('#offline-save').click();
        await page.waitForFunction(() => document.getElementById('offline-message').textContent.includes('Quota exceeded'));
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all())[0].dirty), true);
        failSync = false; authenticated = false;
        await page.locator('#offline-sync').click();
        await page.waitForFunction(() => document.querySelector('#offline-message a'));
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all())[0].dirty), true);
        authenticated = true; remote = 'Changed on another device';
        await page.locator('#offline-sync').click();
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.all())[0]?.path === 'Recovered.md'));
        assert.equal(remote, 'Changed on another device');
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all())[0].content), 'Retained after quota error');

        // Rendering cannot execute HTML scripts or event handlers from Markdown.
        const rendered = await page.evaluate(() => renderOfflineMarkdown('<img src=x onerror="window.pwned=1"><script>window.pwned=2</script>[click](javascript:alert(1))', location.origin + '/app'));
        assert(!rendered.includes('onerror')); assert(!rendered.includes('<script')); assert(!rendered.includes('javascript:'));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        if (process.env.MDNOTES_SCREENSHOTS) {
            await page.emulateMedia({ colorScheme: 'dark' });
            await page.locator('#offline-edit').click();
            await page.waitForFunction(() => document.documentElement.classList.contains('dark'));
            await page.screenshot({ path: path.join(process.env.MDNOTES_SCREENSHOTS, 'offline-mobile.png'), animations: 'disabled' });
            await page.setViewportSize({ width: 1440, height: 960 });
            await page.emulateMedia({ colorScheme: 'light' });
            await page.waitForFunction(() => !document.documentElement.classList.contains('dark'));
            await page.screenshot({ path: path.join(process.env.MDNOTES_SCREENSHOTS, 'offline-desktop.png'), animations: 'disabled' });
            await page.locator('#offline-edit').click();
        }

        // A different server account must never receive pending data from this cache.
        await page.locator('#offline-editor').fill('Previous account private draft');
        account = 'b'.repeat(64);
        const before = sent.length;
        await page.locator('#offline-save').click();
        await page.waitForFunction(() => document.getElementById('offline-message').textContent.includes('another account'));
        assert.equal(sent.length, before);
        await page.goto(origin + '/app/Note.md');
        await eventually(() => page.evaluate(async () => (await MdNotesOfflineDB.meta())?.account === 'b'.repeat(64)));
        assert(!(await page.evaluate(async () => JSON.stringify(await MdNotesOfflineDB.all()))).includes('Previous account private draft'));
        await page.locator('.profile-menu summary').click();
        await page.locator('.profile-popover form button').click();
        await page.waitForURL('**/login');
        assert.equal(await page.evaluate(() => MdNotesOfflineDB.meta()), null);
        assert.equal(await page.evaluate(async () => (await MdNotesOfflineDB.all()).length), 0);
        await context.setOffline(true);
        await page.goto(origin + '/app/Note.md');
        await page.locator('#offline-reader').waitFor();
        assert(!await page.locator('#offline-reader').innerText().then(text => text.includes('Original')));
        assert.deepEqual(errors, []);
    } finally {
        releaseResponse?.();
        await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
});
