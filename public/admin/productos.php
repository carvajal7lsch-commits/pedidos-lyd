<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();
 
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/ProductoController.php';
 
$mensaje  = '';
$tipo_msg = '';
$accion   = $_GET['accion'] ?? 'listar';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear') {
    $resultado = procesarCrearProducto();
    $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
    $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'editar' && $id > 0) {
    $resultado = procesarEditarProducto($id);
    $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
    $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $id > 0) {
    if ($accion === 'desactivar') {
        $resultado = procesarDesactivarProducto($id);
        $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
    } elseif ($accion === 'reactivar') {
        $resultado = procesarReactivarProducto($id);
        $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
    }
}
 
$abrir_modal_crear = ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear' && $tipo_msg === 'error');
 
$productos  = obtenerProductos();
$categorias = obtenerCategorias();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos —
        <?php echo APP_NAME; ?>
    </title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/productos_admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>

    <?php require_once __DIR__ . '/partials/navbar.php'; ?>

    <main class="cont">

        <div class="pagina-header">
            <div>
                <h1>Gestión de Productos</h1>
                <p>Registra y administra el catálogo de productos para la venta</p>
            </div>
            <button class="btn-nuevo-producto" onclick="abrirModalCrear()">
                <i class="bi bi-plus-lg"></i> Nuevo Producto
            </button>
        </div>

        <?php if (!empty($mensaje)): ?>
        <div class="alerta alerta-<?php echo $tipo_msg; ?>">
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>

        <!-- ======== BARRA FILTROS ======== -->
        <div class="filtros-bar">
            <div class="buscador">
                <i class="bi bi-search"></i>
                <input type="text" id="inputBuscar" placeholder="Buscar producto..." oninput="filtrar()">
            </div>
            <div class="filtros-derecha">
                <select class="filtro-select" id="filtroCat" onchange="filtrar()">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['nombre']); ?>">
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select class="filtro-select" id="filtroEstado" onchange="filtrar()">
                    <option value="">Todos los estados</option>
                    <option value="activo" selected>Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
                <div class="vista-toggle">
                    <button class="vista-btn" id="btnVista1" onclick="cambiarVista('tabla')" title="Vista tabla">
                        <i class="bi bi-table"></i>
                    </button>
                    <button class="vista-btn active" id="btnVista2" onclick="cambiarVista('cards')" title="Vista tarjetas">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ======== VISTA TABLA ======== -->
        <div id="vistaTabla">
            <table class="tabla" id="tablaProductos">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="6" class="sin-datos">No hay productos registrados.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($productos as $p): ?>
                    <tr data-nombre="<?php echo strtolower(htmlspecialchars($p['nombre'])); ?>"
                        data-cat="<?php echo strtolower(htmlspecialchars($p['categoria'])); ?>"
                        data-estado="<?php echo $p['estado'] ? 'activo' : 'inactivo'; ?>">
                        <td>
                            <?php if ($p['imagen']): ?>
                            <img src="../uploads/productos/<?php echo htmlspecialchars($p['imagen']); ?>"
                                alt="<?php echo htmlspecialchars($p['nombre']); ?>" class="img-producto">
                            <?php else: ?>
                            <div class="img-placeholder"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><strong>
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </strong></td>
                        <td>
                            <?php echo htmlspecialchars($p['categoria']); ?>
                        </td>
                        <td>$
                            <?php echo number_format($p['precio'], 2); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $p['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                <?php echo $p['estado'] ? '● Activo' : '● Inactivo'; ?>
                            </span>
                        </td>
                        <td class="acciones">
                            <button class="btn-icono btn-editar"
                                onclick="abrirModalEditar(<?php echo $p['id_producto']; ?>)" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <?php if ($p['estado']): ?>
                            <a href="?accion=desactivar&id=<?php echo $p['id_producto']; ?>"
                                class="btn-icono btn-eliminar" title="Desactivar"
                                onclick="return confirm('¿Desactivar este producto?')">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                            <?php else: ?>
                            <a href="?accion=reactivar&id=<?php echo $p['id_producto']; ?>"
                                class="btn-icono btn-reactivar" title="Reactivar">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ======== VISTA CARDS ======== -->
        <div id="vistaCards" style="display:none;">
            <div class="prod-cards-grid" id="prodCardsGrid">
                <?php foreach ($productos as $p): ?>
                <div class="prod-card" data-nombre="<?php echo strtolower(htmlspecialchars($p['nombre'])); ?>"
                    data-cat="<?php echo strtolower(htmlspecialchars($p['categoria'])); ?>"
                    data-estado="<?php echo $p['estado'] ? 'activo' : 'inactivo'; ?>">

                    <div class="carta-img">
                        <!-- Imagen -->
                        <?php if ($p['imagen']): ?>
                        <img src="../uploads/productos/<?php echo htmlspecialchars($p['imagen']); ?>"
                            alt="<?php echo htmlspecialchars($p['nombre']); ?>" class="prod-card-img">
                        <?php else: ?>
                        <div class="prod-card-img-placeholder">
                            <i class="bi bi-image"></i>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Cuerpo -->
                    <div class="prod-card-body">
                        <div class="prod-card-top">
                            <h4 class="prod-card-nombre">
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </h4>
                            <span class="prod-card-cat">
                                <?php echo htmlspecialchars($p['categoria']); ?>
                            </span>
                        </div>
                        <div class="prod-card-meta">
                            <span class="prod-card-precio">$
                                <?php echo number_format($p['precio'], 2); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="prod-card-footer">
                        <span class="badge <?php echo $p['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                            <?php echo $p['estado'] ? '● Activo' : '● Inactivo'; ?>
                        </span>
                        <div class="prod-card-acciones">
                            <button class="btn-icono-sm editar"
                                onclick="abrirModalEditar(<?php echo $p['id_producto']; ?>)" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <?php if ($p['estado']): ?>
                            <a href="?accion=desactivar&id=<?php echo $p['id_producto']; ?>"
                                class="btn-icono-sm eliminar" title="Desactivar"
                                onclick="return confirm('¿Desactivar este producto?')">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                            <?php else: ?>
                            <a href="?accion=reactivar&id=<?php echo $p['id_producto']; ?>"
                                class="btn-icono-sm reactivar" title="Reactivar">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sin resultados -->
        <div class="sin-resultados" id="sinResultados" style="display:none;">
            <i class="bi bi-search"></i>
            <p>Sin resultados para tu búsqueda.</p>
        </div>

    </main>

    <!-- ======================================================
     MODAL — CREAR PRODUCTO
     ====================================================== -->
    <div class="modal-overlay" id="modalCrear" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="bi bi-bag-plus-fill"></i> Nuevo Producto</h3>
                <button class="modal-cerrar" onclick="cerrarModalCrear()" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?accion=crear" enctype="multipart/form-data" id="formCrear">

                    <div class="campo">
                        <label>Nombre del producto</label>
                        <input type="text" name="nombre" placeholder="Ej. Gaseosa Cola 250ml"
                            value="<?php echo $abrir_modal_crear ? htmlspecialchars($_POST['nombre'] ?? '') : ''; ?>"
                            maxlength="100" required>
                    </div>

                    <div class="campo">
                        <label>Categoría</label>
                        <select name="id_categoria" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id_categoria']; ?>" <?php echo ($abrir_modal_crear &&
                                ($_POST['id_categoria'] ?? '' )==$cat['id_categoria']) ? 'selected' : '' ; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Precio</label>
                        <div class="input-prefijo">
                            <span>$</span>
                            <input type="number" name="precio" placeholder="0.00"
                                value="<?php echo $abrir_modal_crear ? htmlspecialchars($_POST['precio'] ?? '') : ''; ?>"
                                min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="campo">
                        <label>Presentación (imagen)</label>
                        <div class="zona-imagen" id="zonaImagen" ondragover="dragOver(event)"
                            ondragleave="dragLeave(event)" ondrop="drop(event)"
                            onclick="document.getElementById('inputImagen').click()">
                            <div id="placeholderImg">
                                <i class="bi bi-cloud-upload icono-upload"></i>
                                <p>Arrastra la imagen aquí o <span class="link-seleccionar">selecciona para
                                        cargar</span></p>
                                <small>JPG, PNG o WEBP — máx. 2MB</small>
                            </div>
                            <div class="preview-wrapper" id="previewWrapper" style="display:none;">
                                <img id="previstaImg" src="" alt="Preview">
                                <button type="button" class="btn-quitar-img" onclick="quitarImagen(event)">
                                    <i class="bi bi-x"></i> Quitar
                                </button>
                            </div>
                        </div>
                        <input type="file" id="inputImagen" name="imagen" accept=".jpg,.jpeg,.png,.webp"
                            style="display:none;" onchange="previsualizarImagen(this.files[0])" required>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancelar-modal" onclick="cerrarModalCrear()">Cancelar</button>
                        <button type="submit" class="btn-guardar-modal">
                            <i class="bi bi-check-lg"></i> Guardar Producto
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- ======================================================
     MODAL — EDITAR PRODUCTO
     ====================================================== -->
    <div class="modal-overlay" id="modalEditar" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="bi bi-pencil-fill"></i> Editar Producto</h3>
                <button class="modal-cerrar" onclick="cerrarModalEditar()" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body" id="modalEditarContenido">
                <p style="text-align:center; color:#94a3b8;">Cargando...</p>
            </div>
        </div>
    </div>

    <script>
        // ── Datos cargados desde PHP (sin fetch) ──
        const categorias = <?php echo json_encode($categorias); ?>;
        const productos = <?php echo json_encode($productos);  ?>;

        // ══════════════════════════════════════════
        // VISTA (tabla / cards) con persistencia
        // ══════════════════════════════════════════
        let vistaActual = sessionStorage.getItem('prodVista') || 'cards';
        cambiarVista(vistaActual);

        function cambiarVista(tipo) {
            vistaActual = tipo;
            sessionStorage.setItem('prodVista', tipo);
            const esTabla = tipo === 'tabla';
            document.getElementById('vistaTabla').style.display = esTabla ? '' : 'none';
            document.getElementById('vistaCards').style.display = esTabla ? 'none' : '';
            document.getElementById('btnVista1').classList.toggle('active', esTabla);
            document.getElementById('btnVista2').classList.toggle('active', !esTabla);
            filtrar();
        }

        // ══════════════════════════════════════════
        // FILTROS — tabla + cards sincronizados
        // ══════════════════════════════════════════
        function filtrar() {
            const q = document.getElementById('inputBuscar').value.toLowerCase().trim();
            const cat = document.getElementById('filtroCat').value.toLowerCase();
            const estado = document.getElementById('filtroEstado').value.toLowerCase();
            let visibles = 0;

            document.querySelectorAll('#tablaProductos tbody tr[data-nombre]').forEach(fila => {
                const okQ = !q || fila.dataset.nombre.includes(q);
                const okCat = !cat || fila.dataset.cat === cat;
                const okEstado = !estado || fila.dataset.estado === estado;
                const mostrar = okQ && okCat && okEstado;
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar && vistaActual === 'tabla') visibles++;
            });

            document.querySelectorAll('.prod-card').forEach(card => {
                const okQ = !q || card.dataset.nombre.includes(q);
                const okCat = !cat || card.dataset.cat === cat;
                const okEstado = !estado || card.dataset.estado === estado;
                const mostrar = okQ && okCat && okEstado;
                card.style.display = mostrar ? '' : 'none';
                if (mostrar && vistaActual === 'cards') visibles++;
            });

            document.getElementById('sinResultados').style.display = visibles === 0 ? 'flex' : 'none';
        }

        // ══════════════════════════════════════════
        // MODALES
        // ══════════════════════════════════════════
        function abrirModalCrear() {
            // Limpiar formulario y preview de imagen
            document.getElementById('formCrear').reset();
            document.getElementById('previewWrapper').style.display = 'none';
            document.getElementById('placeholderImg').style.display = 'block';
            document.getElementById('modalCrear').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function cerrarModalCrear() {
            document.getElementById('modalCrear').style.display = 'none';
            document.body.style.overflow = '';
        }
        function cerrarModalEditar() {
            document.getElementById('modalEditar').style.display = 'none';
            document.body.style.overflow = '';
        }

        // ── Abre modal editar usando datos locales (sin fetch) ──
        function abrirModalEditar(id) {
            const p = productos.find(x => x.id_producto == id);
            if (!p) return;

            document.getElementById('modalEditar').style.display = 'flex';
            document.body.style.overflow = 'hidden';

            const opts = categorias.map(c =>
                `<option value="${c.id_categoria}" ${String(c.id_categoria) === String(p.id_categoria) ? 'selected' : ''}>
            ${c.nombre}
        </option>`
            ).join('');

            document.getElementById('modalEditarContenido').innerHTML = `
        <form method="POST" action="?accion=editar&id=${p.id_producto}" enctype="multipart/form-data">

            <div class="campo">
                <label>Nombre del producto</label>
                <input type="text" name="nombre" value="${p.nombre}" maxlength="100" required>
            </div>

            <div class="campo">
                <label>Categoría</label>
                <select name="id_categoria" required>
                    <option value="">-- Selecciona --</option>
                    ${opts}
                </select>
            </div>

            <div class="campo">
                <label>Precio</label>
                <div class="input-prefijo">
                    <span>$</span>
                    <input type="number" name="precio" value="${p.precio}" min="0" step="0.01" required>
                </div>
            </div>

            <div class="campo">
                <label>Imagen actual</label>
                ${p.imagen
                    ? `<img src="../uploads/productos/${p.imagen}" class="img-preview-modal">`
                    : `<p style="color:#94a3b8;font-size:13px;">Sin imagen registrada</p>`
                }
            </div>

            <div class="campo">
                <label>Cambiar imagen <small style="text-transform:none;font-weight:400;">(opcional)</small></label>
                <div class="zona-imagen" id="zonaImagenEdit"
                     ondragover="dragOver(event)"
                     ondragleave="dragLeave(event)"
                     ondrop="dropEdit(event)"
                     onclick="document.getElementById('inputImagenEdit').click()">
                    <div id="placeholderImgEdit">
                        <i class="bi bi-cloud-upload icono-upload"></i>
                        <p>Arrastra la imagen aquí o <span class="link-seleccionar">selecciona para cargar</span></p>
                        <small>JPG, PNG o WEBP — máx. 2MB</small>
                    </div>
                    <div class="preview-wrapper" id="previewWrapperEdit" style="display:none;">
                        <img id="previstaImgEdit" src="" alt="Preview">
                        <button type="button" class="btn-quitar-img" onclick="quitarImagenEdit(event)">
                            <i class="bi bi-x"></i> Quitar
                        </button>
                    </div>
                </div>
                <input type="file" id="inputImagenEdit" name="imagen"
                       accept=".jpg,.jpeg,.png,.webp"
                       style="display:none;"
                       onchange="previsualizarImagenEdit(this.files[0])">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancelar-modal" onclick="cerrarModalEditar()">Cancelar</button>
                <button type="submit" class="btn-guardar-modal">
                    <i class="bi bi-check-lg"></i> Actualizar Producto
                </button>
            </div>

        </form>
    `;
        }

        // Cerrar al clic fuera
        document.getElementById('modalCrear').addEventListener('click', function (e) {
            if (e.target === this) cerrarModalCrear();
        });
        document.getElementById('modalEditar').addEventListener('click', function (e) {
            if (e.target === this) cerrarModalEditar();
        });

        // Cerrar con Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { cerrarModalCrear(); cerrarModalEditar(); }
        });

        // ── Drag & drop modal CREAR ──
        function dragOver(e) { e.preventDefault(); document.getElementById('zonaImagen').classList.add('dragover'); }
        function dragLeave(e) { document.getElementById('zonaImagen').classList.remove('dragover'); }
        function drop(e) {
            e.preventDefault();
            document.getElementById('zonaImagen').classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer(); dt.items.add(file);
                document.getElementById('inputImagen').files = dt.files;
                previsualizarImagen(file);
            }
        }
        function previsualizarImagen(file) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('previstaImg').src = e.target.result;
                document.getElementById('previewWrapper').style.display = 'block';
                document.getElementById('placeholderImg').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
        function quitarImagen(e) {
            e.stopPropagation();
            document.getElementById('inputImagen').value = '';
            document.getElementById('previewWrapper').style.display = 'none';
            document.getElementById('placeholderImg').style.display = 'block';
        }

        // ── Drag & drop modal EDITAR ──
        function dropEdit(e) {
            e.preventDefault();
            document.getElementById('zonaImagenEdit').classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer(); dt.items.add(file);
                document.getElementById('inputImagenEdit').files = dt.files;
                previsualizarImagenEdit(file);
            }
        }
        function previsualizarImagenEdit(file) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('previstaImgEdit').src = e.target.result;
                document.getElementById('previewWrapperEdit').style.display = 'block';
                document.getElementById('placeholderImgEdit').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
        function quitarImagenEdit(e) {
            e.stopPropagation();
            document.getElementById('inputImagenEdit').value = '';
            document.getElementById('previewWrapperEdit').style.display = 'none';
            document.getElementById('placeholderImgEdit').style.display = 'block';
        }
 
<?php if ($abrir_modal_crear): ?>
            window.addEventListener('load', () => abrirModalCrear());
<?php endif; ?>
    </script>

</body>

</html>