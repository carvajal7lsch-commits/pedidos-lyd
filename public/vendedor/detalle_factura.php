<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_venta    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_vendedor = $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

if (!$id_venta) { header('Location: facturas.php'); exit(); }

// ── Verificar que la venta pertenece al vendedor ─
$stmt = mysqli_prepare($conexion,
    "SELECT v.*, COALESCE(c.nombre, 'Sin cliente') AS cliente_nombre
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     WHERE v.id_venta = ? AND v.id_vendedor = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $id_venta, $id_vendedor);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$venta) { header('Location: facturas.php'); exit(); }

// ── ¿Hay cierre hoy? ────────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT id_cierre FROM cierrediario WHERE id_usuario = ? AND fecha = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cierre_hoy   = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$puede_editar = !$cierre_hoy && ($venta['fecha'] === $hoy);

$mensaje  = '';
$tipo_msg = '';

// ── Procesar acciones ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_editar && isset($_POST['accion'])) {

    // Editar cantidad de un detalle
    if ($_POST['accion'] === 'editar_detalle') {
        $id_detalle   = (int)   $_POST['id_detalle'];
        $nueva_cant   = (int)   $_POST['nueva_cantidad'];
        $cant_anterior = (int)  $_POST['cantidad_anterior'];
        $precio_unit  = (float) $_POST['precio_unitario'];
        $id_producto  = (int)   $_POST['id_producto'];

        if ($nueva_cant <= 0) {
            $mensaje  = 'La cantidad debe ser mayor a 0. Para eliminar usa el botón eliminar.';
            $tipo_msg = 'error';
        } else {
            $diferencia = $nueva_cant - $cant_anterior; // positivo = más, negativo = devuelve

            mysqli_begin_transaction($conexion);
            try {
                // Verificar stock disponible si aumenta
                if ($diferencia > 0) {
                    $stmt_stock = mysqli_prepare($conexion,
                        "SELECT cantidad_disponible FROM inventariocamion
                         WHERE id_vendedor = ? AND id_producto = ? AND fecha_cargue = ? LIMIT 1"
                    );
                    mysqli_stmt_bind_param($stmt_stock, 'iis', $id_vendedor, $id_producto, $hoy);
                    mysqli_stmt_execute($stmt_stock);
                    $stock = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stock))['cantidad_disponible'];
                    if ($diferencia > $stock) {
                        throw new Exception("Stock insuficiente. Solo hay $stock pac. disponibles.");
                    }
                }

                $nuevo_subtotal = $precio_unit * $nueva_cant;

                // Actualizar detalle
                $stmt = mysqli_prepare($conexion,
                    "UPDATE detalle_venta SET cantidad = ?, subtotal = ? WHERE id_detalle = ? AND id_venta = ?"
                );
                mysqli_stmt_bind_param($stmt, 'idii', $nueva_cant, $nuevo_subtotal, $id_detalle, $id_venta);
                mysqli_stmt_execute($stmt);

                // Actualizar total de la venta
                $stmt = mysqli_prepare($conexion,
                    "UPDATE venta SET total = (
                         SELECT SUM(subtotal) FROM detalle_venta WHERE id_venta = ? AND estado = 1
                     ) WHERE id_venta = ?"
                );
                mysqli_stmt_bind_param($stmt, 'ii', $id_venta, $id_venta);
                mysqli_stmt_execute($stmt);

                // Ajustar inventario
                $stmt = mysqli_prepare($conexion,
                    "UPDATE inventariocamion
                     SET cantidad_disponible = cantidad_disponible - ?
                     WHERE id_vendedor = ? AND id_producto = ? AND fecha_cargue = ?"
                );
                mysqli_stmt_bind_param($stmt, 'iiis', $diferencia, $id_vendedor, $id_producto, $hoy);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conexion);
                $mensaje  = 'Cantidad actualizada correctamente.';
                $tipo_msg = 'exito';

            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $mensaje  = $e->getMessage();
                $tipo_msg = 'error';
            }
        }
    }

    // Eliminar un detalle
    if ($_POST['accion'] === 'eliminar_detalle') {
        $id_detalle  = (int) $_POST['id_detalle'];
        $cantidad    = (int) $_POST['cantidad'];
        $id_producto = (int) $_POST['id_producto'];

        // Verificar que no sea el único producto
        $stmt = mysqli_prepare($conexion,
            "SELECT COUNT(*) AS total FROM detalle_venta WHERE id_venta = ? AND estado = 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id_venta);
        mysqli_stmt_execute($stmt);
        $count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

        if ($count <= 1) {
            $mensaje  = 'No puedes eliminar el único producto de la venta.';
            $tipo_msg = 'error';
        } else {
            mysqli_begin_transaction($conexion);
            try {
                // Desactivar detalle
                $stmt = mysqli_prepare($conexion,
                    "UPDATE detalle_venta SET estado = 0 WHERE id_detalle = ? AND id_venta = ?"
                );
                mysqli_stmt_bind_param($stmt, 'ii', $id_detalle, $id_venta);
                mysqli_stmt_execute($stmt);

                // Recalcular total
                $stmt = mysqli_prepare($conexion,
                    "UPDATE venta SET total = (
                         SELECT SUM(subtotal) FROM detalle_venta WHERE id_venta = ? AND estado = 1
                     ) WHERE id_venta = ?"
                );
                mysqli_stmt_bind_param($stmt, 'ii', $id_venta, $id_venta);
                mysqli_stmt_execute($stmt);

                // Devolver al inventario
                $stmt = mysqli_prepare($conexion,
                    "UPDATE inventariocamion
                     SET cantidad_disponible = cantidad_disponible + ?
                     WHERE id_vendedor = ? AND id_producto = ? AND fecha_cargue = ?"
                );
                mysqli_stmt_bind_param($stmt, 'iiis', $cantidad, $id_vendedor, $id_producto, $hoy);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conexion);
                $mensaje  = 'Producto eliminado de la venta.';
                $tipo_msg = 'exito';

            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $mensaje  = 'Error al eliminar el producto.';
                $tipo_msg = 'error';
            }
        }
    }
}

