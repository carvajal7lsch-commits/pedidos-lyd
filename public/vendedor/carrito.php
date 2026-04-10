<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

// ── Verificar jornada cerrada
$hoy = date('Y-m-d');
$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS cnt FROM cierrediario WHERE id_usuario = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cierre_hoy = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
if ($cierre_hoy > 0) {
    header('Location: dashboard.php?msg=jornada_cerrada');
    exit();
}

// ── Validar cliente seleccionado ─────────────
$id_cliente = $_SESSION['pedido_id_cliente'] ?? 0;
$cliente    = null;

if ($id_cliente) {
    $stmt = mysqli_prepare($conexion,
        "SELECT id_cliente, nombre, direccion, telefono
         FROM cliente WHERE id_cliente = ? AND estado = 1 LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id_cliente);
    mysqli_stmt_execute($stmt);
    $cliente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// Redirigir si no hay cliente seleccionado
if (!$cliente) {
    header('Location: clientes.php?msg=selecciona_cliente');
    exit();
}

// ── Procesar guardar pedido ──────────────────
$mensaje  = '';
$tipo_msg = '';
$id_venta_nueva = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'guardar_pedido') {
        $tipo_venta = $_POST['tipo_venta'] === 'credito' ? 'credito' : 'contado';
        $items      = json_decode($_POST['items_json'] ?? '[]', true);
        $errores    = [];

        if (empty($items)) {
            $errores[] = 'El carrito está vacío.';
        }

        if (empty($errores)) {
            // Calcular total
            $total = 0;
            foreach ($items as $item) {
                $total += (float)$item['precio'] * (int)$item['cantidad'];
            }

            mysqli_begin_transaction($conexion);
            try {
                // Insertar venta
                $ahora = date('H:i:s');
                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO venta (id_vendedor, id_cliente, fecha, hora, tipo_venta, total)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, 'iisssd',
                    $id_vendedor, $id_cliente, $hoy, $ahora, $tipo_venta, $total
                );
                mysqli_stmt_execute($stmt);
                $id_venta_nueva = mysqli_insert_id($conexion);

                // Insertar detalle_venta y descontar inventario
                $stmt_det = mysqli_prepare($conexion,
                    "INSERT INTO detalle_venta
                     (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt_inv = mysqli_prepare($conexion,
                    "UPDATE inventariocamion
                     SET cantidad_disponible = cantidad_disponible - ?
                     WHERE id_vendedor = ? AND id_producto = ?
                       AND fecha_cargue = ? AND estado = 1"
                );

                foreach ($items as $item) {
                    $id_prod  = (int)   $item['id'];
                    $cant     = (int)   $item['cantidad'];
                    $precio   = (float) $item['precio'];
                    $subtotal = $precio * $cant;

                    mysqli_stmt_bind_param($stmt_det, 'iiddd',
                        $id_venta_nueva, $id_prod, $cant, $precio, $subtotal
                    );
                    mysqli_stmt_execute($stmt_det);

                    mysqli_stmt_bind_param($stmt_inv, 'iiis',
                        $cant, $id_vendedor, $id_prod, $hoy
                    );
                    mysqli_stmt_execute($stmt_inv);
                }

                mysqli_commit($conexion);

                // Registrar abono si viene uno
                $monto_abono = (float)($_POST['monto_abono'] ?? 0);
                if ($monto_abono > 0 && $tipo_venta === 'credito') {
                    $stmt_ab = mysqli_prepare($conexion,
                        "INSERT INTO abono (id_venta, id_vendedor, monto, fecha)
                         VALUES (?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($stmt_ab, 'iids',
                        $id_venta_nueva, $id_vendedor, $monto_abono, $hoy
                    );
                    mysqli_stmt_execute($stmt_ab);
                }

                // Limpiar sesión del pedido
                unset($_SESSION['pedido_id_cliente']);

                // Redirigir al comprobante
                header("Location: comprobante.php?id={$id_venta_nueva}");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $mensaje  = 'Error al registrar el pedido. Intenta de nuevo.';
                $tipo_msg = 'error';
            }
        } else {
            $mensaje  = implode('<br>', $errores);
            $tipo_msg = 'error';
        }
    }
}

// ── Productos del camión hoy (para validar stock) ─
$prods_camion = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT p.id_producto, p.nombre, p.precio, p.imagen,
            ic.cantidad_disponible AS stock
     FROM inventariocamion ic
     JOIN productos p ON p.id_producto = ic.id_producto
     WHERE ic.id_vendedor = $id_vendedor
       AND ic.fecha_cargue = '$hoy'
       AND ic.estado = 1
       AND ic.cantidad_disponible > 0"
), MYSQLI_ASSOC);

