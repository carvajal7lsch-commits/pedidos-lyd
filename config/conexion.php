<?php
// =============================================
// config/conexion.php
// =============================================
// INSTRUCCIONES:
// 1. Copia este archivo como "conexion.php" en la misma carpeta
// 2. Rellena los datos de tu base de datos
// 3. NUNCA subas conexion.php al repositorio
// =============================================

function conexion() {
    $host = 'localhost';       // Host de la base de datos
    $user = 'root';            // Usuario de MySQL
    $pass = '';                // Contraseña de MySQL
    $db   = 'pedidos_lyd';     // Nombre de la base de datos

    return mysqli_connect($host, $user, $pass, $db);
}

$conexion = conexion();

if (!$conexion) {
    die('❌ Error de conexión: ' . mysqli_connect_error());
}