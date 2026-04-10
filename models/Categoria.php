<?php
// =============================================
// models/Categoria.php
// Consultas a la tabla Categorias
// =============================================

require_once __DIR__ . '/../config/conexion.php';

// ---------------------------------------------
// Obtener todas las categorías
// ---------------------------------------------
function obtenerTodasCategorias() {
    global $conexion;
    $resultado = mysqli_query($conexion,
        "SELECT id_categoria, nombre, estado
         FROM categorias
         ORDER BY id_categoria DESC"
    );
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// ---------------------------------------------
// Obtener una categoría por ID
// ---------------------------------------------
function obtenerCategoriaPorId($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT * FROM categorias WHERE id_categoria = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($resultado);
}

// ---------------------------------------------
// Verificar si ya existe una categoría con ese nombre
// ---------------------------------------------
function existeCategoria($nombre, $excluir_id = 0) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT id_categoria FROM categorias
         WHERE nombre = ? AND id_categoria != ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'si', $nombre, $excluir_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($resultado) > 0;
}

// ---------------------------------------------
// Crear categoría
// ---------------------------------------------
function crearCategoria($nombre) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "INSERT INTO categorias (nombre, estado) VALUES (?, 1)"
    );
    mysqli_stmt_bind_param($stmt, 's', $nombre);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Editar categoría
// ---------------------------------------------
function editarCategoria($id, $nombre) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE categorias SET nombre = ? WHERE id_categoria = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $nombre, $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Desactivar categoría (borrado lógico)
// ---------------------------------------------
function desactivarCategoria($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE categorias SET estado = 0 WHERE id_categoria = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Reactivar categoría
// ---------------------------------------------
function reactivarCategoria($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE categorias SET estado = 1 WHERE id_categoria = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}