<?php
// =============================================
// controllers/ProductoController.php
// =============================================

require_once __DIR__ . '/../models/Producto.php';

define('RUTA_IMAGENES', __DIR__ . '/../public/uploads/productos/');
define('URL_IMAGENES',  'uploads/productos/');

function procesarImagen($archivo) {
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    $max_peso = 2 * 1024 * 1024;

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No se pudo subir la imagen.'];
    }
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensiones_permitidas)) {
        return ['error' => 'Formato no permitido. Usa JPG, PNG o WEBP.'];
    }
    if ($archivo['size'] > $max_peso) {
        return ['error' => 'La imagen no puede superar 2MB.'];
    }
    $nombre_archivo = 'prod_' . time() . '_' . rand(100, 999) . '.' . $extension;
    if (!move_uploaded_file($archivo['tmp_name'], RUTA_IMAGENES . $nombre_archivo)) {
        return ['error' => 'Error al guardar la imagen en el servidor.'];
    }
    return ['ok' => true, 'nombre' => $nombre_archivo];
}

function procesarCrearProducto() {
    $errores = validarFormularioProducto();
    if (!empty($errores)) return ['error' => true, 'errores' => $errores];

    if (empty($_FILES['imagen']['name'])) {
        return ['error' => true, 'errores' => ['La imagen del producto es obligatoria.']];
    }
    $img = procesarImagen($_FILES['imagen']);
    if (isset($img['error'])) return ['error' => true, 'errores' => [$img['error']]];

    $id_categoria  = (int)   $_POST['id_categoria'];
    $nombre        = trim(   $_POST['nombre']);
    $precio        = (float) $_POST['precio'];

    if (crearProducto($id_categoria, $nombre, $precio, $img['nombre'])) {
        return ['exito' => true, 'mensaje' => 'Producto registrado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo guardar el producto.']];
}

function procesarEditarProducto($id) {
    $errores = validarFormularioProducto();
    if (!empty($errores)) return ['error' => true, 'errores' => $errores];

    $id_categoria  = (int)   $_POST['id_categoria'];
    $nombre        = trim(   $_POST['nombre']);
    $precio        = (float) $_POST['precio'];
    $imagen        = null;

    if (!empty($_FILES['imagen']['name'])) {
        $img = procesarImagen($_FILES['imagen']);
        if (isset($img['error'])) return ['error' => true, 'errores' => [$img['error']]];

        $producto_actual = obtenerProductoPorId($id);
        if ($producto_actual['imagen']) {
            $ruta_vieja = RUTA_IMAGENES . $producto_actual['imagen'];
            if (file_exists($ruta_vieja)) unlink($ruta_vieja);
        }
        $imagen = $img['nombre'];
    }

    if (editarProducto($id, $id_categoria, $nombre, $precio, $imagen)) {
        return ['exito' => true, 'mensaje' => 'Producto actualizado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo actualizar el producto.']];
}

function procesarDesactivarProducto($id) {
    if (desactivarProducto($id)) {
        return ['exito' => true, 'mensaje' => 'Producto desactivado. Ya no aparecerá en el cargue del camión.'];
    }
    return ['error' => true, 'errores' => ['No se pudo desactivar el producto.']];
}

function procesarReactivarProducto($id) {
    if (reactivarProducto($id)) {
        return ['exito' => true, 'mensaje' => 'Producto reactivado correctamente.'];
    }
    return ['error' => true, 'errores' => ['No se pudo reactivar el producto.']];
}

function validarFormularioProducto() {
    $errores = [];

    if (empty(trim($_POST['nombre'] ?? ''))) {
        $errores[] = 'El nombre del producto es obligatorio.';
    }
    if (empty($_POST['id_categoria'])) {
        $errores[] = 'Debes seleccionar una categoría.';
    }
    if (!isset($_POST['precio']) || !is_numeric($_POST['precio']) || (float)$_POST['precio'] < 0) {
        $errores[] = 'El precio no puede ser negativo ni estar vacío.';
    }

    return $errores;
}