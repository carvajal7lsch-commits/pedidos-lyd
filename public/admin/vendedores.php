<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../config/config.php';

$mensaje  = '';
$tipo_msg = '';
$accion   = $_GET['accion'] ?? 'listar';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($accion === 'crear') {
        $resultado = procesarCrearUsuario();
    } elseif ($accion === 'editar' && $id > 0) {
        $resultado = procesarEditarUsuario($id);
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $id > 0) {
    if ($accion === 'desactivar') {
        $resultado = procesarDesactivarUsuario($id, $_SESSION['id_usuario']);
        $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
        $accion    = 'listar';
    } elseif ($accion === 'reactivar') {
        $resultado = procesarReactivarUsuario($id);
        $mensaje   = isset($resultado['exito']) ? $resultado['mensaje'] : implode('<br>', $resultado['errores']);
        $tipo_msg  = isset($resultado['exito']) ? 'exito' : 'error';
        $accion    = 'listar';
    }
}

$usuarios = obtenerUsuarios();

$abrir_modal_crear  = ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear'  && $tipo_msg === 'error');
$abrir_modal_editar = ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'editar' && $tipo_msg === 'error');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendedores — <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/vendedores.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
</head>
<body>

<?php require_once __DIR__ . '/partials/navbar.php'; ?>


