<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

// ── Comprueba jornada cerrada (deshabilita agregar productos si ya se cerró hoy) ─
$hoy = date('Y-m-d');
$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS cnt FROM cierrediario WHERE id_usuario = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cierre_hoy = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
$jornada_activa = ($cierre_hoy === 0);

// ── Cliente seleccionado (viene de clientes.php) ─
$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;

// Guardar id_cliente en session si viene por GET
if ($id_cliente) {
    $_SESSION['pedido_id_cliente'] = $id_cliente;
} else {
    $id_cliente = $_SESSION['pedido_id_cliente'] ?? 0;
}

// Datos del cliente seleccionado
$cliente = null;
if ($id_cliente) {
    $stmt = mysqli_prepare($conexion,
        "SELECT id_cliente, nombre FROM cliente WHERE id_cliente = ? AND estado = 1 LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id_cliente);
    mysqli_stmt_execute($stmt);
    $cliente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// ── Categorías activas ───────────────────────
$cats = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre ASC"
), MYSQLI_ASSOC);

// ── Productos con stock del camión ───────────
$productos = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT p.id_producto, p.nombre, p.precio, p.imagen,
            p.id_categoria, c.nombre AS categoria,
            COALESCE(ic.cantidad_disponible, 0) AS stock_camion
     FROM productos p
     JOIN categorias c ON c.id_categoria = p.id_categoria
     LEFT JOIN inventariocamion ic
            ON ic.id_producto = p.id_producto
           AND ic.id_vendedor = $id_vendedor
           AND ic.fecha_cargue = '$hoy'
           AND ic.estado = 1
     WHERE p.estado = 1
     ORDER BY c.nombre ASC, p.nombre ASC"
), MYSQLI_ASSOC);
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

    <title>Productos — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/productos_vendedor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<!-- ══ TOP BAR ══ -->
