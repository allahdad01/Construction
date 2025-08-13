const CACHE_NAME = 'csp-cache-v1';
const OFFLINE_URLS = [
  '/constract360/construction/public/',
  '/constract360/construction/public/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(OFFLINE_URLS);
    self.skipWaiting();
  })());
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;
  event.respondWith((async () => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);
    if (cached) return cached;
    try {
      const res = await fetch(req);
      // Cache basic same-origin GETs
      if (req.url.startsWith(self.location.origin) && res && res.status === 200 && res.type === 'basic') {
        cache.put(req, res.clone());
      }
      return res;
    } catch (e) {
      // Fallback to offline root
      return cache.match('/constract360/construction/public/');
    }
  })());
});

self.addEventListener('message', event => {
  const data = event.data || {};
  if (data && data.type === 'SHOW_NOTIFICATION') {
    const title = data.title || 'Notification';
    const options = {
      body: data.body || '',
      icon: 'https://via.placeholder.com/192.png',
      badge: 'https://via.placeholder.com/72.png',
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