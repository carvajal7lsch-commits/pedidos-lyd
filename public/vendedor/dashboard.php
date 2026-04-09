<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

// Datos de dashboard online (SQL en servidor)
$nombre      = $_SESSION['nombre'];
$id_vendedor = (int) $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');
$ayer        = date('Y-m-d', strtotime('-1 day'));

// Jornada activa: sin cierre de hoy
$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS cnt FROM cierrediario WHERE id_usuario = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$hoyCierre = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];

$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS cnt FROM cierrediario WHERE id_usuario = ? AND fecha < ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$diasPrevios = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];

$jornada_activa = $hoyCierre === 0;
$dia_ruta       = $diasPrevios + 1;

$total_clientes = (int) mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS c FROM cliente WHERE estado = 1"
))['c'];

$total_productos = (int) mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COUNT(*) AS c FROM inventariocamion
     WHERE id_vendedor = $id_vendedor AND fecha_cargue = '$hoy'
       AND estado = 1 AND cantidad_disponible > 0"
))['c'];

// Ventas y pedidos hoy
$hoyVentas = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS pedidos
     FROM venta WHERE id_vendedor = $id_vendedor AND fecha = '$hoy'"
));

$total_ventas_hoy = (float)$hoyVentas['total'];
$pedidos_hoy      = (int)$hoyVentas['pedidos'];

// Ventas ayer para delta
$ayerVentas = mysqli_fetch_assoc(mysqli_query($conexion,
    "SELECT COALESCE(SUM(total),0) AS total
     FROM venta WHERE id_vendedor = $id_vendedor AND fecha = '$ayer'"
));

$total_ayer = (float)$ayerVentas['total'];
if ($total_ayer > 0) {
    $delta_pct = round(100 * ($total_ventas_hoy - $total_ayer) / $total_ayer, 2);
} elseif ($total_ventas_hoy > 0) {
    $delta_pct = 100;
} else {
    $delta_pct = 0;
}
$delta_positivo = $total_ventas_hoy >= $total_ayer;

// ── Cierre automático retroactivo ─────────────────────────
// Al abrir el dashboard, busca días anteriores con ventas pero sin cierre
// y los cierra automáticamente. También cierra HOY si ya pasó las 21:00.
$mensaje  = '';
$tipo_msg = '';
$hora_actual = date('H:i');

// Buscar días sin cierre que tuvieron ventas (anteriores a hoy, o hoy si >= 21:00)
$fecha_limite = ($hora_actual >= '21:00') ? date('Y-m-d', strtotime('+1 day')) : $hoy;

$stmt = mysqli_prepare($conexion,
    "SELECT v.fecha, COUNT(*) AS total_ventas
     FROM venta v
     LEFT JOIN cierrediario c ON c.id_usuario = v.id_vendedor AND c.fecha = v.fecha
     WHERE v.id_vendedor = ? AND v.fecha < ? AND c.id_cierre IS NULL
     GROUP BY v.fecha
     ORDER BY v.fecha ASC"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $fecha_limite);
mysqli_stmt_execute($stmt);
$dias_sin_cierre = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$cierres_realizados = 0;

foreach ($dias_sin_cierre as $dia) {
    $fecha_cierre = $dia['fecha'];

    // Ventas contado del día
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(total), 0) AS total
         FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'contado'"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $fecha_cierre);
    mysqli_stmt_execute($stmt);
    $ventas_contado = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    // Ventas crédito del día
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(total), 0) AS total
         FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'credito'"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $fecha_cierre);
    mysqli_stmt_execute($stmt);
    $ventas_credito = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    // Abonos iniciales de créditos de ese día
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(a.monto), 0) AS total
         FROM abono a
         JOIN venta v ON v.id_venta = a.id_venta
         WHERE v.id_vendedor = ? AND a.fecha = ? AND v.fecha = ? AND v.tipo_venta = 'credito'"
    );
    mysqli_stmt_bind_param($stmt, 'iss', $id_vendedor, $fecha_cierre, $fecha_cierre);
    mysqli_stmt_execute($stmt);
    $abonos_credito = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    $total_contado = $ventas_contado + $abonos_credito;
    $credito_pendiente = $ventas_credito - $abonos_credito;
    $total_general = $total_contado + $credito_pendiente;

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion,
            "INSERT INTO cierrediario (id_usuario, fecha, total_contado, total_credito, total_general, estado)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        mysqli_stmt_bind_param($stmt, 'isddd',
            $id_vendedor, $fecha_cierre, $total_contado, $credito_pendiente, $total_general
        );
        mysqli_stmt_execute($stmt);
        $id_cierre = mysqli_insert_id($conexion);

        $stmt = mysqli_prepare($conexion,
            "UPDATE venta SET id_cierre = ? WHERE id_vendedor = ? AND fecha = ? AND id_cierre IS NULL"
        );
        mysqli_stmt_bind_param($stmt, 'iis', $id_cierre, $id_vendedor, $fecha_cierre);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conexion);
        $cierres_realizados++;

        // Si cerró hoy, actualizar el estado
        if ($fecha_cierre === $hoy) {
            $jornada_activa = false;
        }

    } catch (Exception $e) {
        mysqli_rollback($conexion);
    }
}

