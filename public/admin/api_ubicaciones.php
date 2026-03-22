<?php
// =============================================
// admin/api_ubicaciones.php
// Devuelve JSON con ubicación activa de vendedores
// (solo los actualizados en los últimos 60 minutos)
// =============================================
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json');

$resultado = mysqli_query($conexion,
    "SELECT u.id_usuario, u.nombre,
            uv.latitud, uv.longitud,
            uv.actualizado_en
     FROM ubicacion_vendedor uv
     JOIN usuario u ON u.id_usuario = uv.id_vendedor
     WHERE u.estado = 1
       AND uv.actualizado_en >= NOW() - INTERVAL 60 MINUTE
     ORDER BY uv.actualizado_en DESC"
);

$vendedores = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
echo json_encode($vendedores);