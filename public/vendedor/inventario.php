<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloVendedor();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

$id_vendedor = $_SESSION['id_usuario'];
$hoy         = date('Y-m-d');

// ── Inventario del camión hoy ────────────────
$inventario = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT p.nombre, p.imagen,
            ic.cantidad_cargada     AS ini,
            ic.cantidad_disponible  AS rest,
            (ic.cantidad_cargada - ic.cantidad_disponible) AS ven
     FROM inventariocamion ic
     JOIN productos p ON p.id_producto = ic.id_producto
     WHERE ic.id_vendedor = $id_vendedor
       AND ic.fecha_cargue = '$hoy'
       AND ic.estado = 1
     ORDER BY p.nombre ASC"
), MYSQLI_ASSOC);

// Totales resumen
$total_productos   = count($inventario);
$total_pacas_ini   = array_sum(array_column($inventario, 'ini'));
$total_pacas_rest  = array_sum(array_column($inventario, 'rest'));
$total_pacas_ven   = array_sum(array_column($inventario, 'ven'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="/public/manifest.json">
    <meta name="theme-color" content="#1855CF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LYD">
    <link rel="apple-touch-icon" href="/public/icons/icon-192x192.png">

    <title>Inventario — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/dashboard_vendedor.css">
    <link rel="stylesheet" href="../css/inventario.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<header class="topbar">
    <div class="topbar-left">
        <a href="dashboard.php" class="topbar-btn">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="topbar-titulo-icon">
            <i class="bi bi-truck-front-fill"></i>
        </div>
        <h1 class="page-title">Inventario</h1>
    </div>
</header>

<main class="scroll-body">

    <?php if (empty($inventario)): ?>
    <!-- Sin cargue hoy -->
    <div class="inv-sin-cargue">
        <i class="bi bi-truck-front"></i>
        <p>No hay cargue registrado hoy</p>
        <a href="cargue.php" class="btn-ir-cargue">Registrar Cargue</a>
    </div>

    <?php else: ?>

    <!-- Buscador -->
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="inputBuscar"
               placeholder="Buscar producto..."
               oninput="filtrar()">
    </div>

    <!-- Resumen -->
    <div class="inv-resumen-grid">
        <div class="inv-res-card">
            <div class="inv-res-val"><?php echo $total_productos; ?></div>
            <div class="inv-res-label">Productos</div>
        </div>
        <div class="inv-res-card">
            <div class="inv-res-val"><?php echo $total_pacas_ini; ?></div>
            <div class="inv-res-label">Cargadas</div>
        </div>
        <div class="inv-res-card">
            <div class="inv-res-val color-green"><?php echo $total_pacas_ven; ?></div>
            <div class="inv-res-label">Vendidas</div>
        </div>
        <div class="inv-res-card">
            <div class="inv-res-val color-blue"><?php echo $total_pacas_rest; ?></div>
            <div class="inv-res-label">Restantes</div>
        </div>
    </div>

    <!-- Tabla inventario -->
    <div class="inv-tabla-wrap">

        <!-- Header -->
        <div class="inv-tabla-header">
            <span class="inv-th inv-th-prod">PRODUCTO</span>
            <span class="inv-th">INI</span>
            <span class="inv-th">VEN</span>
            <span class="inv-th inv-th-rest">REST</span>
        </div>

        <!-- Filas -->
        <div id="invLista">
            <?php foreach ($inventario as $item):
                $agotado   = $item['rest'] == 0;
                $stock_bajo = !$agotado && $item['rest'] <= 3;
            ?>
            <div class="inv-fila <?php echo $agotado ? 'fila-agotado' : ($stock_bajo ? 'fila-bajo' : ''); ?>"
                 data-nombre="<?php echo strtolower(htmlspecialchars($item['nombre'])); ?>">

                <!-- Imagen + nombre -->
                <div class="inv-td inv-td-prod">
                    <div class="inv-img-wrap">
                        <?php if ($item['imagen']): ?>
                        <img src="../uploads/productos/<?php echo htmlspecialchars($item['imagen']); ?>"
                             class="inv-img" alt="">
                        <?php else: ?>
                        <div class="inv-img-placeholder"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </div>
                    <span class="inv-prod-nombre"><?php echo htmlspecialchars($item['nombre']); ?></span>
                </div>

                <!-- INI -->
                <div class="inv-td inv-td-num"><?php echo $item['ini']; ?></div>

                <!-- VEN -->
                <div class="inv-td inv-td-num"><?php echo $item['ven']; ?></div>

                <!-- REST -->
                <div class="inv-td inv-td-rest">
                    <?php if ($agotado): ?>
                        <span class="badge-agotado">AGOTADO</span>
                    <?php elseif ($stock_bajo): ?>
                        <span class="badge-bajo"><?php echo $item['rest']; ?></span>
                    <?php else: ?>
                        <strong class="rest-normal"><?php echo $item['rest']; ?></strong>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sin resultados -->
        <div class="inv-sin-resultados" id="sinResultados" style="display:none;">
            <i class="bi bi-search"></i>
            <span>Sin resultados</span>
        </div>

    </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<script>
function filtrar() {
    const q      = document.getElementById('inputBuscar').value.toLowerCase().trim();
    const filas  = document.querySelectorAll('.inv-fila');
    let visibles = 0;

    filas.forEach(fila => {
        const ok = !q || fila.dataset.nombre.includes(q);
        fila.style.display = ok ? '' : 'none';
        if (ok) visibles++;
    });

    document.getElementById('sinResultados').style.display =
        visibles === 0 ? 'flex' : 'none';
}
</script>


<script src="/public/js/db-vendedor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const n     = await DB.contarPendientes();
    const badge = document.getElementById('badge-pendientes');
    if (badge) { badge.textContent = n; badge.style.display = n > 0 ? 'inline-flex' : 'none'; }
    if (!navigator.onLine && document.querySelectorAll('.inv-fila').length === 0) {
        try {
            const inv = await DB.obtenerInventario();
            if (inv.length > 0) renderInventarioOffline(inv);
        } catch(e) {}
    }
});
function renderInventarioOffline(inventario) {
    const lista = document.getElementById('invLista');
    document.querySelector('.inv-sin-cargue') && (document.querySelector('.inv-sin-cargue').style.display='none');
    inventario.forEach(item => {
        const ini=item.cantidad_cargada||0, rest=item.cantidad_disponible||0, ven=Math.max(0,ini-rest);
        const agotado=rest===0, bajo=!agotado&&rest<=3;
        const fila=document.createElement('div');
        fila.className='inv-fila'+(agotado?' fila-agotado':(bajo?' fila-bajo':''));
        fila.dataset.nombre=(item.nombre||'').toLowerCase();
        const img=item.imagen?'<img src="../uploads/productos/'+item.imagen+'" class="inv-img">':'<div class="inv-img-placeholder"><i class="bi bi-image"></i></div>';
        const rb=agotado?'<span class="badge-agotado">AGOTADO</span>':(bajo?'<span class="badge-bajo">'+rest+'</span>':'<strong class="rest-normal">'+rest+'</strong>');
        fila.innerHTML='<div class="inv-td inv-td-prod"><div class="inv-img-wrap">'+img+'</div><span class="inv-prod-nombre">'+(item.nombre||'')+'</span></div><div class="inv-td inv-td-num">'+ini+'</div><div class="inv-td inv-td-num">'+ven+'</div><div class="inv-td inv-td-rest">'+rb+'</div>';
        lista.appendChild(fila);
    });
    const cards=document.querySelectorAll('.inv-res-val');
    const totIni=inventario.reduce((s,i)=>s+(i.cantidad_cargada||0),0);
    const totRest=inventario.reduce((s,i)=>s+(i.cantidad_disponible||0),0);
    if(cards[0])cards[0].textContent=inventario.length;
    if(cards[1])cards[1].textContent=totIni;
    if(cards[2])cards[2].textContent=Math.max(0,totIni-totRest);
    if(cards[3])cards[3].textContent=totRest;
}
</script>

</body>
</html>