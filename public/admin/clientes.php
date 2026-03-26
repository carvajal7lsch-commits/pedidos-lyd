<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../controllers/ClienteController.php';
require_once __DIR__ . '/../../models/cliente.php';
require_once __DIR__ . '/../../config/config.php';

$mensaje  = '';
$tipo_msg = '';
$accion   = $_GET['accion'] ?? 'listar';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear') {
    $resultado = procesarCrearCliente();
    $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
    $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'editar' && $id > 0) {
    $resultado = procesarEditarCliente($id);
    $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
    $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $id > 0) {
    if ($accion === 'desactivar') {
        $resultado = procesarDesactivarCliente($id);
        $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
    } elseif ($accion === 'reactivar') {
        $resultado = procesarReactivarCliente($id);
        $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
    }
}

$clientes = obtenerClientes();

// Calcular etiquetas para cada cliente
foreach ($clientes as &$c) {
    $c['etiquetas'] = etiquetarCliente($c);
}
unset($c);

$abrir_modal_crear  = ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear'  && $tipo_msg === 'error');
$abrir_modal_editar = ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'editar' && $tipo_msg === 'error');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/clientes_admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
</head>
<body>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<div class="cont">

    <!-- ===================== CABECERA ===================== -->
    <div class="pagina-header">
        <div>
            <h1>Gestión de Clientes</h1>
            <p>Registra y administra la base de clientes</p>
        </div>
        <button class="btn-nuevo-cliente" onclick="abrirModalCrear()">
            <i class="bi bi-person-plus-fill"></i> Nuevo Cliente
        </button>
    </div>

    <!-- ===================== MENSAJE ===================== -->
<?php if (!empty($mensaje)): ?>
<div class="alerta alerta-<?php echo $tipo_msg; ?>">
    <?php echo $mensaje; ?>
</div>
<?php endif; ?>

    <!-- ===================== BARRA FILTROS ===================== -->
    <div class="filtros-bar">
        <div class="buscador">
            <i class="bi bi-search"></i>
            <input type="text" id="inputBuscar" placeholder="Buscar por nombre, teléfono o dirección..." oninput="filtrar()">
        </div>
        <div class="filtros-derecha">
            <div class="tab-pills">
                <button class="tab-pill" data-estado="todos">Todos</button>
                <button class="tab-pill active" data-estado="activo">Activos</button>
                <button class="tab-pill" data-estado="inactivo">Inactivos</button>
            </div>
            <select class="filtro-select" id="filtroEtiqueta" onchange="filtrar()">
                <option value="">Todas las etiquetas</option>
                <option value="nuevo">🆕 Nuevo</option>
                <option value="frecuente">⭐ Frecuente</option>
                <option value="vip">👑 VIP</option>
                <option value="inactivo">😴 Inactivo</option>
                <option value="deuda">💳 Con deuda</option>
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
        <table class="tabla" id="tablaClientes">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Etiquetas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="7" class="sin-datos">No hay clientes registrados.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($clientes as $c):
                    $claves_etiquetas = implode(' ', array_column($c['etiquetas'], 'clave'));
                ?>
                <tr data-nombre="<?php echo strtolower(htmlspecialchars($c['nombre'])); ?>"
                    data-tel="<?php echo strtolower(htmlspecialchars($c['telefono'] ?? '')); ?>"
                    data-dir="<?php echo strtolower(htmlspecialchars($c['direccion'])); ?>"
                    data-estado="<?php echo $c['estado'] ? 'activo' : 'inactivo'; ?>"
                    data-etiquetas="<?php echo $claves_etiquetas; ?>">
                    <td class="td-id"><?php echo $c['id_cliente']; ?></td>
                    <td>
                        <div class="cliente-nombre-cell">
                            <div class="cliente-avatar">
                                <?php echo mb_strtoupper(mb_substr($c['nombre'], 0, 1)); ?>
                            </div>
                            <strong><?php echo htmlspecialchars($c['nombre']); ?></strong>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($c['telefono'] ?? '—'); ?></td>
                    <td class="td-dir"><?php echo htmlspecialchars($c['direccion']); ?></td>
                    <td><?php echo renderEtiquetas($c['etiquetas']); ?></td>
                    <td>
                        <span class="badge <?php echo $c['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                            <?php echo $c['estado'] ? '● Activo' : '● Inactivo'; ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <button class="btn-icono btn-editar"
                                onclick="abrirModalEditar(
                                    <?php echo $c['id_cliente']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($c['nombre'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($c['telefono'] ?? '')); ?>',
                                    '<?php echo addslashes(htmlspecialchars($c['direccion'])); ?>'
                                )" title="Editar">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                            <?php if ($c['estado']): ?>
                            <a href="?accion=desactivar&id=<?php echo $c['id_cliente']; ?>"
                               class="btn-icono btn-eliminar" title="Desactivar"
                               onclick="return confirm('¿Desactivar a <?php echo htmlspecialchars($c['nombre']); ?>?')">
                                <i class="bi bi-slash-circle"></i>
                            </a>
                            <?php else: ?>
                            <a href="?accion=reactivar&id=<?php echo $c['id_cliente']; ?>"
                               class="btn-icono btn-reactivar" title="Reactivar">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <?php endif; ?>
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
        <div class="cli-cards-grid">
            <?php foreach ($clientes as $c):
                $claves_etiquetas = implode(' ', array_column($c['etiquetas'], 'clave'));
            ?>
            <div class="cli-card"
                 data-nombre="<?php echo strtolower(htmlspecialchars($c['nombre'])); ?>"
                 data-tel="<?php echo strtolower(htmlspecialchars($c['telefono'] ?? '')); ?>"
                 data-dir="<?php echo strtolower(htmlspecialchars($c['direccion'])); ?>"
                 data-estado="<?php echo $c['estado'] ? 'activo' : 'inactivo'; ?>"
                 data-etiquetas="<?php echo $claves_etiquetas; ?>">

                <div class="cli-card-top">
                    <div class="cli-card-avatar">
                        <?php echo mb_strtoupper(mb_substr($c['nombre'], 0, 1)); ?>
                    </div>
                    <span class="badge <?php echo $c['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <?php echo $c['estado'] ? '● Activo' : '● Inactivo'; ?>
                    </span>
                </div>

                <div class="cli-card-info">
                    <h4 class="cli-card-nombre"><?php echo htmlspecialchars($c['nombre']); ?></h4>
                    <?php echo renderEtiquetas($c['etiquetas']); ?>
                    <?php if (!empty($c['telefono'])): ?>
                    <div class="cli-card-meta">
                        <i class="bi bi-telephone"></i>
                        <span><?php echo htmlspecialchars($c['telefono']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="cli-card-meta">
                        <i class="bi bi-geo-alt"></i>
                        <span><?php echo htmlspecialchars($c['direccion']); ?></span>
                    </div>
                </div>

                <div class="cli-card-footer">
                    <button class="btn-icono-sm editar"
                            onclick="abrirModalEditar(
                                <?php echo $c['id_cliente']; ?>,
                                '<?php echo addslashes(htmlspecialchars($c['nombre'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($c['telefono'] ?? '')); ?>',
                                '<?php echo addslashes(htmlspecialchars($c['direccion'])); ?>'
                            )" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                        <?php if ($c['estado']): ?>
                        <a href="?accion=desactivar&id=<?php echo $c['id_cliente']; ?>"
                           class="btn-icono-sm eliminar" title="Desactivar"
                           onclick="return confirm('¿Desactivar a <?php echo htmlspecialchars($c['nombre']); ?>?')">
                            <i class="bi bi-slash-circle"></i>
                        </a>
                        <?php else: ?>
                        <a href="?accion=reactivar&id=<?php echo $c['id_cliente']; ?>"
                           class="btn-icono-sm reactivar" title="Reactivar">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
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

