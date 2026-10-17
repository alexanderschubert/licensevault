/* LicenseVault Service Worker – ausschließlich für Push-Benachrichtigungen.
   Bewusst KEIN fetch-Handler und kein Cache: Seiten, Schlüssel und QR-Codes werden nie offline gespeichert. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }
    event.waitUntil(self.registration.showNotification(data.title || 'LicenseVault', {
        body: data.body || '',
        icon: '/icons/icon-192-v3.png',
        badge: '/icons/icon-192-v3.png',
        tag: data.tag || 'licensevault',
        data: { url: data.url || '/erinnerungen' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/erinnerungen';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        for (const client of list) {
            if ('focus' in client) { client.navigate && client.navigate(url); return client.focus(); }
        }
        return self.clients.openWindow(url);
    }));
});
