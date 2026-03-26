<?php
// =============================================
// public/vendedor/precarga.php
// Devuelve en JSON todos los datos que el vendedor
// necesita para trabajar offline:
//   - productos activos (catálogo)
//   - clientes activos
//   - inventario del camión del día
// Llamado automáticamente por el SW al instalarse
// y por el dashboard al recuperar conexión.
// =============================================
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['ok' => false, 'msg' => 'Precarga offline deshabilitada. Usa modo online.']);
exit();

$id_vendedor = (int) $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

// ── Productos activos ────────────────────────
$res_prod = mysqli_query($conexion,
    "SELECT p.id_producto, p.nombre, p.precio, p.imagen,
            p.id_categoria, c.nombre AS categoria
     FROM productos p
     INNER JOIN categorias c ON p.id_categoria = c.id_categoria
     WHERE p.estado = 1
     ORDER BY p.nombre ASC"
);
$productos = mysqli_fetch_all($res_prod, MYSQLI_ASSOC);
foreach ($productos as &$p) {
    $p['id_producto']  = (int)   $p['id_producto'];
    $p['id_categoria'] = (int)   $p['id_categoria'];
    $p['precio']       = (float) $p['precio'];
}
unset($p);

// ── Clientes activos ─────────────────────────
$res_cli = mysqli_query($conexion,
    "SELECT id_cliente, nombre, telefono, direccion
     FROM cliente
     WHERE estado = 1
     ORDER BY nombre ASC"
);
$clientes = mysqli_fetch_all($res_cli, MYSQLI_ASSOC);
foreach ($clientes as &$c) {
    $c['id_cliente'] = (int) $c['id_cliente'];
}
unset($c);

// ── Inventario del camión hoy ────────────────
$stmt = mysqli_prepare($conexion,
    "SELECT ic.id_producto, ic.cantidad_cargada,
            ic.cantidad_disponible,
            p.nombre, p.precio, p.imagen,
            c.nombre AS categoria
     FROM inventariocamion ic
     INNER JOIN productos p   ON ic.id_producto  = p.id_producto
     INNER JOIN categorias c  ON p.id_categoria  = c.id_categoria
     WHERE ic.id_vendedor = ? AND ic.fecha_cargue = ? AND ic.estado = 1
     ORDER BY p.nombre ASC"
);
mysqli_stmt_bind_param($stmt, 'is', $id_vendedor, $hoy);
mysqli_stmt_execute($stmt);
$inventario = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
foreach ($inventario as &$i) {
    $i['id_producto']         = (int)   $i['id_producto'];
    $i['cantidad_cargada']    = (int)   $i['cantidad_cargada'];
    $i['cantidad_disponible'] = (int)   $i['cantidad_disponible'];
    $i['precio']              = (float) $i['precio'];
}
unset($i);

// ── Categorías (para filtros offline) ────────
$res_cat = mysqli_query($conexion,
    "SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre ASC"
);
$categorias = mysqli_fetch_all($res_cat, MYSQLI_ASSOC);
foreach ($categorias as &$cat) {
    $cat['id_categoria'] = (int) $cat['id_categoria'];
}
unset($cat);

echo json_encode([
    'ok'         => true,
    'fecha'      => $hoy,
    'timestamp'  => time(),
    'productos'  => $productos,
    'clientes'   => $clientes,
    'inventario' => $inventario,
    'categorias' => $categorias,
], JSON_UNESCAPED_UNICODE);