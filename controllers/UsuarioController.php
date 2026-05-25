<?php
// =============================================
// Lógica del login y crud de usuario
// =============================================

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/usuario.php';

function login($usuario, $contrasena, $csrf_token = '') {
    global $conexion;

    if (!verify_csrf_token($csrf_token)) {
        return ['error' => true, 'mensaje' => 'Error de seguridad (CSRF). Intenta de nuevo.'];
    }

    if (empty($usuario) || empty($contrasena)) {
        return ['error' => true, 'mensaje' => 'Por favor completa todos los campos.'];
    }

    // Buscar el usuario
    $sql = "SELECT * FROM usuario 
            WHERE (nombre = ? OR correo = ?) 
            AND estado = 1 
            LIMIT 1";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $usuario, $usuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($resultado);

    if (!$user) {
        return ['error' => true, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    // Verificar si el usuario está bloqueado temporalmente (ej. 15 minutos)
    if ($user['intentos_fallidos'] >= 5) {
        $ultimo = strtotime($user['ultimo_intento']);
        if (time() - $ultimo < 900) { // 15 minutos
            $restante = ceil((900 - (time() - $ultimo)) / 60);
            return ['error' => true, 'mensaje' => "Demasiados intentos fallidos. Intenta en $restante minutos."];
        } else {
            // Resetear intentos si ya pasó el tiempo
            $stmt_reset = mysqli_prepare($conexion, "UPDATE usuario SET intentos_fallidos = 0 WHERE id_usuario = ?");
            mysqli_stmt_bind_param($stmt_reset, 'i', $user['id_usuario']);
            mysqli_stmt_execute($stmt_reset);
        }
    }

    if (!password_verify($contrasena, $user['contrasena'])) {
        // Registrar intento fallido
        $stmt_fail = mysqli_prepare($conexion, "UPDATE usuario SET intentos_fallidos = intentos_fallidos + 1, ultimo_intento = NOW() WHERE id_usuario = ?");
        mysqli_stmt_bind_param($stmt_fail, 'i', $user['id_usuario']);
        mysqli_stmt_execute($stmt_fail);

        return ['error' => true, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    // Login exitoso: Resetear intentos y regenerar sesión
    $stmt_success = mysqli_prepare($conexion, "UPDATE usuario SET intentos_fallidos = 0, ultimo_intento = NULL WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt_success, 'i', $user['id_usuario']);
    mysqli_stmt_execute($stmt_success);

    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true);

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
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        return ['error' => true, 'errores' => ['Error de seguridad (CSRF). Intenta de nuevo.']];
    }
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
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        return ['error' => true, 'errores' => ['Error de seguridad (CSRF). Intenta de nuevo.']];
    }
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