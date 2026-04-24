<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$nombre      = $_SESSION['nombre'];
$hoy         = date('Y-m-d');

// ── Verificar que no haya cierre hoy ya ──────
$stmt = mysqli_prepare($conexion,
    "SELECT id_cierre FROM cierrediario WHERE id_usuario = ? AND fecha = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cierre_existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($cierre_existente) {
    header('Location: comprobante_cierre.php?id=' . $cierre_existente['id_cierre']);
    exit();
}

// ── Procesar cierre ──────────────────────────
$mensaje  = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'generar_cierre') {

    // Ventas contado del día
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(total), 0) AS total
         FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'contado'"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
    mysqli_stmt_execute($stmt);
    $ventas_contado = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    // Ventas crédito del día
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(total), 0) AS total
         FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'credito'"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
    mysqli_stmt_execute($stmt);
    $ventas_credito = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    // Todos los abonos recaudados hoy por el vendedor (sin importar fecha de venta)
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(monto), 0) AS total
         FROM abono WHERE id_vendedor = ? AND fecha = ?"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
    mysqli_stmt_execute($stmt);
    $abonos_totales_hoy = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    // Para el cálculo de crédito pendiente hoy, solo necesitamos los abonos de ventas de hoy
    $stmt = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(a.monto), 0) AS total
         FROM abono a
         JOIN venta v ON v.id_venta = a.id_venta
         WHERE v.id_vendedor = ? AND a.fecha = ? AND v.fecha = ? AND v.tipo_venta = 'credito'"
    );
    mysqli_stmt_bind_param($stmt, 'iss', $id_vendedor, $hoy, $hoy);
    mysqli_stmt_execute($stmt);
    $abonos_ventas_hoy = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    $total_contado = $ventas_contado + $abonos_totales_hoy;
    $credito_pendiente = $ventas_credito - $abonos_ventas_hoy;
    $total_general = $total_contado + $credito_pendiente;

    mysqli_begin_transaction($conexion);
    try {
        // Insertar cierre
        $stmt = mysqli_prepare($conexion,
            "INSERT INTO cierrediario (id_usuario, fecha, total_contado, total_credito, total_general, estado)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        mysqli_stmt_bind_param($stmt, 'isddd',
            $id_vendedor, $hoy, $total_contado, $credito_pendiente, $total_general
        );
        mysqli_stmt_execute($stmt);
        $id_cierre = mysqli_insert_id($conexion);

        // Asociar ventas del día al cierre
        $stmt = mysqli_prepare($conexion,
            "UPDATE venta SET id_cierre = ? WHERE id_vendedor = ? AND fecha = ? AND id_cierre IS NULL"
        );
        mysqli_stmt_bind_param($stmt, 'iis', $id_cierre, $id_vendedor, $hoy);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conexion);
        header("Location: comprobante_cierre.php?id={$id_cierre}");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $mensaje  = 'Error al generar el cierre. Intenta de nuevo.';
        $tipo_msg = 'error';
    }
}

// ── Datos del día para mostrar ───────────────

// Ventas contado
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS pedidos
     FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'contado'"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$res_contado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Ventas crédito
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS pedidos
     FROM venta WHERE id_vendedor = ? AND fecha = ? AND tipo_venta = 'credito'"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$res_credito = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Todos los abonos recaudados hoy por el vendedor (sin importar fecha de venta)
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(monto), 0) AS total
     FROM abono WHERE id_vendedor = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$abonos_totales_hoy = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

// Abonos detallados para mostrar
$stmt = mysqli_prepare($conexion,
    "SELECT a.monto, c.nombre AS cliente, v.id_venta, v.fecha AS fecha_venta
     FROM abono a
     JOIN venta v ON v.id_venta = a.id_venta
     JOIN cliente c ON v.id_cliente = c.id_cliente
     WHERE a.id_vendedor = ? AND a.fecha = ?
     ORDER BY a.id_abono DESC"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$lista_abonos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Abonos de ventas de hoy para cálculo de crédito pendiente
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(a.monto), 0) AS total
     FROM abono a
     JOIN venta v ON v.id_venta = a.id_venta
     WHERE v.id_vendedor = ? AND a.fecha = ? AND v.fecha = ? AND v.tipo_venta = 'credito'"
);
mysqli_stmt_bind_param($stmt, 'iss', $id_vendedor, $hoy, $hoy);
mysqli_stmt_execute($stmt);
$abonos_ventas_hoy = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

$total_contado = (float)$res_contado['total'] + $abonos_totales_hoy;
$total_credito = (float)$res_credito['total'];
$credito_pendiente = $total_credito - $abonos_ventas_hoy;
$total_general = $total_contado + $credito_pendiente;

// Inventario restante (máximo 3 para preview)
$inventario = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT p.nombre, p.imagen, ic.cantidad_disponible
     FROM inventariocamion ic
     JOIN productos p ON p.id_producto = ic.id_producto
     WHERE ic.id_vendedor = $id_vendedor AND ic.fecha_cargue = '$hoy'
       AND ic.estado = 1 AND ic.cantidad_disponible > 0
     ORDER BY ic.cantidad_disponible DESC"
), MYSQLI_ASSOC);

