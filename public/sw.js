/* SICA-EPO6 — Service worker
 * Permite abrir el sistema SIN internet:
 *   - Librerías (/vendor) e imágenes (/img): se guardan la primera vez y se sirven desde el equipo.
 *   - Página de inicio ("/"): se intenta primero el servidor; si no hay internet se usa la última copia.
 *   - /api y /auth NUNCA se guardan: siempre van al servidor (los datos viven en la base de datos).
 * Si cambias algo en /vendor, sube el número de VERSION para que los equipos descarguen la copia nueva.
 */
const VERSION = 'sica-v7';
const C_ESTATICOS = VERSION + '-estaticos';
const C_PAGINAS = VERSION + '-paginas';

const PRECARGA = [
  '/vendor/bootstrap.min.css',
  '/vendor/fontawesome/css/all.min.css',
  '/vendor/fontawesome/webfonts/fa-solid-900.woff2',
  '/vendor/fontawesome/webfonts/fa-regular-400.woff2',
  '/vendor/fontawesome/webfonts/fa-brands-400.woff2',
  '/vendor/jquery.min.js',
  '/vendor/html5-qrcode.min.js',
  '/vendor/qrious.min.js',
  '/vendor/xlsx.full.min.js',
  '/vendor/jspdf.umd.min.js',
  '/vendor/jspdf.plugin.autotable.min.js',
  '/vendor/chart.umd.min.js',
  '/vendor/pdf.min.js',
  '/vendor/pdf.worker.min.js',
  '/img/logo6.png',
  '/img/epo6.png',
  '/vendor/jszip.min.js',
  '/vendor/sica-docx.js',
  '/plantillas/FrenteMatutino.docx',
  '/plantillas/FrenteVespertino.docx',
  '/plantillas/Reverso.docx',
  '/manifest.webmanifest'
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(C_ESTATICOS)
      .then(c => Promise.all(PRECARGA.map(u => c.add(u).catch(() => {}))))   // un archivo que falle no cancela la instalación
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(claves => Promise.all(claves.filter(k => k.startsWith('sica-') && k !== C_ESTATICOS && k !== C_PAGINAS).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Al cerrar sesión la página avisa para borrar la copia guardada (trae el nombre/rol de quien la abrió)
// Al iniciar sesión (o al abrir con sesión) avisa para guardar la copia YA CON la sesión: así, si luego
// se abre sin internet, entra directo al sistema en vez de mostrar "Sin conexión".
self.addEventListener('message', e => {
  if (e.data === 'limpiar-paginas') e.waitUntil(caches.delete(C_PAGINAS));
  if (e.data === 'guardar-pagina') {
    e.waitUntil(
      fetch('/', { credentials: 'same-origin', cache: 'no-store' }).then(res => {
        if (res.ok && !res.redirected) return caches.open(C_PAGINAS).then(c => c.put('/', res));
      }).catch(() => {})
    );
  }
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/auth/')) return;   // siempre al servidor

  // Páginas: servidor primero, copia guardada si no hay internet
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).then(res => {
        if (res.ok && !res.redirected && url.pathname === '/') {
          const copia = res.clone();
          caches.open(C_PAGINAS).then(c => c.put('/', copia));
        }
        return res;
      }).catch(() =>
        caches.match('/', { cacheName: C_PAGINAS }).then(r => r || new Response(
          '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
          '<body style="font-family:sans-serif;text-align:center;padding:40px"><h2>Sin conexión</h2>' +
          '<p>Abre el sistema una vez con internet para poder usarlo después sin conexión.</p></body>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }))
      )
    );
    return;
  }

  // Librerías: primero la copia guardada
  if (url.pathname.startsWith('/vendor/')) {
    e.respondWith(
      caches.match(req, { cacheName: C_ESTATICOS }).then(r => r || fetch(req).then(res => {
        if (res.ok) { const copia = res.clone(); caches.open(C_ESTATICOS).then(c => c.put(req, copia)); }
        return res;
      }))
    );
    return;
  }

  // Imágenes y plantillas de Word (se pueden reemplazar): primero el servidor (así se ve la plantilla nueva),
  // y si no hay internet, la última copia guardada. La página las pide con ?v=<hora>; aquí se guardan
  // por su ruta (sin el parámetro) para que sirvan sin internet y no se acumulen copias.
  if (url.pathname.startsWith('/img/') || url.pathname.startsWith('/plantillas/') || url.pathname.startsWith('/plantilla-oficial/')) {
    const clave = new Request(url.origin + url.pathname);
    e.respondWith(
      fetch(req).then(res => {
        if (res.ok) { const copia = res.clone(); caches.open(C_ESTATICOS).then(c => c.put(clave, copia)); }
        return res;
      }).catch(() => caches.open(C_ESTATICOS).then(c => c.match(clave)).then(r => r || Response.error()))
    );
  }
});
