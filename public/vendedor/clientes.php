<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';
require_once __DIR__ . '/../../models/Cliente.php';

$hoy = date('Y-m-d');
$stmt = mysqli_prepare($conexion,
    "SELECT COUNT(*) AS cnt FROM cierrediario WHERE id_usuario = ? AND fecha = ?"
);
mysqli_stmt_bind_param($stmt, 'is', $_SESSION['id_usuario'], $hoy);
mysqli_stmt_execute($stmt);
$cierre_hoy = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
$jornada_activa = ($cierre_hoy === 0);

$mensaje  = '';
$tipo_msg = '';
$accion   = $_GET['accion'] ?? 'listar';

// Mensajes contextuales
$intent = $_GET['intent'] ?? '';
$msg_get = $_GET['msg'] ?? '';

if ($intent === 'nuevo_pedido') {
    $mensaje = 'Por favor selecciona un cliente para iniciar el pedido.';
    $tipo_msg = 'info';
} elseif ($msg_get === 'selecciona_cliente') {
    $mensaje = 'Debes seleccionar un cliente antes de ver el pedido.';
    $tipo_msg = 'warning';
}

// ── Crear cliente ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear') {
    $resultado = procesarCrearCliente();
    $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
    $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
}

$abrir_modal = ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipo_msg === 'error');

// ── Obtener clientes con saldo, última visita y producto favorito ─
$clientes = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT c.id_cliente, c.nombre, c.telefono, c.direccion, c.estado,
            COALESCE((
                SELECT SUM(v.total - COALESCE((SELECT SUM(a.monto) FROM abono a WHERE a.id_venta = v.id_venta), 0))
                FROM venta v
                WHERE v.id_cliente = c.id_cliente AND v.tipo_venta = 'credito'
            ), 0) AS saldo_pendiente,
            (SELECT MAX(v2.fecha) FROM venta v2 WHERE v2.id_cliente = c.id_cliente) AS ultima_compra,
            (SELECT p.nombre FROM detalle_venta dv
                JOIN venta v3 ON v3.id_venta = dv.id_venta
                JOIN productos p ON p.id_producto = dv.id_producto
                WHERE v3.id_cliente = c.id_cliente AND dv.estado = 1
                GROUP BY dv.id_producto
                ORDER BY SUM(dv.cantidad) DESC
                LIMIT 1
            ) AS producto_favorito
     FROM cliente c
     WHERE c.estado = 1
     ORDER BY c.nombre ASC"
), MYSQLI_ASSOC);

// Calcular etiquetas y días sin comprar para cada cliente
foreach ($clientes as &$c) {
    $c['etiquetas']      = etiquetarCliente($c);
    $c['dias_sin_compra'] = $c['ultima_compra']
        ? (int) floor((time() - strtotime($c['ultima_compra'])) / 86400)
        : null;
}
unset($c);

// Rutas disponibles (misma lista que en el formulario)
$rutas = ['Guayabal', 'Cruce', 'Acevedo', 'Gallardo', 'El Brasil', 'Quemadas'];
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

    <title>Clientes — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/clientes_vendedor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<!-- ══ TOP BAR ══ -->
<header class="topbar">
    <div class="topbar-left">
        <a href="dashboard.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="page-title">Clientes</h1>
    </div>
    <button class="btn-add" onclick="abrirModal()" title="Nuevo cliente">
        <i class="bi bi-plus-lg"></i>
    </button>
</header>

