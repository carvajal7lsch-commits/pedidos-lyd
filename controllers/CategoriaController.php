<?php
// =============================================
// controllers/CategoriaController.php
// Lógica del CRUD de Categorías
// =============================================

require_once __DIR__ . '/../models/Categoria.php';

// ---------------------------------------------
// Procesar creación
// ---------------------------------------------
function procesarCrearCategoria() {
    $errores = validarFormularioCategoria();
    if (!empty($errores)) {
        return ['error' => true, 'errores' => $errores];
    }

    $nombre = trim($_POST['nombre']);

    // Verificar nombre duplicado
    if (existeCategoria($nombre)) {
        return ['error' => true, 'errores' => ['Ya existe una categoría con ese nombre.']];
    }

    if (crearCategoria($nombre)) {
        return ['exito' => true, 'mensaje' => 'Categoría creada correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo guardar la categoría.']];
}

// ---------------------------------------------
// Procesar edición
// ---------------------------------------------
function procesarEditarCategoria($id) {
    $errores = validarFormularioCategoria();
    if (!empty($errores)) {
        return ['error' => true, 'errores' => $errores];
    }

    $nombre = trim($_POST['nombre']);

    // Verificar nombre duplicado excluyendo el actual
    if (existeCategoria($nombre, $id)) {
        return ['error' => true, 'errores' => ['Ya existe otra categoría con ese nombre.']];
    }

    if (editarCategoria($id, $nombre)) {
        return ['exito' => true, 'mensaje' => 'Categoría actualizada correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo actualizar la categoría.']];
}

// ---------------------------------------------
// Procesar desactivar
// ---------------------------------------------
function procesarDesactivarCategoria($id) {
    if (desactivarCategoria($id)) {
        return ['exito' => true, 'mensaje' => 'Categoría desactivada.'];
    }
    return ['error' => true, 'errores' => ['No se pudo desactivar la categoría.']];
}

// ---------------------------------------------
// Procesar reactivar
// ---------------------------------------------
function procesarReactivarCategoria($id) {
    if (reactivarCategoria($id)) {
        return ['exito' => true, 'mensaje' => 'Categoría reactivada.'];
    }
    return ['error' => true, 'errores' => ['No se pudo reactivar la categoría.']];
}

// ---------------------------------------------
// Validaciones del formulario
// ---------------------------------------------
function validarFormularioCategoria() {
    $errores = [];

    if (empty($_POST['nombre']) || trim($_POST['nombre']) === '') {
        $errores[] = 'El nombre de la categoría es obligatorio.';
    } elseif (strlen(trim($_POST['nombre'])) > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres.';
    }

    return $errores;
}