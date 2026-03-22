<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$nombre      = $_SESSION['nombre'];
$hoy         = date('Y-m-d');
$ayer        = date('Y-m-d', strtotime('-1 day'));

// ── Ventas de hoy ────────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS pedidos
     FROM venta WHERE id_vendedor = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$ventas_hoy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ── Ventas de ayer (para delta) ──────────────
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(total), 0) AS total
     FROM venta WHERE id_vendedor = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $ayer);
mysqli_stmt_execute($stmt);
$ventas_ayer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ── Delta % vs ayer ──────────────────────────
$delta_pct      = 0;
$delta_positivo = true;
if ($ventas_ayer['total'] > 0) {
    $delta_pct      = round((($ventas_hoy['total'] - $ventas_ayer['total']) / $ventas_ayer['total']) * 100);
    $delta_positivo = $delta_pct >= 0;
}

// ── Jornada activa ───────────────────────────
// Activa = no tiene cierre en cierrediario hoy
$stmt = mysqli_prepare($conexion,
    "SELECT id_cierre FROM cierrediario
     WHERE id_usuario = ? AND fecha = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cierre_hoy    = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$jornada_activa = !$cierre_hoy;

// Número de día de ruta = cierres históricos + 1
$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS total FROM cierrediario WHERE id_usuario = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id_vendedor);
mysqli_stmt_execute($stmt);
$dia_ruta = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] + 1;

// ── Clientes activos ─────────────────────────
$res            = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM cliente WHERE estado = 1");
$total_clientes = (int) mysqli_fetch_assoc($res)['total'];

// ── Productos en camión hoy ──────────────────
// Primero busca inventario cargado hoy, si no hay usa catálogo
$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS total FROM inventariocamion
     WHERE id_vendedor = ? AND fecha_cargue = ?
       AND estado = 1 AND cantidad_disponible > 0"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$total_productos = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

if ($total_productos === 0) {
    $res             = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM productos WHERE estado = 1");
    $total_productos = (int) mysqli_fetch_assoc($res)['total'];
}

