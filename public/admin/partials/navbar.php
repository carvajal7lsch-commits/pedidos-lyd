<?php
// =============================================
// Menú reutilizable del panel admin
// se incluye con:  
// require_once __DIR__ . '/partials/navbar.php';
// =============================================

// Obtener la página actual para marcar el enlace activo
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

<head>
    <link rel="stylesheet" href="/public/css/navbar.css">
</head>

<script>
    function toggleSubmenu(event, el) {
        event.preventDefault();
        const li = el.closest('.desplegable');
        li.classList.toggle('abierto');
    }

    // Si la página actual es hija, abrir automáticamente
    document.addEventListener('DOMContentLoaded', () => {
        const abierto = document.querySelector('.desplegable.abierto');
        if (abierto) abierto.classList.add('abierto');
    });
</script>

<nav class="menu" id="menu">
    <div class="logo">
        <div class="favicon">
            <i class="bi bi-truck"></i>
        </div>
        <div class="letras">
            <h2 class="t1">Pedidos LYD</h2>
        </div>
    </div>

    <ul class="lista">
        <li>
            <a href="<?php echo BASE_URL; ?>public/admin/dashboard.php"
                class="a <?php echo $pagina_actual === 'dashboard.php' ? 'activo' : ''; ?>">
                <i class="bi bi-columns-gap"></i>
                <span>Inicio</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>public/admin/productos.php"
                class="a <?php echo $pagina_actual === 'productos.php' ? 'activo' : ''; ?>">
                <i class="bi bi-bag"></i>
                <span>Productos</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>public/admin/categorias.php"
                class="a <?php echo $pagina_actual === 'categorias.php' ? 'activo' : ''; ?>">
                <i class="bi bi-bookmarks"></i>
                <span>Categorías</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>public/admin/reportes.php"
                class="a <?php echo $pagina_actual === 'reportes.php' ? 'activo' : ''; ?>">
                <i class="bi bi-bar-chart"></i>
                <span>Reportes</span>
            </a>
        </li>
        <li
            class="desplegable <?php echo in_array($pagina_actual, ['clientes.php', 'vendedores.php']) ? 'abierto' : ''; ?>">
            <a href="#" class="a" onclick="toggleSubmenu(event, this)">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
                <i class="bi bi-chevron-down flecha"></i>
            </a>
            <ul class="submenu">
                <li>
                    <a href="<?php echo BASE_URL; ?>public/admin/clientes.php" class="sub-a">
                        Clientes
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>public/admin/vendedores.php" class="sub-a">
                        Vendedores
                    </a>
                </li>
            </ul>
        </li>
    </ul>
    </li>
    </ul>

    <div class="salir">
        <a href="<?php echo BASE_URL; ?>controllers/logout.php" class="logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</nav>