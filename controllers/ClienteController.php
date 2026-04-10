<?php
// =============================================
// controllers/ClienteController.php
// Lógica del CRUD de Clientes
// =============================================

require_once __DIR__ . '/../models/cliente.php';

// ---------------------------------------------
// Procesar creación
// ---------------------------------------------
function procesarCrearCliente() {
    $errores = validarFormularioCliente();
    if (!empty($errores)) {
        return ['error' => true, 'errores' => $errores];
    }

    $nombre    = trim($_POST['nombre']);
    $telefono  = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    if (existeCliente($nombre, $telefono)) {
        return ['error' => true, 'errores' => ['Ya existe un cliente con ese nombre y teléfono.']];
    }

    if (crearCliente($nombre, $telefono, $direccion)) {
        return ['exito' => true, 'mensaje' => 'Cliente creado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo guardar el cliente.']];
}

// ---------------------------------------------
// Procesar edición
// ---------------------------------------------
function procesarEditarCliente($id) {
    $errores = validarFormularioCliente();
    if (!empty($errores)) {
        return ['error' => true, 'errores' => $errores];
    }

    $nombre    = trim($_POST['nombre']);
    $telefono  = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    if (existeCliente($nombre, $telefono, $id)) {
        return ['error' => true, 'errores' => ['Ya existe otro cliente con ese nombre y teléfono.']];
    }

    if (editarCliente($id, $nombre, $telefono, $direccion)) {
        return ['exito' => true, 'mensaje' => 'Cliente actualizado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo actualizar el cliente.']];
}

// ---------------------------------------------
// Procesar desactivar
// ---------------------------------------------
function procesarDesactivarCliente($id) {
    if (desactivarCliente($id)) {
        return ['exito' => true, 'mensaje' => 'Cliente desactivado.'];
    }
    return ['error' => true, 'errores' => ['No se pudo desactivar el cliente.']];
}

// ---------------------------------------------
// Procesar reactivar
// ---------------------------------------------
function procesarReactivarCliente($id) {
    if (reactivarCliente($id)) {
        return ['exito' => true, 'mensaje' => 'Cliente reactivado.'];
    }
    return ['error' => true, 'errores' => ['No se pudo reactivar el cliente.']];
}

// ---------------------------------------------
// Validaciones del formulario
// ---------------------------------------------
function validarFormularioCliente() {
    $errores = [];

    if (empty($_POST['nombre']) || trim($_POST['nombre']) === '') {
        $errores[] = 'El nombre del cliente es obligatorio.';
    } elseif (strlen(trim($_POST['nombre'])) > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres.';
    }

    if (!empty($_POST['telefono']) && strlen(trim($_POST['telefono'])) > 20) {
        $errores[] = 'El teléfono no puede superar los 20 caracteres.';
    }

    if (empty($_POST['direccion']) || trim($_POST['direccion']) === '') {
        $errores[] = 'La dirección es obligatoria.';
    } elseif (strlen(trim($_POST['direccion'])) > 150) {
        $errores[] = 'La dirección no puede superar los 150 caracteres.';
    }

    return $errores;
}