// Retires the SilverBullet progressive-web-app worker that previously owned
// this origin. It runs once, refreshes open tabs, and then unregisters itself.
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        await self.registration.unregister();
        const windows = await self.clients.matchAll({ type: 'window' });
        windows.forEach((windowClient) => windowClient.navigate(windowClient.url));
    })());
});
