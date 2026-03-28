<?php
// =============================================
// models/Cliente.php
// Consultas a la tabla Cliente
// =============================================

require_once __DIR__ . '/../config/conexion.php';

// ---------------------------------------------
// Calcular etiquetas automáticas de un cliente
// Devuelve array con todas las etiquetas que aplican
// ---------------------------------------------
function etiquetarCliente($c) {
    global $conexion;
    $id    = (int) $c['id_cliente'];
    $tags  = [];
    $hoy   = date('Y-m-d');
    $hace30 = date('Y-m-d', strtotime('-30 days'));
    $hace15 = date('Y-m-d', strtotime('-15 days'));

    // 🆕 Nuevo — registrado hace menos de 30 días
    // Usamos el id como proxy si no hay fecha de registro
    // Si la BD tiene fecha_registro úsala; si no, comparamos por id relativo
    $stmt = mysqli_prepare($conexion,
        "SELECT COUNT(*) FROM cliente WHERE id_cliente > ? AND estado = 1"
    );
    // Alternativa sin fecha: compara por rango de IDs recientes (top 20% más nuevo)
    $total_cli = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM cliente"))[0];
    $umbral_nuevo = $total_cli - max(1, (int)($total_cli * 0.2));
    if ($id > $umbral_nuevo) {
        $tags[] = ['clave' => 'nuevo', 'label' => 'Nuevo', 'icon' => '🆕', 'color' => '#3b82f6'];
    }

    // 📊 Compras en los últimos 30 días
    $stmt = mysqli_prepare($conexion,
        "SELECT COUNT(*) AS compras, COALESCE(SUM(total), 0) AS volumen
         FROM venta WHERE id_cliente = ? AND fecha >= ?"
    );
    mysqli_stmt_bind_param($stmt, 'is', $id, $hace30);
    mysqli_stmt_execute($stmt);
    $row30 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $compras30  = (int)   $row30['compras'];
    $volumen30  = (float) $row30['volumen'];

    // ⭐ Frecuente — 3 o más compras en últimos 30 días
    if ($compras30 >= 3) {
        $tags[] = ['clave' => 'frecuente', 'label' => 'Frecuente', 'icon' => '⭐', 'color' => '#f59e0b'];
    }

    // 😴 Inactivo — sin compras en los últimos 15 días (solo si tenía historial)
    $stmt2 = mysqli_prepare($conexion,
        "SELECT COUNT(*) AS total FROM venta WHERE id_cliente = ?"
    );
    mysqli_stmt_bind_param($stmt2, 'i', $id);
    mysqli_stmt_execute($stmt2);
    $total_compras = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2))['total'];

    if ($total_compras > 0 && $compras30 === 0) {
        $tags[] = ['clave' => 'inactivo', 'label' => 'Inactivo', 'icon' => '😴', 'color' => '#94a3b8'];
    }

    // 💳 Con deuda — saldo pendiente de crédito
    $stmt3 = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(v.total - COALESCE(
            (SELECT SUM(a.monto) FROM abono a WHERE a.id_venta = v.id_venta), 0
         )), 0) AS saldo
         FROM venta v
         WHERE v.id_cliente = ? AND v.tipo_venta = 'credito'"
    );
    mysqli_stmt_bind_param($stmt3, 'i', $id);
    mysqli_stmt_execute($stmt3);
    $saldo = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3))['saldo'];
    if ($saldo > 0) {
        $tags[] = ['clave' => 'deuda', 'label' => 'Con deuda', 'icon' => '💳', 'color' => '#ef4444'];
    }

    // 👑 VIP — volumen total en top 20%
    $stmt4 = mysqli_prepare($conexion,
        "SELECT COALESCE(SUM(total), 0) AS volumen_total FROM venta WHERE id_cliente = ?"
    );
    mysqli_stmt_bind_param($stmt4, 'i', $id);
    mysqli_stmt_execute($stmt4);
    $vol_total = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4))['volumen_total'];

    // Percentil 80 del volumen total
    $p80_row = mysqli_fetch_row(mysqli_query($conexion,
        "SELECT COALESCE(SUM(total), 0) AS v FROM venta GROUP BY id_cliente ORDER BY v DESC"
    ));
    // Calculamos el umbral VIP con una subconsulta
    $vip_stmt = mysqli_query($conexion,
        "SELECT vol FROM (
            SELECT id_cliente, COALESCE(SUM(total),0) AS vol
            FROM venta GROUP BY id_cliente
         ) t ORDER BY vol DESC LIMIT 1 OFFSET " . max(0, (int)($total_cli * 0.2) - 1)
    );
    $vip_row   = mysqli_fetch_row($vip_stmt);
    $umbral_vip = $vip_row ? (float)$vip_row[0] : PHP_INT_MAX;

    if ($vol_total > 0 && $vol_total >= $umbral_vip && !in_array('nuevo', array_column($tags, 'clave'))) {
        $tags[] = ['clave' => 'vip', 'label' => 'VIP', 'icon' => '👑', 'color' => '#8b5cf6'];
    }

    return $tags;
}

