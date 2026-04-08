<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

// ── Verificar si ya hizo cargue hoy ─────────
$stmt = mysqli_prepare($conexion,
    "SELECT id_inventario FROM inventariocamion
     WHERE id_vendedor = ? AND fecha_cargue = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$cargue_hoy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ── Procesar confirmación del cargue ────────
$mensaje  = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'confirmar_cargue' && !$cargue_hoy) {
        $cantidades = $_POST['cantidades'] ?? [];
        $errores    = [];

        // Filtrar solo los que tienen cantidad > 0
        $items = [];
        foreach ($cantidades as $id_prod => $cant) {
            $cant = (int) $cant;
            if ($cant > 0) {
                $items[(int)$id_prod] = $cant;
            }
        }

        if (empty($items)) {
            $errores[] = 'Debes cargar al menos un producto.';
        }

        if (empty($errores)) {
            // Insertar todos en una transacción
            mysqli_begin_transaction($conexion);
            try {
                $stmt = mysqli_prepare($conexion,
                    "INSERT INTO inventariocamion
                     (id_vendedor, id_producto, cantidad_cargada, cantidad_disponible, fecha_cargue, estado)
                     VALUES (?, ?, ?, ?, ?, 1)"
                );
                foreach ($items as $id_prod => $cant) {
                    mysqli_stmt_bind_param($stmt, 'iiiis',
                        $id_vendedor, $id_prod, $cant, $cant, $hoy
                    );
                    mysqli_stmt_execute($stmt);
                }
                mysqli_commit($conexion);
                $mensaje  = 'Cargue registrado correctamente.';
                $tipo_msg = 'exito';
                // Recargar para mostrar estado actualizado
                header('Location: carga.php?exito=1');
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $errores[] = 'Error al registrar el cargue. Intenta de nuevo.';
            }
        }

        if (!empty($errores)) {
            $mensaje  = implode('<br>', $errores);
            $tipo_msg = 'error';
        }
    }
}

// ── Mensaje de éxito por redirect ───────────
if (isset($_GET['exito'])) {
    $mensaje  = 'Cargue del día registrado exitosamente.';
    $tipo_msg = 'exito';
    // Recargar cargue_hoy
    $stmt = mysqli_prepare($conexion,
        "SELECT id_inventario FROM inventariocamion
         WHERE id_vendedor = ? AND fecha_cargue = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
    mysqli_stmt_execute($stmt);
    $cargue_hoy = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// ── Productos del catálogo ───────────────────
$productos = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT p.id_producto, p.nombre, p.imagen,
            c.nombre AS categoria
     FROM productos p
     JOIN categorias c ON c.id_categoria = p.id_categoria
     WHERE p.estado = 1
     ORDER BY c.nombre ASC, p.nombre ASC"
), MYSQLI_ASSOC);

// ── Inventario restante del último cargue ──
$stmt_restante = mysqli_prepare($conexion,
    "SELECT ic.id_producto, ic.cantidad_disponible
     FROM inventariocamion ic
     WHERE ic.id_vendedor = ? 
       AND ic.fecha_cargue = (
           SELECT MAX(fecha_cargue) FROM inventariocamion 
           WHERE id_vendedor = ? AND fecha_cargue < ?
       )
       AND ic.estado = 1
       AND ic.cantidad_disponible > 0"
);
mysqli_stmt_bind_param($stmt_restante, 'iis', $id_vendedor, $id_vendedor, $hoy);
mysqli_stmt_execute($stmt_restante);
$res_restante = mysqli_stmt_get_result($stmt_restante);
$restante_ayer_map = [];
while ($row = mysqli_fetch_assoc($res_restante)) {
    $restante_ayer_map[$row['id_producto']] = (int) $row['cantidad_disponible'];
}