// ── Facturas emitidas hoy ────────────────────
$facturas_hoy = (int) $ventas_hoy['pedidos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="/public/manifest.json">
    <meta name="theme-color" content="#1855CF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LYD">
    <link rel="apple-touch-icon" href="/public/icons/icon-192x192.png">

    <title>Inicio — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<!-- ══ TOP BAR ══ -->
<header class="topbar">
    <div class="topbar-left">
        <div class="avatar">
            <i class="bi bi-person-fill"></i>
        </div>
        <div>
            <div class="greeting-sub">Hola 👋</div>
            <div class="greeting-name"><?php echo htmlspecialchars($nombre); ?></div>
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>controllers/logout.php" class="topbar-btn" title="Cerrar sesión">
        <i class="bi bi-box-arrow-right"></i>
    </a>
</header>

<!-- ══ CONTENIDO ══ -->
<main class="scroll-body">

    <!-- Banner jornada -->
    <?php if ($jornada_activa): ?>
    <div class="jornada-banner jornada-activa">
        <div class="jornada-left">
            <span class="jornada-dot dot-on"></span>
            <span class="jornada-text">Jornada activa · <?php echo date('g:i A'); ?></span>
        </div>
        <span class="jornada-badge">Día <?php echo $dia_ruta; ?></span>
    </div>
    <?php else: ?>
    <div class="jornada-banner jornada-inactiva">
        <div class="jornada-left">
            <span class="jornada-dot dot-off"></span>
            <span class="jornada-text">Jornada cerrada hoy</span>
        </div>
        <span class="jornada-badge">Día <?php echo $dia_ruta - 1; ?></span>
    </div>
    <?php endif; ?>

    <!-- Card ventas del día -->
    <div class="ventas-card">
        <div class="ventas-label">Ventas del día</div>

        <?php if ($ventas_hoy['total'] > 0): ?>
            <div class="ventas-amount">
                $<?php echo number_format($ventas_hoy['total'], 0, ',', '.'); ?>
                <span class="ventas-cop">COP</span>
            </div>
            <div class="ventas-footer">
                <div class="ventas-delta <?php echo $delta_positivo ? 'delta-pos' : 'delta-neg'; ?>">
                    <i class="bi bi-arrow-<?php echo $delta_positivo ? 'up' : 'down'; ?>-short"></i>
                    <span><?php echo ($delta_positivo ? '+' : '') . $delta_pct; ?>% vs ayer</span>
                </div>
                <div class="ventas-pedidos">
                    <span><?php echo (int) $ventas_hoy['pedidos']; ?></span> pedidos hoy
                </div>
            </div>
        <?php else: ?>
            <div class="ventas-amount">$0</div>
            <div class="ventas-empty-msg">Empieza tu primera venta del día</div>
        <?php endif; ?>
    </div>

    <!-- CTA Tomar pedido -->
    <?php if ($jornada_activa): ?>
    <a href="clientes.php" class="cta-btn">
        <div class="cta-left">
            <span class="cta-title">Tomar Pedido</span>
            <span class="cta-sub">Iniciar nueva venta</span>
        </div>
        <div class="cta-icon">
            <i class="bi bi-cart3"></i>
        </div>
    </a>
    <?php else: ?>
    <div class="cta-btn cta-disabled">
        <div class="cta-left">
            <span class="cta-title">Tomar Pedido</span>
            <span class="cta-sub">La jornada de hoy ya fue cerrada</span>
        </div>
        <div class="cta-icon">
            <i class="bi bi-lock-fill"></i>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grid accesos rápidos -->
    <div class="access-grid">

        <a href="clientes.php" class="access-card">
            <div class="access-icon-wrap ic-blue">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="access-title">Clientes</div>
            <div class="access-sub">Ver directorio</div>
            <div class="access-count cnt-blue"><?php echo $total_clientes; ?> clientes</div>
        </a>

        <a href="inventario.php" class="access-card">
            <div class="access-icon-wrap ic-orange">
                <i class="bi bi-truck-front-fill"></i>
            </div>
            <div class="access-title">Inventario</div>
            <div class="access-sub">Stock camión</div>
            <div class="access-count cnt-orange"><?php echo $total_productos; ?> productos</div>
        </a>

        <a href="facturas.php" class="access-card">
            <div class="access-icon-wrap ic-purple">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="access-title">Facturas</div>
            <div class="access-sub">Historial hoy</div>
            <div class="access-count cnt-purple"><?php echo $facturas_hoy; ?> emitidas</div>
        </a>

        <a href="cierre.php" class="access-card">
            <div class="access-icon-wrap ic-red">
                <i class="bi bi-flag-fill"></i>
            </div>
            <div class="access-title">Cierre de Ruta</div>
            <div class="access-sub">Finalizar jornada</div>
            <div class="access-count cnt-red">
                <?php echo $jornada_activa ? 'Pendiente' : 'Completado'; ?>
            </div>
        </a>

    </div>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<script>
// ── Tracking de ubicación silencioso ─────────────────────
// Envía la posición del vendedor al servidor cada 30 segundos
// Solo si el navegador tiene geolocalización disponible
(function iniciarTracking() {
    if (!navigator.geolocation) return;

    function enviarUbicacion(pos) {
        const fd = new FormData();
        fd.append('lat', pos.coords.latitude);
        fd.append('lng', pos.coords.longitude);
        fetch('ubicacion.php', { method: 'POST', body: fd }).catch(() => {});
    }

    function obtenerYEnviar() {
        navigator.geolocation.getCurrentPosition(enviarUbicacion, () => {}, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 20000
        });
    }

    obtenerYEnviar();
    setInterval(obtenerYEnviar, 30000);
})();
</script>

