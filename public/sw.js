/* KamVerify service worker — Web Push delivery.
 * Shows OS/browser notifications and focuses the app on click. */

self.addEventListener('push', function (event) {
    if (!event.data) return;

    let payload = {};
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'KamVerify', body: event.data.text() };
    }

    const title = payload.title || 'KamVerify';
    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            icon: payload.icon || '/icon.png',
            badge: '/icon.png',
            tag: payload.tag || 'kamverify',
            data: { url: payload.url || '/' },
            renotify: true,
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (const client of list) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            return clients.openWindow(url);
        })
    );
});
