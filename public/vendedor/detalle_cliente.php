<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../models/cliente.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: clientes.php'); exit(); }

$mensaje  = '';
$tipo_msg = '';
$hoy      = date('Y-m-d');

// ── Abonar a una factura específica ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'abonar' && !empty($_POST['id_venta'])) {
        $id_venta    = (int)   $_POST['id_venta'];
        $monto_abono = (float) ($_POST['monto_abono'] ?? 0);

        if ($monto_abono <= 0) {
            $mensaje  = 'Ingresa un monto válido.';
            $tipo_msg = 'error';
        } else {
            // Verificar que el monto no supere el saldo pendiente
            $stmt_check = mysqli_prepare($conexion,
                "SELECT v.total - (SELECT COALESCE(SUM(monto), 0) FROM abono WHERE id_venta = v.id_venta) AS saldo
                 FROM venta v
                 WHERE v.id_venta = ? AND v.id_cliente = ?"
            );
            mysqli_stmt_bind_param($stmt_check, 'ii', $id_venta, $id);
            mysqli_stmt_execute($stmt_check);
            $row_check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
            $saldo_disp = (float)($row_check['saldo'] ?? 0);

            if ($monto_abono > $saldo_disp) {
                $mensaje  = 'El abono supera el saldo pendiente ($' . number_format($saldo_disp, 0, ',', '.') . ').';
                $tipo_msg = 'error';
            } else {
                $id_vendedor_abono = $_SESSION['id_usuario'];
                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO abono (id_venta, id_vendedor, monto, fecha)
                     VALUES (?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, 'iids', $id_venta, $id_vendedor_abono, $monto_abono, $hoy);
                if (mysqli_stmt_execute($stmt)) {
                    $mensaje  = 'Abono registrado correctamente.';
                    $tipo_msg = 'exito';
                } else {
                    $mensaje  = 'No se pudo registrar el abono.';
                    $tipo_msg = 'error';
                }
            }
        }
    } elseif ($_POST['accion'] === 'pagar_todo') {
        $id_vendedor_abono = $_SESSION['id_usuario'];
        // Para cada factura pendiente insertar un abono por el saldo restante
        $stmt_facturas = mysqli_prepare($conexion,
            "SELECT sub.id_venta, sub.saldo FROM (
                 SELECT v.id_venta,
                        v.total - (SELECT COALESCE(SUM(monto), 0) FROM abono WHERE id_venta = v.id_venta) AS saldo
                 FROM venta v
                 WHERE v.id_cliente = ? AND v.tipo_venta = 'credito'
             ) AS sub WHERE sub.saldo > 0"
        );
        mysqli_stmt_bind_param($stmt_facturas, 'i', $id);
        mysqli_stmt_execute($stmt_facturas);
        $facturas_pend = mysqli_fetch_all(mysqli_stmt_get_result($stmt_facturas), MYSQLI_ASSOC);

        $stmt_ab = mysqli_prepare($conexion,
            "INSERT INTO abono (id_venta, id_vendedor, monto, fecha) VALUES (?, ?, ?, ?)"
        );
        $ok = true;
        foreach ($facturas_pend as $fp) {
            $saldo_fp = (float) $fp['saldo'];
            mysqli_stmt_bind_param($stmt_ab, 'iids',
                $fp['id_venta'], $id_vendedor_abono, $saldo_fp, $hoy
            );
            if (!mysqli_stmt_execute($stmt_ab)) { $ok = false; break; }
        }

        $mensaje  = $ok ? 'Todas las facturas pagadas.' : 'Error al registrar los pagos.';
        $tipo_msg = $ok ? 'exito' : 'error';
    }
}

// ── Datos del cliente ────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT * FROM cliente WHERE id_cliente = ? AND estado = 1 LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$cliente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$cliente) { header('Location: clientes.php'); exit(); }

$etiquetas_cliente = etiquetarCliente($cliente);