$total_inventario = count($inventario);
$preview_inv      = array_slice($inventario, 0, 3);
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

    <title>Cierre de Ruta — <?php echo APP_NAME; ?></title>
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
        <div>
            <h1 class="page-title">Cierre de Ruta</h1>
            <div class="page-subtitle">
                <i class="bi bi-calendar3"></i>
                <?php echo fecha_es('d \d\e F, Y'); ?>
            </div>
        </div>
    </div>
</header>

<main class="scroll-body">

    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Encabezado resumen -->
    <div class="seccion-label">RESUMEN FINANCIERO
        <span class="seccion-moneda">COP (Pesos)</span>
    </div>

    <!-- Total vendido -->
    <div class="total-vendido-card">
        <div>
            <div class="tv-label">Total Vendido</div>
            <div class="tv-monto">
                $<?php echo number_format($total_general, 0, ',', '.'); ?>
            </div>
        </div>
        <div class="tv-icon">
            <i class="bi bi-cash-stack"></i>
        </div>
    </div>

    <!-- Desglose contado / crédito -->
    <div class="desglose-grid">
        <div class="desglose-card">
            <div class="desglose-label">Dinero en Efectivo</div>
            <div class="desglose-monto contado">
                $<?php echo number_format($total_contado, 0, ',', '.'); ?>
            </div>
            <?php if ($abonos_totales_hoy > 0): ?>
            <div class="desglose-extra">
                Incluye $<?php echo number_format($abonos_totales_hoy, 0, ',', '.'); ?> en abonos
            </div>
            <?php endif; ?>
            <div class="desglose-bar bar-contado"></div>
        </div>
        <div class="desglose-card">
            <div class="desglose-label">Crédito Nuevo</div>
            <div class="desglose-monto credito">
                $<?php echo number_format($credito_pendiente, 0, ',', '.'); ?>
            </div>
            <?php if ($total_credito > 0): ?>
            <div class="desglose-extra">
                De $<?php echo number_format($total_credito, 0, ',', '.'); ?> vendidos hoy
            </div>
            <?php endif; ?>
            <div class="desglose-bar bar-credito"></div>
        </div>
    </div>

    <!-- Detalle de Cobranza (Abonos) -->
    <div class="seccion-label" style="margin-top:4px;">
        DETALLE DE COBRANZA
        <span class="seccion-moneda"><?php echo count($lista_abonos); ?> pagos</span>
    </div>

    <?php if (empty($lista_abonos)): ?>
    <div class="cobranza-vacia">
        <i class="bi bi-wallet2"></i>
        <span>No se recibieron abonos hoy</span>
    </div>
    <?php else: ?>
    <div class="cobranza-lista">
        <?php foreach ($lista_abonos as $ab): 
            $es_hoy = ($ab['fecha_venta'] === $hoy);
        ?>
        <div class="cobranza-item">
            <div class="cobranza-info">
                <div class="cobranza-cliente"><?php echo htmlspecialchars($ab['cliente']); ?></div>
                <div class="cobranza-meta">
                    <span class="badge-venta <?php echo $es_hoy ? 'badge-hoy' : 'badge-pasada'; ?>">
                        <?php echo $es_hoy ? 'Venta de hoy' : 'Deuda anterior (' . date('d/m/y', strtotime($ab['fecha_venta'])) . ')'; ?>
                    </span>
                    <span class="cobranza-id">#<?php echo $ab['id_venta']; ?></span>
                </div>
            </div>
            <div class="cobranza-monto">
                +$<?php echo number_format($ab['monto'], 0, ',', '.'); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Inventario restante -->
    <div class="seccion-label" style="margin-top:4px;">
        INVENTARIO RESTANTE
        <?php if ($total_inventario > 0): ?>
        <a href="inventario.php" class="seccion-link">
            <i class="bi bi-truck-front"></i> Ver Detalle
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($inventario)): ?>
    <div class="inv-vacio">
        <i class="bi bi-check-circle-fill"></i>
        <span>Camión vacío — ¡todo vendido!</span>
    </div>
    <?php else: ?>
    <div class="inv-lista">
        <?php foreach ($preview_inv as $item): ?>
        <div class="inv-item">
            <div class="inv-img-wrap">
                <?php if ($item['imagen']): ?>
                <img src="../uploads/productos/<?php echo htmlspecialchars($item['imagen']); ?>"
                     class="inv-img" alt="">
                <?php else: ?>
                <div class="inv-img-placeholder"><i class="bi bi-image"></i></div>
                <?php endif; ?>
            </div>
            <div class="inv-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
            <div class="inv-cant">
                <?php echo $item['cantidad_disponible']; ?>
                <span>PACAS</span>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($total_inventario > 3): ?>
        <div class="inv-mas">
            Mostrando 3 de <?php echo $total_inventario; ?> productos en inventario
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Advertencia si no hay ventas -->
    <?php if ($total_general == 0): ?>
    <div class="aviso-sin-ventas">
        <i class="bi bi-info-circle"></i>
        <span>No hay ventas registradas hoy. ¿Seguro que quieres cerrar?</span>
    </div>
    <?php endif; ?>

    <!-- Botón generar cierre -->
    <form method="POST" id="formCierre">
        <input type="hidden" name="accion" value="generar_cierre">
        <button type="button" class="btn-generar-cierre" onclick="confirmarCierre()">
            <i class="bi bi-cloud-upload-fill"></i> Generar Cierre
        </button>
    </form>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<script>
function confirmarCierre() {
    document.getElementById('formCierre').submit();
}
</script>

</body>
</html>