<div class="cont">

    <!-- CABECERA -->
    <div class="pagina-header">
        <div>
            <h1>Gestión de Vendedores</h1>
            <p>Administra los vendedores con acceso al sistema</p>
        </div>
        <button class="btn-nuevo" onclick="abrirModalCrear()">
            <i class="bi bi-person-plus-fill"></i> Nuevo Vendedor
        </button>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alerta alerta-<?php echo $tipo_msg; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <!-- BARRA FILTROS -->
    <div class="filtros-bar">
        <div class="buscador">
            <i class="bi bi-search"></i>
            <input type="text" id="inputBuscar" placeholder="Buscar por nombre o correo..." oninput="filtrar()">
        </div>
        <div class="filtros-derecha">
            <div class="tab-pills">
                <button class="tab-pill" data-estado="todos">Todos</button>
                <button class="tab-pill active" data-estado="activo">Activos</button>
                <button class="tab-pill" data-estado="inactivo">Inactivos</button>
            </div>
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

    <!-- VISTA TABLA -->
    <div id="vistaTabla">
        <table class="tabla" id="tablaVendedores">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                <tr><td colspan="5" class="sin-datos">No hay vendedores registrados.</td></tr>
                <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                <?php if ($u['rol'] !== 'admin'): ?>
                <tr data-nombre="<?php echo strtolower(htmlspecialchars($u['nombre'])); ?>"
                    data-correo="<?php echo strtolower(htmlspecialchars($u['correo'] ?? '')); ?>"
                    data-estado="<?php echo $u['estado'] ? 'activo' : 'inactivo'; ?>">
                    <td class="td-id"><?php echo $u['id_usuario']; ?></td>
                    <td>
                        <div class="usuario-nombre-cell">
                            <div class="usuario-avatar">
                                <?php echo mb_strtoupper(mb_substr($u['nombre'], 0, 1)); ?>
                            </div>
                            <strong><?php echo htmlspecialchars($u['nombre']); ?></strong>
                        </div>
                    </td>
                    <td class="td-correo"><?php echo htmlspecialchars($u['correo'] ?? '—'); ?></td>
                    <td>
                        <span class="badge <?php echo $u['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                            <?php echo $u['estado'] ? '● Activo' : '● Inactivo'; ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <button class="btn-icono btn-editar"
                                onclick="abrirModalEditar(
                                    <?php echo $u['id_usuario']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($u['nombre'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($u['correo'] ?? '')); ?>'
                                )" title="Editar">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <?php if ($u['id_usuario'] !== $_SESSION['id_usuario']): ?>
                            <?php if ($u['estado']): ?>
                            <a href="?accion=desactivar&id=<?php echo $u['id_usuario']; ?>"
                               class="btn-icono btn-eliminar" title="Desactivar"
                               onclick="return confirm('¿Desactivar a <?php echo htmlspecialchars($u['nombre']); ?>?')">
                                <i class="bi bi-slash-circle"></i>
                            </a>
                            <?php else: ?>
                            <a href="?accion=reactivar&id=<?php echo $u['id_usuario']; ?>"
                               class="btn-icono btn-reactivar" title="Reactivar">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-tu">tú</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- VISTA CARDS -->
    <div id="vistaCards" style="display:none;">
        <div class="vend-cards-grid">
            <?php foreach ($usuarios as $u): ?>
            <?php if ($u['rol'] !== 'admin'): ?>
            <div class="vend-card"
                 data-nombre="<?php echo strtolower(htmlspecialchars($u['nombre'])); ?>"
                 data-correo="<?php echo strtolower(htmlspecialchars($u['correo'] ?? '')); ?>"
                 data-estado="<?php echo $u['estado'] ? 'activo' : 'inactivo'; ?>">

                <div class="vend-card-top">
                    <div class="vend-card-avatar">
                        <?php echo mb_strtoupper(mb_substr($u['nombre'], 0, 1)); ?>
                    </div>
                    <span class="badge <?php echo $u['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <?php echo $u['estado'] ? '● Activo' : '● Inactivo'; ?>
                    </span>
                </div>

                <div class="vend-card-info">
                    <h4 class="vend-card-nombre"><?php echo htmlspecialchars($u['nombre']); ?></h4>
                    <div class="vend-card-meta">
                        <i class="bi bi-envelope"></i>
                        <span><?php echo htmlspecialchars($u['correo'] ?? '—'); ?></span>
                    </div>
                    <div class="vend-card-meta">
                        <i class="bi bi-person-badge"></i>
                        <span>Vendedor</span>
                    </div>
                </div>

                <div class="vend-card-footer">
                    <button class="btn-icono-sm editar"
                            onclick="abrirModalEditar(
                                <?php echo $u['id_usuario']; ?>,
                                '<?php echo addslashes(htmlspecialchars($u['nombre'])); ?>',
                                '<?php echo addslashes(htmlspecialchars($u['correo'] ?? '')); ?>'
                            )" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <?php if ($u['id_usuario'] !== $_SESSION['id_usuario']): ?>
                        <?php if ($u['estado']): ?>
                        <a href="?accion=desactivar&id=<?php echo $u['id_usuario']; ?>"
                           class="btn-icono-sm eliminar" title="Desactivar"
                           onclick="return confirm('¿Desactivar a <?php echo htmlspecialchars($u['nombre']); ?>?')">
                            <i class="bi bi-slash-circle"></i>
                        </a>
                        <?php else: ?>
                        <a href="?accion=reactivar&id=<?php echo $u['id_usuario']; ?>"
                           class="btn-icono-sm reactivar" title="Reactivar">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="sin-resultados" id="sinResultados" style="display:none;">
        <i class="bi bi-search"></i>
        <p>Sin resultados para tu búsqueda.</p>
    </div>

</div>

<!-- ======================================================
     MODAL — CREAR VENDEDOR
     ====================================================== -->
<div class="modal-overlay" id="modalCrear" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="bi bi-person-plus-fill"></i> Nuevo Vendedor</h3>
            <button class="modal-cerrar" onclick="cerrarModal('modalCrear')" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="?accion=crear">
                <div class="campo">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" placeholder="Ej. Roberto Gómez"
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                           maxlength="100" required>
                </div>
                <div class="campo">
                    <label>Correo electrónico</label>
                    <input type="email" name="correo" placeholder="rgomez@gmail.com"
                           value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                           maxlength="100" required>
                </div>
                <div class="campo">
                    <label>Contraseña</label>
                    <div class="input-password">
                        <input type="password" id="pwCrear" name="contrasena" placeholder="••••••••" required>
                        <button type="button" class="btn-ojo" onclick="togglePw('pwCrear', this)" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar-modal" onclick="cerrarModal('modalCrear')">Cancelar</button>
                    <button type="submit" class="btn-guardar-modal">
                        <i class="bi bi-check-lg"></i> Guardar vendedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================
     MODAL — EDITAR VENDEDOR
     ====================================================== -->
