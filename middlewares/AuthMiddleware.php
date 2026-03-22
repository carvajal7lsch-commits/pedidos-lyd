<?php
// =============================================
// middlewares/AuthMiddleware.php
// Protege las páginas que requieren sesión
// Inclúyelo al inicio de cada página protegida
// =============================================
require_once __DIR__ . '/../config/config.php';

function verificarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['id_usuario'])) {
        header('Location: ' . BASE_URL . 'public/login.php');
        exit();
    }
}

function soloAdmin() {
    verificarSesion();

    if ($_SESSION['rol'] !== 'admin') {
        header('Location: ' . BASE_URL . 'public/vendedor/dashboard.php');
        exit();
    }
}

function soloVendedor() {
    verificarSesion();

    if ($_SESSION['rol'] !== 'vendedor') {
        header('Location: ' . BASE_URL . 'public/admin/dashboard.php');
        exit();
    }
}