<!-- ══ CONTENIDO ══ -->
<main class="scroll-body">

    <?php if (!$jornada_activa): ?>
    <div class="alerta alerta-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        Jornada cerrada: no se permiten pedidos hoy.
    </div>
    <?php endif; ?>

    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- Buscador + filtros -->
    <div class="filtros-wrap">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="inputBuscar" placeholder="Buscar tienda o cliente..." oninput="filtrar()">
        </div>

        <!-- Filtro por ruta -->
        <div class="filter-pills" id="pillsRuta">
            <button class="fpill active" data-ruta="" onclick="setRuta(this)">Todas</button>
            <?php foreach ($rutas as $ruta): ?>
            <button class="fpill" data-ruta="<?php echo strtolower($ruta); ?>" onclick="setRuta(this)">
                <?php echo $ruta; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Filtro por saldo -->
        <div class="filter-pills" id="pillsSaldo">
            <button class="fpill active" data-saldo="" onclick="setSaldo(this)">Todos</button>
            <button class="fpill" data-saldo="saldo" onclick="setSaldo(this)">Con saldo</button>
            <button class="fpill" data-saldo="libre" onclick="setSaldo(this)">Sin saldo</button>
        </div>
    </div>

    <!-- Lista de clientes -->
    <div class="clientes-lista" id="clientesLista">
        <?php if (empty($clientes)): ?>
        <div class="lista-vacia">
            <i class="bi bi-people"></i>
            <p>No hay clientes registrados</p>
        </div>
        <?php else: ?>
        <?php foreach ($clientes as $c): ?>
        <div class="cliente-card <?php echo $c['saldo_pendiente'] > 0 ? 'cliente-card--deuda' : ''; ?>"
             data-nombre="<?php echo strtolower(htmlspecialchars($c['nombre'])); ?>"
             data-saldo="<?php echo $c['saldo_pendiente'] > 0 ? 'saldo' : 'libre'; ?>"
             data-ruta="<?php echo strtolower(htmlspecialchars($c['direccion'] ?? '')); ?>">

            <?php if ($c['saldo_pendiente'] > 0): ?>
            <div class="card-deuda-banner">
                <span class="card-deuda-banner__icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                <span class="card-deuda-banner__monto">
                    SALDO: $<?php echo number_format($c['saldo_pendiente'], 0, ',', '.'); ?>
                </span>
                <span class="card-deuda-banner__badge">CON DEUDA</span>
            </div>
            <?php endif; ?>

            <a href="detalle_cliente.php?id=<?php echo $c['id_cliente']; ?>" class="card-link">
                <div class="card-top">
                    <div class="cli-avatar-sm">
                        <?php echo mb_strtoupper(mb_substr($c['nombre'], 0, 1)); ?>
                    </div>
                    <div class="card-info">
                        <div class="card-nombre"><?php echo htmlspecialchars($c['nombre']); ?></div>
                        <?php if (!empty($c['etiquetas'])): ?>
                        <div class="card-etiquetas">
                            <?php foreach ($c['etiquetas'] as $t): ?>
                            <span class="etiqueta etiqueta-<?php echo $t['clave']; ?>">
                                <?php echo $t['icon'] . ' ' . $t['label']; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-bottom">
                    <div class="card-dir">
                        <i class="bi bi-geo-alt"></i>
                        <span><?php echo htmlspecialchars($c['direccion']); ?></span>
                    </div>
                    <div class="card-meta-row">
                        <?php if ($c['dias_sin_compra'] !== null): ?>
                        <span class="card-meta-item <?php echo $c['dias_sin_compra'] > 15 ? 'card-meta-alerta' : ''; ?>">
                            <i class="bi bi-clock"></i>
                            <?php echo $c['dias_sin_compra'] === 0 ? 'Hoy' : 'Hace ' . $c['dias_sin_compra'] . 'd'; ?>
                        </span>
                        <?php else: ?>
                        <span class="card-meta-item card-meta-gris">
                            <i class="bi bi-clock"></i> Sin visitas
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($c['producto_favorito'])): ?>
                        <span class="card-meta-item">
                            <i class="bi bi-star-fill"></i>
                            <?php echo htmlspecialchars($c['producto_favorito']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>

            <div class="card-actions">
                <?php if ($jornada_activa): ?>
                <a href="productos.php?id_cliente=<?php echo $c['id_cliente']; ?>" class="btn-pedido">
                    <i class="bi bi-cart3"></i> Hacer Pedido
                </a>
                <?php else: ?>
                <button class="btn-pedido btn-disabled" disabled>
                    <i class="bi bi-cart3"></i> Hacer Pedido
                </button>
                <?php endif; ?>

                <?php if (!empty($c['telefono'])): ?>
                <a href="tel:<?php echo htmlspecialchars($c['telefono']); ?>" class="btn-accion-sec" title="Llamar">
                    <i class="bi bi-telephone-fill"></i>
                </a>
                <?php endif; ?>

                <a href="detalle_cliente.php?id=<?php echo $c['id_cliente']; ?>" class="btn-accion-sec" title="Ver detalle">
                    <i class="bi bi-three-dots"></i>
                </a>
            </div>

        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="lista-vacia" id="sinResultados" style="display:none;">
        <i class="bi bi-search"></i>
        <p>Sin resultados para tu búsqueda</p>
    </div>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<!-- ══ MODAL NUEVO CLIENTE ══ -->
<div class="modal-overlay" id="modalCrear" style="display:none;">
    <div class="bottom-sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <h2 class="sheet-title">Nuevo Cliente</h2>
            <button class="sheet-cerrar" onclick="cerrarModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="?accion=crear" class="sheet-form">
            <div class="form-campo">
                <label>Nombre de la tienda</label>
                <input type="text" name="nombre"
                       placeholder="Ej. Tienda La Esperanza"
                       value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                       maxlength="100" required>
            </div>
            <div class="form-campo">
                <label>Ruta / Dirección</label>
                <div class="input-icon-wrap">
                    <i class="bi bi-geo-alt"></i>
                    <select name="direccion" required>
                        <option value="">-- Selecciona la ruta --</option>
                        <?php
                        $sel = $_POST['direccion'] ?? '';
                        foreach ($rutas as $ruta):
                        ?>
                        <option value="<?php echo $ruta; ?>"
                            <?php echo $sel === $ruta ? 'selected' : ''; ?>>
                            <?php echo $ruta; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-campo">
                <label>Teléfono <span class="label-opcional">(opcional)</span></label>
                <div class="input-icon-wrap">
                    <i class="bi bi-telephone"></i>
                    <input type="text" name="telefono"
                           placeholder="300 000 0000"
                           value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>"
                           maxlength="20">
                </div>
            </div>
            <button type="submit" class="btn-guardar-sheet">
                <i class="bi bi-floppy2-fill"></i> Guardar Cliente
            </button>
        </form>
    </div>
</div>

<script>
let filtroRuta  = '';
let filtroSaldo = '';

function filtrar() {
    const q     = document.getElementById('inputBuscar').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.cliente-card');
    let visibles = 0;

    cards.forEach(card => {
        const okQ     = !q           || card.dataset.nombre.includes(q);
        const okRuta  = !filtroRuta  || card.dataset.ruta  === filtroRuta;
        const okSaldo = !filtroSaldo || card.dataset.saldo === filtroSaldo;
        const mostrar = okQ && okRuta && okSaldo;
        card.style.display = mostrar ? '' : 'none';
        if (mostrar) visibles++;
    });

    document.getElementById('sinResultados').style.display = visibles === 0 ? 'flex' : 'none';
}

function setRuta(pill) {
    document.querySelectorAll('#pillsRuta .fpill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    filtroRuta = pill.dataset.ruta;
    filtrar();
}

function setSaldo(pill) {
    document.querySelectorAll('#pillsSaldo .fpill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    filtroSaldo = pill.dataset.saldo;
    filtrar();
}

function abrirModal() {
    document.getElementById('modalCrear').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        document.querySelector('.bottom-sheet').classList.add('sheet-open');
    }, 10);
}

function cerrarModal() {
    document.querySelector('.bottom-sheet').classList.remove('sheet-open');
    setTimeout(() => {
        document.getElementById('modalCrear').style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

document.getElementById('modalCrear').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

<?php if ($abrir_modal): ?>
    window.addEventListener('load', () => abrirModal());
<?php endif; ?>
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const badge = document.getElementById('badge-pendientes');
        if (badge) badge.style.display = 'none';
    });
</script>

</body>
</html>