<header class="topbar">
    <div class="topbar-left">
        <a href="<?php echo $id_cliente ? 'clientes.php' : 'dashboard.php'; ?>" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="page-title">Productos</h1>
            <?php if ($cliente): ?>
            <div class="page-subtitle">
                <i class="bi bi-person-fill"></i>
                <?php echo htmlspecialchars($cliente['nombre']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ══ CONTENIDO ══ -->
<main class="scroll-body" id="mainScroll">

    <?php if (!$jornada_activa): ?>
    <div class="alerta alerta-error" style="margin: 1rem;">
        <i class="bi bi-exclamation-circle-fill"></i>
        Jornada cerrada: no se pueden agregar productos.
    </div>
    <?php endif; ?>

    <!-- Buscador -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="inputBuscar" placeholder="Buscar por nombre..." oninput="filtrar()">
    </div>

    <!-- Pills de categoría -->
    <div class="cats-scroll">
        <div class="cats-track" id="catsTrack">
            <button class="cat-pill active" data-cat="todos" onclick="setCat(this)">Todos</button>
            <?php foreach ($cats as $cat): ?>
            <button class="cat-pill" data-cat="<?php echo $cat['id_categoria']; ?>" onclick="setCat(this)">
                <?php echo htmlspecialchars($cat['nombre']); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Grid de productos -->
    <div class="prod-grid" id="prodGrid">
        <?php foreach ($productos as $p): ?>
        <div class="prod-card <?php echo $p['stock_camion'] == 0 ? 'sin-stock' : ''; ?>"
             data-id="<?php echo $p['id_producto']; ?>"
             data-nombre="<?php echo strtolower(htmlspecialchars($p['nombre'])); ?>"
             data-cat="<?php echo $p['id_categoria']; ?>"
             data-precio="<?php echo $p['precio']; ?>"
             data-stock="<?php echo $p['stock_camion']; ?>"
             data-imagen="<?php echo htmlspecialchars($p['imagen'] ?? ''); ?>">

            <!-- Imagen -->
            <div class="prod-img-wrap">
                <?php if ($p['imagen']): ?>
                    <img src="../uploads/productos/<?php echo htmlspecialchars($p['imagen']); ?>"
                         alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                         class="prod-img" loading="lazy">
                <?php else: ?>
                    <div class="prod-img-placeholder">
                        <i class="bi bi-image"></i>
                    </div>
                <?php endif; ?>
                <?php if ($p['stock_camion'] == 0): ?>
                <div class="stock-overlay">Sin stock</div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="prod-info">
                <div class="prod-nombre"><?php echo htmlspecialchars($p['nombre']); ?></div>
                <div class="prod-stock-tag <?php echo $p['stock_camion'] > 0 ? 'tag-ok' : 'tag-empty'; ?>">
                    <i class="bi bi-truck-front"></i>
                    <?php echo $p['stock_camion'] > 0
                        ? 'Camión: ' . $p['stock_camion'] . ' pac.'
                        : 'Sin stock'; ?>
                </div>
                <div class="prod-precio">
                    $<?php echo number_format($p['precio'], 0, ',', '.'); ?>
                    <span class="prod-cop">COP</span>
                </div>
                <?php if ($jornada_activa && $p['stock_camion'] > 0): ?>
                <button class="btn-anadir" onclick="abrirModal(<?php echo $p['id_producto']; ?>)">
                    <i class="bi bi-cart-plus"></i>
                    Añadir
                </button>
                <?php else: ?>
                <button class="btn-anadir btn-disabled" disabled>
                    <i class="bi bi-cart-plus"></i>
                    <?php echo $p['stock_camion'] == 0 ? 'Sin stock' : 'Jornada cerrada'; ?>
                </button>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <div class="lista-vacia" id="sinResultados" style="display:none;">
        <i class="bi bi-search"></i>
        <p>Sin resultados</p>
    </div>

</main>

<!-- ══ BARRA CARRITO FLOTANTE ══ -->
<div class="carrito-bar" id="carritoBar" style="display:none;">
    <div class="carrito-bar-left">
        <div class="carrito-badge" id="carritoBadge">0</div>
        <div>
            <div class="carrito-bar-label">Subtotal Estimado</div>
            <div class="carrito-bar-total" id="carritoTotal">$0</div>
        </div>
    </div>
    <a href="carrito.php" class="carrito-bar-btn">
        Ver Pedido <i class="bi bi-chevron-right"></i>
    </a>
</div>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<!-- ══ MODAL CANTIDAD ══ -->
<div class="modal-overlay" id="modalCantidad" style="display:none;">
    <div class="bottom-sheet" id="bottomSheet">
        <div class="sheet-handle"></div>

        <button class="sheet-x" onclick="cerrarModal()">
            <i class="bi bi-x-lg"></i>
        </button>

        <!-- Info producto -->
        <div class="modal-prod-info">
            <div class="modal-prod-img-wrap" id="modalImgWrap"></div>
            <div>
                <div class="modal-prod-nombre" id="modalNombre"></div>
                <div class="modal-prod-precio" id="modalPrecioUnit"></div>
            </div>
        </div>

        <!-- Selector cantidad -->
        <div class="cantidad-label">CANTIDAD</div>
        <div class="cantidad-wrap">
            <button class="cant-btn cant-menos" onclick="cambiarCant(-1)">
                <i class="bi bi-dash"></i>
            </button>
            <span class="cant-valor" id="cantValor">1</span>
            <button class="cant-btn cant-mas" onclick="cambiarCant(1)">
                <i class="bi bi-plus"></i>
            </button>
        </div>

        <!-- Resumen precio -->
        <div class="modal-resumen">
            <div class="resumen-row">
                <span>Precio paca</span>
                <span id="resumenPrecio">$0</span>
            </div>
            <div class="resumen-row resumen-subtotal">
                <span>Subtotal</span>
                <span id="resumenSubtotal">$0</span>
            </div>
        </div>

        <button class="btn-confirmar" onclick="confirmarCantidad()">
            <i class="bi bi-check-circle"></i> Confirmar Cantidad
        </button>
    </div>
</div>

<!-- Datos PHP → JS -->
<script>
const BASE_URL     = '<?php echo BASE_URL; ?>';
const ID_CLIENTE   = <?php echo $id_cliente ?: 'null'; ?>;
const UPLOAD_PATH  = '../uploads/productos/';

// Carrito en sessionStorage
let carrito = JSON.parse(sessionStorage.getItem('carrito') || '{}');

// Producto activo en modal
let modalProducto = null;
let modalCantidad = 1;

// ══════════════════════════════════════════
// FILTROS
// ══════════════════════════════════════════
let catActual = 'todos';

function filtrar() {
    const q     = document.getElementById('inputBuscar').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.prod-card');
    let visibles = 0;

    cards.forEach(card => {
        const okQ   = !q   || card.dataset.nombre.includes(q);
        const okCat = catActual === 'todos' || card.dataset.cat === catActual;
        const show  = okQ && okCat;
        card.style.display = show ? '' : 'none';
        if (show) visibles++;
    });

    document.getElementById('sinResultados').style.display = visibles === 0 ? 'flex' : 'none';
}

function setCat(pill) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    catActual = pill.dataset.cat;
    filtrar();
}

