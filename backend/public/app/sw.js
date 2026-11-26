/*
 * Service worker SIMPRES web: menyimpan tampilan aplikasi di HP supaya tetap terbuka tanpa internet
 * (absensi offline masuk antrean). Data API tidak pernah disimpan di cache.
 * Ganti VERSION setiap kali file web berubah, supaya HP pengguna mengambil versi baru.
 */
const VERSION = 'simpres-web-2';
const FILES = [
    './', 'index.html', 'app.css', 'manifest.webmanifest',
    'gen/res.css', 'gen/res.js',
    'gen/raw/intro_simpres.json', 'gen/raw/onboarding_location.json', 'gen/raw/onboarding_offline.json', 'gen/raw/onboarding_leave.json',
    'vendor/lottie_light.min.js',
    'js/core.js', 'js/ui.js', 'js/auth.js', 'js/home.js', 'js/attendance.js', 'js/leave.js',
    'icons/icon-180.png', 'icons/icon-192.png', 'icons/icon-512.png',
    '../fonts/lexend_light.ttf', '../fonts/lexend_regular.ttf', '../fonts/lexend_medium.ttf', '../fonts/lexend_semibold.ttf',
];

self.addEventListener('install', e => {
    e.waitUntil(caches.open(VERSION).then(c => c.addAll(FILES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
    e.waitUntil(caches.keys()
        .then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);
    // API dan tile peta selalu dari jaringan.
    if (e.request.method !== 'GET' || url.pathname.includes('/api/') || url.origin !== location.origin) return;
    // File aplikasi: ambil versi terbaru jika online, pakai cache jika offline.
    e.respondWith(fetch(e.request)
        .then(res => {
            if (res.ok) caches.open(VERSION).then(c => c.put(e.request, res.clone()));
            return res;
        })
        .catch(() => caches.match(e.request).then(r => r || caches.match('index.html'))));
});
