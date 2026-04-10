<?php
// =============================================
// Variables globales del sistema
// =============================================

// Zona horaria Colombia (UTC-5)
date_default_timezone_set('America/Bogota');

// URL base del proyecto - detecta automáticamente host y subcarpeta
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'];
$base_dir = str_replace(basename($script), '', $script);
// Asegurarnos de que termine en /public/ para las redirecciones si es necesario, 
// o simplemente la raíz del proyecto.
define('BASE_URL', $protocol . '://' . $host . $base_dir . '../');

// Nombre del sistema
define('APP_NAME', 'Pedidos LYD');

// Datos de la empresa (para comprobantes)
define('EMPRESA_NOMBRE', 'Deposito LYD');
define('EMPRESA_NIT',    '900.123.456-7');

// =============================================
// Helper de fecha/hora en español (Colombia)
// =============================================

/**
 * Devuelve fecha/hora formateada en español.
 *
 * Tokens soportados:
 *   d  -> día con cero (01-31)
 *   j  -> día sin cero (1-31)
 *   F  -> mes completo (enero, febrero...)
 *   M  -> mes abreviado (ene, feb...)
 *   Y  -> año 4 dígitos
 *   g  -> hora 12h sin cero
 *   i  -> minutos con cero
 *   A  -> a.m. / p.m.
 *
 * Cualquier otro carácter se incluye literal.
 * Usa \X para escapar una letra y que salga literal (igual que date()).
 * $timestamp = 0 usa time() (ahora).
 */
function fecha_es(string $formato, int $timestamp = 0): string {
    if ($timestamp === 0) $timestamp = time();

    $meses = [
        1=>'enero',     2=>'febrero',   3=>'marzo',
        4=>'abril',     5=>'mayo',      6=>'junio',
        7=>'julio',     8=>'agosto',    9=>'septiembre',
        10=>'octubre',  11=>'noviembre', 12=>'diciembre',
    ];
    $meses_cortos = [
        1=>'ene', 2=>'feb', 3=>'mar', 4=>'abr',
        5=>'may', 6=>'jun', 7=>'jul', 8=>'ago',
        9=>'sep', 10=>'oct', 11=>'nov', 12=>'dic',
    ];

    $n    = (int) date('n', $timestamp);
    $map  = [
        'd' => str_pad((int) date('j', $timestamp), 2, '0', STR_PAD_LEFT),
        'j' => (int) date('j', $timestamp),
        'F' => $meses[$n],
        'M' => $meses_cortos[$n],
        'Y' => date('Y', $timestamp),
        'g' => date('g', $timestamp),
        'i' => date('i', $timestamp),
        'A' => (date('A', $timestamp) === 'AM') ? 'a.m.' : 'p.m.',
    ];

    $resultado = '';
    $len = strlen($formato);
    for ($k = 0; $k < $len; $k++) {
        $c = $formato[$k];
        if ($c === '\\' && $k + 1 < $len) {
            $resultado .= $formato[++$k]; // carácter escapado, sale literal
        } elseif (isset($map[$c])) {
            $resultado .= $map[$c];
        } else {
            $resultado .= $c;
        }
    }

    return $resultado;
}