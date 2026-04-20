<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_venta) { header('Location: reportes.php?reporte=facturas'); exit(); }

// ── Datos de la venta (SIN restricción de vendedor) ─
$stmt = mysqli_prepare($conexion,
    "SELECT v.id_venta, v.fecha, v.hora, v.tipo_venta, v.total,
            c.nombre AS cliente_nombre, c.direccion AS cliente_dir
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     WHERE v.id_venta = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$venta) { header('Location: reportes.php?reporte=facturas'); exit(); }

// ── Abono inicial si lo hubo ─────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(monto), 0) AS total_abonado
     FROM abono WHERE id_venta = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$abono_row      = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_abonado  = (float) $abono_row['total_abonado'];
$saldo_restante = $venta['total'] - $total_abonado;

// ── Detalle de la venta ──────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT dv.cantidad, dv.precio_unitario, dv.subtotal,
            p.nombre AS producto
     FROM detalle_venta dv
     JOIN productos p ON p.id_producto = dv.id_producto
     WHERE dv.id_venta = ? AND dv.estado = 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$detalles = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Número de orden formateado
$fecha_completa = $venta['fecha'] . ' ' . ($venta['hora'] ?? '00:00:00');
$num_orden = 'ORD-' . date('Y') . '-' . str_pad($id_venta, 4, '0', STR_PAD_LEFT);
$fecha_fmt = fecha_es('d \d\e F, Y · g:i A', strtotime($fecha_completa));
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="/public/manifest.json">
    <meta name="theme-color" content="#1855CF">
    <title>Comprobante <?php echo $num_orden; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/carrito.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Ocultar botones de navegación del admin si interfieren */
        .admin-nav { display: none; }
        .btn-nuevo-pedido { display: none; } /* Admin doesn't need 'Nuevo Pedido' button here */
        <?php if($is_iframe): ?>
        .topbar { display: none !important; }
        body { background: #fff !important; }
        .scroll-body { padding-top: 20px !important; }
        .comprobante-card { box-shadow: none !important; border: none !important; }
        <?php endif; ?>
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <button onclick="<?php echo $is_iframe ? 'window.parent.cerrarModalFactura()' : 'window.close()'; ?>" class="topbar-btn" title="Cerrar">
            <i class="bi bi-x-lg"></i>
        </button>
        <div>
            <div class="page-subtitle-top">TICKET / COMPROBANTE</div>
            <h1 class="page-title" id="tituloOrden">#<?php echo $num_orden; ?></h1>
        </div>
    </div>
</header>

<main class="scroll-body" style="padding-bottom: 50px;">
    <!-- Comprobante -->
    <div class="comprobante-card" id="comprobanteCard">
        <!-- Empresa -->
        <div class="comp-empresa">
            <div class="comp-empresa-icon">
                <i class="bi bi-truck-front-fill"></i>
            </div>
            <div class="comp-empresa-nombre"><?php echo EMPRESA_NOMBRE; ?></div>
            <div class="comp-empresa-nit">NIT: <?php echo EMPRESA_NIT; ?></div>
        </div>
        <div class="comp-divider"></div>
        <!-- Cliente -->
        <div class="comp-seccion">
            <div class="comp-sec-label">CLIENTE</div>
            <div class="comp-sec-valor comp-cliente-nombre" id="compClienteNombre">
                <?php echo htmlspecialchars(strtoupper($venta['cliente_nombre'] ?? 'Sin cliente')); ?>
            </div>
            <div class="comp-sec-valor-sub" id="compClienteDir">
                <?php echo htmlspecialchars($venta['cliente_dir'] ?? ''); ?>
            </div>
        </div>
        <!-- Fecha -->
        <div class="comp-fecha-wrap">
            <div class="comp-sec-label">FECHA DE EMISIÓN</div>
            <div class="comp-fecha-val" id="compFecha"><?php echo $fecha_fmt; ?></div>
        </div>
        <div class="comp-divider"></div>
        <!-- Tabla de productos -->
        <div class="comp-tabla-header">
            <span class="comp-th-desc">DESCRIPCIÓN</span>
            <span class="comp-th-cant">CANT</span>
            <span class="comp-th-sub">SUBTOTAL</span>
        </div>
        <div id="compTablaFilas">
            <?php foreach ($detalles as $d): ?>
            <div class="comp-tabla-row">
                <div class="comp-td-desc">
                    <div class="comp-prod-nombre"><?php echo htmlspecialchars($d['producto']); ?></div>
                    <div class="comp-prod-precio-unit">
                        Paca: $<?php echo number_format($d['precio_unitario'], 0, ',', '.'); ?>
                    </div>
                </div>
                <div class="comp-td-cant"><?php echo $d['cantidad']; ?></div>
                <div class="comp-td-sub">$<?php echo number_format($d['subtotal'], 0, ',', '.'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="comp-divider"></div>
        <!-- Total -->
        <div class="comp-total-row" id="compTotalRow">
            <span class="comp-total-label">TOTAL VENTA</span>
            <span class="comp-total-monto" id="compTotalMonto">
                $<?php echo number_format($venta['total'], 0, ',', '.'); ?>
            </span>
        </div>
        <div id="compAbonoWrap">
            <?php if ($total_abonado > 0): ?>
            <div class="comp-abono-row">
                <span class="comp-abono-label">ABONO INICIAL</span>
                <span class="comp-abono-val">
                    - $<?php echo number_format($total_abonado, 0, ',', '.'); ?>
                </span>
            </div>
            <div class="comp-saldo-row">
                <span class="comp-saldo-label">SALDO PENDIENTE</span>
                <span class="comp-saldo-val">
                    $<?php echo number_format($saldo_restante, 0, ',', '.'); ?>
                </span>
            </div>
            <?php else: ?>
            <div class="comp-total-row comp-total-cobrado">
                <span class="comp-total-label">TOTAL COBRADO</span>
                <span class="comp-total-monto">
                    $<?php echo number_format($venta['total'], 0, ',', '.'); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <!-- Tipo de pago -->
        <div class="comp-tipo-badge <?php echo $venta['tipo_venta'] === 'contado' ? 'badge-contado' : 'badge-credito'; ?>"
             id="compTipoBadge">
            <i class="bi bi-<?php echo $venta['tipo_venta'] === 'contado' ? 'cash-coin' : 'clock-history'; ?>"></i>
            <?php echo $venta['tipo_venta'] === 'contado' ? 'Pago de contado' : 'Venta a crédito'; ?>
        </div>
    </div>

    <!-- Acciones -->
    <button class="btn-accion btn-imprimir" onclick="imprimirComprobante()">
        <i class="bi bi-printer-fill"></i> Imprimir Comprobante
    </button>
    <button class="btn-accion btn-pdf" onclick="generarPDF()">
        <i class="bi bi-share-fill"></i> Descargar Ticket (PDF)
    </button>
</main>

<style>
@media print {
    .topbar, .vend-navbar, .btn-accion { display: none !important; }
    .scroll-body { padding: 0 !important; }
    .comprobante-card { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
    body { background: #fff !important; }
}
</style>

<script>
function imprimirComprobante() {
    window.print();
}

function generarPDF() {
    const element = document.getElementById('comprobanteCard');
    const orden = '<?php echo $num_orden; ?>';
    
    const opt = {
        margin:       [5, 2],
        filename:     `comprobante_${orden}.pdf`,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 3, useCORS: true, scrollY: 0, letterRendering: true, backgroundColor: '#ffffff' },
        jsPDF:        { unit: 'mm', format: [80, 400], orientation: 'portrait' },
        pagebreak:    { mode: ['avoid-all'] }
    };

    html2pdf().set(opt).from(element).toPdf().get('pdf').then((pdf) => {
    }).output('blob').then((blob) => {
        const nombre = `comprobante_${orden}.pdf`;
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        a.click();
    });
}
</script>
</body>
</html>