if ($cierres_realizados > 0) {
    $mensaje  = $cierres_realizados === 1
        ? 'Se cerró automáticamente 1 jornada pendiente.'
        : "Se cerraron automáticamente $cierres_realizados jornadas pendientes.";
    $tipo_msg = 'success';
}

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
            <div class="greeting-name" id="dash-nombre"><?php echo htmlspecialchars($nombre); ?></div>
        </div>
    </div>
    <a href="<?php echo BASE_URL; ?>controllers/logout.php" class="topbar-btn" title="Cerrar sesión">
        <i class="bi bi-box-arrow-right"></i>
    </a>
</header>

<!-- ══ CONTENIDO ══ -->
<main class="scroll-body">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'jornada_cerrada'): ?>
    <div class="alerta alerta-error" style="margin:1rem;">
        <i class="bi bi-exclamation-circle-fill"></i>
        Jornada cerrada: no es posible tomar pedidos hoy.
    </div>
    <?php endif; ?>

    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>" style="margin:1rem;">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Banner jornada -->
    <div id="dash-banner" class="jornada-banner jornada-activa">
        <div class="jornada-left">
            <span class="jornada-dot dot-on"></span>
            <span class="jornada-text" id="dash-banner-txt">Cargando...</span>
        </div>
        <span class="jornada-badge" id="dash-dia-ruta">—</span>
    </div>

    <!-- Card ventas del día -->
    <div class="ventas-card">
        <div class="ventas-label">Ventas del día</div>
        <div id="dash-ventas-content">
            <div class="ventas-amount" id="dash-total">$—</div>
            <div class="ventas-empty-msg" id="dash-ventas-empty" style="display:none">
                Empieza tu primera venta del día
            </div>
            <div class="ventas-footer" id="dash-ventas-footer" style="display:none">
                <div class="ventas-delta" id="dash-delta">
                    <i class="bi bi-arrow-up-short"></i>
                    <span id="dash-delta-txt"></span>
                </div>
                <div class="ventas-pedidos">
                    <span id="dash-pedidos">0</span> pedidos hoy
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Tomar pedido -->
    <div id="dash-cta-wrap">
        <a href="clientes.php" class="cta-btn" id="dash-cta-activo">
            <div class="cta-left">
                <span class="cta-title">Tomar Pedido</span>
                <span class="cta-sub">Iniciar nueva venta</span>
            </div>
            <div class="cta-icon"><i class="bi bi-cart3"></i></div>
        </a>
        <div class="cta-btn cta-disabled" id="dash-cta-cerrado" style="display:none">
            <div class="cta-left">
                <span class="cta-title">Tomar Pedido</span>
                <span class="cta-sub">La jornada de hoy ya fue cerrada</span>
            </div>
            <div class="cta-icon"><i class="bi bi-lock-fill"></i></div>
        </div>
    </div>

    <!-- Grid accesos rápidos -->
    <div class="access-grid">
        <a href="clientes.php" class="access-card">
            <div class="access-icon-wrap ic-blue"><i class="bi bi-people-fill"></i></div>
            <div class="access-title">Clientes</div>
            <div class="access-sub">Ver directorio</div>
            <div class="access-count cnt-blue" id="dash-cnt-clientes">— clientes</div>
        </a>
        <a href="inventario.php" class="access-card">
            <div class="access-icon-wrap ic-orange"><i class="bi bi-truck-front-fill"></i></div>
            <div class="access-title">Inventario</div>
            <div class="access-sub">Stock camión</div>
            <div class="access-count cnt-orange" id="dash-cnt-productos">— productos</div>
        </a>
        <a href="facturas.php" class="access-card">
            <div class="access-icon-wrap ic-purple"><i class="bi bi-receipt"></i></div>
            <div class="access-title">Facturas</div>
            <div class="access-sub">Historial hoy</div>
            <div class="access-count cnt-purple" id="dash-cnt-facturas">— emitidas</div>
        </a>
        <a href="cierre.php" class="access-card">
            <div class="access-icon-wrap ic-red"><i class="bi bi-flag-fill"></i></div>
            <div class="access-title">Cierre de Ruta</div>
            <div class="access-sub">Finalizar jornada</div>
            <div class="access-count cnt-red" id="dash-cnt-cierre">—</div>
        </a>
    </div>

