<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';

// ── Métricas ──────────────────────────────────────────────
$total_clientes  = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM Cliente"))[0]            ?? 0;
$activos_cli     = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM Cliente WHERE estado=1"))[0] ?? 0;
$inactivos_cli   = $total_clientes - $activos_cli;
$total_productos = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM Productos"))[0]           ?? 0;
$activos_prod    = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM Productos WHERE estado=1"))[0] ?? 0;
$total_categ     = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM Categorias WHERE estado=1"))[0] ?? 0;
$total_usuarios  = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM Usuario"))[0]             ?? 0;

$pct_activos  = $total_clientes > 0 ? round(($activos_cli / $total_clientes) * 100) : 0;
// Ring r=54 → C = 2π×54 ≈ 339
$ring_offset  = round(339 - (339 * $pct_activos / 100));

// ── Actividad reciente ────────────────────────────────────
$actividad = [];
$r1 = mysqli_query($conexion,
    "SELECT id_cliente AS id, nombre, 'tl-cli' AS tipo FROM Cliente ORDER BY id_cliente DESC LIMIT 4");
while ($r = mysqli_fetch_assoc($r1)) $actividad[] = $r;

$r2 = mysqli_query($conexion,
    "SELECT id_producto AS id, nombre, 'tl-prod' AS tipo FROM Productos ORDER BY id_producto DESC LIMIT 4");
while ($r = mysqli_fetch_assoc($r2)) $actividad[] = $r;

usort($actividad, fn($a,$b) => $b['id'] - $a['id']);
$actividad = array_slice($actividad, 0, 6);

