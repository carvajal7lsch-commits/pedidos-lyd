<?php
// =============================================
// Lógica del login y crud de usuario
// =============================================

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Usuario.php';

function login($usuario, $contrasena) {
    global $conexion;

    if (empty($usuario) || empty($contrasena)) {
        return ['error' => true, 'mensaje' => 'Por favor completa todos los campos.'];
    }

    $sql   = "SELECT * FROM usuario
              WHERE (nombre = '$usuario' OR correo = '$usuario')
              AND estado = 1
              LIMIT 1";
    $query = mysqli_query($conexion, $sql);
    $user  = mysqli_fetch_assoc($query);

    if (!$user) {
        return ['error' => true, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    if ($contrasena !== $user['contrasena']) {
        return ['error' => true, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    session_start();
    $_SESSION['id_usuario'] = $user['id_usuario'];
    $_SESSION['nombre']     = $user['nombre'];
    $_SESSION['rol']        = $user['rol'];

    if ($user['rol'] === 'admin') {
        header('Location: ' . BASE_URL . 'public/admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'public/vendedor/dashboard.php');
    }
    exit();
}

function logout() {
    session_start();
    session_destroy();
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// =============================================
// CRUD USUARIOS (solo admin)
// =============================================

function procesarCrearUsuario() {
    $errores = validarFormularioUsuario();
    if (!empty($errores)) {
        return ['error' => true, 'errores' => $errores];
    }

    $nombre     = trim($_POST['nombre']);
    $correo     = trim($_POST['correo']);
    $contrasena = trim($_POST['contrasena']);

    if (existeCorreo($correo)) {
        return ['error' => true, 'errores' => ['Ya existe un usuario con ese correo.']];
    }

    // crearUsuario() asigna rol='vendedor' internamente
    if (crearUsuario($nombre, $correo, $contrasena)) {
        return ['exito' => true, 'mensaje' => 'Vendedor creado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo guardar el usuario.']];
}

function procesarEditarUsuario($id) {
    $errores = validarFormularioUsuario(false);
    if (!empty($errores)) {
        return ['error' => true, 'errores' => $errores];
    }

    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);

    if (existeCorreo($correo, $id)) {
        return ['error' => true, 'errores' => ['Ya existe otro usuario con ese correo.']];
    }

    // Cambiar contraseña solo si se llenó el campo
    if (!empty(trim($_POST['contrasena'] ?? ''))) {
        cambiarContrasena($id, trim($_POST['contrasena']));
    }

    // editarUsuario() ya no recibe $rol
    if (editarUsuario($id, $nombre, $correo)) {
        return ['exito' => true, 'mensaje' => 'Vendedor actualizado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo actualizar el usuario.']];
}

function procesarDesactivarUsuario($id, $id_sesion) {
    if ($id === $id_sesion) {
        return ['error' => true, 'errores' => ['No puedes desactivar tu propio usuario.']];
    }
    if (desactivarUsuario($id)) {
        return ['exito' => true, 'mensaje' => 'Vendedor desactivado.'];
    }
    return ['error' => true, 'errores' => ['No se pudo desactivar el vendedor.']];
}

function procesarReactivarUsuario($id) {
    if (reactivarUsuario($id)) {
        return ['exito' => true, 'mensaje' => 'Vendedor reactivado.'];
    }
    return ['error' => true, 'errores' => ['No se pudo reactivar el vendedor.']];
}

function validarFormularioUsuario($validar_contrasena = true) {
    $errores = [];

    if (empty($_POST['nombre']) || trim($_POST['nombre']) === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if (empty($_POST['correo']) || !filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresa un correo electrónico válido.';
    }
    if ($validar_contrasena && empty(trim($_POST['contrasena'] ?? ''))) {
        $errores[] = 'La contraseña es obligatoria.';
    }

    return $errores;
}