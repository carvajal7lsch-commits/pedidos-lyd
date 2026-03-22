<?php
// =============================================
// models/Usuario.php
// Consultas a la tabla Usuario
// =============================================

require_once __DIR__ . '/../config/conexion.php';

// ---------------------------------------------
// Obtener todos los usuarios
// ---------------------------------------------
function obtenerUsuarios() {
    global $conexion;
    $resultado = mysqli_query($conexion,
        "SELECT id_usuario, nombre, correo, rol, estado
         FROM Usuario
         ORDER BY id_usuario DESC"
    );
    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// ---------------------------------------------
// Obtener un usuario por ID
// ---------------------------------------------
function obtenerUsuarioPorId($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT * FROM Usuario WHERE id_usuario = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($resultado);
}

// ---------------------------------------------
// Verificar si ya existe un correo registrado
// ---------------------------------------------
function existeCorreo($correo, $excluir_id = 0) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "SELECT id_usuario FROM Usuario
         WHERE correo = ? AND id_usuario != ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'si', $correo, $excluir_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($resultado) > 0;
}

// ---------------------------------------------
// Crear usuario (rol siempre 'vendedor')
// ---------------------------------------------
function crearUsuario($nombre, $correo, $contrasena) {
    global $conexion;
    $rol  = 'vendedor';
    $stmt = mysqli_prepare($conexion,
        "INSERT INTO Usuario (nombre, correo, contrasena, rol, estado)
         VALUES (?, ?, ?, ?, 1)"
    );
    mysqli_stmt_bind_param($stmt, 'ssss', $nombre, $correo, $contrasena, $rol);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Editar usuario (rol no se toca, siempre vendedor)
// ---------------------------------------------
function editarUsuario($id, $nombre, $correo) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE Usuario SET nombre = ?, correo = ?
         WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $correo, $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Cambiar contraseña
// ---------------------------------------------
function cambiarContrasena($id, $contrasena) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE Usuario SET contrasena = ? WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $contrasena, $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Desactivar usuario (borrado lógico)
// ---------------------------------------------
function desactivarUsuario($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE Usuario SET estado = 0 WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

// ---------------------------------------------
// Reactivar usuario
// ---------------------------------------------
function reactivarUsuario($id) {
    global $conexion;
    $stmt = mysqli_prepare($conexion,
        "UPDATE Usuario SET estado = 1 WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}