// Indexar por id para JS
$prods_map = [];
foreach ($prods_camion as $p) {
    $prods_map[$p['id_producto']] = $p;
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

    <title>Carrito — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css?v=<?php echo filemtime('../css/dashboard_vendedor.css'); ?>">
    <link rel="stylesheet" href="../css/carrito.css?v=<?php echo filemtime('../css/carrito.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="productos.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="page-subtitle-top">CLIENTE SELECCIONADO</div>
            <h1 class="page-title" id="tituloCliente">
                <?php echo $cliente ? htmlspecialchars($cliente['nombre']) : '...'; ?>
            </h1>
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

    <!-- Buscador dentro del carrito -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="inputBuscar" placeholder="Buscar producto..." oninput="filtrarCarrito()">
    </div>

    <!-- Lista de productos en el carrito -->
    <div id="carritoLista"></div>

    <!-- Carrito vacío -->
    <div class="carrito-vacio" id="carritoVacio" style="display:none;">
        <i class="bi bi-cart-x"></i>
        <p>El carrito está vacío</p>
        <a href="productos.php" class="btn-ir-productos">Agregar productos</a>
    </div>

    <!-- Total general -->
    <div class="total-card" id="totalCard">
        <div class="total-label">TOTAL GENERAL</div>
        <div class="total-monto" id="totalMonto">$0</div>
    </div>

    <!-- Tipo de pago -->
    <div class="tipo-pago-wrap">
        <button class="tipo-btn tipo-activo" id="btnContado" onclick="setTipo('contado')">
            <i class="bi bi-cash-coin"></i> Contado
        </button>
        <button class="tipo-btn" id="btnCredito" onclick="setTipo('credito')">
            <i class="bi bi-clock-history"></i> Crédito
        </button>
    </div>

    <!-- Botón guardar -->
    <form method="POST" id="formPedido">
        <input type="hidden" name="accion" value="guardar_pedido">
        <input type="hidden" name="tipo_venta" id="inputTipo" value="contado">
        <input type="hidden" name="items_json" id="inputItems" value="[]">
        <input type="hidden" name="monto_abono" id="inputAbono" value="0">
        <button type="button" class="btn-guardar-pedido" onclick="confirmarPedido()">
            <i class="bi bi-floppy2-fill"></i> Guardar Pedido
        </button>
    </form>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<!-- ══ MODAL CRÉDITO ══ -->
<div class="modal-overlay" id="modalCredito" style="display:none;">
    <div class="bottom-sheet" id="sheetCredito">
        <div class="sheet-handle"></div>
        <div class="credito-sheet-header">
            <h2 class="sheet-title">Detalles de Crédito</h2>
            <button class="sheet-cerrar" onclick="cerrarModalCredito()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="credito-total-card">
            <div class="cred-total-label">TOTAL A FINANCIAR</div>
            <div class="cred-total-monto" id="credTotalMonto">$0</div>
            <div class="cred-total-sub">PESOS COLOMBIANOS</div>
        </div>

        <div class="abono-wrap">
            <div class="abono-toggle-row">
                <div class="abono-toggle-info">
                    <div class="abono-label">¿REGISTRA ABONO?</div>
                    <div class="abono-estado" id="abonoEstado">NO</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggleAbono" onchange="toggleAbono()">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="abono-campo" id="abonoCampo" style="display:none;">
                <div class="abono-campo-label">VALOR DEL ABONO</div>
                <div class="abono-input-wrap">
                    <span class="abono-prefix">$</span>
                    <input type="number" id="inputAbonoVal" placeholder="0"
                           min="0" oninput="actualizarSaldo()">
                </div>
                <div class="saldo-restante-wrap" id="saldoRestanteWrap">
                    <span class="saldo-restante-label">Saldo restante:</span>
                    <span class="saldo-restante-val" id="saldoRestanteVal">$0</span>
                </div>
            </div>
        </div>

        <button class="btn-confirmar-credito" onclick="confirmarCredito()">
            Confirmar Venta a Crédito
        </button>
        <button class="btn-cancelar-credito" onclick="cerrarModalCredito()">
            Cancelar
        </button>
    </div>
</div>

<script>

let PRODS_MAP  = <?php echo json_encode($prods_map); ?>;
const UPLOAD   = '../uploads/productos/';


let CLIENTE_ACTUAL = {
    id:       <?php echo $cliente ? $cliente['id_cliente'] : 'null'; ?>,
    nombre:   '<?php echo $cliente ? addslashes($cliente['nombre']) : ''; ?>',
    dir:      '<?php echo $cliente ? addslashes($cliente['direccion'] ?? '') : ''; ?>',
    telefono: '<?php echo $cliente ? addslashes($cliente['telefono'] ?? '') : ''; ?>',
};

const VENDEDOR_ID     = <?php echo $_SESSION['id_usuario']; ?>;
const VENDEDOR_NOMBRE = '<?php echo addslashes($_SESSION['nombre']); ?>';

// Carrito desde sessionStorage
let carrito  = JSON.parse(sessionStorage.getItem('carrito') || '{}');
let tipoPago = 'contado';

// ══════════════════════════════════════════
// RENDER CARRITO
// ══════════════════════════════════════════
function renderCarrito() {
    const lista  = document.getElementById('carritoLista');
    const vacio  = document.getElementById('carritoVacio');
    const items  = Object.values(carrito);

    if (items.length === 0) {
        lista.innerHTML  = '';
        vacio.style.display  = 'flex';
        document.getElementById('totalCard').style.display     = 'none';
        document.getElementById('formPedido').style.display    = 'none';
        document.querySelector('.tipo-pago-wrap').style.display = 'none';
        return;
    }

    vacio.style.display  = 'none';
    document.getElementById('totalCard').style.display     = '';
    document.getElementById('formPedido').style.display    = '';
    document.querySelector('.tipo-pago-wrap').style.display = '';

    lista.innerHTML = '';
    let total = 0;

    items.forEach(item => {
        const stock    = PRODS_MAP[item.id]?.stock ?? 0;
        const subtotal = item.precio * item.cantidad;
        total += subtotal;

        const img = item.imagen
            ? `<img src="${UPLOAD}${item.imagen}" class="ci-img">`
            : `<div class="ci-img-placeholder"><i class="bi bi-image"></i></div>`;

        lista.innerHTML += `
        <div class="carrito-item" data-nombre="${item.nombre.toLowerCase()}" data-id="${item.id}">
            <div class="ci-img-wrap">${img}</div>
            <div class="ci-info">
                <div class="ci-nombre">${item.nombre}</div>
                <div class="ci-precio">
                    $${item.precio.toLocaleString('es-CO')} COP
                    <span class="ci-stock-badge">Stock: ${stock}</span>
                </div>
                <div class="ci-cant-wrap">
                    <button class="ci-cant-btn" onclick="cambiarCant(${item.id}, -1)">
                        <i class="bi bi-dash"></i>
                    </button>
                    <span class="ci-cant-val" id="cant_${item.id}">${item.cantidad}</span>
                    <button class="ci-cant-btn ci-cant-plus" onclick="cambiarCant(${item.id}, 1)">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </div>
            <div class="ci-right">
                <div class="ci-subtotal" id="sub_${item.id}">
                    $${subtotal.toLocaleString('es-CO')}
                </div>
                <button class="ci-eliminar" onclick="eliminarItem(${item.id})">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        </div>`;
    });

    document.getElementById('totalMonto').textContent =
        '$' + total.toLocaleString('es-CO') + ' COP';
}

function filtrarCarrito() {
    const q = document.getElementById('inputBuscar').value.toLowerCase().trim();
    document.querySelectorAll('.carrito-item').forEach(el => {
        el.style.display = !q || el.dataset.nombre.includes(q) ? '' : 'none';
    });
}

// ══════════════════════════════════════════
// MANEJO CANTIDADES
// ══════════════════════════════════════════
function cambiarCant(id, delta) {
    if (!carrito[id]) return;
    const stock  = PRODS_MAP[id]?.stock ?? 999;
    const nueva  = Math.max(0, carrito[id].cantidad + delta);

    if (nueva > stock) return;

    if (nueva === 0) {
        eliminarItem(id);
        return;
    }

    carrito[id].cantidad = nueva;
    sessionStorage.setItem('carrito', JSON.stringify(carrito));
    renderCarrito();
}

function eliminarItem(id) {
    delete carrito[id];
    sessionStorage.setItem('carrito', JSON.stringify(carrito));
    renderCarrito();
}

// ══════════════════════════════════════════
// TIPO DE PAGO
// ══════════════════════════════════════════
function setTipo(tipo) {
    tipoPago = tipo;
    document.getElementById('inputTipo').value = tipo;
    document.getElementById('btnContado').classList.toggle('tipo-activo', tipo === 'contado');
    document.getElementById('btnCredito').classList.toggle('tipo-activo', tipo === 'credito');
}

// ══════════════════════════════════════════
// CONFIRMAR Y ENVIAR
// ══════════════════════════════════════════
function confirmarPedido() {
    const items = Object.values(carrito);
    if (items.length === 0) {
        alert('El carrito está vacío.');
        return;
    }

    if (tipoPago === 'credito') {
        abrirModalCredito();
        return;
    }

    // Contado — enviar directo


    document.getElementById('inputItems').value = JSON.stringify(items);
    document.getElementById('inputAbono').value = '0';
    sessionStorage.removeItem('carrito');
    document.getElementById('formPedido').submit();
}

// ══════════════════════════════════════════
// MODAL CRÉDITO
// ══════════════════════════════════════════
function calcularTotal() {
    return Object.values(carrito).reduce((s, i) => s + i.precio * i.cantidad, 0);
}

function abrirModalCredito() {
    const total = calcularTotal();
    document.getElementById('credTotalMonto').textContent =
        '$' + total.toLocaleString('es-CO');
    // Reset estado
    document.getElementById('toggleAbono').checked = false;
    document.getElementById('abonoEstado').textContent = 'NO';
    document.getElementById('abonoCampo').style.display = 'none';
    document.getElementById('inputAbonoVal').value = '';
    document.getElementById('saldoRestanteWrap').style.display = 'none';

    document.getElementById('modalCredito').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('sheetCredito').classList.add('sheet-open'), 10);
}