// ── Sesión ────────────────────────────────────────────────
$admin_nombre   = $_SESSION['nombre'] ?? 'Administrador';
$dia_semana     = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'][date('w')];
$fecha_hoy      = $dia_semana . ', ' . date('j') . ' de ' . [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
][(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Pedidos LYD</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/camion.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>

<body>
<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<div class="dash-wrapper">

    <!-- ══════════════════════════════════════════════════════
         HERO BANNER — indigo gradient + CSS truck
    ══════════════════════════════════════════════════════════ -->
    <div class="hero-banner">

        <!-- ambient orbs -->
        <div class="hero-orb-1"></div>
        <div class="hero-orb-2"></div>

        <!-- left: text -->
        <div class="hero-text">
            <div class="hero-date"><?php echo $fecha_hoy; ?></div>
            <div class="hero-title">Resumen del Panel</div>
            <div class="hero-sub">
                Bienvenido, <strong><?php echo htmlspecialchars($admin_nombre); ?></strong> &nbsp;·&nbsp;
                <span class="hero-chip ok">
                    <i class="bi bi-check-circle-fill"></i>
                    <?php echo $activos_cli; ?> clientes activos
                </span>
                <?php if ($inactivos_cli > 0): ?>
                <span class="hero-chip warn">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?php echo $inactivos_cli; ?> inactivos
                </span>
                <?php endif; ?>
                <span class="hero-chip">
                    <i class="bi bi-box-seam-fill"></i>
                    <?php echo $activos_prod; ?> productos activos
                </span>
            </div>
        </div>

        <!-- right: CSS Truck -->
        <div class="hero-truck-zone">
            <div class="d-truck-scene">

                <!-- shadow -->
                <div class="d-truck-shadow"></div>

                <!-- cargo box -->
                <div class="d-cargo">
                    <div class="d-cargo-logo">LYD</div>
                    <!-- <div class="d-cargo-stripe"></div> -->
                </div>

                <!-- cab -->
                <div class="d-cab">
                    <div class="d-headlight"></div>
                    <div class="d-grille"></div>
                    <div class="d-mirror"></div>
                </div>

                <!-- chassis -->
                <div class="d-chassis"></div>

                <!-- axles -->
                <div class="d-axle d-axle-1"></div>
                <div class="d-axle d-axle-2"></div>
                <div class="d-axle d-axle-3"></div>

                <!-- wheels -->
                <div class="d-wheel d-wheel-1"></div>
                <div class="d-wheel d-wheel-2"></div>
                <div class="d-wheel-2-inner"></div>
                <div class="d-wheel d-wheel-3"></div>

                <!-- road -->
                <div class="d-road"></div>

                <!-- speed lines -->
                <div class="d-speed">
                    <span></span><span></span><span></span><span></span>
                </div>

            </div>
        </div>
    </div><!-- /hero-banner -->


    <!-- ══════════════════════════════════════════════════════
         KPI ROW — 5 metric cards
    ══════════════════════════════════════════════════════════ -->
    <div class="cont-consultas">

        <div class="card color">
            <div class="kpi-top">
                <i class="bi bi-people-fill kpi-icon color"></i>
                <span class="kpi-trend pos"><i class="bi bi-arrow-up-short"></i> total</span>
            </div>
            <div class="kpi-num" data-target="<?php echo $total_clientes; ?>">0</div>
            <div class="kpi-label">Clientes</div>
        </div>

        <div class="card color">
            <div class="kpi-top">
                <i class="bi bi-person-check-fill kpi-icon color"></i>
                <span class="kpi-trend pos"><i class="bi bi-check2"></i> activos</span>
            </div>
            <div class="kpi-num" data-target="<?php echo $activos_cli; ?>">0</div>
            <div class="kpi-label">Cli. Activos</div>
        </div>

        <div class="card color">
            <div class="kpi-top">
                <i class="bi bi-bag-fill kpi-icon color"></i>
                <span class="kpi-trend neu"><i class="bi bi-dash"></i> catálogo</span>
            </div>
            <div class="kpi-num" data-target="<?php echo $total_productos; ?>">0</div>
            <div class="kpi-label">Productos</div>
        </div>

        <div class="card color">
            <div class="kpi-top">
                <i class="bi bi-bookmarks-fill kpi-icon color"></i>
                <span class="kpi-trend pos"><i class="bi bi-check2"></i> OK</span>
            </div>
            <div class="kpi-num" data-target="<?php echo $total_categ; ?>">0</div>
            <div class="kpi-label">Categorías</div>
        </div>

        <div class="card color">
            <div class="kpi-top">
                <i class="bi bi-person-fill kpi-icon color"></i>
                <span class="kpi-trend neu"><i class="bi bi-person"></i> staff</span>
            </div>
            <div class="kpi-num" data-target="<?php echo $total_usuarios; ?>">0</div>
            <div class="kpi-label">Usuarios</div>
        </div>

    </div><!-- /kpi-row -->


    <!-- ══════════════════════════════════════════════════════
         CONTENEDOR DE LAS CAJAS ABAJO DEL CAMION DASHBOARD
    ══════════════════════════════════════════════════════════ -->
    <div class="content-row">

        <!-- Mapa de vendedores en tiempo real -->
        <div class="dash-card dash-card-map">
            <div class="dc-header">
                <div class="dc-title"><i class="bi bi-geo-alt-fill"></i>Ubicación de Vendedores</div>
                <span class="dc-badge" id="mapaBadge">Cargando...</span>
            </div>
            <div id="mapaVendedores" style="width:100%;height:320px;border-radius:12px;overflow:hidden;"></div>
            <div class="mapa-leyenda" id="mapaLeyenda"></div>
        </div>

        <!-- Ring — % activos -->
        <div class="dash-card">
            <div class="dc-header">
                <div class="dc-title"><i class="bi bi-pie-chart-fill"></i>Tasa de Actividad</div>
            </div>
            <div class="ring-box">
                <div class="ring-label-top">Clientes activos vs total</div>
                <div class="rw">
                    <svg viewBox="0 0 120 120">
                        <defs>
                            <linearGradient id="rg1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%"   stop-color="#4f46e5"/>
                                <stop offset="100%" stop-color="#3b82f6"/>
                            </linearGradient>
                        </defs>
                        <circle class="rw-track" cx="60" cy="60" r="54"/>
                        <circle class="rw-fill"  cx="60" cy="60" r="54"
                                id="ringFill"
                                style="stroke-dashoffset:<?php echo $ring_offset; ?>"/>
                    </svg>
                    <div class="rw-center">
                        <span class="rw-pct" id="ringPct" data-target="<?php echo $pct_activos; ?>">0%</span>
                        <span class="rw-sub">activos</span>
                    </div>
                </div>
                <div class="ring-legend">
                    <div class="ring-leg-item">
                        <span><span class="ring-leg-dot" style="background:#4f46e5"></span>Activos</span>
                        <span class="ring-leg-val"><?php echo $activos_cli; ?></span>
                    </div>
                    <div class="ring-leg-item">
                        <span><span class="ring-leg-dot" style="background:#e2e8f0"></span>Inactivos</span>
                        <span class="ring-leg-val"><?php echo $inactivos_cli; ?></span>
                    </div>
                    <div class="ring-leg-item">
                        <span><span class="ring-leg-dot" style="background:#10b981"></span>Total</span>
                        <span class="ring-leg-val"><?php echo $total_clientes; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="dash-card">
            <div class="dc-header">
                <div class="dc-title"><i class="bi bi-grid-3x3-gap-fill"></i>Accesos Rápidos</div>
            </div>
            <div class="ql-grid">
                <a href="<?php echo BASE_URL; ?>public/admin/productos.php" class="ql-btn">
                    <i class="bi bi-bag-fill qlb-icon"></i>
                    <span class="qlb-label">Productos</span>
                    <span class="qlb-desc">Gestionar catálogo</span>
                </a>
                <a href="<?php echo BASE_URL; ?>public/admin/categorias.php" class="ql-btn v-green">
                    <i class="bi bi-bookmarks-fill qlb-icon"></i>
                    <span class="qlb-label">Categorías</span>
                    <span class="qlb-desc">Organizar grupos</span>
                </a>
                <a href="<?php echo BASE_URL; ?>public/admin/clientes.php" class="ql-btn v-purple">
                    <i class="bi bi-people-fill qlb-icon"></i>
                    <span class="qlb-label">Clientes</span>
                    <span class="qlb-desc">Ver y editar</span>
                </a>
                <a href="<?php echo BASE_URL; ?>public/admin/vendedores.php" class="ql-btn v-orange">
                    <i class="bi bi-person-badge-fill qlb-icon"></i>
                    <span class="qlb-label">Vendedores</span>
                    <span class="qlb-desc">Gestionar equipo</span>
                </a>
            </div>
        </div>

    </div><!-- /content-row -->

</div><!-- /dash-wrapper -->


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Count-up animado ─────────────────────────────────────
function countUp(el, target, dur = 1300, suffix = '') {
    const t0 = performance.now();
    (function frame(now) {
        const p = Math.min((now - t0) / dur, 1);
        const e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(e * target) + suffix;
        if (p < 1) requestAnimationFrame(frame);
    })(t0);
}

document.addEventListener('DOMContentLoaded', () => {
    // KPI numbers
    document.querySelectorAll('.kpi-num[data-target]').forEach(el => {
        countUp(el, parseInt(el.dataset.target), 1200);
    });

    // Ring pct
    const rp = document.getElementById('ringPct');
    if (rp) countUp(rp, parseInt(rp.dataset.target), 1400, '%');

    // Ring SVG fill
    const rf = document.getElementById('ringFill');
    if (rf) {
        const target = parseFloat(rf.style.strokeDashoffset);
        rf.style.strokeDashoffset = '339';
        setTimeout(() => {
            rf.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(.4,0,.2,1)';
            rf.style.strokeDashoffset = target;
        }, 250);
    }

    // ── Mapa de vendedores ──────────────────────────────
    const colores = ['#4f46e5','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];
    const mapa = L.map('mapaVendedores', { zoomControl: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19
    }).addTo(mapa);

    // Colombia centro como fallback
    mapa.setView([4.5709, -74.2973], 7);

    const markers = {};

    function crearIcono(nombre, color) {
        const inicial = nombre.charAt(0).toUpperCase();
        return L.divIcon({
            className: '',
            html: `<div style="
                background:${color};
                color:#fff;
                width:36px;height:36px;
                border-radius:50% 50% 50% 0;
                transform:rotate(-45deg);
                border:3px solid #fff;
                box-shadow:0 2px 8px rgba(0,0,0,0.3);
                display:flex;align-items:center;justify-content:center;">
                <span style="transform:rotate(45deg);font-weight:700;font-size:14px;">${inicial}</span>
            </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36],
            popupAnchor: [0, -36]
        });
    }

    function tiempoRelativo(fechaStr) {
        const diff = Math.floor((Date.now() - new Date(fechaStr)) / 1000);
        if (diff < 60)  return 'Hace ' + diff + 's';
        if (diff < 3600) return 'Hace ' + Math.floor(diff/60) + 'min';
        return 'Hace ' + Math.floor(diff/3600) + 'h';
    }

    function actualizarMapa() {
        fetch('api_ubicaciones.php')
            .then(r => r.json())
            .then(vendedores => {
                const badge = document.getElementById('mapaBadge');
                const leyenda = document.getElementById('mapaLeyenda');

                if (vendedores.length === 0) {
                    badge.textContent = 'Sin vendedores activos';
                    leyenda.innerHTML = '<p style="color:#94a3b8;font-size:13px;text-align:center;padding:8px 0;">Ningún vendedor ha compartido su ubicación aún.</p>';
                    return;
                }

                badge.textContent = vendedores.length + ' en ruta';
                const bounds = [];
                let leyendaHtml = '';

                vendedores.forEach((v, i) => {
                    const color = colores[i % colores.length];
                    const lat   = parseFloat(v.latitud);
                    const lng   = parseFloat(v.longitud);
                    const popup = `<b>${v.nombre}</b><br><small>${tiempoRelativo(v.actualizado_en)}</small>`;

                    if (markers[v.id_usuario]) {
                        markers[v.id_usuario].setLatLng([lat, lng]).setPopupContent(popup);
                    } else {
                        markers[v.id_usuario] = L.marker([lat, lng], { icon: crearIcono(v.nombre, color) })
                            .addTo(mapa)
                            .bindPopup(popup);
                    }

                    bounds.push([lat, lng]);
                    leyendaHtml += `
                        <div class="mapa-leyenda-item">
                            <span class="mapa-leyenda-dot" style="background:${color}"></span>
                            <span class="mapa-leyenda-nombre">${v.nombre}</span>
                            <span class="mapa-leyenda-tiempo">${tiempoRelativo(v.actualizado_en)}</span>
                        </div>`;
                });

                leyenda.innerHTML = leyendaHtml;

                if (bounds.length === 1) {
                    mapa.setView(bounds[0], 14);
                } else if (bounds.length > 1) {
                    mapa.fitBounds(bounds, { padding: [30, 30] });
                }
            })
            .catch(() => {
                document.getElementById('mapaBadge').textContent = 'Error al cargar';
            });
    }

    actualizarMapa();
    setInterval(actualizarMapa, 30000); // refresca cada 30s
});
</script>
</body>
</html>