<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json');

$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_venta) {
    echo json_encode(['error' => 'ID inválido']);
    exit();
}

$stmt = mysqli_prepare($conexion,
    "SELECT v.*, COALESCE(c.nombre, 'Consumidor Final') AS cliente_nombre, c.telefono, c.direccion, u.nombre AS vendedor_nombre
     FROM venta v
     LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
     LEFT JOIN usuario u ON u.id_usuario = v.id_vendedor
     WHERE v.id_venta = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$venta) {
    echo json_encode(['error' => 'Factura no encontrada']);
    exit();
}

// Obtener lo recaudado
$stmt = mysqli_prepare($conexion, "SELECT COALESCE(SUM(monto), 0) as recaudado FROM abono WHERE id_venta = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_venta);
mysqli_stmt_execute($stmt);
$abonos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$venta['recaudado'] = $venta['tipo_venta'] === 'contado' ? $venta['total'] : (float)$abonos['recaudado'];

// Detalles
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

echo json_encode([
    'factura' => $venta,
    'detalles' => $detalles
]);