</div><!-- /cont -->

<!-- ======================================================
     MODAL — CREAR CLIENTE
     ====================================================== -->
<div class="modal-overlay" id="modalCrear" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="bi bi-person-plus-fill"></i> Nuevo Cliente</h3>
            <button class="modal-cerrar" onclick="cerrarModal('modalCrear')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="?accion=crear">
                <div class="campo">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" placeholder="Ej. María García"
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                           maxlength="100" required>
                </div>
                <div class="campo">
                    <label>Teléfono <span class="label-opcional">(opcional)</span></label>
                    <input type="text" name="telefono" placeholder="Ej. 3001234567"
                           value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>"
                           maxlength="20">
                </div>
                <div class="campo">
                    <label>Ruta / Dirección</label>
                    <select name="direccion" required>
                        <option value="">-- Selecciona la ruta --</option>
                        <?php
                        $rutas = ['Guayabal','Cruce','Acevedo','Gallardo','El Brasil','Quemadas'];
                        $sel   = $_POST['direccion'] ?? '';
                        foreach ($rutas as $ruta):
                        ?>
                        <option value="<?php echo $ruta; ?>"
                            <?php echo $sel === $ruta ? 'selected' : ''; ?>>
                            <?php echo $ruta; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar-modal" onclick="cerrarModal('modalCrear')">Cancelar</button>
                    <button type="submit" class="btn-guardar-modal">
                        <i class="bi bi-check-lg"></i> Guardar cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================
     MODAL — EDITAR CLIENTE
     ====================================================== -->
