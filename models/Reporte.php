<?php
// =============================================
// models/Reporte.php
// Consultas para el módulo de reportes admin
// =============================================

require_once __DIR__ . '/../config/conexion.php';

// ── 1. VENTAS POR VENDEDOR ────────────────────────────────
function getVentasPorVendedor($conexion, $desde = null, $hasta = null) {
    $where = '';
    if ($desde && $hasta) {
        $desde = mysqli_real_escape_string($conexion, $desde);
        $hasta = mysqli_real_escape_string($conexion, $hasta);
        $where = "AND v.fecha BETWEEN '$desde' AND '$hasta'";
    } elseif ($desde) {
        $desde = mysqli_real_escape_string($conexion, $desde);
        $where = "AND v.fecha >= '$desde'";
    } elseif ($hasta) {
        $hasta = mysqli_real_escape_string($conexion, $hasta);
        $where = "AND v.fecha <= '$hasta'";
    }

    $sql = "
        SELECT
            u.id_usuario,
            u.nombre AS vendedor,
            COUNT(v.id_venta)                                       AS num_ventas,
            SUM(CASE WHEN v.tipo_venta = 'contado'  THEN v.total ELSE 0 END) AS total_contado,
            SUM(CASE WHEN v.tipo_venta = 'credito'  THEN v.total ELSE 0 END) AS total_credito,
            SUM(v.total)                                            AS total_general,
            MIN(v.fecha)                                            AS primera_venta,
            MAX(v.fecha)                                            AS ultima_venta
        FROM usuario u
        INNER JOIN venta v ON v.id_vendedor = u.id_usuario
        WHERE u.rol = 'vendedor' $where
        GROUP BY u.id_usuario, u.nombre
        ORDER BY total_general DESC
    ";
    $result = mysqli_query($conexion, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    return $rows;
}

// ── 2. VENTAS POR PERÍODO ─────────────────────────────────
function getVentasPorPeriodo($conexion, $agrupacion = 'dia', $desde = null, $hasta = null) {
    $where = '';
    if ($desde && $hasta) {
        $desde_esc = mysqli_real_escape_string($conexion, $desde);
        $hasta_esc = mysqli_real_escape_string($conexion, $hasta);
        $where = "WHERE v.fecha BETWEEN '$desde_esc' AND '$hasta_esc'";
    } elseif ($desde) {
        $desde_esc = mysqli_real_escape_string($conexion, $desde);
        $where = "WHERE v.fecha >= '$desde_esc'";
    } elseif ($hasta) {
        $hasta_esc = mysqli_real_escape_string($conexion, $hasta);
        $where = "WHERE v.fecha <= '$hasta_esc'";
    }

    switch ($agrupacion) {
        case 'mes':
            $grupo   = "DATE_FORMAT(v.fecha, '%Y-%m')";
            $label   = "DATE_FORMAT(v.fecha, '%b %Y')";
            break;
        case 'semana':
            $grupo   = "YEARWEEK(v.fecha, 1)";
            $label   = "CONCAT('Semana ', WEEK(v.fecha,1), ' - ', YEAR(v.fecha))";
            break;
        default: // dia
            $grupo   = "v.fecha";
            $label   = "v.fecha";
    }

    $sql = "
        SELECT
            $grupo  AS periodo_key,
            $label  AS periodo_label,
            COUNT(v.id_venta)                                                   AS num_ventas,
            SUM(CASE WHEN v.tipo_venta = 'contado' THEN v.total ELSE 0 END)     AS total_contado,
            SUM(CASE WHEN v.tipo_venta = 'credito' THEN v.total ELSE 0 END)     AS total_credito,
            SUM(v.total)                                                         AS total_general
        FROM venta v
        $where
        GROUP BY periodo_key, periodo_label
        ORDER BY periodo_key ASC
    ";
    $result = mysqli_query($conexion, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    return $rows;
}

// ── 3. PRODUCTOS MÁS VENDIDOS ─────────────────────────────
function getProductosMasVendidos($conexion, $desde = null, $hasta = null, $limite = 20) {
    $where = '';
    if ($desde && $hasta) {
        $desde = mysqli_real_escape_string($conexion, $desde);
        $hasta = mysqli_real_escape_string($conexion, $hasta);
        $where = "AND v.fecha BETWEEN '$desde' AND '$hasta'";
    } elseif ($desde) {
        $desde = mysqli_real_escape_string($conexion, $desde);
        $where = "AND v.fecha >= '$desde'";
    } elseif ($hasta) {
        $hasta = mysqli_real_escape_string($conexion, $hasta);
        $where = "AND v.fecha <= '$hasta'";
    }

    $limite = (int) $limite;
    $sql = "
        SELECT
            p.id_producto,
            p.nombre                    AS producto,
            c.nombre                    AS categoria,
            SUM(dv.cantidad)            AS unidades_vendidas,
            SUM(dv.subtotal)            AS ingresos_total,
            AVG(dv.precio_unitario)     AS precio_promedio,
            COUNT(DISTINCT v.id_venta)  AS num_ventas
        FROM detalle_venta dv
        INNER JOIN venta     v  ON v.id_venta    = dv.id_venta
        INNER JOIN productos p  ON p.id_producto = dv.id_producto
        INNER JOIN categorias c ON c.id_categoria = p.id_categoria
        WHERE dv.estado = 1 $where
        GROUP BY p.id_producto, p.nombre, c.nombre
        ORDER BY unidades_vendidas DESC
        LIMIT $limite
    ";
    $result = mysqli_query($conexion, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    return $rows;
}

// ── 4. CLIENTES CON DEUDA ─────────────────────────────────
function getClientesConDeuda($conexion) {
    $sql = "
        SELECT
            cl.id_cliente,
            cl.nombre           AS cliente,
            cl.telefono,
            cl.direccion,
            COUNT(v.id_venta)                                               AS num_ventas_credito,
            SUM(v.total)                                                    AS total_credito,
            COALESCE(SUM(ab.monto_abonado), 0)                             AS total_abonado,
            SUM(v.total) - COALESCE(SUM(ab.monto_abonado), 0)             AS saldo_pendiente,
            MAX(v.fecha)                                                    AS ultima_compra
        FROM cliente cl
        INNER JOIN venta v ON v.id_cliente = cl.id_cliente AND v.tipo_venta = 'credito'
        LEFT JOIN (
            SELECT id_venta, SUM(monto) AS monto_abonado
            FROM abono
            GROUP BY id_venta
        ) ab ON ab.id_venta = v.id_venta
        GROUP BY cl.id_cliente, cl.nombre, cl.telefono, cl.direccion
        HAVING saldo_pendiente > 0
        ORDER BY saldo_pendiente DESC
    ";
    $result = mysqli_query($conexion, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    return $rows;
}

// ── 5. CIERRES DIARIOS ───────────────────────────────────
function getCierresDiarios($conexion, $desde = null, $hasta = null) {
    $where = '';
    if ($desde && $hasta) {
        $desde = mysqli_real_escape_string($conexion, $desde);
        $hasta = mysqli_real_escape_string($conexion, $hasta);
        $where = "AND cd.fecha BETWEEN '$desde' AND '$hasta'";
    } elseif ($desde) {
        $desde = mysqli_real_escape_string($conexion, $desde);
        $where = "AND cd.fecha >= '$desde'";
    } elseif ($hasta) {
        $hasta = mysqli_real_escape_string($conexion, $hasta);
        $where = "AND cd.fecha <= '$hasta'";
    }

    $sql = "
        SELECT
            cd.id_cierre,
            cd.fecha,
            u.nombre        AS vendedor,
            cd.total_contado,
            cd.total_credito,
            cd.total_general,
            cd.estado
        FROM cierrediario cd
        INNER JOIN usuario u ON u.id_usuario = cd.id_usuario
        WHERE 1=1 $where
        ORDER BY cd.fecha DESC, u.nombre ASC
    ";
    $result = mysqli_query($conexion, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    return $rows;
}

// ── HELPERS ──────────────────────────────────────────────
function formatPesos($valor) {
    return '$ ' . number_format((float)$valor, 0, ',', '.');
}