<?php
// Sin autenticación — esta página se sirve desde caché cuando no hay red
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sin conexión — Pedidos LYD</title>
    <link rel="stylesheet" href="/public/css/dashboard_vendedor.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f1f5f9;
            font-family: 'Sora', sans-serif;
            margin: 0;
            padding: 2rem;
            box-sizing: border-box;
            text-align: center;
        }
        .offline-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .offline-titulo {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .offline-sub {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .offline-btn {
            background: #1855CF;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.9rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .offline-badge {
            background: #fef3c7;
            color: #92400e;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            margin-top: 1.5rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="offline-icon">📡</div>
    <div class="offline-titulo">Sin conexión</div>
    <div class="offline-sub">
        No hay internet disponible y esta página<br>
        no está en caché todavía.
    </div>
    <a href="/public/vendedor/dashboard.php" class="offline-btn">
        Volver al inicio
    </a>
    <div class="offline-badge">
        💡 Visita el panel con internet primero para activar el modo offline
    </div>
</body>
</html>