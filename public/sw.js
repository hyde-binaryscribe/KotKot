/* KotKot service worker: the app opens even without network. */
const CACHE = 'kotkot-2.7.0';
const SHELL = [
  './',
  'assets/app.css?v=2.7.0',
  'assets/breeds.js?v=2.7.0',
  'assets/gazette.js?v=2.7.0',
  'assets/app.js?v=2.7.0',
  'assets/icon.svg',
  'assets/icon-192.png',
  'assets/icon-512.png',
  'assets/fonts/atkinson-regular.woff2',
  'assets/fonts/atkinson-bold.woff2',
  'manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  if (url.pathname.endsWith('/api.php')) return; // always live data

  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => { caches.open(CACHE).then((c) => c.put('./', res.clone())); return res; })
        .catch(() => caches.match('./'))
    );
    return;
  }
  event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
});

// Reminders sent by the server (door left open, treatment to do).
self.addEventListener('push', (event) => {
  let msg = {};
  try { msg = event.data ? event.data.json() : {}; } catch { msg = { body: event.data && event.data.text() }; }
  event.waitUntil(self.registration.showNotification(msg.title || 'KotKot', {
    body: msg.body || '',
    tag: msg.tag || 'kotkot',
    renotify: true,
    icon: 'assets/icon-192.png',
    badge: 'assets/icon-192.png',
    data: { url: msg.url || './#/' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || './#/', self.registration.scope).href;
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      const open = list.find((c) => 'focus' in c);
      return open ? open.focus().then((c) => (c.navigate ? c.navigate(url) : c)) : self.clients.openWindow(url);
    })
  );
});
