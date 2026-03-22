<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_cierre   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_vendedor = $_SESSION['id_usuario'];

if (!$id_cierre) { header('Location: dashboard.php'); exit(); }

// ── Datos del cierre ─────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT cd.*, u.nombre AS vendedor
     FROM cierrediario cd
     JOIN usuario u ON u.id_usuario = cd.id_usuario
     WHERE cd.id_cierre = ? AND cd.id_usuario = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $id_cierre, $id_vendedor);
mysqli_stmt_execute($stmt);
$cierre = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$cierre) { header('Location: dashboard.php'); exit(); }

// Abonos del día para desglose
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(a.monto), 0) AS total
     FROM abono a
     JOIN venta v ON v.id_venta = a.id_venta
     WHERE v.id_vendedor = ? AND a.fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $cierre['fecha']);
mysqli_stmt_execute($stmt);
$abonos_dia = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

$ventas_contado_puro = (float)$cierre['total_contado'] - $abonos_dia;

$fecha_fmt = fecha_es('d M, Y', strtotime($cierre['fecha']));
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

    <title>Comprobante de Cierre</title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/cierre.css">
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
        <h1 class="page-title">Comprobante de Cierre</h1>
    </div>
</header>

<main class="scroll-body">

    <!-- Éxito -->
    <div class="cierre-exito-wrap">
        <div class="cierre-check">
            <i class="bi bi-check-lg"></i>
        </div>
        <div class="cierre-exito-titulo">Cierre Exitoso</div>
        <div class="cierre-exito-fecha"><?php echo $fecha_fmt; ?></div>
    </div>

    <!-- Card comprobante -->
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
                <span class="cierre-row-val">
                    $<?php echo number_format($ventas_contado_puro, 0, ',', '.'); ?>
                </span>
            </div>

            <?php if ($abonos_dia > 0): ?>
            <div class="cierre-sec-row">
                <span class="cierre-row-key">Abonos Recibidos</span>
                <span class="cierre-row-val color-green">
                    $<?php echo number_format($abonos_dia, 0, ',', '.'); ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="cierre-sec-row">
                <span class="cierre-row-key">Ventas Crédito</span>
                <span class="cierre-row-val">
                    $<?php echo number_format($cierre['total_credito'], 0, ',', '.'); ?>
                </span>
            </div>

            <div class="cierre-divider"></div>

            <div class="cierre-sec-row cierre-total-row">
                <span class="cierre-total-key">Total Recaudado</span>
                <span class="cierre-total-val">
                    $<?php echo number_format($cierre['total_general'], 0, ',', '.'); ?>
                </span>
            </div>
        </div>

        <!-- Código de barras decorativo -->
        <div class="cierre-barcode">
            <div class="barcode-lines">
                <?php
                // Generar líneas decorativas variadas
                $widths = [2,1,3,1,2,1,1,3,1,2,1,3,2,1,1,2,3,1,2,1,1,3,1,2];
                foreach ($widths as $w): ?>
                <div class="barcode-line" style="width:<?php echo $w; ?>px;"></div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Botón finalizar -->
    <a href="dashboard.php" class="btn-finalizar-jornada">
        <i class="bi bi-check2-all"></i> Finalizar Jornada
    </a>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

</body>
</html>