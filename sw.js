self.addEventListener('install', (e) => {
  self.skipWaiting();
});
self.addEventListener('fetch', (e) => {
  // Simple pass-through for live data
  e.respondWith(fetch(e.request));
});