</main>

<!-- ══ AYUDA FLOTANTE ══ -->
<button class="btn-ayuda-float" onclick="abrirAyuda()">
    <i class="bi bi-question-lg"></i>
</button>

<!-- ══ MODAL DE AYUDA ══ -->
<div class="modal-overlay" id="modalAyuda" style="display:none;">
    <div class="bottom-sheet" id="sheetAyuda">
        <div class="sheet-handle"></div>
        <button class="sheet-cerrar" onclick="cerrarAyuda()">
            <i class="bi bi-x-lg"></i>
        </button>
        <h2 class="ayuda-title">¿Cómo funciona?</h2>
        <p class="ayuda-sub">Guía rápida de uso diario</p>

        <div class="ayuda-item">
            <div class="ayuda-icon"><i class="bi bi-layers-fill"></i></div>
            <div class="ayuda-text">
                <h4>1. Carga de Inicio</h4>
                <p>Al empezar tu día, ingresa a <strong>Carga</strong> para registrar qué productos y cantidades llevas hoy en el camión. Esto actualizará tu inventario.</p>
            </div>
        </div>

        <div class="ayuda-item">
            <div class="ayuda-icon"><i class="bi bi-cart-plus-fill"></i></div>
            <div class="ayuda-text">
                <h4>2. Tomar un Pedido</h4>
                <p>Usa el botón gigante verde o ve a <strong>Clientes</strong> -> Selecciona el lugar -> Busca los productos y pon las cantidades. Arriba te saldrá una barra para proceder al cobro.</p>
            </div>
        </div>

        <div class="ayuda-item">
            <div class="ayuda-icon"><i class="bi bi-receipt"></i></div>
            <div class="ayuda-text">
                <h4>3. Venta y Abonos</h4>
                <p>Al confirmar tu pedido podrás elegir si lo pagan de contado ahora mismo, o darlo a <strong>Crédito</strong>. Si es a crédito podrás ingresarle un abono de dinero inicial.</p>
            </div>
        </div>

        <div class="ayuda-item">
            <div class="ayuda-icon"><i class="bi bi-flag-fill"></i></div>
            <div class="ayuda-text">
                <h4>4. Cierre de Ruta</h4>
                <p>Al finalizar tu tarde es indispensable que vayas a <strong>Cierre de Ruta</strong> y le des confirmar. Esto enviará la consolidación de toda la plata ingresada ese día.</p>
            </div>
        </div>
    </div>
</div>

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

<script>
// Datos de sesion PHP disponibles siempre (no requieren BD)
const SESION = {
    nombre:      '<?php echo addslashes($nombre); ?>',
    vendedor_id: <?php echo $id_vendedor; ?>,
    hoy:         '<?php echo $hoy; ?>',
};

// Datos calculados del dashboard (online)
const DASHBOARD = {
    jornada_activa: <?php echo $jornada_activa ? 'true' : 'false'; ?>,
    dia_ruta: <?php echo $dia_ruta; ?>,
    total_ventas_hoy: <?php echo $total_ventas_hoy; ?>,
    pedidos_hoy: <?php echo $pedidos_hoy; ?>,
    delta_pct: <?php echo $delta_pct; ?>,
    delta_positivo: <?php echo $delta_positivo ? 'true' : 'false'; ?>,
    total_clientes: <?php echo $total_clientes; ?>,
    total_productos: <?php echo $total_productos; ?>,
};

document.addEventListener('DOMContentLoaded', () => {
    pintarDashboard(DASHBOARD);
});

// ── Pull-to-refresh: en dashboard recarga datos frescos ──────────
window.PTR = {
    onRefresh: async () => {
        // Si quieres, aquí puede volver a consultar un endpoint; ahora usa datos directos.
        pintarDashboard(DASHBOARD);
        mostrarToast('Dashboard actualizado');
    }
};

