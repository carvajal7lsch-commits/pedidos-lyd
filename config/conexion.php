<?php
// =============================================
// config/conexion.php
// =============================================
// INSTRUCCIONES:
// 1. Rellena los datos de tu base de datos en el archivo .env
// 2. NUNCA subas el archivo .env al repositorio
// =============================================

require_once __DIR__ . '/env.php';

// Intentar cargar .env desde la raíz del proyecto
loadEnv(__DIR__ . '/../.env');

// Habilitar el reporte estricto de errores de MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function conexion() {
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
    $db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'pedidos_lyd';

    try {
        $con = mysqli_connect($host, $user, $pass, $db);
        mysqli_set_charset($con, "utf8mb4");
        return $con;
    } catch (mysqli_sql_exception $e) {
        // En producción, es recomendable loguear $e->getMessage() y mostrar un mensaje genérico.
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        die('❌ Error crítico del sistema. Por favor, contacta al administrador.');
    }
}

try {
    $conexion = conexion();
} catch (Exception $e) {
    error_log($e->getMessage());
    die('❌ Error crítico del sistema. Por favor, contacta al administrador.');
}