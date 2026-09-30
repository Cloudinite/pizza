// Offline support: serve the app from cache, refresh the cache in the background.
const CACHE = 'liftlog-v1';
const FILES = [
  './',
  'index.html',
  'styles.css',
  'app.js',
  'manifest.webmanifest',
  'icons/icon.svg',
  'icons/icon-192.png',
  'icons/icon-512.png',
  'icons/icon-maskable-512.png',
  'icons/apple-touch-icon.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(FILES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
  const key = req.mode === 'navigate' ? 'index.html' : req;
  const network = caches.open(CACHE).then((cache) =>
    fetch(req).then((res) => {
      if (res.ok) cache.put(key, res.clone());
      return res;
    })
  );
  e.waitUntil(network.catch(() => {}));
  e.respondWith(
    caches.match(key, { ignoreSearch: true }).then((cached) => cached || network)
  );
});