<div class="modal-overlay" id="modalEditar" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="bi bi-pencil-fill"></i> Editar Vendedor</h3>
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
                    <label>Correo electrónico</label>
                    <input type="email" id="editCorreo" name="correo" maxlength="100" required>
                </div>
                <div class="campo">
                    <label>Nueva contraseña <span class="label-opcional">(dejar vacío para no cambiar)</span></label>
                    <div class="input-password">
                        <input type="password" id="pwEditar" name="contrasena" placeholder="••••••••">
                        <button type="button" class="btn-ojo" onclick="togglePw('pwEditar', this)" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar-modal" onclick="cerrarModal('modalEditar')">Cancelar</button>
                    <button type="submit" class="btn-guardar-modal">
                        <i class="bi bi-check-lg"></i> Actualizar vendedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales — declaradas primero
let vistaActual  = sessionStorage.getItem('vendVista') || 'cards';
let estadoFiltro = 'todos';

// ── Funciones ──────────────────────────────
function cambiarVista(tipo) {
    vistaActual = tipo;
    sessionStorage.setItem('vendVista', tipo);
    const esTabla = tipo === 'tabla';
    document.getElementById('vistaTabla').style.display = esTabla ? '' : 'none';
    document.getElementById('vistaCards').style.display = esTabla ? 'none' : '';
    document.getElementById('btnVista1').classList.toggle('active', esTabla);
    document.getElementById('btnVista2').classList.toggle('active', !esTabla);
    filtrar();
}

function filtrar() {
    const q = document.getElementById('inputBuscar').value.toLowerCase().trim();
    let visibles = 0;

    document.querySelectorAll('#tablaVendedores tbody tr[data-nombre]').forEach(el => {
        const texto   = (el.dataset.nombre + ' ' + el.dataset.correo).toLowerCase();
        const okQ     = !q || texto.includes(q);
        const okState = estadoFiltro === 'todos' || el.dataset.estado === estadoFiltro;
        const mostrar = okQ && okState;
        el.style.display = mostrar ? '' : 'none';
        if (mostrar && vistaActual === 'tabla') visibles++;
    });

    document.querySelectorAll('.vend-card').forEach(el => {
        const texto   = (el.dataset.nombre + ' ' + el.dataset.correo).toLowerCase();
        const okQ     = !q || texto.includes(q);
        const okState = estadoFiltro === 'todos' || el.dataset.estado === estadoFiltro;
        const mostrar = okQ && okState;
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

function abrirModalEditar(id, nombre, correo) {
    document.getElementById('formEditar').action  = '?accion=editar&id=' + id;
    document.getElementById('editNombre').value   = nombre;
    document.getElementById('editCorreo').value   = correo;
    document.getElementById('pwEditar').value     = '';
    abrirModal('modalEditar');
    setTimeout(() => document.getElementById('editNombre').focus(), 100);
}

function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// ── Inicialización ──────────────────────────
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

<?php if ($abrir_modal_crear): ?>
    window.addEventListener('load', () => abrirModalCrear());
<?php endif; ?>

<?php if ($abrir_modal_editar): ?>
    window.addEventListener('load', () => abrirModalEditar(
        <?php echo $id; ?>,
        '<?php echo addslashes(htmlspecialchars($_POST['nombre'] ?? '')); ?>',
        '<?php echo addslashes(htmlspecialchars($_POST['correo'] ?? '')); ?>'
    ));
<?php endif; ?>
</script>

</body>
</html>