// ══════════════════════════════════════════
// MODAL CANTIDAD
// ══════════════════════════════════════════
function abrirModal(id) {
    const card  = document.querySelector(`.prod-card[data-id="${id}"]`);
    const stock = parseInt(card.dataset.stock);

    modalProducto = {
        id:     id,
        nombre: card.querySelector('.prod-nombre').textContent,
        precio: parseFloat(card.dataset.precio),
        imagen: card.dataset.imagen,
        stock:  stock,
    };

    // Cantidad previa en carrito o 1
    modalCantidad = carrito[id] ? carrito[id].cantidad : 1;

    // Rellenar modal
    document.getElementById('modalNombre').textContent = modalProducto.nombre;
    document.getElementById('modalPrecioUnit').textContent =
        '$' + modalProducto.precio.toLocaleString('es-CO') + ' COP';

    const imgWrap = document.getElementById('modalImgWrap');
    if (modalProducto.imagen) {
        imgWrap.innerHTML = `<img src="${UPLOAD_PATH}${modalProducto.imagen}" class="modal-prod-img">`;
    } else {
        imgWrap.innerHTML = `<div class="modal-prod-img-placeholder"><i class="bi bi-image"></i></div>`;
    }

    actualizarModalCantidad();
    document.getElementById('modalCantidad').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('bottomSheet').classList.add('sheet-open'), 10);
}

function cerrarModal() {
    document.getElementById('bottomSheet').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalCantidad').style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

function cambiarCant(delta) {
    const nueva = modalCantidad + delta;
    if (nueva < 0) return;
    if (nueva > modalProducto.stock) return;
    modalCantidad = nueva;
    actualizarModalCantidad();
}

function actualizarModalCantidad() {
    const subtotal = modalProducto.precio * modalCantidad;
    document.getElementById('cantValor').textContent = modalCantidad;
    document.getElementById('resumenPrecio').textContent =
        '$' + modalProducto.precio.toLocaleString('es-CO');
    document.getElementById('resumenSubtotal').textContent =
        '$' + subtotal.toLocaleString('es-CO');

    // Botón confirmar
    const btnConf = document.querySelector('.btn-confirmar');
    if (modalCantidad === 0) {
        btnConf.innerHTML = '<i class="bi bi-trash3"></i> Quitar del pedido';
        btnConf.classList.add('btn-quitar');
    } else {
        btnConf.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar Cantidad';
        btnConf.classList.remove('btn-quitar');
    }

    // Deshabilitar - en 0
    document.querySelector('.cant-menos').style.opacity = modalCantidad === 0 ? '0.35' : '1';
    // Deshabilitar + en stock máximo
    document.querySelector('.cant-mas').style.opacity  = modalCantidad >= modalProducto.stock ? '0.35' : '1';
}

function confirmarCantidad() {
    if (modalCantidad === 0) {
        delete carrito[modalProducto.id];
    } else {
        carrito[modalProducto.id] = {
            id:       modalProducto.id,
            nombre:   modalProducto.nombre,
            precio:   modalProducto.precio,
            imagen:   modalProducto.imagen,
            cantidad: modalCantidad,
        };
    }
    sessionStorage.setItem('carrito', JSON.stringify(carrito));
    actualizarCarritoBar();
    cerrarModal();
}

// ══════════════════════════════════════════
// BARRA CARRITO
// ══════════════════════════════════════════
function actualizarCarritoBar() {
    const items    = Object.values(carrito);
    const totalUds = items.reduce((s, i) => s + i.cantidad, 0);
    const totalCop = items.reduce((s, i) => s + i.precio * i.cantidad, 0);
    const bar      = document.getElementById('carritoBar');

    if (totalUds === 0) {
        bar.style.display = 'none';
        return;
    }

    bar.style.display = 'flex';
    document.getElementById('carritoBadge').textContent  = totalUds;
    document.getElementById('carritoTotal').textContent  =
        '$' + totalCop.toLocaleString('es-CO') + ' COP';
}

// Cerrar modal al tocar overlay
document.getElementById('modalCantidad').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

// Init
actualizarCarritoBar();
</script>


<script>
    // Solo modo online: no se usa IndexedDB ni mode offline.
    document.addEventListener('DOMContentLoaded', () => {
        actualizarCarritoBar();
    });
</script>

</body>
</html>