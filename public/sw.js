/*
 * The service worker of the panel: shows the notifications sent by the panel (a server crashed, restarted...) on
 * this phone or computer, even when the panel is closed, and opens the right page when one is clicked. It keeps
 * nothing in cache: the panel is always loaded from the network.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: event.data ? event.data.text() : '' };
    }
    event.waitUntil(
        self.registration.showNotification(data.title || 'Panel', {
            body: data.body || '',
            icon: '/favicons/android-chrome-192x192.png',
            badge: '/favicons/android-icon-96x96.png',
            tag: data.tag || undefined,
            renotify: !!data.tag,
            data: { url: data.url || '/' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    return client.focus().then((focused) => (focused && 'navigate' in focused ? focused.navigate(url) : focused));
                }
            }

            return self.clients.openWindow(url);
        })
    );
});
