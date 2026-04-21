# 🚛 Pedidos LYD

Sistema de gestión de pedidos y ventas para **Depósito LYD**, diseñado para vendedores que trabajan rutas de distribución desde un camión. Una solución moderna para el control de inventario, ventas y geolocalización.

> [!TIP]
> **Documentación Técnica Avanzada:** Para ver detalles profundos sobre la arquitectura, el stack tecnológico y los requerimientos, consulte la [📄 Ficha Técnica (Digital)](FICHA_TECNICA.html) o el archivo [Markdown](FICHA_TECNICA.md).

---

## ✨ Características principales

### Panel Administrador
- **Dashboard Bento:** Métricas clave del negocio y monitoreo de actividad.
- **Seguimiento GPS:** Mapa en tiempo real con la ubicación de todos los vendedores.
- **Gestión Total:** Control de productos, imágenes, categorías y estados.
- **CRM Inteligente:** Gestión de clientes con etiquetado automático (VIP, Frecuente, Con deuda, etc.).

### Panel Vendedor (Mobile-First)
- **Cargue Inteligente:** Gestión de inventario del camión con control de sobrantes.
- **Catálogo Digital:** Productos con stock en tiempo real y buscador integrado.
- **Ventas Dinámicas:** Soporte para contado, crédito y registro de abonos.
- **Comprobantes PDF:** Generación y descarga inmediata de facturas para compartir.
- **Cierre Automatizado:** Resumen contable al final del día para cuadre de caja.

### PWA (Progressive Web App)
- Instalable en Android e iOS como una aplicación nativa.
- Experiencia de pantalla completa y acceso rápido desde el inicio.
- Iconografía personalizada y branding del Depósito LYD.

---

## 🏗️ Estructura del proyecto

```
Pedidos_LYD/
├── FICHA_TECNICA.md          # Especificaciones técnicas detalladas
├── FICHA_TECNICA.html        # Versión digital interactiva de la ficha
├── config/                   # Configuración y conexión
├── controllers/              # Lógica de negocio (MVC)
├── models/                   # Capa de datos (MySQLi)
├── public/                   # Archivos públicos y vistas
│   ├── admin/                # Panel de control central
│   └── vendedor/             # Aplicación móvil para rutas
└── database/                 # Estructura de la base de datos
```

---

## 🛠️ Stack Tecnológico Principal

| Capa | Tecnología |
|---|---|
| **Backend** | PHP 8.2+ (MVC Manual) |
| **Base de Datos** | MySQL / MariaDB |
| **Frontend** | HTML5 + CSS3 (Modern) + Vanilla JS |
| **Mapas** | Leaflet.js |
| **Documentos** | jsPDF |
| **Iconografía** | Bootstrap Icons |

> **Nota:** La aplicación funciona 100% online. El PWA requiere **HTTPS** en producción. En localhost funcionan sin configuración adicional.

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
