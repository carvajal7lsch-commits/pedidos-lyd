<?php
// =============================================
// models/Producto.php
// =============================================

require_once __DIR__ . '/../config/conexion.php';

// Todos los productos con categoría
function obtenerProductos() {
    global $conexion;
    $resultado = mysqli_query($conexion,
        "SELECT p.id_producto, p.id_categoria, p.nombre, p.precio,
                p.imagen, p.estado,
                c.nombre AS categoria
         FROM productos p
         INNER JOIN categorias c ON p.id_categoria = c.id_categoria
         ORDER BY p.id_producto DESC"
    );
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// Solo productos activos (para cargue del camión)
function obtenerProductosActivos() {
    global $conexion;
    $resultado = mysqli_query($conexion,
        "SELECT p.id_producto, p.nombre, p.precio,
                p.imagen,
                c.nombre AS categoria
         FROM productos p
         INNER JOIN categorias c ON p.id_categoria = c.id_categoria
         WHERE p.estado = 1
         ORDER BY p.nombre ASC"
    );
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// Un producto por ID
function obtenerProductoPorId($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT * FROM productos WHERE id_producto = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// Crear producto
function crearProducto($id_categoria, $nombre, $precio, $imagen) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "INSERT INTO productos (id_categoria, nombre, precio, imagen, estado)
         VALUES (?, ?, ?, ?, 1)"
    );
    mysqli_stmt_bind_param($stmt, 'isds', $id_categoria, $nombre, $precio, $imagen);
    return mysqli_stmt_execute($stmt);
}

// Editar producto
function editarProducto($id, $id_categoria, $nombre, $precio, $imagen) {
    global $conexion;

    if ($imagen !== null) {
        $stmt = mysqli_prepare($conexion,
            "UPDATE productos
             SET id_categoria = ?, nombre = ?, precio = ?, imagen = ?
             WHERE id_producto = ?"
        );
        mysqli_stmt_bind_param($stmt, 'isdsi', $id_categoria, $nombre, $precio, $imagen, $id);
    } else {
        $stmt = mysqli_prepare($conexion,
            "UPDATE productos
             SET id_categoria = ?, nombre = ?, precio = ?
             WHERE id_producto = ?"
        );
        mysqli_stmt_bind_param($stmt, 'isdi', $id_categoria, $nombre, $precio, $id);
    }
    return mysqli_stmt_execute($stmt);
}

// Desactivar producto
function desactivarProducto($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion, "UPDATE productos SET estado = 0 WHERE id_producto = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

// Reactivar producto
function reactivarProducto($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion, "UPDATE productos SET estado = 1 WHERE id_producto = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

// Categorías activas para el select
function obtenerCategorias() {
    global $conexion;
    $resultado = mysqli_query($conexion,
        "SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre"
    );
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}