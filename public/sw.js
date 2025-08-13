self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('message', event => {
  const data = event.data || {};
  if (data && data.type === 'SHOW_NOTIFICATION') {
    const title = data.title || 'Notification';
    const options = {
      body: data.body || '',
      icon: '/constract360/construction/public/assets/icons/icon-192.png',
      badge: '/constract360/construction/public/assets/icons/badge-72.png',
      data: { url: data.url || '/constract360/construction/public/' }
    };
    event.waitUntil(self.registration.showNotification(title, options));
  }
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/constract360/construction/public/';
  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
      if ('focus' in client) { return client.focus(); }
    }
    if (clients.openWindow) { return clients.openWindow(url); }
  })());
});