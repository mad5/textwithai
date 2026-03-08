// Service Worker für die PWA TextWithAI
// Es werden absichtlich keine Inhalte gecacht (Anforderung: "keine Inhalte cachen")

self.addEventListener('install', (event) => {
  // Sofort aktivieren, wenn installiert
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Übernimmt die Kontrolle sofort
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Reicht alle Anfragen direkt ans Netzwerk weiter, ohne Cache-Logik
  // (Entspricht dem Standardverhalten, aber notwendig für PWA-Erkennung)
  return; 
});