// ── Recargar venta actualizada ───────────────
$stmt = mysqli_prepare($conexion,
    "SELECT v.*, COALESCE(c.nombre, 'Sin cliente') AS cliente_nombre
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     WHERE v.id_venta = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ── Detalles de la venta ─────────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT dv.id_detalle, dv.id_producto, dv.cantidad,
            dv.precio_unitario, dv.subtotal,
            p.nombre AS producto, p.imagen
     FROM detalle_venta dv
     JOIN productos p ON p.id_producto = dv.id_producto
     WHERE dv.id_venta = ? AND dv.estado = 1
     ORDER BY dv.id_detalle ASC"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$detalles = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$num_orden = '#FAC-' . str_pad($id_venta, 3, '0', STR_PAD_LEFT);
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

    <title><?php echo $num_orden; ?> — <?php echo APP_NAME; ?></title>
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
        <a href="facturas.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="page-subtitle-top">FACTURA</div>
            <h1 class="page-title"><?php echo $num_orden; ?></h1>
        </div>
    </div>
    <!-- Enlace al comprobante -->
    <a href="comprobante.php?id=<?php echo $id_venta; ?>" class="topbar-btn" title="Ver comprobante">
        <i class="bi bi-receipt"></i>
    </a>
</header>

<main class="scroll-body">

    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>">
        <i class="bi bi-<?php echo $tipo_msg === 'exito' ? 'check-circle-fill' : 'exclamation-circle-fill'; ?>"></i>
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Info cliente + tipo -->
    <div class="df-cliente-card">
        <div class="df-avatar">
            <?php echo mb_strtoupper(mb_substr($venta['cliente_nombre'], 0, 1)); ?>
        </div>
        <div class="df-cliente-info">
            <div class="df-cliente-nombre"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></div>
            <div class="df-fecha"><?php echo fecha_es('d \d\e F, Y', strtotime($venta['fecha'])); ?></div>
        </div>
        <span class="fact-tipo-badge tipo-<?php echo $venta['tipo_venta']; ?>">
            <?php echo strtoupper($venta['tipo_venta']); ?>
        </span>
    </div>

    <!-- Aviso si no puede editar -->
    <?php if (!$puede_editar): ?>
    <div class="df-aviso-bloqueado">
        <i class="bi bi-lock-fill"></i>
        <span>Jornada cerrada — esta venta no se puede modificar</span>
    </div>
    <?php endif; ?>

    <!-- Productos -->
    <div class="df-seccion-titulo">Productos</div>

    <div class="df-prod-lista">
        <?php foreach ($detalles as $d): ?>
        <div class="df-prod-item" id="prod_<?php echo $d['id_detalle']; ?>">

            <div class="df-prod-img-wrap">
                <?php if ($d['imagen']): ?>
                <img src="../uploads/productos/<?php echo htmlspecialchars($d['imagen']); ?>"
                     class="df-prod-img" alt="">
                <?php else: ?>
                <div class="df-prod-img-placeholder"><i class="bi bi-image"></i></div>
                <?php endif; ?>
            </div>

            <div class="df-prod-info">
                <div class="df-prod-nombre"><?php echo htmlspecialchars($d['producto']); ?></div>
                <div class="df-prod-precio">
                    $<?php echo number_format($d['precio_unitario'], 0, ',', '.'); ?> x pac.
                </div>
            </div>

            <div class="df-prod-derecha">
                <div class="df-prod-cant"><?php echo $d['cantidad']; ?> pac.</div>
                <div class="df-prod-subtotal">
                    $<?php echo number_format($d['subtotal'], 0, ',', '.'); ?>
                </div>
                <?php if ($puede_editar): ?>
                <div class="df-prod-acciones">
                    <button class="df-btn-edit"
                            onclick="abrirModalEditar(
                                <?php echo $d['id_detalle']; ?>,
                                <?php echo $d['id_producto']; ?>,
                                '<?php echo addslashes(htmlspecialchars($d['producto'])); ?>',
                                <?php echo $d['cantidad']; ?>,
                                <?php echo $d['precio_unitario']; ?>
                            )" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="df-btn-del"
                            onclick="confirmarEliminar(
                                <?php echo $d['id_detalle']; ?>,
                                <?php echo $d['id_producto']; ?>,
                                <?php echo $d['cantidad']; ?>,
                                '<?php echo addslashes(htmlspecialchars($d['producto'])); ?>'
                            )" title="Eliminar">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <!-- Total -->
    <div class="df-total-row">
        <span class="df-total-label">TOTAL</span>
        <span class="df-total-monto">
            $<?php echo number_format($venta['total'], 0, ',', '.'); ?>
        </span>
    </div>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<!-- ══ MODAL EDITAR CANTIDAD ══ -->
