# 🚛 Pedidos LYD

Sistema de gestión de pedidos y ventas para **Depósito LYD**, diseñado para vendedores que trabajan rutas de distribución desde un camión.

---

## ✨ Características principales

### Panel Administrador
- Dashboard con métricas del negocio y mapa de ubicación de vendedores en tiempo real
- Gestión de productos con imágenes, categorías y control de estado activo/inactivo
- Gestión de clientes con etiquetado automático (VIP, Frecuente, Con deuda, Inactivo, Nuevo)
- Gestión de vendedores
- Filtros por estado activo por defecto en todos los módulos

### Panel Vendedor (mobile-first)
- Cargue diario del camión con inventario por producto
- Catálogo de productos con stock disponible en tiempo real
- Carrito de compras con soporte para ventas de **contado** y **crédito**
- Registro de abonos a ventas a crédito
- Comprobante de venta con descarga en PDF y opción de compartir
- Historial de facturas del día
- Vista de inventario del camión (cargadas / vendidas / restantes)
- Detalle de cliente con historial, saldo pendiente y producto favorito
- Cierre de jornada con resumen contado/crédito
- Tracking de ubicación GPS en tiempo real

### Modo Online (ahora obligatorio)
- La aplicación requiere conexión a Internet para todas las operaciones.
- No se utiliza Service Worker ni IndexedDB para datos de ventas.
- Sincronización offline y reporte de pendientes se han deshabilitado.

### PWA (Progressive Web App)
- Instalable en Android desde Chrome ("Agregar a pantalla de inicio")
- Modo pantalla completa sin barra del navegador
- Ícono personalizado del camión en la pantalla de inicio

 **Nota:** La aplicación funciona 100% online. El PWA es solo para instalación y experiencia de usuario, no incluye funcionalidades offline.

---

## 🏗️ Estructura del proyecto

```
Pedidos_LYD/
├── config/
│   ├── conexion.php          # Credenciales BD (NO incluido en el repo)
│   ├── conexion.example.php  # Plantilla de conexión
│   └── config.php            # Constantes globales + helper fecha_es()
│
├── controllers/              # Lógica de negocio
│   ├── CategoriaController.php
│   ├── ClienteController.php
│   ├── ProductoController.php
│   ├── UsuarioController.php
│   └── Logout.php
│
├── models/                   # Consultas a la base de datos
│   ├── Producto.php
│   ├── categoria.php
│   ├── cliente.php
│   └── Usuario.php
│
├── middlewares/
│   └── AuthMiddleware.php    # Protección de rutas por rol
│
├── database/
│   └── BD                    # Dump SQL completo con datos de ejemplo
│
└── public/
    ├── login.php
    ├── manifest.json         # Configuración PWA
    ├── icons/                # Íconos PWA (72px → 512px)
    ├── css/                  # Estilos por módulo
    ├── js/
    │   ├── db-vendedor.js    # Capa IndexedDB para modo offline
    │   └── validacion_login.js
    ├── uploads/
    │   └── productos/        # Imágenes subidas de productos
    ├── admin/                # Panel administrador
    │   ├── dashboard.php
    │   ├── productos.php
    │   ├── categorias.php
    │   ├── clientes.php
    │   ├── vendedores.php
    │   └── partials/navbar.php
    └── vendedor/             # Panel vendedor
        ├── dashboard.php
        ├── carga.php
        ├── productos.php
        ├── clientes.php
        ├── carrito.php
        ├── comprobante.php
        ├── facturas.php
        ├── inventario.php
        ├── cierre.php
        ├── detalle_cliente.php
        ├── detalle_factura.php
        ├── sync.php          # Endpoint de sincronización offline
        ├── sw-vendedor.js    # Service Worker
        ├── offline.php       # Página fallback sin conexión
        └── partials/navbar.php
```

---

## 🗄️ Base de datos

**Motor:** MySQL / MariaDB  
**Nombre:** `pedidos_lyd`

### Tablas

