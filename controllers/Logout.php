<?php
// =============================================
// Cierra la sesión y redirige al login
// =============================================

require_once '../config/config.php';

session_start();
session_destroy();

header('Location: ' . BASE_URL . 'public/login.php');
exit();