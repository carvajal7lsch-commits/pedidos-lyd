<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$offline  = isset($_GET['offline']) && $_GET['offline'] === '1';
$id_local = isset($_GET['id_local']) ? (int)$_GET['id_local'] : 0;
$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$offline && !$id_venta) { header('Location: dashboard.php'); exit(); }

// ── Datos de la venta ────────────────────────
if (!$offline) {
$stmt = mysqli_prepare($conexion,
    "SELECT v.id_venta, v.fecha, v.tipo_venta, v.total,
            c.nombre AS cliente_nombre, c.direccion AS cliente_dir
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     WHERE v.id_venta = ? AND v.id_vendedor = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $id_venta, $_SESSION['id_usuario']);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$venta) { header('Location: dashboard.php'); exit(); }
}

// ── Abono inicial si lo hubo ─────────────────
if (!$offline) {
$stmt = mysqli_prepare($conexion,
    "SELECT COALESCE(SUM(monto), 0) AS total_abonado
     FROM abono WHERE id_venta = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$abono_row     = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_abonado = (float) $abono_row['total_abonado'];
$saldo_restante = $venta['total'] - $total_abonado;
} else {
$total_abonado  = 0;
$saldo_restante = 0;
$venta = ['id_venta'=>0,'fecha'=>date('Y-m-d'),'tipo_venta'=>'contado','total'=>0,'cliente_nombre'=>'','cliente_dir'=>''];
$detalles = [];
}

// ── Detalle de la venta ──────────────────────
if (!$offline) {
$stmt = mysqli_prepare($conexion,
    "SELECT dv.cantidad, dv.precio_unitario, dv.subtotal,
            p.nombre AS producto
     FROM detalle_venta dv
     JOIN productos p ON p.id_producto = dv.id_producto
     WHERE dv.id_venta = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$detalles = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

// Número de orden formateado
$num_orden = $offline ? 'OFFLINE-'.str_pad($id_local,4,'0',STR_PAD_LEFT) : 'ORD-'.date('Y').'-'.str_pad($id_venta,4,'0',STR_PAD_LEFT);
$fecha_fmt = $offline ? fecha_es('d \d\e F, Y · g:i A') : fecha_es('d \d\e F, Y · g:i A', strtotime($venta['fecha']));
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

    <title>Comprobante <?php echo $num_orden; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/carrito.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="dashboard.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="page-subtitle-top">COMPROBANTE</div>
            <h1 class="page-title">#<?php echo $num_orden; ?></h1>
        </div>
    </div>
</header>

<main class="scroll-body">

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
            <div class="comp-sec-valor comp-cliente-nombre">
                <?php echo htmlspecialchars(strtoupper($venta['cliente_nombre'] ?? 'Sin cliente')); ?>
            </div>
            <div class="comp-sec-valor-sub">
                <?php echo htmlspecialchars($venta['cliente_dir'] ?? ''); ?>
            </div>
        </div>

        <!-- Fecha -->
        <div class="comp-fecha-wrap">
            <div class="comp-sec-label">FECHA DE EMISIÓN</div>
            <div class="comp-fecha-val"><?php echo $fecha_fmt; ?></div>
        </div>

        <div class="comp-divider"></div>

        <!-- Tabla de productos -->
        <div class="comp-tabla-header">
            <span class="comp-th-desc">DESCRIPCIÓN</span>
            <span class="comp-th-cant">CANT</span>
            <span class="comp-th-sub">SUBTOTAL</span>
        </div>

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

        <div class="comp-divider"></div>

        <!-- Total -->
        <div class="comp-total-row">
            <span class="comp-total-label">TOTAL VENTA</span>
            <span class="comp-total-monto">
                $<?php echo number_format($venta['total'], 0, ',', '.'); ?>
            </span>
        </div>

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

        <!-- Tipo de pago -->
        <div class="comp-tipo-badge <?php echo $venta['tipo_venta'] === 'contado' ? 'badge-contado' : 'badge-credito'; ?>">
            <i class="bi bi-<?php echo $venta['tipo_venta'] === 'contado' ? 'cash-coin' : 'clock-history'; ?>"></i>
            <?php echo $venta['tipo_venta'] === 'contado' ? 'Pago de contado' : 'Venta a crédito'; ?>
        </div>

    </div>

    <!-- Acciones -->
    <button class="btn-accion btn-imprimir" onclick="imprimirComprobante()">
        <i class="bi bi-printer-fill"></i> Imprimir Comprobante
    </button>
    <button class="btn-accion btn-pdf" onclick="generarPDF()">
        <i class="bi bi-share-fill"></i> Compartir / Descargar PDF
    </button>

    <a href="productos.php" class="btn-accion btn-nuevo-pedido">
        <i class="bi bi-plus-lg"></i> Nuevo Pedido
    </a>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<script>
// Datos para el PDF
const DATOS = {
    orden:         '<?php echo $num_orden; ?>',
    empresa:       '<?php echo addslashes(EMPRESA_NOMBRE); ?>',
    nit:           '<?php echo EMPRESA_NIT; ?>',
    cliente:       '<?php echo addslashes(strtoupper($venta['cliente_nombre'] ?? '')); ?>',
    dir:           '<?php echo addslashes($venta['cliente_dir'] ?? ''); ?>',
    fecha:         '<?php echo $fecha_fmt; ?>',
    tipo:          '<?php echo $venta['tipo_venta']; ?>',
    total:         '<?php echo number_format($venta['total'], 0, ',', '.'); ?>',
    totalAbonado:  '<?php echo $total_abonado > 0 ? number_format($total_abonado, 0, ',', '.') : ''; ?>',
    saldoRestante: '<?php echo $total_abonado > 0 ? number_format($saldo_restante, 0, ',', '.') : ''; ?>',
    detalles: <?php echo json_encode(array_map(function($d) {
        return [
            'nombre'   => $d['producto'],
            'precio'   => number_format($d['precio_unitario'], 0, ',', '.'),
            'cantidad' => $d['cantidad'],
            'subtotal' => number_format($d['subtotal'], 0, ',', '.'),
        ];
    }, $detalles)); ?>
};

// ── Imprimir ────────────────────────────────
function imprimirComprobante() {
    window.print();
}

// ── Generar PDF con jsPDF ───────────────────
function generarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: [80, 200], orientation: 'portrait' });

    let y = 10;
    const lm = 5;  // left margin
    const pw = 70; // page width usable

    // Empresa
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.text(DATOS.empresa, 40, y, { align: 'center' });
    y += 5;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.text('NIT: ' + DATOS.nit, 40, y, { align: 'center' });
    y += 5;

    doc.text('Comprobante ' + DATOS.orden, 40, y, { align: 'center' });
    y += 6;

    // Línea
    doc.setLineWidth(0.3);
    doc.line(lm, y, lm + pw, y);
    y += 4;

    // Cliente
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('CLIENTE', lm, y);
    y += 4;
    doc.setFont('helvetica', 'normal');
    doc.text(DATOS.cliente, lm, y);
    y += 4;
    doc.text(DATOS.dir, lm, y);
    y += 4;
    doc.text(DATOS.fecha, lm, y);
    y += 5;

    // Línea
    doc.line(lm, y, lm + pw, y);
    y += 4;

    // Header tabla
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.text('DESCRIPCIÓN', lm, y);
    doc.text('CANT', lm + 42, y);
    doc.text('SUBTOTAL', lm + 54, y);
    y += 4;
    doc.line(lm, y, lm + pw, y);
    y += 3;

    // Filas
    doc.setFont('helvetica', 'normal');
    DATOS.detalles.forEach(d => {
        doc.setFontSize(7.5);
        doc.setFont('helvetica', 'bold');
        doc.text(d.nombre, lm, y);
        doc.setFont('helvetica', 'normal');
        doc.text(String(d.cantidad), lm + 44, y);
        doc.text('$' + d.subtotal, lm + 54, y);
        y += 4;
        doc.setFontSize(7);
        doc.text('Paca: $' + d.precio, lm + 2, y);
        y += 5;
    });

    // Línea
    doc.line(lm, y, lm + pw, y);
    y += 4;

    // Total
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text('TOTAL:', lm, y);
    doc.text('$' + DATOS.total, lm + pw, y, { align: 'right' });
    y += 5;

    // Abono si existe
    if (DATOS.totalAbonado) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.text('Abono inicial:', lm, y);
        doc.text('- $' + DATOS.totalAbonado, lm + pw, y, { align: 'right' });
        y += 4;
        doc.setFont('helvetica', 'bold');
        doc.text('Saldo pendiente:', lm, y);
        doc.text('$' + DATOS.saldoRestante, lm + pw, y, { align: 'right' });
        y += 5;
    }

    doc.setFontSize(7.5);
    doc.setFont('helvetica', 'normal');
    const tipoText = DATOS.tipo === 'contado' ? 'Pago de contado' : 'Venta a crédito';
    doc.text(tipoText, 40, y, { align: 'center' });

    // Ajustar alto del documento al contenido
    doc.internal.pageSize.height = y + 15;

    // Compartir o descargar
    const nombre = `comprobante_${DATOS.orden}.pdf`;
    if (navigator.share) {
        doc.getBlob(blob => {
            const file = new File([blob], nombre, { type: 'application/pdf' });
            navigator.share({ files: [file], title: 'Comprobante ' + DATOS.orden })
                .catch(() => doc.save(nombre));
        });
    } else {
        doc.save(nombre);
    }
}
</script>