| Tabla | Descripción |
|---|---|
| `usuario` | Admins y vendedores (roles: `admin` / `vendedor`) |
| `cliente` | Clientes del depósito |
| `categorias` | Categorías de productos |
| `productos` | Catálogo con precio e imagen |
| `inventariocamion` | Cargue diario por vendedor |
| `venta` | Ventas (contado o crédito) |
| `detalle_venta` | Líneas de cada venta |
| `abono` | Pagos parciales a ventas en crédito |
| `cierrediario` | Cierre de jornada por vendedor |
| `ubicacion_vendedor` | GPS en tiempo real del vendedor |

El dump completo con estructura y datos de ejemplo está en `database/BD`.

---

## 🚀 Instalación local (XAMPP)

### Requisitos
- XAMPP con PHP 8.x y MySQL/MariaDB
- Navegador moderno (Chrome recomendado para PWA)

### Pasos

**1. Clonar el repositorio**
```bash
git clone https://github.com/tu-usuario/pedidos-lyd.git
```

**2. Mover al directorio de XAMPP**
```
Copiar la carpeta Pedidos_LYD/ dentro de htdocs/
```

**3. Configurar la base de datos**
```bash
# Crear la base de datos en phpMyAdmin o desde MySQL:
CREATE DATABASE pedidos_lyd;

# Importar el dump:
mysql -u root pedidos_lyd < database/BD
```

**4. Configurar la conexión**
```bash
# Copiar la plantilla
cp config/conexion.example.php config/conexion.php

# Editar con tus credenciales si es necesario
# Por defecto usa root sin contraseña (XAMPP estándar)
```

**5. Crear carpeta de uploads**
```
Verificar que existe: public/uploads/productos/
Si no existe, crearla manualmente.
```

**6. Acceder al sistema**
```
http://localhost/Pedidos_LYD/public/login.php
```

### Credenciales de ejemplo
| Rol | Correo | Contraseña |
|---|---|---|
| Admin | admin@gmail.com | 12345 |
| Vendedor | manuelpan@gmail.com | 12345 |

> ⚠️ Cambiar las contraseñas antes de pasar a producción.

---

## 📱 PWA e instalación móvil

1. Abrir el panel del vendedor en **Chrome para Android**
2. Chrome mostrará el banner "Agregar a pantalla de inicio"
3. También disponible en: menú ⋮ → "Instalar aplicación"

> **Nota:** El PWA requiere **HTTPS** en producción. En localhost funcionan sin configuración adicional.

---

## 🛠️ Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.x (sin framework, patrón MVC manual) |
| Base de datos | MySQL / MariaDB via `mysqli` |
| Frontend | HTML5 + CSS3 + JS vanilla |
| PWA | Web App Manifest |
| PDF | jsPDF |
| Mapas | Leaflet.js |
| Íconos | Bootstrap Icons |
| Fuentes | Sora + DM Sans (vendedor) / Inter (admin) |

---

## 📋 Changelog

## v2.0.0 — 2026-03-25
- 🔴 Cambios importantes
- Eliminación completa del modo offline
- Eliminados Service Worker, IndexedDB y sincronización local
- Sistema ahora funciona 100% online
- ✅ Mejoras
- Simplificación de la arquitectura
- Mejor rendimiento general
- Reducción de complejidad en frontend

### v1.0.0 — 2026-03-20
- 🎉 Primera versión estable
- Panel administrador completo (productos, categorías, clientes, vendedores, dashboard)
- Panel vendedor mobile-first (cargue, ventas, comprobantes, cierre)
- Modo offline con Service Worker + IndexedDB
- Sincronización automática de ventas offline
- PWA instalable con ícono personalizado
- Fechas en español con zona horaria Colombia (America/Bogota)
- Filtros por estado activo por defecto en módulos admin
- Tracking GPS de vendedores en tiempo real

---

## 📄 Licencia

Proyecto privado — Depósito LYD. Todos los derechos reservados.
