self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => self.clients.claim());

self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) {}
  const title = data.title || 'MR SOS';
  const options = {
    body: data.body || '',
    icon: '/img/image.png',   // pon un icono si tienes
    badge: '/img/badge-72.png',  // opcional
    data: { url: data.url || '/admin/' }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification?.data?.url || '/admin/';
  event.waitUntil(clients.openWindow(url));
});
