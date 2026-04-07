<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

// ── ¿Hay cierre hoy? (bloquea edición) ──────
$stmt = mysqli_prepare($conexion,
    "SELECT id_cierre FROM cierrediario WHERE id_usuario = ? AND fecha = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cierre_hoy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$puede_editar = !$cierre_hoy;

// ── Totales del día ──────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT
        COALESCE(SUM(total), 0)                                      AS total_general,
        COALESCE(SUM(CASE WHEN tipo_venta='contado' THEN total END), 0) AS total_contado,
        COALESCE(SUM(CASE WHEN tipo_venta='credito' THEN total END), 0) AS total_credito,
        COUNT(*) AS total_ventas
     FROM venta WHERE id_vendedor = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$totales = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Abonos iniciales de créditos de hoy
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(a.monto), 0) AS total
     FROM abono a
     JOIN venta v ON v.id_venta = a.id_venta
     WHERE v.id_vendedor = ? AND a.fecha = ? AND v.fecha = ? AND v.tipo_venta = 'credito'"
);
mysqli_stmt_bind_param($stmt, 'iss', $id_vendedor, $hoy, $hoy);
mysqli_stmt_execute($stmt);
$abonos_credito_hoy = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

$total_contado_raw = (float)$totales['total_contado'];
$total_credito_raw = (float)$totales['total_credito'];
$efectivo = $total_contado_raw + $abonos_credito_hoy;
$credito_pendiente = $total_credito_raw - $abonos_credito_hoy;
$total_real = $efectivo + $credito_pendiente;

// ── Ventas del día con cliente ───────────────
$stmt = mysqli_prepare($conexion,
    "SELECT v.id_venta, v.tipo_venta, v.total,
            COALESCE(c.nombre, 'Sin cliente') AS cliente
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     WHERE v.id_vendedor = ? AND v.fecha = ?
     ORDER BY v.id_venta DESC"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$ventas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
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

    <title>Ventas del Día — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/facturas.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="dashboard.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="page-title">Ventas del Día</h1>
    </div>
</header>

<main class="scroll-body">

    <!-- Resumen total -->
    <div class="fact-total-card">
        <div class="fact-total-label">TOTAL VENDIDO HOY</div>
        <div class="fact-total-monto">
            $<?php echo number_format($total_real, 0, ',', '.'); ?>
            <span class="fact-total-cop">COP</span>
        </div>
    </div>

    <!-- Desglose contado / crédito -->
    <div class="fact-desglose-grid">
        <div class="fact-des-card">
            <div class="fact-des-label">EFECTIVO</div>
            <div class="fact-des-monto">
                $<?php echo number_format($efectivo, 0, ',', '.'); ?>
            </div>
            <?php if ($abonos_credito_hoy > 0): ?>
            <div style="font-size:.7rem;color:var(--text-muted,#8b95a5);margin-top:2px;">
                + $<?php echo number_format($abonos_credito_hoy, 0, ',', '.'); ?> abonos
            </div>
            <?php endif; ?>
        </div>
        <div class="fact-des-card">
            <div class="fact-des-label">CRÉDITO PENDIENTE</div>
            <div class="fact-des-monto">
                $<?php echo number_format($credito_pendiente, 0, ',', '.'); ?>
            </div>
            <?php if ($abonos_credito_hoy > 0): ?>
            <div style="font-size:.7rem;color:var(--text-muted,#8b95a5);margin-top:2px;">
                De $<?php echo number_format($total_credito_raw, 0, ',', '.'); ?> en créditos
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Buscador -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="inputBuscar"
               placeholder="Buscar por tienda..."
               oninput="filtrar()">
    </div>

    <!-- Pills filtro -->
    <div class="filter-pills">
        <button class="fpill active" data-tipo="todas" onclick="setFiltro(this)">Todas</button>
        <button class="fpill" data-tipo="contado" onclick="setFiltro(this)">Contado</button>
        <button class="fpill" data-tipo="credito" onclick="setFiltro(this)">Crédito</button>
    </div>

    <!-- Lista ventas -->
    <div class="fact-seccion-titulo">
        VENTAS RECIENTES
        <span class="fact-seccion-count"><?php echo $totales['total_ventas']; ?> registros</span>
    </div>

    <?php if (empty($ventas)): ?>
    <div class="fact-vacia">
        <i class="bi bi-receipt"></i>
        <p>No hay ventas registradas hoy</p>
    </div>
    <?php else: ?>

    <div class="fact-lista" id="factLista">
        <?php foreach ($ventas as $v): ?>
        <a href="detalle_factura.php?id=<?php echo $v['id_venta']; ?>"
           class="fact-item"
           data-cliente="<?php echo strtolower(htmlspecialchars($v['cliente'])); ?>"
           data-tipo="<?php echo $v['tipo_venta']; ?>">

            <div class="fact-avatar">
                <?php echo mb_strtoupper(mb_substr($v['cliente'], 0, 1)); ?>
            </div>

            <div class="fact-info">
                <div class="fact-cliente"><?php echo htmlspecialchars($v['cliente']); ?></div>
                <div class="fact-id">#FAC-<?php echo str_pad($v['id_venta'], 3, '0', STR_PAD_LEFT); ?></div>
            </div>

            <div class="fact-derecha">
                <div class="fact-monto">
                    $<?php echo number_format($v['total'], 0, ',', '.'); ?>
                </div>
                <span class="fact-tipo-badge tipo-<?php echo $v['tipo_venta']; ?>">
                    <?php echo strtoupper($v['tipo_venta']); ?>
                </span>
            </div>

        </a>
        <?php endforeach; ?>
    </div>

    <div class="fact-vacia" id="sinResultados" style="display:none;">
        <i class="bi bi-search"></i>
        <p>Sin resultados</p>
    </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<script>
let filtroActual = 'todas';

function filtrar() {
    const q      = document.getElementById('inputBuscar').value.toLowerCase().trim();
    const items  = document.querySelectorAll('.fact-item');
    let visibles = 0;

    items.forEach(item => {
        const okQ    = !q || item.dataset.cliente.includes(q);
        const okTipo = filtroActual === 'todas' || item.dataset.tipo === filtroActual;
        const show   = okQ && okTipo;
        item.style.display = show ? '' : 'none';
        if (show) visibles++;
    });

    document.getElementById('sinResultados').style.display =
        visibles === 0 ? 'flex' : 'none';
}

function setFiltro(pill) {
    document.querySelectorAll('.fpill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    filtroActual = pill.dataset.tipo;
    filtrar();
}
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
    const badge = document.getElementById('badge-pendientes');
    if (badge) badge.style.display = 'none';
});
</script>

</body>
</html>