const CACHE_NAME = 'csp-cache-v1';
const BASE_URL = self.location.origin + self.location.pathname.substring(0, self.location.pathname.lastIndexOf('/') + 1);
const OFFLINE_URLS = [
  BASE_URL,
  BASE_URL + 'manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Don't cache dynamic content
const DYNAMIC_PATTERNS = [
  /\.php$/,
  /\/api\//,
  /\/dashboard\//,
  /\/users\//,
  /\/admin\//,
  /\/employee\//,
  /\/super-admin\//,
  /\/reports\//,
  /\/settings\/
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
  
  // Check if this is dynamic content that shouldn't be cached
  const isDynamicContent = DYNAMIC_PATTERNS.some(pattern => 
    pattern.test(req.url) || req.url.includes('?')
  );
  
  event.respondWith((async () => {
    // For dynamic content, always fetch fresh from network
    if (isDynamicContent) {
      try {
        const res = await fetch(req);
        return res;
      } catch (e) {
        // Fallback to offline root if network fails
        return caches.match(BASE_URL);
      }
    }
    
    // For static content, use cache-first strategy
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);
    if (cached) return cached;
    
    try {
      const res = await fetch(req);
      // Only cache static assets, not dynamic content
      if (res && res.status === 200 && !isDynamicContent) {
        cache.put(req, res.clone());
      }
      return res;
    } catch (e) {
      // Fallback to offline root
      return cache.match(BASE_URL);
    }
  })());
});

self.addEventListener('message', event => {
  const data = event.data || {};
  if (data && data.type === 'SHOW_NOTIFICATION') {
    const title = data.title || 'Notification';
    const options = {
      body: data.body || '',
      icon: BASE_URL + 'uploads/logos/icon-192.png',
      badge: BASE_URL + 'uploads/logos/icon-192.png',
      data: { url: data.url || BASE_URL }
    };
    event.waitUntil(self.registration.showNotification(title, options));
  }
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || BASE_URL;
  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
      if ('focus' in client) { return client.focus(); }
    }
    if (clients.openWindow) { return clients.openWindow(url); }
  })());
});