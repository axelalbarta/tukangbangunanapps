// Service worker sederhana: menyimpan file tampilan (CSS, JS, ikon) supaya cepat dibuka,
// dan menampilkan halaman offline saat tidak ada sinyal. Data proyek & dokumen tidak disimpan di HP.
const CACHE = 'arsip-tb-v17';
const ASSETS = ['offline.html', 'assets/css/app.css', 'assets/js/app.js', 'assets/img/icon-192.png', 'assets/img/icon-512.png', 'assets/img/logo-dark.png', 'assets/img/logo-light.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }
  // Aset: ambil versi terbaru dari server dulu, pakai cadangan hanya saat offline
  if (url.pathname.includes('/assets/')) {
    e.respondWith(fetch(req).then(res => {
      if (res.ok) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); }
      return res;
    }).catch(() => caches.match(req, { ignoreSearch: true })));
  }
});

// Notifikasi dari server (Web Push)
self.addEventListener('push', e => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { isi: e.data ? e.data.text() : '' }; }
  const lingkup = self.registration.scope;
  e.waitUntil(self.registration.showNotification(d.judul || 'Arsip Tukang Bangunan', {
    body: d.isi || '',
    icon: lingkup + 'assets/img/icon-192.png',
    badge: lingkup + 'assets/img/badge-96.png',
    tag: d.tag || undefined,
    renotify: !!d.tag,
    data: { url: new URL(d.url || 'dashboard', lingkup).href },
    lang: 'id',
  }));
});

// Ketuk notifikasi: buka halaman terkait (pakai jendela aplikasi yang sudah terbuka bila ada)
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const tujuan = (e.notification.data && e.notification.data.url) || self.registration.scope;
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const c of list) {
      if (c.url.startsWith(self.registration.scope) && 'focus' in c) {
        return c.focus().then(w => (w && 'navigate' in w) ? w.navigate(tujuan) : null);
      }
    }
    return clients.openWindow(tujuan);
  }));
});