// ── Pintar dashboard con datos ────────────────────────────
function pintarDashboard(d) {
    const fmt = n => '$' + Number(n).toLocaleString('es-CO');

    // Banner jornada
    const banner = document.getElementById('dash-banner');
    const bannerTxt = document.getElementById('dash-banner-txt');
    const diaRutaEl = document.getElementById('dash-dia-ruta');

    if (d.jornada_activa) {
        banner.className = 'jornada-banner jornada-activa';
        const hora = new Date().toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
        bannerTxt.textContent = 'Jornada activa · ' + hora;
        diaRutaEl.textContent = 'Día ' + d.dia_ruta;
    } else {
        banner.className = 'jornada-banner jornada-inactiva';
        bannerTxt.innerHTML = '<span class="jornada-dot dot-off"></span> Jornada cerrada hoy';
        diaRutaEl.textContent = 'Día ' + (d.dia_ruta - 1);
    }

    // Card ventas
    const totalEl   = document.getElementById('dash-total');
    const footer    = document.getElementById('dash-ventas-footer');
    const emptyMsg  = document.getElementById('dash-ventas-empty');
    const deltaEl   = document.getElementById('dash-delta');
    const deltaTxt  = document.getElementById('dash-delta-txt');
    const pedidosEl = document.getElementById('dash-pedidos');

    if (d.total_ventas_hoy > 0) {
        totalEl.innerHTML = fmt(d.total_ventas_hoy) + ' <span class="ventas-cop">COP</span>';
        footer.style.display  = '';
        emptyMsg.style.display = 'none';
        const signo = d.delta_positivo ? '+' : '';
        deltaTxt.textContent = signo + d.delta_pct + '% vs ayer';
        deltaEl.className = 'ventas-delta ' + (d.delta_positivo ? 'delta-pos' : 'delta-neg');
        deltaEl.querySelector('i').className = 'bi bi-arrow-' + (d.delta_positivo ? 'up' : 'down') + '-short';
        pedidosEl.textContent = d.pedidos_hoy;
    } else {
        totalEl.textContent = '$0';
        footer.style.display  = 'none';
        emptyMsg.style.display = '';
    }

    // CTA
    const ctaActivo  = document.getElementById('dash-cta-activo');
    const ctaCerrado = document.getElementById('dash-cta-cerrado');
    if (d.jornada_activa) {
        ctaActivo.style.display  = '';
        ctaCerrado.style.display = 'none';
    } else {
        ctaActivo.style.display  = 'none';
        ctaCerrado.style.display = '';
    }

    // Contadores del grid
    document.getElementById('dash-cnt-clientes').textContent  = d.total_clientes + ' clientes';
    document.getElementById('dash-cnt-productos').textContent = d.total_productos + ' productos';
    document.getElementById('dash-cnt-facturas').textContent  = d.pedidos_hoy + ' emitidas';
    document.getElementById('dash-cnt-cierre').textContent    = d.jornada_activa ? 'Pendiente' : 'Completado';
}

// ── Tracking GPS silencioso ───────────────────────────────
(function iniciarTracking() {
    if (!navigator.geolocation) return;
    function enviar(pos) {
        const fd = new FormData();
        fd.append('lat', pos.coords.latitude);
        fd.append('lng', pos.coords.longitude);
        fetch('ubicacion.php', { method: 'POST', body: fd }).catch(() => {});
    }
    function tick() {
        navigator.geolocation.getCurrentPosition(enviar, () => {}, {
            enableHighAccuracy: true, timeout: 10000, maximumAge: 20000
        });
    }
    tick();
    setInterval(tick, 30000);
})();

function mostrarToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);' +
        'background:#1e293b;color:#fff;padding:.7rem 1.2rem;border-radius:10px;' +
        'font-size:.85rem;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.3);white-space:nowrap';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ── Ayuda ──────────────────────────────────────────────────
function abrirAyuda() {
    document.getElementById('modalAyuda').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('sheetAyuda').classList.add('sheet-open'), 10);
}

function cerrarAyuda() {
    document.getElementById('sheetAyuda').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalAyuda').style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}

document.getElementById('modalAyuda').addEventListener('click', function(e) {
    if (e.target === this) cerrarAyuda();
});
</script>

</body>
</html>