<!-- Estilos de impresión -->
<style>
@media print {
    .topbar, .vend-navbar, .btn-accion { display: none !important; }
    .scroll-body { padding: 0 !important; }
    .comprobante-card {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
    }
    body { background: #fff !important; }
}
</style>

</body>
</html>
<!-- ======================================================
     OFFLINE: cargar comprobante desde IndexedDB
====================================================== -->
<script src="/public/js/db-vendedor.js"></script>
<script>
const ES_OFFLINE = <?php echo $offline ? 'true' : 'false'; ?>;
const ID_LOCAL   = <?php echo $id_local ?: 0; ?>;

if (ES_OFFLINE) {
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const venta = await DB.obtenerVentaPorIdLocal(ID_LOCAL);
            if (!venta) {
                alert('No se encontró la venta offline.');
                window.location.href = 'dashboard.php';
                return;
            }
            renderComprobanteOffline(venta);
        } catch(e) {
            console.error('[Offline] Error:', e);
        }
    });
}

function renderComprobanteOffline(v) {
    const fmt  = n => '$' + n.toLocaleString('es-CO');
    const hoy  = new Date();
    const meses = ['enero','febrero','marzo','abril','mayo','junio',
                   'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    const hora = hoy.toLocaleTimeString('es-CO', {hour:'2-digit',minute:'2-digit'});
    const fechaStr = hoy.getDate() + ' de ' + meses[hoy.getMonth()] + ', ' + hoy.getFullYear() + ' · ' + hora;

    document.querySelector('.page-title').textContent = '#OFFLINE-' + String(ID_LOCAL).padStart(4,'0');
    document.querySelector('.comp-fecha-val').textContent = fechaStr;

    const nombreEl = document.querySelector('.comp-cliente-nombre');
    const dirEl    = document.querySelector('.comp-sec-valor-sub');
    if (nombreEl) nombreEl.textContent = (v.cliente_nombre || 'Sin cliente').toUpperCase();
    if (dirEl)    dirEl.textContent    = v.cliente_dir || '';

    // Eliminar filas vacías del PHP
    document.querySelectorAll('.comp-tabla-row').forEach(r => r.remove());

    // Insertar filas reales desde IndexedDB
    const header = document.querySelector('.comp-tabla-header');
    [...v.items].reverse().forEach(item => {
        const subtotal = item.precio * item.cantidad;
        const row = document.createElement('div');
        row.className = 'comp-tabla-row';
        row.innerHTML =
            '<div class="comp-td-desc">' +
                '<div class="comp-prod-nombre">' + item.nombre + '</div>' +
                '<div class="comp-prod-precio-unit">Paca: ' + fmt(item.precio) + '</div>' +
            '</div>' +
            '<div class="comp-td-cant">' + item.cantidad + '</div>' +
            '<div class="comp-td-sub">' + fmt(subtotal) + '</div>';
        header.insertAdjacentElement('afterend', row);
    });

    // Total
    document.querySelector('.comp-total-monto').textContent = fmt(v.total);

    // Abono
    if (v.abono > 0) {
        const saldo = v.total - v.abono;
        document.querySelector('.comp-total-row').insertAdjacentHTML('afterend',
            '<div class="comp-abono-row">' +
                '<span class="comp-abono-label">ABONO INICIAL</span>' +
                '<span class="comp-abono-val">- ' + fmt(v.abono) + '</span>' +
            '</div>' +
            '<div class="comp-saldo-row">' +
                '<span class="comp-saldo-label">SALDO PENDIENTE</span>' +
                '<span class="comp-saldo-val">' + fmt(saldo) + '</span>' +
            '</div>'
        );
    }

    // Tipo pago
    const badge = document.querySelector('.comp-tipo-badge');
    if (badge) {
        badge.className = 'comp-tipo-badge ' + (v.tipo_venta === 'contado' ? 'badge-contado' : 'badge-credito');
        badge.innerHTML = v.tipo_venta === 'contado'
            ? '<i class="bi bi-cash-coin"></i> Pago de contado'
            : '<i class="bi bi-clock-history"></i> Venta a crédito';
    }

    // Banner offline
    const banner = document.createElement('div');
    banner.style.cssText = 'background:#fef3c7;color:#92400e;border-radius:10px;padding:0.7rem 1rem;font-size:0.82rem;margin:0 1rem 1rem;display:flex;align-items:center;gap:0.5rem;';
    banner.innerHTML = '⚠️ <strong>Venta offline</strong> — Se sincronizará al recuperar conexión';
    document.querySelector('.comprobante-card').before(banner);

    // Sobreescribir DATOS para PDF
    DATOS.orden    = 'OFFLINE-' + String(ID_LOCAL).padStart(4,'0');
    DATOS.cliente  = (v.cliente_nombre || '').toUpperCase();
    DATOS.dir      = v.cliente_dir || '';
    DATOS.tipo     = v.tipo_venta;
    DATOS.total    = v.total.toLocaleString('es-CO');
    DATOS.totalAbonado  = v.abono > 0 ? v.abono.toLocaleString('es-CO') : '';
    DATOS.saldoRestante = v.abono > 0 ? (v.total - v.abono).toLocaleString('es-CO') : '';
    DATOS.detalles = v.items.map(i => ({
        nombre:   i.nombre,
        precio:   i.precio.toLocaleString('es-CO'),
        cantidad: i.cantidad,
        subtotal: (i.precio * i.cantidad).toLocaleString('es-CO'),
    }));
    DATOS.fecha = fechaStr;
}

// Badge pendientes
document.addEventListener('DOMContentLoaded', async () => {
    const n     = await DB.contarPendientes();
    const badge = document.getElementById('badge-pendientes');
    if (badge) { badge.textContent = n; badge.style.display = n > 0 ? 'inline-flex' : 'none'; }
});
</script>