// Helper para renderizar etiquetas como HTML
function renderEtiquetas(array $tags): string {
    if (empty($tags)) return '';
    $html = '<div class="etiquetas-wrap">';
    foreach ($tags as $t) {
        $html .= '<span class="etiqueta etiqueta-' . $t['clave'] . '">'
               . $t['icon'] . ' ' . $t['label']
               . '</span>';
    }
    $html .= '</div>';
    return $html;
}

// ---------------------------------------------
// Obtener todos los clientes
// ---------------------------------------------
function obtenerClientes() {
    global $conexion;
    $resultado = mysqli_query($conexion,
        "SELECT id_cliente, nombre, telefono, direccion, estado
         FROM cliente
         ORDER BY id_cliente DESC"
    );
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// ---------------------------------------------
// Obtener un cliente por ID
// ---------------------------------------------
function obtenerClientePorId($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT * FROM cliente WHERE id_cliente = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($resultado);
}

// ---------------------------------------------
// Buscar clientes por nombre (para ventas)
// ---------------------------------------------
function buscarClientes($termino) {
    global $conexion;
    $like = '%' . $termino . '%';
    $stmt = mysqli_prepare($conexion,
        "SELECT id_cliente, nombre, telefono, direccion
         FROM cliente
         WHERE (nombre LIKE ? OR telefono LIKE ?) AND estado = 1
         ORDER BY nombre
         LIMIT 10"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// ---------------------------------------------
// Verificar si ya existe un cliente con ese nombre y teléfono
// ---------------------------------------------
function existeCliente($nombre, $telefono, $excluir_id = 0) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT id_cliente FROM cliente
         WHERE nombre = ? AND telefono = ? AND id_cliente != ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $telefono, $excluir_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($resultado) > 0;
}

// ---------------------------------------------
// Crear cliente
// ---------------------------------------------
function crearCliente($nombre, $telefono, $direccion) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "INSERT INTO cliente (nombre, telefono, direccion, estado)
         VALUES (?, ?, ?, 1)"
    );
    mysqli_stmt_bind_param($stmt, 'sss', $nombre, $telefono, $direccion);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Editar cliente
// ---------------------------------------------
function editarCliente($id, $nombre, $telefono, $direccion) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE cliente SET nombre = ?, telefono = ?, direccion = ?
         WHERE id_cliente = ?"
    );
    mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $telefono, $direccion, $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Desactivar cliente (borrado lógico)
// ---------------------------------------------
function desactivarCliente($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE cliente SET estado = 0 WHERE id_cliente = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Reactivar cliente
// ---------------------------------------------
function reactivarCliente($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE cliente SET estado = 1 WHERE id_cliente = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}