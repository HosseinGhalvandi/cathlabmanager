// Service worker برای نصب‌پذیری (PWA) و نمایش صفحه آفلاین.
// صفحات PHP اطلاعات بیماران را دارند، پس هیچ‌وقت کش نمی‌شوند؛ فقط فایل‌های ثابت کش می‌شوند.
const CACHE = 'cathlab-v1';
const STATIC_ASSETS = [
  'offline.html',
  'manifest.json',
  'icons/icon-192.png',
  'icons/icon-512.png',
  'icons/maskable-512.png',
  'icons/apple-touch-icon.png',
  'icons/favicon-32.png'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;

  // صفحات: همیشه از شبکه، در صورت قطعی اینترنت صفحه آفلاین
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }

  // فایل‌های ثابت خود سایت: اول کش
  const url = new URL(req.url);
  if (url.origin === self.location.origin && /\.(png|json|css|js)$/.test(url.pathname)) {
    event.respondWith(caches.match(req).then(hit => hit || fetch(req)));
  }
});
