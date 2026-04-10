<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../controllers/CategoriaController.php';
require_once __DIR__ . '/../../models/categoria.php';
require_once __DIR__ . '/../../config/config.php'; /* BASE URL */


$mensaje  = '';
$tipo_msg = '';
$accion   = $_GET['accion'] ?? 'listar';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Procesar acciones POST ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($accion === 'crear') {
        $resultado = procesarCrearCategoria();
    } elseif ($accion === 'editar' && $id > 0) {
        $resultado = procesarEditarCategoria($id);
    }

    if (isset($resultado['exito'])) {
        $mensaje  = $resultado['mensaje'];
        $tipo_msg = 'exito';
        $accion   = 'listar';
    } else {
        $mensaje  = implode('<br>', $resultado['errores']);
        $tipo_msg = 'error';
    }
}

// ── Acciones GET (desactivar / reactivar) ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $id > 0) {
    if ($accion === 'desactivar') {
        $resultado = procesarDesactivarCategoria($id);
        $mensaje   = $resultado['exito'] ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
        $accion    = 'listar';
    } elseif ($accion === 'reactivar') {
        $resultado = procesarReactivarCategoria($id);
        $mensaje   = $resultado['exito'] ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
        $accion    = 'listar';
    }
}

// ── Cargar datos ────────────────────────────
$categorias = obtenerTodasCategorias();
$categoria  = ($accion === 'editar' && $id > 0) ? obtenerCategoriaPorId($id) : null;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/categorias_admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
</head>

