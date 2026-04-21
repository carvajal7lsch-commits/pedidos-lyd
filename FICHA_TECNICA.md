# 📄 Ficha Técnica: Pedidos LYD (v2.0.0)

**Pedidos LYD** es una solución integral de gestión logística y comercial diseñada para la optimización de rutas de distribución en camión. El sistema permite el control en tiempo real de inventarios, ventas (contado/crédito) y el seguimiento geográfico de la flota.

---

## 🏗️ Arquitectura del Sistema

| Componente | Detalle |
|---|---|
| **Modelo de Desarrollo** | 100% Online (Cloud-based) |
| **Arquitectura** | Monolítica bajo patrón MVC (Model-View-Controller) manual |
| **Interfaz** | Mobile-first / PWA (Progressive Web App) |
| **Seguridad** | Middleware de autenticación por roles y saneamiento de datos |

---

## 🛠️ Stack Tecnológico

### Backend & Datos
- **Lenguaje:** PHP 8.2+
- **Base de Datos:** MySQL / MariaDB (Motor InnoDB)
- **Acceso a Datos:** Extensión `mysqli` con soporte para transacciones.

### Frontend (User Interface)
- **Estructura:** HTML5 Semántico
- **Estilos:** CSS3 Moderno (Custom Properties, Grid, Flexbox)
- **Lógica:** JavaScript Vanilla (ES6+)
- **Iconografía:** Bootstrap Icons

### Integraciones & Librerías
- **Mapas:** Leaflet.js (OpenStreetMap) para rastreo GPS.
- **Documentos:** jsPDF para generación dinámica de comprobantes de venta.
- **Fuentes:** Sora, DM Sans (Vendedor) e Inter (Admin).

---

## 📦 Módulos Principales

### 1. Panel de Administración (Desktop Optimized)
- **Dashboard de Inteligencia:** Métricas clave (KPIs) sobre clientes, productos y categorías.
- **Geolocalización en Tiempo Real:** Mapa interactivo con la ubicación exacta de los vendedores activos.
- **Gestión de Catálogo:** Control total sobre productos, precios, categorías e imágenes.
- **Administración de Clientes:** Segmentación automática basada en comportamiento (VIP, Frecuente, Con deuda).

### 2. Panel del Vendedor (Mobile-First / PWA)
- **Gestión de Carga:** Registro diario de inventario subido al camión con trazabilidad de "sobrantes".
- **Catálogo Interactivo:** Venta asistida con stock disponible actualizado al instante.
- **Módulo de Cobranza:** Soporte para ventas de contado y crédito con registro de abonos parciales.
- **Documentación Digital:** Generación de facturas en PDF y comprobantes compartibles.
- **Cierre de Jornada:** Resumen contable automatizado para entrega de valores a tesorería.

---

## 📋 Especificaciones de Infraestructura

### Servidor (Requerimientos Mínimos)
- **Sistema Operativo:** Linux (Ubuntu/Debian) o Windows (XAMPP).
- **Web Server:** Apache 2.4+ con `mod_rewrite` habilitado.
- **PHP:** Versión 8.1 o superior.
- **MySQL:** Versión 5.7 o MariaDB 10.4+.

### Cliente (Dispositivos)
- **Vendedor:** Smartphone con Android (Chrome) o iOS (Safari), conectividad GPS y Datos Móviles.
- **Administrador:** PC/Laptop con resolución mínima de 1280px.

---

## 🔒 Seguridad y Rendimiento
- **Prevención de Inyección SQL:** Implementación estricta de **sentencias preparadas (Prepared Statements)** en todos los módulos, incluyendo el sistema de autenticación central.
- **Autenticación:** Sistema de sesiones nativas de PHP con protección de rutas mediante middleware.
- **Integridad de Datos:** Restricciones de llave foránea (`ON DELETE RESTRICT/SET NULL`) en base de datos para evitar orfandad de registros.
- **Optimización:** Carga asíncrona de ubicaciones GPS y assets comprimidos para bajo consumo de datos.

---

**© 2026 Pedidos LYD — Todos los derechos reservados.**