// ── Si ya cargó hoy, obtener detalle ────────
$items_cargados = [];
if ($cargue_hoy) {
    $stmt = mysqli_prepare($conexion,
        "SELECT ic.id_inventario, ic.id_producto, ic.cantidad_cargada,
                ic.cantidad_disponible, p.nombre, p.imagen
         FROM inventariocamion ic
         JOIN productos p ON p.id_producto = ic.id_producto
         WHERE ic.id_vendedor = ? AND ic.fecha_cargue = ? AND ic.estado = 1
         ORDER BY p.nombre ASC"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
    mysqli_stmt_execute($stmt);
    $items_cargados = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
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

    <title>Cargue — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/cargue.css">
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
            <h1 class="page-title">Cargue de Camión</h1>
            <div class="page-subtitle">
                <i class="bi bi-calendar3"></i>
                <?php echo fecha_es('d \d\e F Y'); ?>
            </div>
        </div>
    </div>
</header>

<main class="scroll-body">

    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>">
        <i class="bi bi-<?php echo $tipo_msg === 'exito' ? 'check-circle-fill' : 'exclamation-circle-fill'; ?>"></i>
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <?php if ($cargue_hoy): ?>
    <!-- ══ YA HAY CARGUE HOY — mostrar resumen ══ -->
    <div class="cargue-hecho-banner">
        <i class="bi bi-truck-front-fill"></i>
        <div>
            <div class="cargue-hecho-title">Camión cargado hoy</div>
            <div class="cargue-hecho-sub"><?php echo count($items_cargados); ?> productos · <?php echo fecha_es('d M Y'); ?></div>
        </div>
    </div>

    <div class="seccion-titulo">Detalle del cargue</div>

    <div class="resumen-lista">
        <?php foreach ($items_cargados as $item): ?>
        <div class="resumen-item">
            <div class="resumen-img-wrap">
                <?php if ($item['imagen']): ?>
                <img src="../uploads/productos/<?php echo htmlspecialchars($item['imagen']); ?>"
                     class="resumen-img" alt="<?php echo htmlspecialchars($item['nombre']); ?>">
                <?php else: ?>
                <div class="resumen-img-placeholder"><i class="bi bi-image"></i></div>
                <?php endif; ?>
            </div>
            <div class="resumen-info">
                <div class="resumen-nombre"><?php echo htmlspecialchars($item['nombre']); ?></div>
                <div class="resumen-uds">
                    <?php echo $item['cantidad_disponible']; ?> /
                    <?php echo $item['cantidad_cargada']; ?> pac. disponibles
                </div>
            </div>
            <div class="resumen-check">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ══ PASO 1 — Seleccionar cantidades ══ -->

    <!-- Indicador de pasos -->
    <div class="pasos-wrap">
        <div class="paso activo">
            <div class="paso-num">1</div>
            <div class="paso-label">Cantidades</div>
        </div>
        <div class="paso-linea"></div>
        <div class="paso" id="paso2ind">
            <div class="paso-num">2</div>
            <div class="paso-label">Resumen</div>
        </div>
        <div class="paso-linea"></div>
        <div class="paso" id="paso3ind">
            <div class="paso-num">3</div>
            <div class="paso-label">Confirmar</div>
        </div>
    </div>

    <!-- ── VISTA PASO 1 ── -->
    <div id="vistaPaso1">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="inputBuscar" placeholder="Buscar producto..." oninput="filtrarProductos()">
        </div>

        <div class="prod-cargue-lista" id="prodLista">
            <?php foreach ($productos as $p): ?>
            <div class="prod-cargue-item"
                 data-nombre="<?php echo strtolower(htmlspecialchars($p['nombre'])); ?>"
                 data-id="<?php echo $p['id_producto']; ?>">

                <div class="pci-left">
                    <div class="pci-img-wrap">
                        <?php if ($p['imagen']): ?>
                        <img src="../uploads/productos/<?php echo htmlspecialchars($p['imagen']); ?>"
                             class="pci-img" alt="">
                        <?php else: ?>
                        <div class="pci-img-placeholder"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="pci-info">
                        <div class="pci-nombre"><?php echo htmlspecialchars($p['nombre']); ?></div>
                        <div class="pci-cat"><?php echo htmlspecialchars($p['categoria']); ?></div>
                        <?php if (isset($restante_ayer_map[$p['id_producto']])): ?>
                        <div class="restante-wrap">
                            <label class="restante-label">
                                <input type="checkbox" id="check_rest_<?php echo $p['id_producto']; ?>" checked onchange="validarCant(<?php echo $p['id_producto']; ?>)">
                                <span class="restante-badge">
                                    <i class="bi bi-box-seam-fill"></i> Ayer: <?php echo $restante_ayer_map[$p['id_producto']]; ?>
                                </span>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pci-right">
                    <div class="cant-ctrl">
                        <button type="button" class="cant-btn-sm"
                                onclick="cambiarCant(<?php echo $p['id_producto']; ?>, -1)">
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number"
                               class="cant-input"
                               id="cant_<?php echo $p['id_producto']; ?>"
                               value="0" min="0"
                               onchange="validarCant(<?php echo $p['id_producto']; ?>)">
                        <button type="button" class="cant-btn-sm cant-btn-plus"
                                onclick="cambiarCant(<?php echo $p['id_producto']; ?>, 1)">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <button class="btn-confirmar-cargue" onclick="irAResumen()">
            <i class="bi bi-truck-front"></i> Confirmar Cargue
        </button>
    </div>

    <!-- ── VISTA PASO 2 — Resumen ── -->
    <div id="vistaPaso2" style="display:none;">
        <div class="resumen-header">
            <div class="seccion-titulo">
                Productos seleccionados
                <span class="seccion-badge" id="resumenCount">0 ítems</span>
            </div>
        </div>

        <div class="resumen-lista" id="resumenLista"></div>

        <div class="paso2-btns">
            <button class="btn-secundario" onclick="volverPaso1()">
                <i class="bi bi-arrow-left"></i> Editar
            </button>
            <button class="btn-confirmar-cargue btn-iniciar" onclick="irAPaso3()">
                <i class="bi bi-play-circle"></i> Iniciar Cargue
            </button>
        </div>
    </div>

    <!-- ── VISTA PASO 3 — Confirmar uno a uno ── -->
    <div id="vistaPaso3" style="display:none;">
        <form method="POST" id="formCargue" action="?accion=confirmar">
            <input type="hidden" name="accion" value="confirmar_cargue">
            <div id="hiddenCantidades"></div>
        </form>

        <div class="seccion-titulo">
            Productos pendientes
            <span class="seccion-badge" id="pendienteCount">0</span>
        </div>

        <div class="paso3-lista" id="paso3Lista"></div>

        <!-- Botón finalizar — aparece cuando todos están confirmados -->
        <div id="finalizarWrap" style="display:none;">
            <div class="todos-listos">
                <i class="bi bi-check-circle-fill"></i>
                <span>¡Todo cargado!</span>
            </div>
            <button class="btn-confirmar-cargue" onclick="enviarCargue()">
                <i class="bi bi-floppy2-fill"></i> Finalizar y Registrar
            </button>
        </div>
    </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<script>
// Datos de productos desde PHP
const PRODUCTOS = <?php echo json_encode(array_values($productos)); ?>;
const RESTANTE_AYER = <?php echo json_encode($restante_ayer_map); ?>;
const UPLOAD    = '../uploads/productos/';

// Cantidades seleccionadas { id_producto: {cant, rest, total} }
let seleccion = {};

// Confirmados en paso 3
let confirmados = {};

// ══════════════════════════════════════════
// PASO 1 — Cantidades
// ══════════════════════════════════════════
function cambiarCant(id, delta) {
    const input = document.getElementById('cant_' + id);
    const nueva = Math.max(0, (parseInt(input.value) || 0) + delta);
    input.value = nueva;
    actualizarEstiloItem(id, nueva);
}

function validarCant(id) {
    const input = document.getElementById('cant_' + id);
    const val   = Math.max(0, parseInt(input.value) || 0);
    input.value = val;
    actualizarEstiloItem(id, val);
}

function actualizarEstiloItem(id, cant) {
    const item = document.querySelector(`.prod-cargue-item[data-id="${id}"]`);
    const checkRest = document.getElementById('check_rest_' + id);
    const rest = (checkRest && checkRest.checked) ? (RESTANTE_AYER[id] || 0) : 0;
    
    if (cant > 0 || rest > 0) {
        item.classList.add('item-activo');
    } else {
        item.classList.remove('item-activo');
    }
}

function filtrarProductos() {
    const q = document.getElementById('inputBuscar').value.toLowerCase().trim();
    document.querySelectorAll('.prod-cargue-item').forEach(item => {
        item.style.display = !q || item.dataset.nombre.includes(q) ? '' : 'none';
    });
}

function irAResumen() {
    // Recoger cantidades > 0 y restantes chequeados
    seleccion = {};
    PRODUCTOS.forEach(p => {
        const id = p.id_producto;
        const input = document.getElementById('cant_' + id);
        const cant  = parseInt(input?.value) || 0;
        const checkRest = document.getElementById('check_rest_' + id);
        const rest = (checkRest && checkRest.checked) ? (RESTANTE_AYER[id] || 0) : 0;
        
        const total = cant + rest;
        if (total > 0) {
            seleccion[id] = { cant: cant, rest: rest, total: total };
        }
    });

    if (Object.keys(seleccion).length === 0) {
        alert('Debes cargar al menos un producto (nuevo o sobrante del día anterior).');
        return;
    }

    renderResumen();
    setPaso(2);
}

// ══════════════════════════════════════════
// PASO 2 — Resumen
// ══════════════════════════════════════════
function renderResumen() {
    const lista  = document.getElementById('resumenLista');
    const count  = document.getElementById('resumenCount');
    const ids    = Object.keys(seleccion);
    count.textContent = ids.length + (ids.length === 1 ? ' ítem' : ' ítems');
    lista.innerHTML   = '';

    ids.forEach(id => {
        const prod = PRODUCTOS.find(p => p.id_producto == id);
        const obj  = seleccion[id];
        const img  = prod.imagen
            ? `<img src="${UPLOAD}${prod.imagen}" class="resumen-img">`
            : `<div class="resumen-img-placeholder"><i class="bi bi-image"></i></div>`;

        let desglose = `<div class="resumen-uds">${obj.total} pac.</div>`;
        if (obj.rest > 0 && obj.cant > 0) {
            desglose = `<div class="resumen-uds">${obj.total} pac <span class="resumen-desglose">(Ayer: ${obj.rest} + Hoy: ${obj.cant})</span></div>`;
        } else if (obj.rest > 0) {
            desglose = `<div class="resumen-uds">${obj.total} pac <span class="resumen-desglose">(Todo de ayer)</span></div>`;
        } else {
            desglose = `<div class="resumen-uds">${obj.total} pac <span class="resumen-desglose">(Todo nuevo)</span></div>`;
        }

        lista.innerHTML += `
            <div class="resumen-item">
                <div class="resumen-img-wrap">${img}</div>
                <div class="resumen-info">
                    <div class="resumen-nombre">${prod.nombre}</div>
                    ${desglose}
                </div>
                <div class="resumen-check">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>`;
    });
}

function volverPaso1() { setPaso(1); }

function irAPaso3() {
    renderPaso3();
    setPaso(3);
}

// ══════════════════════════════════════════
// PASO 3 — Confirmar uno a uno
// ══════════════════════════════════════════
function renderPaso3() {
    const lista = document.getElementById('paso3Lista');
    lista.innerHTML = '';
    confirmados = {};

    Object.keys(seleccion).forEach(id => {
        const prod = PRODUCTOS.find(p => p.id_producto == id);
        const obj  = seleccion[id];
        const img  = prod.imagen
            ? `<img src="${UPLOAD}${prod.imagen}" class="pci-img">`
            : `<div class="pci-img-placeholder"><i class="bi bi-image"></i></div>`;

        let cantText = `${obj.total} pac. a cargar`;
        let autoConfirm = false;

        if (obj.cant > 0 && obj.rest > 0) {
            cantText = `${obj.cant} pac. nuevas a cargar`;
        } else if (obj.cant === 0 && obj.rest > 0) {
            cantText = `<span class="texto-cargado" style="color: #64748b;"><i class="bi bi-truck"></i> Ya en camión</span>`;
            autoConfirm = true;
        }

        const btnHtml = autoConfirm 
            ? `<button class="btn-confirmar-item" disabled id="btnconf_${id}"><i class="bi bi-check-all"></i></button>`
            : `<button class="btn-confirmar-item" id="btnconf_${id}" onclick="confirmarItem(${id})"><i class="bi bi-check-lg"></i></button>`;

        const extraClass = autoConfirm ? 'item-confirmado' : '';

        lista.innerHTML += `
            <div class="paso3-item ${extraClass}" id="p3item_${id}" data-id="${id}">
                <div class="pci-left">
                    <div class="pci-img-wrap">${img}</div>
                    <div class="pci-info">
                        <div class="pci-nombre">${prod.nombre}</div>
                        <div class="paso3-cant">${cantText}</div>
                    </div>
                </div>
                ${btnHtml}
            </div>`;
            
        if (autoConfirm) {
            confirmados[id] = true;
        }
    });

    actualizarPendienteCount();
    
    if (Object.keys(confirmados).length === Object.keys(seleccion).length) {
        document.getElementById('finalizarWrap').style.display = 'block';
        document.getElementById('pendienteCount').textContent  = '0';
    } else {
        document.getElementById('finalizarWrap').style.display = 'none';
    }
}

function confirmarItem(id) {
    if (confirmados[id]) return;
    confirmados[id] = true;

    const item = document.getElementById('p3item_' + id);
    const btn  = document.getElementById('btnconf_' + id);

    item.classList.add('item-confirmado');
    btn.innerHTML  = '<i class="bi bi-check-lg"></i>';
    btn.disabled   = true;

    // Cambiar cant por texto "Cargado con éxito"
    item.querySelector('.paso3-cant').innerHTML =
        '<span class="texto-cargado"><i class="bi bi-check-circle-fill"></i> Cargado con éxito</span>';

    actualizarPendienteCount();

    // Si todos están confirmados mostrar botón finalizar
    if (Object.keys(confirmados).length === Object.keys(seleccion).length) {
        document.getElementById('finalizarWrap').style.display = 'block';
        document.getElementById('pendienteCount').textContent  = '0';
    }
}

function actualizarPendienteCount() {
    const pendientes = Object.keys(seleccion).length - Object.keys(confirmados).length;
    document.getElementById('pendienteCount').textContent = pendientes;
}

function enviarCargue() {
    // Llenar el form con los hidden inputs y submitear
    const wrap = document.getElementById('hiddenCantidades');
    wrap.innerHTML = '';
    Object.keys(seleccion).forEach(id => {
        wrap.innerHTML += `<input type="hidden" name="cantidades[${id}]" value="${seleccion[id].total}">`;
    });
    document.getElementById('formCargue').submit();
}

// ══════════════════════════════════════════
// NAVEGACIÓN ENTRE PASOS
// ══════════════════════════════════════════
function setPaso(num) {
    document.getElementById('vistaPaso1').style.display = num === 1 ? '' : 'none';
    document.getElementById('vistaPaso2').style.display = num === 2 ? '' : 'none';
    document.getElementById('vistaPaso3').style.display = num === 3 ? '' : 'none';

    // Indicadores
    [1, 2, 3].forEach(n => {
        const el = document.querySelector(`.paso:nth-child(${n * 2 - 1})`);
        if (el) {
            el.classList.toggle('activo',    n === num);
            el.classList.toggle('completado', n < num);
        }
    });
}
</script>

</body>
</html>