<!-- ══ OFFLINE: Service Worker + Sync + Cache ══ -->
<script src="/public/js/db-vendedor.js"></script>
<script>
// ── Datos del servidor para cachear en IndexedDB ──────────
const DATOS_SERVIDOR = {
    total_ventas_hoy:  <?php echo (float) $ventas_hoy['total']; ?>,
    pedidos_hoy:       <?php echo (int)   $ventas_hoy['pedidos']; ?>,
    delta_pct:         <?php echo $delta_pct; ?>,
    delta_positivo:    <?php echo $delta_positivo ? 'true' : 'false'; ?>,
    jornada_activa:    <?php echo $jornada_activa ? 'true' : 'false'; ?>,
    dia_ruta:          <?php echo $dia_ruta; ?>,
    total_clientes:    <?php echo $total_clientes; ?>,
    total_productos:   <?php echo $total_productos; ?>,
    nombre:            '<?php echo addslashes($nombre); ?>',
    vendedor_id:       <?php echo $id_vendedor; ?>,
    hoy:               '<?php echo $hoy; ?>',
};

// ── Registrar Service Worker ──────────────────────────────
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/public/vendedor/sw-vendedor.js')
        .then(reg => {
            console.log('[SW] Registrado:', reg.scope);
        })
        .catch(err => console.warn('[SW] Error:', err));
}

// ── Cachear datos del servidor en IndexedDB ───────────────
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Guardar snapshot del dashboard
        await DB.guardarDashboard(DATOS_SERVIDOR);

        // ── Sincronizar ventas pendientes si hay red ──────
        const pendientes = await DB.obtenerVentasPendientes();
        if (pendientes.length > 0) {
            await sincronizarPendientes(pendientes);
        }

        // ── Mostrar badge de pendientes en navbar ─────────
        actualizarBadgePendientes();

    } catch(e) {
        console.warn('[Offline] Error al cachear datos:', e);
    }
});

// ── Sincronizar ventas offline con el servidor ────────────
async function sincronizarPendientes(ventas) {
    if (!navigator.onLine) return;

    try {
        const res = await fetch('sync.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ ventas }),
        });

        if (!res.ok) return;
        const data = await res.json();

        for (const r of data.resultados || []) {
            if (r.ok) {
                await DB.marcarVentaSincronizada(r.id_local, r.id_servidor);
                console.log(`[Sync] Venta ${r.id_local} → servidor #${r.id_servidor}`);
            }
        }

        // Recargar la página para reflejar datos reales del servidor
        const sinc_count = (data.resultados || []).filter(r => r.ok).length;
        if (sinc_count > 0) {
            mostrarToast(`✅ ${sinc_count} venta(s) sincronizada(s) con el servidor`);
            setTimeout(() => location.reload(), 2000);
        }

    } catch(e) {
        console.warn('[Sync] No se pudo sincronizar:', e);
    }
}

// ── Badge pendientes ──────────────────────────────────────
async function actualizarBadgePendientes() {
    const n = await DB.contarPendientes();
    const badge = document.getElementById('badge-pendientes');
    if (!badge) return;
    badge.textContent = n;
    badge.style.display = n > 0 ? 'inline-flex' : 'none';
}

// ── Toast notificación ────────────────────────────────────
function mostrarToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = `
        position:fixed; bottom:80px; left:50%; transform:translateX(-50%);
        background:#1e293b; color:#fff; padding:0.7rem 1.2rem;
        border-radius:10px; font-size:0.85rem; z-index:9999;
        box-shadow:0 4px 12px rgba(0,0,0,0.3); white-space:nowrap;
    `;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ── Escuchar cambio de conectividad ───────────────────────
window.addEventListener('online', async () => {
    const pendientes = await DB.obtenerVentasPendientes();
    if (pendientes.length > 0) {
        mostrarToast('📶 Conexión recuperada. Sincronizando ventas...');
        await sincronizarPendientes(pendientes);
    }
});
</script>

</body>
</html>