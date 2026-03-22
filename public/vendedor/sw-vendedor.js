// =============================================
// sw-vendedor.js  — Service Worker
// Ubicación: public/vendedor/sw-vendedor.js
// Cachea el panel del vendedor para modo offline
// =============================================
// IMPORTANTE: debe estar en public/vendedor/ para
// que su scope cubra todas las páginas del vendedor

const CACHE_VERSION = 'lyd-v1';
const CACHE_ESTATICO = 'lyd-static-v1';

// Assets que se cachean al instalar el SW
const ASSETS_ESTATICOS = [
    '/public/vendedor/offline.php',
    '/public/css/dashboard_vendedor.css',
    '/public/css/carrito.css',
    '/public/css/clientes_vendedor.css',
    '/public/css/inventario.css',
    '/public/css/facturas.css',
    '/public/css/navbar_vendedor.css',
    '/public/js/db-vendedor.js',
];

// Páginas del vendedor que se cachean con estrategia Network-First
const PAGINAS_VENDEDOR = [
    '/public/vendedor/dashboard.php',
    '/public/vendedor/productos.php',
    '/public/vendedor/clientes.php',
    '/public/vendedor/carrito.php',
    '/public/vendedor/comprobante.php',
    '/public/vendedor/inventario.php',
    '/public/vendedor/facturas.php',
];

// ── INSTALL ─────────────────────────────────
self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_ESTATICO)
            .then(cache => cache.addAll(ASSETS_ESTATICOS))
            .then(() => self.skipWaiting())
    );
});

// ── ACTIVATE ────────────────────────────────
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== CACHE_VERSION && k !== CACHE_ESTATICO)
                    .map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── FETCH ────────────────────────────────────
self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Solo interceptar GET del mismo origen
    if (e.request.method !== 'GET' || url.origin !== location.origin) return;

    // sync.php y ubicacion.php: siempre red, nunca cachear
    if (url.pathname.includes('sync.php') || url.pathname.includes('ubicacion.php')) return;

    // Assets estáticos: Cache-First
    if (esAssetEstatico(url.pathname)) {
        e.respondWith(cacheFirst(e.request));
        return;
    }

    // Páginas del vendedor: Network-First con fallback a caché
    if (esPaginaVendedor(url.pathname)) {
        e.respondWith(networkFirstConCache(e.request));
        return;
    }

    // Todo lo demás: intentar red, sin interferir
});

// ── ESTRATEGIAS ─────────────────────────────

// Cache-First: sirve caché, si no hay va a red
function cacheFirst(request) {
    return caches.match(request).then(cached => {
        if (cached) return cached;
        return fetch(request).then(response => {
            if (response && response.status === 200) {
                const clone = response.clone();
                caches.open(CACHE_ESTATICO).then(cache => cache.put(request, clone));
            }
            return response;
        });
    });
}

// Network-First: intenta red, guarda en caché, si falla sirve caché o offline
function networkFirstConCache(request) {
    return fetch(request)
        .then(response => {
            if (response && response.status === 200) {
                const clone = response.clone();
                caches.open(CACHE_VERSION).then(cache => cache.put(request, clone));
            }
            return response;
        })
        .catch(() => {
            return caches.match(request).then(cached => {
                if (cached) return cached;
                // Fallback a offline.php
                return caches.match('/public/vendedor/offline.php');
            });
        });
}

// ── HELPERS ─────────────────────────────────
function esAssetEstatico(path) {
    return path.includes('/public/css/') ||
        path.includes('/public/js/') ||
        path.includes('/public/uploads/') ||
        path.endsWith('.css') ||
        path.endsWith('.js') ||
        path.endsWith('.png') ||
        path.endsWith('.jpg') ||
        path.endsWith('.webp') ||
        path.endsWith('.woff2');
}

function esPaginaVendedor(path) {
    return PAGINAS_VENDEDOR.some(p => path.includes(p.replace('.php', '')));
}

// ── MENSAJE DESDE PÁGINA ─────────────────────
// La página puede enviar { tipo: 'SKIP_WAITING' } para activar el SW nuevo
self.addEventListener('message', e => {
    if (e.data && e.data.tipo === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});