// ── Facturas pendientes con saldo real ───────
$stmt = mysqli_prepare($conexion,
    "SELECT * FROM (
         SELECT v.id_venta, v.fecha, v.total,
                (SELECT COALESCE(SUM(monto), 0) FROM abono WHERE id_venta = v.id_venta) AS total_abonado,
                v.total - (SELECT COALESCE(SUM(monto), 0) FROM abono WHERE id_venta = v.id_venta) AS saldo_pendiente
         FROM venta v
         WHERE v.id_cliente = ? AND v.tipo_venta = 'credito'
     ) AS sub
     WHERE sub.saldo_pendiente > 0
     ORDER BY sub.fecha ASC"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$facturas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Saldo total = suma de saldos pendientes por factura
$saldo_total = array_sum(array_column($facturas, 'saldo_pendiente'));

// ── Historial de ventas (últimas 10) ────────
$stmt = mysqli_prepare($conexion,
    "SELECT v.id_venta, v.fecha, v.total, v.tipo_venta
     FROM venta v
     WHERE v.id_cliente = ?
     ORDER BY v.fecha DESC, v.id_venta DESC
     LIMIT 10"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$historial = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// ── PERFIL COMERCIAL ─────────────────────────

// Top 3 productos más comprados
$stmt = mysqli_prepare($conexion,
    "SELECT p.nombre, SUM(dv.cantidad) AS total_unidades
     FROM detalle_venta dv
     JOIN venta v ON v.id_venta = dv.id_venta
     JOIN productos p ON p.id_producto = dv.id_producto
     WHERE v.id_cliente = ? AND dv.estado = 1
     GROUP BY dv.id_producto
     ORDER BY total_unidades DESC
     LIMIT 3"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$productos_favoritos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Estadísticas generales de compra
$stmt = mysqli_prepare($conexion,
    "SELECT
        COUNT(*)                                                         AS total_visitas,
        COALESCE(AVG(total), 0)                                          AS ticket_promedio,
        COALESCE(MAX(fecha), NULL)                                       AS ultima_visita,
        COALESCE(MIN(fecha), NULL)                                       AS primera_visita,
        SUM(CASE WHEN tipo_venta = 'contado' THEN 1 ELSE 0 END)         AS num_contado,
        SUM(CASE WHEN tipo_venta = 'credito' THEN 1 ELSE 0 END)         AS num_credito
     FROM venta
     WHERE id_cliente = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Frecuencia promedio de compra en días
$frecuencia_dias = null;
if ($stats['total_visitas'] >= 2) {
    $dias_totales   = (strtotime($stats['ultima_visita']) - strtotime($stats['primera_visita'])) / 86400;
    $frecuencia_dias = round($dias_totales / ($stats['total_visitas'] - 1));
}

// Días desde la última visita
$dias_sin_compra = $stats['ultima_visita']
    ? (int) floor((time() - strtotime($stats['ultima_visita'])) / 86400)
    : null;
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

    <title><?php echo htmlspecialchars($cliente['nombre']); ?> — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css?v=<?php echo filemtime('../css/dashboard_vendedor.css'); ?>">
    <link rel="stylesheet" href="../css/clientes_vendedor.css?v=<?php echo filemtime('../css/clientes_vendedor.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <script src="../js/formatters.js?v=<?php echo filemtime('../js/formatters.js'); ?>"></script>
</head>
<body>

<!-- ══ TOP BAR ══ -->
<header class="topbar">
    <div class="topbar-left">
        <a href="clientes.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="page-title"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
    </div>
    <a href="productos.php?id_cliente=<?php echo $id; ?>" class="btn-add" title="Hacer pedido">
        <i class="bi bi-cart3"></i>
    </a>
</header>

<main class="scroll-body">

    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Info del cliente -->
    <div class="detalle-cliente-card">
        <div class="detalle-avatar">
            <?php echo mb_strtoupper(mb_substr($cliente['nombre'], 0, 1)); ?>
        </div>
        <div class="detalle-info">
            <div class="detalle-nombre"><?php echo htmlspecialchars($cliente['nombre']); ?></div>
            <?php echo renderEtiquetas($etiquetas_cliente); ?>
            <div class="detalle-meta">
                <i class="bi bi-geo-alt"></i>
                <span><?php echo htmlspecialchars($cliente['direccion']); ?></span>
            </div>
            <?php if (!empty($cliente['telefono'])): ?>
            <a href="tel:<?php echo htmlspecialchars($cliente['telefono']); ?>" class="detalle-meta detalle-tel">
                <i class="bi bi-telephone-fill"></i>
                <span><?php echo htmlspecialchars($cliente['telefono']); ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cartera / saldo pendiente -->
    <?php if (!empty($facturas)): ?>
    <div class="cartera-card">
        <div class="cartera-label">Saldo Total Pendiente</div>
        <div class="cartera-monto">
            $<?php echo number_format($saldo_total, 0, ',', '.'); ?>
        </div>
        <form method="POST" id="formPagarTodo">
            <input type="hidden" name="accion" value="pagar_todo">
            <button type="button" class="btn-pagar-todo" onclick="abrirConfirmarTodo()">
                <i class="bi bi-cash-coin"></i> Pagar todo
            </button>
        </form>
    </div>

    <!-- Facturas pendientes -->
    <div class="seccion-titulo">
        Facturas Pendientes
        <span class="seccion-badge"><?php echo count($facturas); ?> facturas</span>
    </div>

    <div class="facturas-lista">
        <?php foreach ($facturas as $i => $f): ?>
        <div class="factura-card">
            <a href="detalle_factura.php?id=<?php echo $f['id_venta']; ?>" class="factura-link">
                <div class="factura-top">
                    <div class="factura-id">#FAC-<?php echo str_pad($f['id_venta'], 3, '0', STR_PAD_LEFT); ?></div>
                    <div class="factura-fecha">
                        Emitida: <?php echo fecha_es('d M Y', strtotime($f['fecha'])); ?>
                    </div>
                </div>
                <div class="factura-bottom">
                    <div>
                        <div class="factura-label">SALDO</div>
                        <div class="factura-monto">$<?php echo number_format($f['saldo_pendiente'], 0, ',', '.'); ?></div>
                        <?php if ($f['total_abonado'] > 0): ?>
                        <div class="factura-abonado">
                            Abonado: $<?php echo number_format($f['total_abonado'], 0, ',', '.'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-right factura-chevron"></i>
                </div>
            </a>
            <div class="factura-footer">
                <button class="btn-abonar" onclick="abrirModalAbono(
                    <?php echo $f['id_venta']; ?>,
                    <?php echo $f['saldo_pendiente']; ?>
                )">Abonar</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="cartera-limpia">
        <i class="bi bi-check-circle-fill"></i>
        <p>Sin saldo pendiente</p>
    </div>
    <?php endif; ?>

    <!-- ══ PERFIL COMERCIAL ══ -->
    <?php if ($stats['total_visitas'] > 0): ?>
    <div class="seccion-titulo" style="margin-top:8px;">
        Perfil Comercial
    </div>

    <!-- KPIs rápidos -->
    <div class="perfil-kpis">
        <div class="perfil-kpi">
            <div class="perfil-kpi-valor"><?php echo $stats['total_visitas']; ?></div>
            <div class="perfil-kpi-label">Visitas</div>
        </div>
        <div class="perfil-kpi">
            <div class="perfil-kpi-valor">
                $<?php echo number_format($stats['ticket_promedio'], 0, ',', '.'); ?>
            </div>
            <div class="perfil-kpi-label">Ticket Promedio</div>
        </div>
        <div class="perfil-kpi">
            <div class="perfil-kpi-valor">
                <?php echo $frecuencia_dias !== null ? 'c/' . $frecuencia_dias . 'd' : '—'; ?>
            </div>
            <div class="perfil-kpi-label">Frecuencia</div>
        </div>
        <div class="perfil-kpi <?php echo ($dias_sin_compra !== null && $dias_sin_compra > 15) ? 'perfil-kpi-alerta' : ''; ?>">
            <div class="perfil-kpi-valor">
                <?php echo $dias_sin_compra !== null ? $dias_sin_compra . 'd' : '—'; ?>
            </div>
            <div class="perfil-kpi-label">Última visita</div>
        </div>
    </div>

    <!-- Forma de pago preferida -->
    <?php if ($stats['total_visitas'] > 0):
        $pct_contado = round(($stats['num_contado'] / $stats['total_visitas']) * 100);
        $pct_credito = 100 - $pct_contado;
    ?>
    <div class="perfil-card">
        <div class="perfil-card-titulo">
            <i class="bi bi-cash-coin"></i> Forma de Pago
        </div>
        <div class="perfil-pago-barra-wrap">
            <div class="perfil-pago-barra">
                <?php if ($pct_contado > 0): ?>
                <div class="perfil-pago-segmento contado" style="width:<?php echo $pct_contado; ?>%">
                    <?php if ($pct_contado > 15): ?><?php echo $pct_contado; ?>%<?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($pct_credito > 0): ?>
                <div class="perfil-pago-segmento credito" style="width:<?php echo $pct_credito; ?>%">
                    <?php if ($pct_credito > 15): ?><?php echo $pct_credito; ?>%<?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="perfil-pago-leyenda">
                <span class="perfil-pago-dot contado"></span>
                Contado (<?php echo $stats['num_contado']; ?>)
                &nbsp;&nbsp;
                <span class="perfil-pago-dot credito"></span>
                Crédito (<?php echo $stats['num_credito']; ?>)
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Productos favoritos -->
    <?php if (!empty($productos_favoritos)): ?>
    <div class="perfil-card">
        <div class="perfil-card-titulo">
            <i class="bi bi-star-fill"></i> Le gusta comprar
        </div>
        <div class="perfil-prods">
            <?php foreach ($productos_favoritos as $i => $pf): ?>
            <div class="perfil-prod-item">
                <div class="perfil-prod-rank"><?php echo $i + 1; ?></div>
                <div class="perfil-prod-nombre"><?php echo htmlspecialchars($pf['nombre']); ?></div>
                <div class="perfil-prod-cant"><?php echo $pf['total_unidades']; ?> uds.</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Historial de compras -->
    <?php if (!empty($historial)): ?>
    <div class="seccion-titulo" style="margin-top:8px;">
        Últimas Compras
    </div>
    <div class="historial-lista">
        <?php foreach ($historial as $h): ?>
        <a href="detalle_factura.php?id=<?php echo $h['id_venta']; ?>" class="historial-item">
            <div class="historial-left">
                <div class="historial-id">#FAC-<?php echo str_pad($h['id_venta'], 3, '0', STR_PAD_LEFT); ?></div>
                <div class="historial-fecha"><?php echo fecha_es('d M Y', strtotime($h['fecha'])); ?></div>
            </div>
            <div class="historial-right">
                <div class="historial-monto">$<?php echo number_format($h['total'], 0, ',', '.'); ?></div>
                <span class="historial-tipo tipo-<?php echo $h['tipo_venta']; ?>">
                    <?php echo $h['tipo_venta'] === 'contado' ? 'Contado' : 'Crédito'; ?>
                </span>
                <i class="bi bi-chevron-right historial-chevron"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<!-- ══ MODAL ABONO ══ -->
<div class="modal-overlay" id="modalAbono" style="display:none;">
    <div class="bottom-sheet" id="sheetAbono">
        <div class="sheet-handle"></div>
        <div class="credito-sheet-header">
            <h2 class="sheet-title">Registrar Abono</h2>
            <button class="sheet-cerrar" onclick="cerrarModalAbono()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="credito-total-card">
            <div class="cred-total-label">SALDO PENDIENTE</div>
            <div class="cred-total-monto" id="abonoSaldoMonto">$0</div>
            <div class="cred-total-sub">PESOS COLOMBIANOS</div>
        </div>

        <form method="POST" id="formAbono">
            <input type="hidden" name="accion" value="abonar">
            <input type="hidden" name="id_venta" id="abonoIdVenta" value="">

            <div class="abono-wrap">
                <div class="abono-campo" style="display:block;">
                    <div class="abono-campo-label">VALOR DEL ABONO</div>
                    <div class="abono-input-wrap">
                        <span class="abono-prefix">$</span>
                        <input type="tel" id="abonoMontoInput" name="monto_abono_display"
                               placeholder="0" oninput="formatCurrencyInput(this); actualizarSaldoModal()">
                        <input type="hidden" name="monto_abono" id="abonoMontoReal">
                    </div>
                    <div class="saldo-restante-wrap" id="saldoRestanteModal" style="display:none;">
                        <span class="saldo-restante-label">Saldo restante:</span>
                        <span class="saldo-restante-val" id="saldoRestanteModalVal">$0</span>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-confirmar-credito" onclick="enviarAbono()">
                Confirmar Abono
            </button>
            <button type="button" class="btn-cancelar-credito" onclick="cerrarModalAbono()">
                Cancelar
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL CONFIRMAR PAGO TOTAL ══ -->
<div class="modal-overlay" id="modalConfirmarTodo" style="display:none;">
    <div class="bottom-sheet" id="sheetConfirmarTodo">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <h2 class="sheet-title">Confirmar Pago Total</h2>
            <button class="sheet-cerrar" onclick="cerrarConfirmarTodo()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 48px; color: #f59e0b; margin-bottom: 12px;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <p style="font-family: 'DM Sans', sans-serif; color: #475569; font-size: 15px; line-height: 1.5; margin: 0 0 16px 0;">
                ¿Estás seguro de registrar el pago de <strong>todas</strong> las facturas de este cliente?<br>
                Esta acción no se puede deshacer.
            </p>
            <div style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Monto total a recaudar</div>
                <div style="font-size: 24px; font-weight: 800; color: #1A2B6B; font-family: 'Sora', sans-serif;">
                    $<?php echo number_format($saldo_total, 0, ',', '.'); ?>
                </div>
            </div>
        </div>

        <button type="button" class="btn-confirmar-credito" onclick="confirmarPagoTodo()" style="background: #1A2B6B;">
            Sí, registrar pagos
        </button>
        <button type="button" class="btn-cancelar-credito" onclick="cerrarConfirmarTodo()">
            No, cancelar
        </button>
    </div>
</div>

<script>
let abonoSaldoActual = 0;

function abrirModalAbono(id_venta, saldo) {
    abonoSaldoActual = saldo;
    document.getElementById('abonoIdVenta').value    = id_venta;
    document.getElementById('abonoMontoInput').value = '';
    document.getElementById('abonoSaldoMonto').textContent =
        '$' + saldo.toLocaleString('es-CO');
    document.getElementById('saldoRestanteModal').style.display = 'none';

    document.getElementById('modalAbono').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        document.getElementById('sheetAbono').classList.add('sheet-open');
        document.getElementById('abonoMontoInput').focus();
    }, 10);
}

function cerrarModalAbono() {
    document.getElementById('sheetAbono').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalAbono').style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

function actualizarSaldoModal() {
    let monto     = getRawValue(document.getElementById('abonoMontoInput'));

    // Limitar el abono al saldo pendiente
    if (monto > abonoSaldoActual) {
        monto = abonoSaldoActual;
        const input = document.getElementById('abonoMontoInput');
        input.value = new Intl.NumberFormat('es-CO').format(monto);
    }

    const restante = Math.max(0, abonoSaldoActual - monto);
    const wrap    = document.getElementById('saldoRestanteModal');

    if (monto > 0) {
        wrap.style.display = '';
        const el = document.getElementById('saldoRestanteModalVal');
        el.textContent = '$' + restante.toLocaleString('es-CO');
        el.style.color = '#15803d';
    } else {
        wrap.style.display = 'none';
    }
}

function enviarAbono() {
    const monto = getRawValue(document.getElementById('abonoMontoInput'));
    if (monto <= 0) {
        alert('Ingresa un monto válido.');
        return;
    }
    document.getElementById('abonoMontoReal').value = monto;
    document.getElementById('formAbono').submit();
}

document.getElementById('modalAbono').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalAbono();
});

// ══ FUNCIONES PAGO TODO ══
function abrirConfirmarTodo() {
    document.getElementById('modalConfirmarTodo').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        document.getElementById('sheetConfirmarTodo').classList.add('sheet-open');
    }, 10);
}

function cerrarConfirmarTodo() {
    document.getElementById('sheetConfirmarTodo').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalConfirmarTodo').style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

function confirmarPagoTodo() {
    document.getElementById('formPagarTodo').submit();
}

document.getElementById('modalConfirmarTodo').addEventListener('click', function(e) {
    if (e.target === this) cerrarConfirmarTodo();
});
</script>

</body>
</html>