<?php
require_once __DIR__ . '/../../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/conexion.php';

$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_venta) {
    echo "<p class='error'>ID de venta no válido</p>";
    exit();
}

// ── Datos de la venta ────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT v.id_venta, v.fecha, v.hora, v.tipo_venta, v.total,
            c.nombre AS cliente_nombre, c.direccion AS cliente_dir
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     WHERE v.id_venta = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$venta) {
    echo "<p class='error'>No se encontró el comprobante</p>";
    exit();
}

// ── Abonos ──────────────────────────────────
$stmt = mysqli_prepare($conexion, "SELECT COALESCE(SUM(monto), 0) AS total_abonado FROM abono WHERE id_venta = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$total_abonado = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total_abonado'];
$saldo_restante = $venta['total'] - $total_abonado;

// ── Detalle ──────────────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT dv.cantidad, dv.precio_unitario, dv.subtotal, p.nombre AS producto
     FROM detalle_venta dv
     JOIN productos p ON p.id_producto = dv.id_producto
     WHERE dv.id_venta = ? AND dv.estado = 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$detalles = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$num_orden = 'ORD-' . date('Y', strtotime($venta['fecha'])) . '-' . str_pad($id_venta, 4, '0', STR_PAD_LEFT);
$fecha_fmt = date('d/m/Y g:i A', strtotime($venta['fecha'] . ' ' . ($venta['hora'] ?? '00:00:00')));
?>

<div class="comprobante-card">
    <!-- Header Empresa -->
    <div class="comp-empresa">
        <div class="comp-empresa-icon"><i class="bi bi-truck-front-fill"></i></div>
        <div class="comp-empresa-nombre"><?php echo EMPRESA_NOMBRE; ?></div>
        <div class="comp-empresa-nit">NIT: <?php echo EMPRESA_NIT; ?></div>
        <div class="comp-orden-num" style="font-size: 13px; font-weight: 700; color: #1855CF; margin-top: 5px;">#<?php echo $num_orden; ?></div>
    </div>

    <div class="comp-divider"></div>

    <!-- Cliente y Fecha -->
    <div class="comp-seccion">
        <div class="comp-sec-label">CLIENTE</div>
        <div class="comp-cliente-nombre"><?php echo htmlspecialchars(strtoupper($venta['cliente_nombre'] ?? 'Sin cliente')); ?></div>
        <?php if ($venta['cliente_dir']): ?>
        <div class="comp-sec-valor-sub"><?php echo htmlspecialchars($venta['cliente_dir']); ?></div>
        <?php endif; ?>
    </div>
    <div class="comp-fecha-wrap" style="background: #F8FAFF; padding: 10px 20px; text-align: center;">
        <div class="comp-sec-label">EMITIDO EL</div>
        <div class="comp-fecha-val" style="font-size: 13px; font-weight: 700; color: #0F1623;"><?php echo $fecha_fmt; ?></div>
    </div>

    <div class="comp-divider"></div>

    <!-- Productos -->
    <div class="comp-tabla-header" style="display: flex; padding: 8px 20px; background: #F8FAFF; font-size: 10px; font-weight: 700; color: #8A93A8;">
        <span style="flex: 1;">DESCRIPCIÓN</span>
        <span style="width: 40px; text-align: center;">CANT</span>
        <span style="width: 80px; text-align: right;">SUBTOTAL</span>
    </div>
    <?php foreach ($detalles as $d): ?>
    <div class="comp-tabla-row" style="display: flex; align-items: center; padding: 10px 20px; border-bottom: 1px solid #F4F5F7;">
        <div class="comp-td-desc" style="flex: 1;">
            <div class="comp-prod-nombre" style="font-size: 13px; font-weight: 700; color: #0F1623;"><?php echo htmlspecialchars($d['producto']); ?></div>
            <div class="comp-prod-precio-unit" style="font-size: 11px; color: #8A93A8;">Paca: $<?php echo number_format($d['precio_unitario'], 0, ',', '.'); ?></div>
        </div>
        <div class="comp-td-cant" style="width: 40px; text-align: center; font-size: 13px; font-weight: 700;"><?php echo $d['cantidad']; ?></div>
        <div class="comp-td-sub" style="width: 80px; text-align: right; font-size: 13px; font-weight: 700;">$<?php echo number_format($d['subtotal'], 0, ',', '.'); ?></div>
    </div>
    <?php endforeach; ?>

    <div class="comp-divider"></div>

    <!-- Totales -->
    <div class="comp-total-row" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 10px;">
        <span class="comp-total-label" style="font-size: 12px; font-weight: 700; color: #64748B;">TOTAL VENTA</span>
        <span class="comp-total-monto" style="font-size: 22px; font-weight: 800; color: #1855CF;">$<?php echo number_format($venta['total'], 0, ',', '.'); ?></span>
    </div>

    <?php if ($total_abonado > 0): ?>
    <div class="comp-abono-row" style="display:flex; justify-content:space-between; padding:0 20px 5px; font-size:13px; color:#64748b;">
        <span>ABONOS A LA FECHA</span>
        <span>- $<?php echo number_format($total_abonado, 0, ',', '.'); ?></span>
    </div>
    <div class="comp-saldo-row" style="display:flex; justify-content:space-between; padding:0 20px 15px; font-size:14px; color:#b91c1c; font-weight:700;">
        <span>SALDO PENDIENTE</span>
        <span>$<?php echo number_format($saldo_restante, 0, ',', '.'); ?></span>
    </div>
    <?php else: ?>
    <div style="padding:0 20px 15px; font-size:13px; color:#065f46; font-weight:600; text-align:right;">
        <i class="bi bi-patch-check-fill"></i> PAGADO TOTALMENTE
    </div>
    <?php endif; ?>

    <div class="comp-tipo-badge <?php echo $venta['tipo_venta'] === 'contado' ? 'badge-contado' : 'badge-credito'; ?>" style="margin: 0 20px 20px; display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; <?php echo $venta['tipo_venta'] === 'contado' ? 'background: #F0FDF4; color: #15803d;' : 'background: #FEF9EC; color: #B45309;'; ?>">
        <i class="bi bi-<?php echo $venta['tipo_venta'] === 'contado' ? 'cash-coin' : 'clock-history'; ?>"></i>
        <?php echo $venta['tipo_venta'] === 'contado' ? 'Pago de contado' : 'Venta a crédito'; ?>
    </div>
</div>
