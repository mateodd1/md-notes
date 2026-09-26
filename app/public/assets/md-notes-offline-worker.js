/* OFFLINE_CONFIG is supplied by the same-origin worker endpoint. No authenticated HTML is cached. */
importScripts('/assets/md-notes-offline-db.js');
const SHELL_CACHE = 'md-notes-shell-' + OFFLINE_CONFIG.version;
const MEDIA_PREFIX = 'md-notes-media-';
self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL_CACHE);
        await cache.addAll(OFFLINE_CONFIG.assets.map(url => new Request(url, { cache: 'reload' })));
        await self.skipWaiting();
    })());
});
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith('md-notes-shell-') && name !== SHELL_CACHE) await caches.delete(name);
        }
        await self.clients.claim();
    })());
});
const base = OFFLINE_CONFIG.base.replace(/\/$/, '');
const noteNavigation = path => path === base || path === base + '/' || path === '/offline'
    || (path.startsWith(base + '/') && path.endsWith('.md')
        && !/^(?:share|history|properties|download|account-export|media)\//.test(path.slice(base.length + 1)));
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (OFFLINE_CONFIG.assets.includes(url.pathname) && url.pathname !== '/offline') {
        event.respondWith((async () => (await caches.open(SHELL_CACHE)).match(url.pathname).then(hit => hit || fetch(request)))());
        return;
    }
    if (request.mode === 'navigate' && noteNavigation(url.pathname)) {
        event.respondWith(fetch(request).catch(async () => (await caches.open(SHELL_CACHE)).match('/offline')));
        return;
    }
    if (new RegExp('^' + base + '/media/[a-z0-9]{24}\\.[a-z0-9]{1,10}$').test(url.pathname)) {
        event.respondWith(fetch(request).catch(async () => {
            const config = await MdNotesOfflineDB.meta();
            const cached = config && await (await caches.open(MEDIA_PREFIX + config.account)).match(url.origin + url.pathname);
            return cached || new Response('', { status: 503 });
        }));
    }
});
self.addEventListener('message', event => {
    if (event.data?.type !== 'CLEAR_OFFLINE') return;
    event.waitUntil((async () => {
        await MdNotesOfflineDB.clear();
        for (const name of await caches.keys()) if (name.startsWith(MEDIA_PREFIX)) await caches.delete(name);
        for (const client of await self.clients.matchAll()) client.postMessage({ type: 'OFFLINE_CLEARED' });
        event.ports[0]?.postMessage({ cleared: true });
    })());
});