<?php if ($puede_editar): ?>
<div class="modal-overlay" id="modalEditar" style="display:none;">
    <div class="bottom-sheet" id="sheetEditar">
        <div class="sheet-handle"></div>
        <div class="credito-sheet-header">
            <h2 class="sheet-title">Editar Cantidad</h2>
            <button class="sheet-cerrar" onclick="cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="df-modal-prod-nombre" id="modalProdNombre"></div>

        <form method="POST" id="formEditar">
            <input type="hidden" name="accion"           value="editar_detalle">
            <input type="hidden" name="id_detalle"       id="editIdDetalle">
            <input type="hidden" name="id_producto"      id="editIdProducto">
            <input type="hidden" name="cantidad_anterior" id="editCantAnterior">
            <input type="hidden" name="precio_unitario"  id="editPrecioUnit">

            <div class="cantidad-label">CANTIDAD (PACAS)</div>
            <div class="cantidad-wrap">
                <button type="button" class="cant-btn" onclick="cambiarCantModal(-1)">
                    <i class="bi bi-dash"></i>
                </button>
                <span class="cant-valor" id="editCantValor">1</span>
                <button type="button" class="cant-btn cant-mas" onclick="cambiarCantModal(1)">
                    <i class="bi bi-plus"></i>
                </button>
            </div>

            <div class="modal-resumen">
                <div class="resumen-row">
                    <span>Precio x pac.</span>
                    <span id="editPrecioShow">$0</span>
                </div>
                <div class="resumen-row resumen-subtotal">
                    <span>Subtotal</span>
                    <span id="editSubtotal">$0</span>
                </div>
            </div>

            <button type="submit" class="btn-confirmar-credito">
                <i class="bi bi-check-lg"></i> Guardar Cambios
            </button>
            <button type="button" class="btn-cancelar-credito" onclick="cerrarModal()">
                Cancelar
            </button>
        </form>
    </div>
</div>

<!-- Form eliminar -->
<form method="POST" id="formEliminar" style="display:none;">
    <input type="hidden" name="accion"      value="eliminar_detalle">
    <input type="hidden" name="id_detalle"  id="delIdDetalle">
    <input type="hidden" name="id_producto" id="delIdProducto">
    <input type="hidden" name="cantidad"    id="delCantidad">
</form>
<?php endif; ?>

<script>
let editCantActual = 1;
let editPrecioActual = 0;

function abrirModalEditar(id_detalle, id_producto, nombre, cantidad, precio) {
    editCantActual   = cantidad;
    editPrecioActual = precio;

    document.getElementById('editIdDetalle').value    = id_detalle;
    document.getElementById('editIdProducto').value   = id_producto;
    document.getElementById('editCantAnterior').value = cantidad;
    document.getElementById('editPrecioUnit').value   = precio;
    document.getElementById('modalProdNombre').textContent = nombre;
    document.getElementById('editCantValor').textContent   = cantidad;
    document.getElementById('editPrecioShow').textContent  =
        '$' + precio.toLocaleString('es-CO');

    actualizarSubtotalModal();

    document.getElementById('modalEditar').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('sheetEditar').classList.add('sheet-open'), 10);
}

function cerrarModal() {
    document.getElementById('sheetEditar').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalEditar').style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

function cambiarCantModal(delta) {
    const nueva = Math.max(1, editCantActual + delta);
    editCantActual = nueva;
    document.getElementById('editCantValor').textContent = nueva;
    actualizarSubtotalModal();
}

function actualizarSubtotalModal() {
    const sub = editPrecioActual * editCantActual;
    document.getElementById('editSubtotal').textContent =
        '$' + sub.toLocaleString('es-CO');
}

function confirmarEliminar(id_detalle, id_producto, cantidad, nombre) {
    document.getElementById('delIdDetalle').value  = id_detalle;
    document.getElementById('delIdProducto').value = id_producto;
    document.getElementById('delCantidad').value   = cantidad;
    document.getElementById('formEliminar').submit();
}

document.getElementById('modalEditar')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
</script>

</body>
</html>