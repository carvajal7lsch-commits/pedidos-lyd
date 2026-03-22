<?php
// =============================================
// vendedor/ubicacion.php
// Recibe lat/lng del vendedor y lo guarda en BD
// Llamado en segundo plano desde el dashboard
// =============================================
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json');

$lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;
$id  = (int) $_SESSION['id_usuario'];

if (!$lat || !$lng) {
    echo json_encode(['ok' => false, 'msg' => 'Coordenadas inválidas']);
    exit();
}

// UPSERT — si ya existe actualiza, si no inserta
$stmt = mysqli_prepare($conexion,
    "INSERT INTO ubicacion_vendedor (id_vendedor, latitud, longitud, actualizado_en)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
         latitud        = VALUES(latitud),
         longitud       = VALUES(longitud),
         actualizado_en = NOW()"
);
mysqli_stmt_bind_param($stmt, 'idd', $id, $lat, $lng);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar']);
}