function cerrarModalCredito() {
    document.getElementById('sheetCredito').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalCredito').style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

function toggleAbono() {
    const activo = document.getElementById('toggleAbono').checked;
    document.getElementById('abonoEstado').textContent    = activo ? 'SÍ' : 'NO';
    document.getElementById('abonoCampo').style.display   = activo ? '' : 'none';
    if (!activo) {
        document.getElementById('inputAbonoVal').value = '';
        document.getElementById('saldoRestanteWrap').style.display = 'none';
    }
}

function actualizarSaldo() {
    const total  = calcularTotal();
    const abono  = parseFloat(document.getElementById('inputAbonoVal').value) || 0;
    const saldo  = Math.max(0, total - abono);
    const wrap   = document.getElementById('saldoRestanteWrap');

    if (abono > 0) {
        wrap.style.display = '';
        document.getElementById('saldoRestanteVal').textContent =
            '$' + saldo.toLocaleString('es-CO');
        document.getElementById('saldoRestanteVal').style.color =
            abono > total ? '#C03030' : '#15803d';
    } else {
        wrap.style.display = 'none';
    }
}

function confirmarCredito() {
    const items  = Object.values(carrito);
    const total  = calcularTotal();
    const activo = document.getElementById('toggleAbono').checked;
    let   abono  = 0;

    if (activo) {
        abono = parseFloat(document.getElementById('inputAbonoVal').value) || 0;
        if (abono <= 0) {
            alert('Ingresa un valor de abono mayor a $0.');
            return;
        }
        if (abono > total) {
            alert('El abono no puede superar el total de la venta.');
            return;
        }
    }

    cerrarModalCredito();


    document.getElementById('inputItems').value = JSON.stringify(items);
    document.getElementById('inputAbono').value = abono;
    document.getElementById('inputTipo').value  = 'credito';
    sessionStorage.removeItem('carrito');
    document.getElementById('formPedido').submit();
}

// Cerrar al tocar overlay
document.getElementById('modalCredito').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalCredito();
});
</script>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        renderCarrito();
    });
</script>

</body>
</html>