<div class="modal-overlay" id="modalEditar" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="bi bi-pencil-fill"></i> Editar Cliente</h3>
            <button class="modal-cerrar" onclick="cerrarModal('modalEditar')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formEditar" action="">
                <div class="campo">
                    <label>Nombre completo</label>
                    <input type="text" id="editNombre" name="nombre" maxlength="100" required>
                </div>
                <div class="campo">
                    <label>Teléfono <span class="label-opcional">(opcional)</span></label>
                    <input type="text" id="editTelefono" name="telefono" maxlength="20">
                </div>
                <div class="campo">
                    <label>Ruta / Dirección</label>
                    <select id="editDireccion" name="direccion" required>
                        <option value="">-- Selecciona la ruta --</option>
                        <?php foreach (['Guayabal','Cruce','Acevedo','Gallardo','El Brasil','Quemadas'] as $ruta): ?>
                        <option value="<?php echo $ruta; ?>"><?php echo $ruta; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar-modal" onclick="cerrarModal('modalEditar')">Cancelar</button>
                    <button type="submit" class="btn-guardar-modal">
                        <i class="bi bi-check-lg"></i> Actualizar cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales — declaradas PRIMERO antes de cualquier llamada
let vistaActual  = sessionStorage.getItem('cliVista') || 'cards';
let estadoFiltro = 'activo';

// ── Funciones ──────────────────────────────────────────────────────────────

function cambiarVista(tipo) {
    vistaActual = tipo;
    sessionStorage.setItem('cliVista', tipo);
    const esTabla = tipo === 'tabla';
    document.getElementById('vistaTabla').style.display = esTabla ? '' : 'none';
    document.getElementById('vistaCards').style.display = esTabla ? 'none' : '';
    document.getElementById('btnVista1').classList.toggle('active', esTabla);
    document.getElementById('btnVista2').classList.toggle('active', !esTabla);
    filtrar();
}

function filtrar() {
    const q         = document.getElementById('inputBuscar').value.toLowerCase().trim();
    const filtroTag = document.getElementById('filtroEtiqueta').value;
    let visibles = 0;

    document.querySelectorAll('#tablaClientes tbody tr[data-nombre]').forEach(el => {
        const texto   = (el.dataset.nombre + ' ' + el.dataset.tel + ' ' + el.dataset.dir).toLowerCase();
        const okQ     = !q || texto.includes(q);
        const okState = estadoFiltro === 'todos' || el.dataset.estado === estadoFiltro;
        const okTag   = !filtroTag || (el.dataset.etiquetas || '').includes(filtroTag);
        const mostrar = okQ && okState && okTag;
        el.style.display = mostrar ? '' : 'none';
        if (mostrar && vistaActual === 'tabla') visibles++;
    });

    document.querySelectorAll('.cli-card').forEach(el => {
        const texto   = (el.dataset.nombre + ' ' + el.dataset.tel + ' ' + el.dataset.dir).toLowerCase();
        const okQ     = !q || texto.includes(q);
        const okState = estadoFiltro === 'todos' || el.dataset.estado === estadoFiltro;
        const okTag   = !filtroTag || (el.dataset.etiquetas || '').includes(filtroTag);
        const mostrar = okQ && okState && okTag;
        el.style.display = mostrar ? '' : 'none';
        if (mostrar && vistaActual === 'cards') visibles++;
    });

    document.getElementById('sinResultados').style.display = visibles === 0 ? 'flex' : 'none';
}

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
    setTimeout(() => document.querySelector('#modalCrear input[name="nombre"]').focus(), 100);
}

function abrirModalEditar(id, nombre, telefono, direccion) {
    document.getElementById('formEditar').action  = '?accion=editar&id=' + id;
    document.getElementById('editNombre').value    = nombre;
    document.getElementById('editTelefono').value  = telefono;
    document.getElementById('editDireccion').value = direccion;
    // Si la dirección no coincide con ninguna opción, selecciona la primera ruta disponible
    const sel = document.getElementById('editDireccion');
    if (!sel.value) sel.selectedIndex = 0;
    abrirModal('modalEditar');
    setTimeout(() => document.getElementById('editNombre').focus(), 100);
}

// ── Inicialización — DESPUÉS de declarar todo ─────────────────────────────
cambiarVista(vistaActual);

document.querySelectorAll('.tab-pill').forEach(pill => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.tab-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        estadoFiltro = pill.dataset.estado;
        filtrar();
    });
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) cerrarModal(this.id);
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { cerrarModal('modalCrear'); cerrarModal('modalEditar'); }
});
</script>
<?php if ($abrir_modal_crear): ?>
<script>window.addEventListener('load', () => abrirModalCrear());</script>
<?php endif; ?>

<?php if ($abrir_modal_editar): ?>
<script>window.addEventListener('load', () => abrirModalEditar(
    <?php echo $id; ?>,
    '<?php echo addslashes(htmlspecialchars($_POST['nombre'] ?? '')); ?>',
    '<?php echo addslashes(htmlspecialchars($_POST['telefono'] ?? '')); ?>',
    '<?php echo addslashes(htmlspecialchars($_POST['direccion'] ?? '')); ?>'
));</script>
<?php endif; ?>

</body>
</html>