<body>

    <?php require_once __DIR__ . '/partials/navbar.php'; ?>

    <div class="cont">
        <!-- ===================== CABECERA ===================== -->
        <div class="modulo-header">
            <div>
                <h2>Categorías</h2>
                <p style="font-size:13.5px;color:#64748b;margin:3px 0 0;">Administra las categorías de productos</p>
            </div>
            <button class="btn-nuevo" onclick="abrirModalCrear()">
                <i class="bi bi-plus-lg"></i> Nueva Categoría
            </button>
        </div>
           <!-- ===================== MENSAJE ===================== -->
    <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?php echo $tipo_msg; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

        <!-- ===================== BARRA DE FILTROS ===================== -->
        <div class="filtros-bar">
            <div class="buscador">
                <i class="bi bi-search"></i>
                <input type="text" id="inputBuscar" placeholder="Buscar categoría..." oninput="filtrar()">
            </div>
            <div class="filtros-derecha">
                <select class="filtro-select" id="filtroEstado" onchange="filtrar()">
                    <option value="">Todos los estados</option>
                    <option value="activa" selected>Activa</option>
                    <option value="inactiva">Inactiva</option>
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

        <!-- ===================== VISTA TABLA ===================== -->
        <div id="vistaTabla">
            <table class="tabla" id="tablaCategorias">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                    <tr>
                        <td colspan="4" class="sin-datos">No hay categorías registradas.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($categorias as $c): ?>
                    <tr data-nombre="<?php echo strtolower(htmlspecialchars($c['nombre'])); ?>"
                        data-estado="<?php echo $c['estado'] ? 'activa' : 'inactiva'; ?>">
                        <td><?php echo $c['id_categoria']; ?></td>
                        <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                        <td>
                            <span class="badge <?php echo $c['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                <?php echo $c['estado'] ? '● Activa' : '● Inactiva'; ?>
                            </span>
                        </td>
                        <td class="acciones">
                            <button class="btn-editar"
                                    onclick="abrirModalEditar(<?php echo $c['id_categoria']; ?>, '<?php echo addslashes(htmlspecialchars($c['nombre'])); ?>')">
                                <i class="bi bi-pencil-fill"></i> Editar
                            </button>
                            <?php if ($c['estado']): ?>
                            <a href="?accion=desactivar&id=<?php echo $c['id_categoria']; ?>"
                               class="btn-desactivar"
                               onclick="return confirm('¿Desactivar esta categoría? Los productos asociados no se verán afectados.')">
                                <i class="bi bi-slash-circle"></i> Desactivar
                            </a>
                            <?php else: ?>
                            <a href="?accion=reactivar&id=<?php echo $c['id_categoria']; ?>" class="btn-reactivar">
                                <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===================== VISTA CARDS ===================== -->
        <div id="vistaCards" style="display:none;">
            <div class="cards-grid" id="cardsGrid">
                <?php if (!empty($categorias)): ?>
                <?php foreach ($categorias as $c): ?>
                <div class="cat-card"
                     data-nombre="<?php echo strtolower(htmlspecialchars($c['nombre'])); ?>"
                     data-estado="<?php echo $c['estado'] ? 'activa' : 'inactiva'; ?>">

                    <div class="cat-card-icon">
                        <i class="bi bi-tag-fill"></i>
                    </div>

                    <div class="cat-card-info">
                        <span class="cat-card-id">#<?php echo $c['id_categoria']; ?></span>
                        <h4 class="cat-card-nombre"><?php echo htmlspecialchars($c['nombre']); ?></h4>
                        <span class="badge <?php echo $c['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                            <?php echo $c['estado'] ? '● Activa' : '● Inactiva'; ?>
                        </span>
                    </div>

                    <div class="cat-card-acciones">
                        <button class="btn-icono btn-editar-icono"
                                onclick="abrirModalEditar(<?php echo $c['id_categoria']; ?>, '<?php echo addslashes(htmlspecialchars($c['nombre'])); ?>')"
                                title="Editar">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <?php if ($c['estado']): ?>
                        <a href="?accion=desactivar&id=<?php echo $c['id_categoria']; ?>"
                           class="btn-icono btn-desactivar-icono"
                           title="Desactivar"
                           onclick="return confirm('¿Desactivar esta categoría?')">
                            <i class="bi bi-slash-circle"></i>
                        </a>
                        <?php else: ?>
                        <a href="?accion=reactivar&id=<?php echo $c['id_categoria']; ?>"
                           class="btn-icono btn-reactivar-icono"
                           title="Reactivar">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sin resultados -->
        <div class="sin-resultados" id="sinResultados" style="display:none;">
            <i class="bi bi-search"></i>
            <p>Sin resultados para tu búsqueda.</p>
        </div>

    </div>

    <!-- ======================================================
         MODAL — CREAR CATEGORÍA
         ====================================================== -->
    <div class="modal-overlay" id="modalCrear" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="bi bi-tag-fill"></i> Nueva Categoría</h3>
                <button class="modal-cerrar" onclick="cerrarModal('modalCrear')" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?accion=crear">
                    <div class="campo">
                        <label for="nombreCrear">Nombre de la categoría</label>
                        <input type="text" id="nombreCrear" name="nombre" maxlength="100"
                               placeholder="Ej. Cervezas, Gaseosas…"
                               value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                               required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancelar-modal" onclick="cerrarModal('modalCrear')">Cancelar</button>
                        <button type="submit" class="btn-guardar-modal">
                            <i class="bi bi-check-lg"></i> Guardar categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ======================================================
         MODAL — EDITAR CATEGORÍA
         ====================================================== -->
    <div class="modal-overlay" id="modalEditar" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="bi bi-pencil-fill"></i> Editar Categoría</h3>
                <button class="modal-cerrar" onclick="cerrarModal('modalEditar')" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="formEditar" action="">
                    <div class="campo">
                        <label for="nombreEditar">Nombre de la categoría</label>
                        <input type="text" id="nombreEditar" name="nombre" maxlength="100"
                               placeholder="Ej. Cervezas, Gaseosas…"
                               required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancelar-modal" onclick="cerrarModal('modalEditar')">Cancelar</button>
                        <button type="submit" class="btn-guardar-modal">
                            <i class="bi bi-check-lg"></i> Actualizar categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ── Vista activa persistida en sessionStorage ──
        let vistaActual = sessionStorage.getItem('catVista') || 'cards';
        cambiarVista(vistaActual, false); // sin animación al cargar

        function cambiarVista(tipo, animado = true) {
            vistaActual = tipo;
            sessionStorage.setItem('catVista', tipo);

            const esTabla = tipo === 'tabla';
            document.getElementById('vistaTabla').style.display = esTabla ? '' : 'none';
            document.getElementById('vistaCards').style.display = esTabla ? 'none' : '';
            document.getElementById('btnVista1').classList.toggle('active', esTabla);
            document.getElementById('btnVista2').classList.toggle('active', !esTabla);

            // Re-aplica filtros al cambiar vista
            filtrar();
        }

        // ── Filtros ──
        function filtrar() {
            const q      = document.getElementById('inputBuscar').value.toLowerCase().trim();
            const estado = document.getElementById('filtroEstado').value.toLowerCase();
            let visibles = 0;

            // Filtrar filas de tabla
            document.querySelectorAll('#tablaCategorias tbody tr[data-nombre]').forEach(fila => {
                const okQ      = !q      || fila.dataset.nombre.includes(q);
                const okEstado = !estado || fila.dataset.estado === estado;
                const mostrar  = okQ && okEstado;
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) visibles++;
            });

            // Filtrar cards
            document.querySelectorAll('.cat-card').forEach(card => {
                const okQ      = !q      || card.dataset.nombre.includes(q);
                const okEstado = !estado || card.dataset.estado === estado;
                const mostrar  = okQ && okEstado;
                card.style.display = mostrar ? '' : 'none';
            });

            // Si estamos en tabla contamos filas; en cards contamos cards visibles
            if (vistaActual === 'cards') {
                visibles = document.querySelectorAll('.cat-card:not([style*="none"])').length;
            }

            document.getElementById('sinResultados').style.display = visibles === 0 ? 'flex' : 'none';
        }

        // ── Modales ──
        function abrirModal(id) {
            document.getElementById(id).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = '';
        }

        function abrirModalCrear() {
            abrirModal('modalCrear');
            setTimeout(() => document.getElementById('nombreCrear').focus(), 100);
        }

        function abrirModalEditar(id, nombre) {
            document.getElementById('formEditar').action = '?accion=editar&id=' + id;
            document.getElementById('nombreEditar').value = nombre;
            abrirModal('modalEditar');
            setTimeout(() => document.getElementById('nombreEditar').focus(), 100);
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) cerrarModal(this.id);
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal('modalCrear');
                cerrarModal('modalEditar');
            }
        });

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($accion ?? '') === 'crear' && ($tipo_msg ?? '') === 'error'): ?>
            window.addEventListener('load', () => abrirModalCrear());
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($accion ?? '') === 'editar' && ($tipo_msg ?? '') === 'error'): ?>
            window.addEventListener('load', () => abrirModalEditar(
                <?php echo $id; ?>,
                '<?php echo addslashes(htmlspecialchars($_POST['nombre'] ?? '')); ?>'
            ));
        <?php endif; ?>
    </script>

</body>
</html>