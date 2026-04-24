<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_cierre = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == 1;

if (!$id_cierre) {
    die("ID de cierre no proporcionado.");
}

// ── Datos del cierre ─────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT cd.*, u.nombre AS vendedor
     FROM cierrediario cd
     JOIN usuario u ON u.id_usuario = cd.id_usuario
     WHERE cd.id_cierre = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_cierre);
mysqli_stmt_execute($stmt);
$cierre = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$cierre) {
    die("Cierre no encontrado.");
}

$id_vendedor = $cierre['id_usuario'];

// Abonos del día (todos los recaudados hoy por este vendedor)
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(monto), 0) AS total
     FROM abono
     WHERE id_vendedor = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $cierre['fecha']);
mysqli_stmt_execute($stmt);
$abonos_dia = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

// Lista detallada de abonos para el comprobante
$stmt = mysqli_prepare($conexion,
    "SELECT a.monto, c.nombre AS cliente, v.id_venta, v.fecha AS fecha_venta
     FROM abono a
     JOIN venta v ON v.id_venta = a.id_venta
     JOIN cliente c ON v.id_cliente = c.id_cliente
     WHERE a.id_vendedor = ? AND a.fecha = ?
     ORDER BY a.id_abono ASC"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $cierre['fecha']);
mysqli_stmt_execute($stmt);
$lista_abonos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Ventas contado del día (sin abonos)
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(total), 0) AS total
     FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'contado'"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $cierre['fecha']);
mysqli_stmt_execute($stmt);
$ventas_contado_puro = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

$fecha_fmt = fecha_es('d M, Y', strtotime($cierre['fecha']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Cierre #<?php echo $id_cierre; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/cierre.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { background: <?php echo $is_iframe ? '#fff' : '#F0F2F7'; ?>; }
        .scroll-body { padding: <?php echo $is_iframe ? '10px' : '20px'; ?>; }
        <?php if($is_iframe): ?>
        .topbar, .btn-finalizar-jornada { display: none; }
        .cierre-comp-card { margin-top: 0; box-shadow: none; border: 1px solid #eee; }
        <?php endif; ?>
    </style>
</head>
<body>

<?php if(!$is_iframe): ?>
<header class="topbar">
    <div class="topbar-left">
        <a href="reportes.php?reporte=cierres" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="page-title">Comprobante de Cierre</h1>
    </div>
</header>
<?php endif; ?>

<main class="scroll-body">
    <div class="cierre-comp-card">
        <!-- Logística -->
        <div class="cierre-seccion">
            <div class="cierre-sec-label">DETALLES DE LOGÍSTICA</div>
            <div class="cierre-sec-row">
                <span class="cierre-row-key">Vendedor</span>
                <span class="cierre-row-val"><?php echo htmlspecialchars($cierre['vendedor']); ?></span>
            </div>
            <div class="cierre-sec-row">
                <span class="cierre-row-key">Fecha</span>
                <span class="cierre-row-val"><?php echo $fecha_fmt; ?></span>
            </div>
        </div>

        <div class="cierre-divider"></div>

        <!-- Resumen financiero -->
        <div class="cierre-seccion">
            <div class="cierre-sec-label">RESUMEN FINANCIERO</div>
            <div class="cierre-sec-row">
                <span class="cierre-row-key">Ventas Contado</span>
                <span class="cierre-row-val">$<?php echo number_format($ventas_contado_puro, 0, ',', '.'); ?></span>
            </div>
            <?php if ($abonos_dia > 0): ?>
            <div class="cierre-sec-row">
                <span class="cierre-row-key">Abonos Recibidos</span>
                <span class="cierre-row-val" style="color: #10b981;">+$<?php echo number_format($abonos_dia, 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <div class="cierre-sec-row">
                <span class="cierre-row-key">Ventas Crédito</span>
                <span class="cierre-row-val">$<?php echo number_format($cierre['total_credito'], 0, ',', '.'); ?></span>
            </div>
            <div class="cierre-divider"></div>
            <div class="cierre-sec-row" style="margin-top: 10px;">
                <span style="font-weight: 800; font-size: 14px; color: #0F1623;">Total Recaudado</span>
                <span style="font-weight: 800; font-size: 18px; color: #1855CF;">$<?php echo number_format($cierre['total_general'], 0, ',', '.'); ?></span>
            </div>
        </div>

        <?php if (!empty($lista_abonos)): ?>
        <div class="cierre-divider"></div>
        <div class="cierre-seccion">
            <div class="cierre-sec-label">DETALLE DE ABONOS</div>
            <?php foreach ($lista_abonos as $ab): ?>
            <div class="cierre-sec-row" style="padding: 4px 0;">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-size: 13px; font-weight: 600; color: #0F1623;"><?php echo htmlspecialchars($ab['cliente']); ?></span>
                    <span style="font-size: 10px; color: #64748B;">Venta #<?php echo $ab['id_venta']; ?></span>
                </div>
                <span style="color: #10b981; font-weight: 600; font-size: 13px;">+$<?php echo number_format($ab['monto'], 0, ',', '.'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="cierre-barcode" style="margin-top: 20px; opacity: 0.5;">
            <div class="barcode-lines" style="display: flex; justify-content: center; gap: 2px; height: 30px;">
                <?php for($i=0; $i<20; $i++): ?>
                <div style="background: #000; width: <?php echo rand(1, 3); ?>px; height: 100%;"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</main>

</body>
</html>
