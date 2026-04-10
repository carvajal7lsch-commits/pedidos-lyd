<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/config.php';

//Si ya está logueado, redirigir directo
session_start();
if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'admin') {
        header('Location: ' . BASE_URL . 'public/admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'public/vendedor/dashboard.php');
    }
    exit();
}

// Procesar el formulario cuando se envía
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../controllers/UsuarioController.php';
    $resultado = login(
        trim($_POST['usuario']     ?? ''),
        trim($_POST['contrasena'] ?? '')
    );
    // Si llega aquí es porque hubo error (si no, ya redirigió)
    if (isset($resultado['error'])) {
        $error = $resultado['mensaje'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <link rel="manifest" href="./manifest.json">
    <meta name="theme-color" content="#1855CF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LYD">
    <link rel="apple-touch-icon" href="./icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos LYD</title>
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="./css/camion_login.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
        rel="stylesheet">
</head>


<body>
    <canvas id="antigravity-bg"></canvas>

    <div class="cont2">
        <div class="video">
            <div class="section">
                <!-- Canvas 3D — ocupa toda la burbuja, recortado por border-radius:50% del CSS -->
                <canvas id="truck-canvas"></canvas>
                <div class="truck-scene">
                    <!-- Speed lines left of truck -->
                    <div class="speed-lines">
                        <span></span><span></span><span></span>
                    </div>

                    <!-- Cargo box -->
                    <div class="truck-cargo">
                        <div class="cargo-logo">LYD</div>
                    </div>

                    <!-- Cab -->
                    <div class="truck-cab"></div>

                    <!-- Chassis -->
                    <div class="truck-chassis"></div>

                    <!-- Wheels (circle motif) -->
                    <div class="wheel wheel-1"></div>
                    <div class="wheel wheel-2"></div>
                    <div class="wheel wheel-3"></div>

                    <!-- Road -->
                    <div class="road"></div>
                </div>

                <!-- Texto "Pedidos / LYD" flota encima en la parte baja -->
                <div class="sistema">
                    <h1 class="title3">Pedidos</h1>
                    <h2 class="title2">LYD</h2>
                </div>
            </div>
            <div class="blob"></div>
            <div class="blob-2"></div>
            <!-- <div class="blop-3"></div> -->
        </div>
    </div>

    <div class="cont1">
        <div class="card-login" id="cardLogin">

            <div class="status-bar" id="statusBar"></div>

            <div class="head">
                <div class="card">
                    <div class="icon">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
                <div class="saludo">
                    <h2>Bienvenido</h2>
                    <p>Ingresa tus credenciales</p>
                </div>
            </div>

            <form method="post" id="loginForm" novalidate>

                <?php if (!empty($error)): ?>
                <div class="alerta-error" id="alertaError">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="alerta-error" id="alertaJS" style="display:none;">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span id="alertaMsg"></span>
                </div>

                <div class="inputs">
                    <div class="user field-wrap" id="wrapUsuario">
                        <label for="usuario">Usuario</label>
                        <div class="input-wrap">
                            <i class="bi bi-person-fill"></i>
                            <input type="text" id="usuario" placeholder="Ingresa Usuario" name="usuario"
                                autocomplete="username" required>
                        </div>
                        <span class="field-msg" id="msgUsuario"></span>
                    </div>

                    <div class="clave field-wrap" id="wrapClave">
                        <label for="contrasena">Contraseña</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="contrasena" placeholder="• • • • • • • •" name="contrasena"
                                autocomplete="current-password" required>
                            <button type="button" class="eye-btn" id="eyeBtn" tabindex="-1"
                                aria-label="Mostrar contraseña">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <span class="field-msg" id="msgClave"></span>
                    </div>
                </div>

                <div class="btn">
                    <button class="btn_iniciar" type="submit" id="btnSubmit">
                        Ingresar <i class="bi bi-box-arrow-right"></i>
                    </button>
                </div>

            </form>

            <div class="footer">
                <p>pedidos LYD</p>
            </div>
        </div>
    </div>

    <script src="./js/validacion_login.js"></script>

    <script>
        (function () {

            const canvas = document.getElementById("antigravity-bg");
            const ctx = canvas.getContext("2d");

            let W, H;
            let mouse = { x: -9999, y: -9999 };
            let particles = [];

            // Paleta de colores
            const COLORS = [
                "#00000",
            ];

            // Configuración — menos partículas en móvil para ahorrar batería
            const COUNT = window.innerWidth <= 480 ? 60 : 320;
            const MOUSE_RADIUS = 130;
            const MOUSE_FORCE = 6.8;
            const RETURN_SPEED = 0.055;
            const FRICTION = 0.88;

            // Resize canvas
            function resize() {
                W = canvas.width = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }

            window.addEventListener("resize", () => {
                resize();
                init();
            });

            resize();

            // Crear partícula
            function createParticle() {

                const ox = Math.random() * W;
                const oy = Math.random() * H;

                const type = Math.random();

                const len = type < 0.55
                    ? Math.random() * 3 + 1.2
                    : Math.random() * 14 + 5;

                const angle = Math.random() * Math.PI * 2;

                const finalColor = Math.random() < 0.7
                    ? COLORS[Math.floor(Math.random() * 8)]
                    : COLORS[8 + Math.floor(Math.random() * 4)];

                return {
                    ox: ox,
                    oy: oy,

                    x: ox,
                    y: oy,

                    vx: 0,
                    vy: 0,

                    len: len,
                    angle: angle,

                    color: finalColor,
                    alpha: Math.random() * 0.55 + 0.25,
                    width: type < 0.55 ? len : Math.random() * 1.8 + 0.8
                };
            }

            // Inicializar partículas
            function init() {
                particles = Array.from({ length: COUNT }, createParticle);
            }

            init();

            // Mouse move
            window.addEventListener("mousemove", e => {
                mouse.x = e.clientX;
                mouse.y = e.clientY;
            });

            // Cuando el mouse sale
            window.addEventListener("mouseleave", () => {
                mouse.x = -9999;
                mouse.y = -9999;
            });

            // Actualizar partícula
            function update(p) {

                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;

                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < MOUSE_RADIUS && dist > 0.5) {

                    const force = (MOUSE_RADIUS - dist) / MOUSE_RADIUS;
                    const angle = Math.atan2(dy, dx);

                    p.vx += Math.cos(angle) * force * MOUSE_FORCE;
                    p.vy += Math.sin(angle) * force * MOUSE_FORCE;
                }

                // volver al origen
                p.vx += (p.ox - p.x) * RETURN_SPEED;
                p.vy += (p.oy - p.y) * RETURN_SPEED;

                // fricción
                p.vx *= FRICTION;
                p.vy *= FRICTION;

                p.x += p.vx;
                p.y += p.vy;

                const speed = Math.sqrt(p.vx * p.vx + p.vy * p.vy);

                if (speed > 0.1) {
                    p.angle += (Math.atan2(p.vy, p.vx) - p.angle) * 0.12;
                }
            }

            // Dibujar partícula
            function draw(p) {

                ctx.save();

                ctx.translate(p.x, p.y);
                ctx.rotate(p.angle);

                ctx.globalAlpha = p.alpha;
                ctx.fillStyle = p.color;

                // rectángulo pequeño tipo "dash"
                ctx.fillRect(
                    -p.len / 2,
                    -p.width / 2,
                    p.len,
                    p.width
                );

                ctx.restore();
            }

            // Loop animación
            function loop() {

                ctx.clearRect(0, 0, W, H);

                particles.forEach(p => {
                    update(p);
                    draw(p);
                });

                requestAnimationFrame(loop);
            }

            loop();

        })();
    </script>


</body>

</html>