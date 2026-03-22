// =============================================
// db-vendedor.js
// Capa de IndexedDB para modo offline
// =============================================

const DB_NAME = 'pedidos_lyd';
const DB_VERSION = 1;

let _db = null;

function abrirDB() {
    if (_db) return Promise.resolve(_db);

    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = e => {
            const db = e.target.result;

            // Productos del catálogo
            if (!db.objectStoreNames.contains('productos')) {
                const st = db.createObjectStore('productos', { keyPath: 'id_producto' });
                st.createIndex('id_categoria', 'id_categoria', { unique: false });
            }

            // Clientes
            if (!db.objectStoreNames.contains('clientes')) {
                db.createObjectStore('clientes', { keyPath: 'id_cliente' });
            }

            // Inventario del camión (productos cargados hoy)
            if (!db.objectStoreNames.contains('inventario')) {
                db.createObjectStore('inventario', { keyPath: 'id_producto' });
            }

            // Ventas generadas offline pendientes de sincronizar
            if (!db.objectStoreNames.contains('ventas_pendientes')) {
                const st2 = db.createObjectStore('ventas_pendientes', {
                    keyPath: 'id_local',
                    autoIncrement: true
                });
                st2.createIndex('sincronizada', 'sincronizada', { unique: false });
            }

            // Datos del dashboard cacheados
            if (!db.objectStoreNames.contains('cache_dashboard')) {
                db.createObjectStore('cache_dashboard', { keyPath: 'clave' });
            }
        };

        req.onsuccess = e => { _db = e.target.result; resolve(_db); };
        req.onerror = e => reject(e.target.error);
    });
}

// ── Helper genérico ─────────────────────────
function tx(store, modo, fn) {
    return abrirDB().then(db => new Promise((resolve, reject) => {
        const t = db.transaction(store, modo);
        const st = Array.isArray(store) ? store.map(s => t.objectStore(s)) : t.objectStore(store);
        fn(st, resolve, reject);
        t.onerror = e => reject(e.target.error);
    }));
}

// ── PRODUCTOS ───────────────────────────────
const DB = {

    guardarProductos(lista) {
        return tx('productos', 'readwrite', (st, res, rej) => {
            st.clear();
            lista.forEach(p => st.put(p));
            st.transaction.oncomplete = () => res(true);
        });
    },

    obtenerProductos() {
        return tx('productos', 'readonly', (st, res, rej) => {
            const r = st.getAll();
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    // ── CLIENTES ────────────────────────────
    guardarClientes(lista) {
        return tx('clientes', 'readwrite', (st, res, rej) => {
            st.clear();
            lista.forEach(c => st.put(c));
            st.transaction.oncomplete = () => res(true);
        });
    },

    obtenerClientes() {
        return tx('clientes', 'readonly', (st, res, rej) => {
            const r = st.getAll();
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    agregarClienteLocal(cliente) {
        // Clientes creados offline tienen id_cliente negativo temporal
        return tx('clientes', 'readwrite', (st, res, rej) => {
            const r = st.put(cliente);
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    // ── INVENTARIO ──────────────────────────
    guardarInventario(lista) {
        return tx('inventario', 'readwrite', (st, res, rej) => {
            st.clear();
            lista.forEach(i => st.put(i));
            st.transaction.oncomplete = () => res(true);
        });
    },

    obtenerInventario() {
        return tx('inventario', 'readonly', (st, res, rej) => {
            const r = st.getAll();
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    descontarInventario(id_producto, cantidad) {
        return tx('inventario', 'readwrite', (st, res, rej) => {
            const r = st.get(id_producto);
            r.onsuccess = () => {
                const item = r.result;
                if (!item) return res(false);
                item.cantidad_disponible = Math.max(0, (item.cantidad_disponible || 0) - cantidad);
                const r2 = st.put(item);
                r2.onsuccess = () => res(true);
            };
            r.onerror = () => rej(r.error);
        });
    },

    // ── VENTAS PENDIENTES ───────────────────
    guardarVentaPendiente(venta) {
        // venta = { id_cliente, cliente_nombre, cliente_dir, tipo_venta,
        //           items, total, abono, fecha, timestamp, vendedor_id, vendedor_nombre }
        const payload = { ...venta, sincronizada: 0 };
        return tx('ventas_pendientes', 'readwrite', (st, res, rej) => {
            const r = st.add(payload);
            r.onsuccess = () => res(r.result); // devuelve id_local
            r.onerror = () => rej(r.error);
        });
    },

    obtenerVentasPendientes() {
        return tx('ventas_pendientes', 'readonly', (st, res, rej) => {
            const idx = st.index('sincronizada');
            const r = idx.getAll(0);
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    obtenerVentaPorIdLocal(id_local) {
        return tx('ventas_pendientes', 'readonly', (st, res, rej) => {
            const r = st.get(id_local);
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    marcarVentaSincronizada(id_local, id_servidor) {
        return tx('ventas_pendientes', 'readwrite', (st, res, rej) => {
            const r = st.get(id_local);
            r.onsuccess = () => {
                const v = r.result;
                if (!v) return res(false);
                v.sincronizada = 1;
                v.id_servidor = id_servidor;
                const r2 = st.put(v);
                r2.onsuccess = () => res(true);
            };
            r.onerror = () => rej(r.error);
        });
    },

    contarPendientes() {
        return tx('ventas_pendientes', 'readonly', (st, res, rej) => {
            const idx = st.index('sincronizada');
            const r = idx.count(0);
            r.onsuccess = () => res(r.result);
            r.onerror = () => rej(r.error);
        });
    },

    // Todas las ventas (pendientes + sincronizadas) del día
    obtenerTodasVentasHoy() {
        const hoy = new Date().toISOString().split('T')[0];
        return tx('ventas_pendientes', 'readonly', (st, res, rej) => {
            const r = st.getAll();
            r.onsuccess = () => res(r.result.filter(v => v.fecha === hoy));
            r.onerror = () => rej(r.error);
        });
    },

    // ── CACHE DASHBOARD ─────────────────────
    guardarDashboard(datos) {
        return tx('cache_dashboard', 'readwrite', (st, res, rej) => {
            const r = st.put({ clave: 'datos', ...datos, actualizado: Date.now() });
            r.onsuccess = () => res(true);
            r.onerror = () => rej(r.error);
        });
    },

    obtenerDashboard() {
        return tx('cache_dashboard', 'readonly', (st, res, rej) => {
            const r = st.get('datos');
            r.onsuccess = () => res(r.result || null);
            r.onerror = () => rej(r.error);
        });
    },
};

// Exportar globalmente
window.DB = DB;