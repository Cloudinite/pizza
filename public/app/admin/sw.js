/* Pizza Slice admin – service worker.
   Makes the admin installable and gives it an offline screen. Orders and all API calls
   always go to the network (never cached), so staff never see stale orders. */
const BASE = '__BASE__'; // secret admin address, filled in by sw.php
const CACHE = 'ps-admin-v3';
const SHELL = [BASE + 'offline', BASE + 'assets/admin.css', BASE + 'assets/icon-192.png', '/assets/img/logo-104.webp', '/assets/fonts/pjs-sk.woff2'];

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
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Pages: network first, offline screen as fallback.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(BASE + 'offline')));
    return;
  }
  // Versioned static assets: cache first.
  if (url.pathname.startsWith(BASE + 'assets/') || url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then(async (c) => {
            // drop older ?v= versions of the same file so the cache does not grow forever
            if (url.search) {
              for (const k of await c.keys()) {
                const ku = new URL(k.url);
                if (ku.pathname === url.pathname && ku.search && ku.search !== url.search) c.delete(k);
              }
            }
            c.put(req, copy);
          });
        }
        return res;
      }))
    );
  }
  // Everything else (api.php …) goes straight to the network.
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if (c.url.includes(BASE) && 'focus' in c) return c.focus();
      }
      return self.clients.openWindow(BASE);
    })
  );
});
