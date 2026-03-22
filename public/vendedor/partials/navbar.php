<?php
// =============================================
// Menú reutilizable del panel vendedor (móvil)
// se incluye con:
// require_once __DIR__ . '/partials/navbar.php';
// =============================================

$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<head>
<link rel="stylesheet" href="/./public/css/navbar_vendedor.css">
<style>
.badge-offline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 99px;
    padding: 0 4px;
    position: absolute;
    top: 4px;
    right: 4px;
}
.vend-nav-item { position: relative; }
</style>
</head>


<nav class="vend-navbar">
    <a href="<?php echo BASE_URL; ?>public/vendedor/dashboard.php"
       class="vend-nav-item <?php echo $pagina_actual === 'dashboard.php' ? 'vend-nav-active' : 'vend-nav-inactive'; ?>">
        <i class="bi bi-house-fill"></i>
        <span class="vend-nav-label">Inicio</span>
        <span class="badge-offline" id="badge-pendientes" style="display:none;">0</span>
        <?php if ($pagina_actual === 'dashboard.php'): ?>
        <div class="vend-nav-pip"></div>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>public/vendedor/productos.php"
       class="vend-nav-item <?php echo $pagina_actual === 'productos.php' ? 'vend-nav-active' : 'vend-nav-inactive'; ?>">
        <i class="bi bi-box-seam"></i>
        <span class="vend-nav-label">Productos</span>
        <?php if ($pagina_actual === 'productos.php'): ?>
        <div class="vend-nav-pip"></div>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>public/vendedor/carga.php"
       class="vend-nav-item <?php echo $pagina_actual === 'carga.php' ? 'vend-nav-active' : 'vend-nav-inactive'; ?>">
        <i class="bi bi-layers-fill"></i>
        <span class="vend-nav-label">Carga</span>
        <?php if ($pagina_actual === 'carga.php'): ?>
        <div class="vend-nav-pip"></div>
        <?php endif; ?>
    </a>

    <a href="<?php echo BASE_URL; ?>controllers/logout.php"
       class="vend-nav-item vend-nav-inactive">
        <i class="bi bi-box-arrow-right"></i>
        <span class="vend-nav-label">Salir</span>
    </a>
</nav>