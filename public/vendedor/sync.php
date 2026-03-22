<?php
// =============================================
// public/vendedor/sync.php
// Recibe ventas generadas offline y las guarda en BD
// Llamado automáticamente cuando vuelve la conexión
// =============================================
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit();
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !isset($data['ventas']) || !is_array($data['ventas'])) {
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
    exit();
}

$id_vendedor = (int) $_SESSION['id_usuario'];
$resultados  = [];

foreach ($data['ventas'] as $venta) {
    $id_local   = $venta['id_local']   ?? null;
    $id_cliente = isset($venta['id_cliente']) && $venta['id_cliente'] > 0
                    ? (int) $venta['id_cliente']
                    : null;
    $tipo_venta = $venta['tipo_venta'] === 'credito' ? 'credito' : 'contado';
    $total      = (float) ($venta['total'] ?? 0);
    $abono      = (float) ($venta['abono'] ?? 0);
    $fecha      = $venta['fecha'] ?? date('Y-m-d');
    $items      = $venta['items'] ?? [];

    // Validar fecha — no permitir más de 1 día atrás
    $fecha_min = date('Y-m-d', strtotime('-1 day'));
    if ($fecha < $fecha_min) {
        $resultados[] = ['id_local' => $id_local, 'ok' => false, 'msg' => 'Fecha fuera de rango'];
        continue;
    }

    if (empty($items)) {
        $resultados[] = ['id_local' => $id_local, 'ok' => false, 'msg' => 'Sin items'];
        continue;
    }

    // Si el cliente fue creado offline (id_cliente null o negativo)
    // lo creamos en BD ahora
    if (!$id_cliente && !empty($venta['cliente_nombre'])) {
        $nombre_cli   = trim($venta['cliente_nombre']);
        $tel_cli      = trim($venta['cliente_telefono'] ?? '');
        $dir_cli      = trim($venta['cliente_dir']      ?? 'Sin dirección');

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO cliente (nombre, telefono, direccion, estado) VALUES (?, ?, ?, 1)"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $nombre_cli, $tel_cli, $dir_cli);
        if (mysqli_stmt_execute($stmt)) {
            $id_cliente = mysqli_insert_id($conexion);
        }
    }

    mysqli_begin_transaction($conexion);
    try {
        // Insertar venta
        $stmt = mysqli_prepare($conexion,
            "INSERT INTO venta (id_vendedor, id_cliente, fecha, tipo_venta, total)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iissd',
            $id_vendedor, $id_cliente, $fecha, $tipo_venta, $total
        );
        mysqli_stmt_execute($stmt);
        $id_venta = mysqli_insert_id($conexion);

        // Insertar detalle y descontar inventario
        $stmt_det = mysqli_prepare($conexion,
            "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, subtotal)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt_inv = mysqli_prepare($conexion,
            "UPDATE inventariocamion
             SET cantidad_disponible = GREATEST(0, cantidad_disponible - ?)
             WHERE id_vendedor = ? AND id_producto = ?
               AND fecha_cargue = ? AND estado = 1"
        );

        foreach ($items as $item) {
            $id_prod  = (int)   $item['id'];
            $cant     = (int)   $item['cantidad'];
            $precio   = (float) $item['precio'];
            $subtotal = $precio * $cant;

            mysqli_stmt_bind_param($stmt_det, 'iiddd',
                $id_venta, $id_prod, $cant, $precio, $subtotal
            );
            mysqli_stmt_execute($stmt_det);

            mysqli_stmt_bind_param($stmt_inv, 'iiis',
                $cant, $id_vendedor, $id_prod, $fecha
            );
            mysqli_stmt_execute($stmt_inv);
        }

        // Abono inicial si aplica
        if ($abono > 0 && $tipo_venta === 'credito') {
            $stmt_ab = mysqli_prepare($conexion,
                "INSERT INTO abono (id_venta, id_vendedor, monto, fecha) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_ab, 'iids',
                $id_venta, $id_vendedor, $abono, $fecha
            );
            mysqli_stmt_execute($stmt_ab);
        }

        mysqli_commit($conexion);
        $resultados[] = ['id_local' => $id_local, 'ok' => true, 'id_servidor' => $id_venta];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $resultados[] = ['id_local' => $id_local, 'ok' => false, 'msg' => $e->getMessage()];
    }
}

echo json_encode(['ok' => true, 'resultados' => $resultados]);