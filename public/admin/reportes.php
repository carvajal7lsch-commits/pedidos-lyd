<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../models/Reporte.php';

// ── Parámetros de filtro ───────────────────────────────────
$reporte    = $_GET['reporte']    ?? 'vendedor';
$desde      = $_GET['desde']      ?? date('Y-m-01');       // primer día del mes
$hasta      = $_GET['hasta']      ?? date('Y-m-d');        // hoy
$agrupacion = $_GET['agrupacion'] ?? 'dia';

$reportes_validos    = ['vendedor', 'periodo', 'productos', 'deuda', 'cierres'];
$agrupaciones_validas = ['dia', 'semana', 'mes'];

if (!in_array($reporte,    $reportes_validos))    $reporte    = 'vendedor';
if (!in_array($agrupacion, $agrupaciones_validas)) $agrupacion = 'dia';

// ── Cargar datos según reporte activo ─────────────────────
$datos     = [];
$totales   = [];

switch ($reporte) {
    case 'vendedor':
        $datos = getVentasPorVendedor($conexion, $desde, $hasta);
        $totales = [
            'ventas'   => array_sum(array_column($datos, 'num_ventas')),
            'contado'  => array_sum(array_column($datos, 'total_contado')),
            'credito'  => array_sum(array_column($datos, 'total_credito')),
            'general'  => array_sum(array_column($datos, 'total_general')),
        ];
        break;

    case 'periodo':
        $datos = getVentasPorPeriodo($conexion, $agrupacion, $desde, $hasta);
        $totales = [
            'ventas'  => array_sum(array_column($datos, 'num_ventas')),
            'contado' => array_sum(array_column($datos, 'total_contado')),
            'credito' => array_sum(array_column($datos, 'total_credito')),
            'general' => array_sum(array_column($datos, 'total_general')),
        ];
        break;

    case 'productos':
        $datos = getProductosMasVendidos($conexion, $desde, $hasta);
        $totales = [
            'unidades' => array_sum(array_column($datos, 'unidades_vendidas')),
            'ingresos' => array_sum(array_column($datos, 'ingresos_total')),
            'ventas'   => array_sum(array_column($datos, 'num_ventas')),
        ];
        break;

    case 'deuda':
        $datos = getClientesConDeuda($conexion);
        $totales = [
            'clientes'  => count($datos),
            'credito'   => array_sum(array_column($datos, 'total_credito')),
            'abonado'   => array_sum(array_column($datos, 'total_abonado')),
            'pendiente' => array_sum(array_column($datos, 'saldo_pendiente')),
        ];
        break;

    case 'cierres':
        $datos = getCierresDiarios($conexion, $desde, $hasta);
        $totales = [
            'cierres'  => count($datos),
            'contado'  => array_sum(array_column($datos, 'total_contado')),
            'credito'  => array_sum(array_column($datos, 'total_credito')),
            'general'  => array_sum(array_column($datos, 'total_general')),
        ];
        break;
}

// ── Etiquetas de tabs ──────────────────────────────────────
$tabs = [
    'vendedor'  => ['icon' => 'bi-person-lines-fill',  'label' => 'Por Vendedor'],
    'periodo'   => ['icon' => 'bi-calendar3',           'label' => 'Por Período'],
    'productos' => ['icon' => 'bi-bag-fill',            'label' => 'Más Vendidos'],
    'deuda'     => ['icon' => 'bi-credit-card-2-back',  'label' => 'Deuda Clientes'],
    'cierres'   => ['icon' => 'bi-journal-check',       'label' => 'Cierres Diarios'],
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes —
        <?php echo APP_NAME; ?>
    </title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/reportes.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- jsPDF + AutoTable para exportar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <!-- SheetJS para exportar Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>

<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>

    <div class="cont">

        <!-- ══ CABECERA ══════════════════════════════════════════ -->
        <div class="pagina-header">
            <div>
                <h1>Reportes</h1>
                <p>Análisis de ventas, productos y cartera ·
                    <?php echo fecha_es('F Y'); ?>
                </p>
            </div>
        </div>

        <!-- ══ TABS ══════════════════════════════════════════════ -->
        <div class="report-tabs" role="tablist">
            <?php foreach ($tabs as $key => $tab): ?>
            <a href="?reporte=<?php echo $key; ?>&desde=<?php echo urlencode($desde); ?>&hasta=<?php echo urlencode($hasta); ?>&agrupacion=<?php echo $agrupacion; ?>"
                class="report-tab <?php echo $reporte === $key ? 'active' : ''; ?>" role="tab">
                <i class="bi <?php echo $tab['icon']; ?>"></i>
                <span>
                    <?php echo $tab['label']; ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ══ FILTROS ═══════════════════════════════════════════ -->
        <form method="GET" action="" class="filtros-panel" id="formFiltros">
            <input type="hidden" name="reporte" value="<?php echo $reporte; ?>">

            <div class="filtro-grupo">
                <label for="desde">Desde</label>
                <input type="date" id="desde" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
            </div>

            <div class="filtro-grupo">
                <label for="hasta">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
            </div>

            <?php if ($reporte === 'periodo'): ?>
            <div class="filtro-grupo">
                <label for="agrupacion">Agrupar por</label>
                <select id="agrupacion" name="agrupacion">
                    <option value="dia" <?php echo $agrupacion==='dia' ? 'selected' : '' ; ?>>Día</option>
                    <option value="semana" <?php echo $agrupacion==='semana' ? 'selected' : '' ; ?>>Semana</option>
                    <option value="mes" <?php echo $agrupacion==='mes' ? 'selected' : '' ; ?>>Mes</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="filtros-acciones">
                <button type="submit" class="btn-filtrar">
                    <i class="bi bi-funnel-fill"></i> Aplicar
                </button>
                <button type="button" class="btn-export pdf" onclick="exportarPDF()" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                </button>
                <button type="button" class="btn-export excel" onclick="exportarExcel()" title="Exportar Excel">
                    <i class="bi bi-file-earmark-excel-fill"></i> Excel
                </button>
            </div>
        </form>

        <!-- ══ KPI RESUMEN ════════════════════════════════════════ -->
        <?php if (!empty($datos)): ?>
        <div class="kpi-row">

            <?php if ($reporte === 'vendedor' || $reporte === 'periodo' || $reporte === 'cierres'): ?>
            <div class="kpi-card accent">
                <div class="kpi-label">Total General</div>
                <div class="kpi-value" id="kpiTotal">
                    <?php echo formatPesos($totales['general']); ?>
                </div>
                <div class="kpi-sub">
                    <?php if (isset($totales['ventas'])): ?>
                    <?php echo $totales['ventas']; ?> ventas en el período
                    <?php elseif (isset($totales['cierres'])): ?>
                    <?php echo $totales['cierres']; ?> cierres en el período
                    <?php endif; ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Contado</div>
                <div class="kpi-value">
                    <?php echo formatPesos($totales['contado']); ?>
                </div>
                <div class="kpi-sub">
                    <?php echo $totales['general'] > 0 ? round($totales['contado']/$totales['general']*100) : 0; ?>% del
                    total
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Crédito</div>
                <div class="kpi-value">
                    <?php echo formatPesos($totales['credito']); ?>
                </div>
                <div class="kpi-sub">
                    <?php echo $totales['general'] > 0 ? round($totales['credito']/$totales['general']*100) : 0; ?>% del
                    total
                </div>
            </div>
            <?php if ($reporte === 'vendedor'): ?>
            <div class="kpi-card">
                <div class="kpi-label">Vendedores</div>
                <div class="kpi-value">
                    <?php echo count($datos); ?>
                </div>
                <div class="kpi-sub">con ventas en el período</div>
            </div>
            <?php endif; ?>

            <?php elseif ($reporte === 'productos'): ?>
            <div class="kpi-card accent">
                <div class="kpi-label">Ingresos Totales</div>
                <div class="kpi-value">
                    <?php echo formatPesos($totales['ingresos']); ?>
                </div>
                <div class="kpi-sub">top
                    <?php echo count($datos); ?> productos
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Unidades Vendidas</div>
                <div class="kpi-value">
                    <?php echo number_format($totales['unidades']); ?>
                </div>
                <div class="kpi-sub">en el período</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Transacciones</div>
                <div class="kpi-value">
                    <?php echo number_format($totales['ventas']); ?>
                </div>
                <div class="kpi-sub">ventas con estos productos</div>
            </div>

            <?php elseif ($reporte === 'deuda'): ?>
            <div class="kpi-card accent">
                <div class="kpi-label">Saldo Pendiente</div>
                <div class="kpi-value">
                    <?php echo formatPesos($totales['pendiente']); ?>
                </div>
                <div class="kpi-sub">
                    <?php echo $totales['clientes']; ?> clientes con deuda
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Crédito Otorgado</div>
                <div class="kpi-value">
                    <?php echo formatPesos($totales['credito']); ?>
                </div>
                <div class="kpi-sub">en ventas a crédito</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Abonado</div>
                <div class="kpi-value">
                    <?php echo formatPesos($totales['abonado']); ?>
                </div>
                <div class="kpi-sub">
                    <?php echo $totales['credito'] > 0 ? round($totales['abonado']/$totales['credito']*100) : 0; ?>%
                    recuperado
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- ══ TABLA PRINCIPAL ════════════════════════════════════ -->
        <div class="report-section">
            <div class="report-section-header">
                <h2 class="report-section-title">
                    <i class="bi <?php echo $tabs[$reporte]['icon']; ?>"></i>
                    <?php echo $tabs[$reporte]['label']; ?>
                </h2>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <span class="report-count">
                        <?php echo count($datos); ?> registros
                    </span>
                    <div class="buscador-tabla">
                        <i class="bi bi-search"></i>
                        <input type="text" id="inputBuscar" placeholder="Buscar..." oninput="filtrarTabla(this.value)">
                    </div>
                </div>
            </div>

            <div class="tabla-scroll">
                <?php if (empty($datos)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>No hay datos para el período seleccionado.</p>
                </div>

                <?php elseif ($reporte === 'vendedor'): ?>
                <table class="tabla-reporte" id="tablaReporte">
                    <thead>
                        <tr>
                            <th onclick="sortTabla(0)">Vendedor <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(1)"># Ventas <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(2)">Contado <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(3)">Crédito <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(4)">Total <i class="bi bi-chevron-expand sort-icon"></i>
                            </th>
                            <th>Período</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $i => $row): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($row['vendedor']); ?>
                                </strong>
                            </td>
                            <td class="num">
                                <?php echo $row['num_ventas']; ?>
                            </td>
                            <td class="num monto-positivo">
                                <?php echo formatPesos($row['total_contado']); ?>
                            </td>
                            <td class="num monto-alerta">
                                <?php echo formatPesos($row['total_credito']); ?>
                            </td>
                            <td class="num"><strong>
                                    <?php echo formatPesos($row['total_general']); ?>
                                </strong></td>
                            <td class="muted">
                                <?php echo $row['primera_venta']; ?>
                                <?php if ($row['primera_venta'] !== $row['ultima_venta']): ?>
                                →
                                <?php echo $row['ultima_venta']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="tabla-footer">
                        <tr>
                            <td><strong>TOTAL</strong></td>
                            <td class="num">
                                <?php echo $totales['ventas']; ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['contado']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['credito']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['general']); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <?php elseif ($reporte === 'periodo'): ?>
                <table class="tabla-reporte" id="tablaReporte">
                    <thead>
                        <tr>
                            <th onclick="sortTabla(0)">Período <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(1)"># Ventas <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(2)">Contado <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(3)">Crédito <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(4)">Total <i class="bi bi-chevron-expand sort-icon"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $row): ?>
                        <tr>
                            <td><strong>
                                    <?php echo htmlspecialchars($row['periodo_label']); ?>
                                </strong></td>
                            <td class="num">
                                <?php echo $row['num_ventas']; ?>
                            </td>
                            <td class="num monto-positivo">
                                <?php echo formatPesos($row['total_contado']); ?>
                            </td>
                            <td class="num monto-alerta">
                                <?php echo formatPesos($row['total_credito']); ?>
                            </td>
                            <td class="num"><strong>
                                    <?php echo formatPesos($row['total_general']); ?>
                                </strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="tabla-footer">
                        <tr>
                            <td><strong>TOTAL</strong></td>
                            <td class="num">
                                <?php echo $totales['ventas']; ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['contado']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['credito']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['general']); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <?php elseif ($reporte === 'productos'): ?>
                <table class="tabla-reporte" id="tablaReporte">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th onclick="sortTabla(1)">Producto <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th onclick="sortTabla(2)">Categoría <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(3)">Unidades <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(4)">Ingresos <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(5)">P. Promedio <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num"># Ventas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $i => $row): ?>
                        <tr>
                            <td>
                                <?php
                            $rank = $i + 1;
                            $cls  = $rank <= 3 ? "rank-$rank" : "rank-n";
                            echo "<span class=\"rank-badge $cls\">$rank</span>";
                            ?>
                            </td>
                            <td><strong>
                                    <?php echo htmlspecialchars($row['producto']); ?>
                                </strong></td>
                            <td class="muted">
                                <?php echo htmlspecialchars($row['categoria']); ?>
                            </td>
                            <td class="num"><strong>
                                    <?php echo number_format($row['unidades_vendidas']); ?>
                                </strong></td>
                            <td class="num monto-positivo">
                                <?php echo formatPesos($row['ingresos_total']); ?>
                            </td>
                            <td class="num muted">
                                <?php echo formatPesos($row['precio_promedio']); ?>
                            </td>
                            <td class="num">
                                <?php echo $row['num_ventas']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="tabla-footer">
                        <tr>
                            <td></td>
                            <td><strong>TOTAL</strong></td>
                            <td></td>
                            <td class="num">
                                <?php echo number_format($totales['unidades']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['ingresos']); ?>
                            </td>
                            <td></td>
                            <td class="num">
                                <?php echo number_format($totales['ventas']); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <?php elseif ($reporte === 'deuda'): ?>
                <?php $max_deuda = !empty($datos) ? max(array_column($datos, 'saldo_pendiente')) : 1; ?>
                <table class="tabla-reporte" id="tablaReporte">
                    <thead>
                        <tr>
                            <th onclick="sortTabla(0)">Cliente <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th>Teléfono</th>
                            <th onclick="sortTabla(2)"># Compras <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(3)">Total Crédito <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(4)">Abonado <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(5)">Saldo Pendiente <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th>Última Compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $row): ?>
                        <?php
                        $pct = $max_deuda > 0 ? ($row['saldo_pendiente'] / $max_deuda * 100) : 0;
                        $alta = $row['saldo_pendiente'] > ($max_deuda * 0.5);
                    ?>
                        <tr>
                            <td><strong>
                                    <?php echo htmlspecialchars($row['cliente']); ?>
                                </strong>
                                <div style="font-size:11.5px;color:#94a3b8;margin-top:2px">
                                    <?php echo htmlspecialchars($row['direccion']); ?>
                                </div>
                            </td>
                            <td class="muted">
                                <?php echo htmlspecialchars($row['telefono'] ?? '—'); ?>
                            </td>
                            <td class="num">
                                <?php echo $row['num_ventas_credito']; ?>
                            </td>
                            <td class="num muted">
                                <?php echo formatPesos($row['total_credito']); ?>
                            </td>
                            <td class="num monto-positivo">
                                <?php echo formatPesos($row['total_abonado']); ?>
                            </td>
                            <td class="num">
                                <div class="deuda-bar-wrap">
                                    <span class="<?php echo $alta ? 'monto-peligro' : 'monto-alerta'; ?>">
                                        <strong>
                                            <?php echo formatPesos($row['saldo_pendiente']); ?>
                                        </strong>
                                    </span>
                                    <div class="deuda-bar">
                                        <div class="deuda-bar-fill <?php echo $alta ? 'alta' : ''; ?>"
                                            style="width:<?php echo round($pct); ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="muted">
                                <?php echo $row['ultima_compra']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="tabla-footer">
                        <tr>
                            <td colspan="3"><strong>TOTAL (
                                    <?php echo $totales['clientes']; ?> clientes)
                                </strong></td>
                            <td class="num">
                                <?php echo formatPesos($totales['credito']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['abonado']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['pendiente']); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <?php elseif ($reporte === 'cierres'): ?>
                <table class="tabla-reporte" id="tablaReporte">
                    <thead>
                        <tr>
                            <th onclick="sortTabla(0)">Fecha <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th onclick="sortTabla(1)">Vendedor <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(2)">Contado <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(3)">Crédito <i
                                    class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(4)">Total <i class="bi bi-chevron-expand sort-icon"></i>
                            </th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $row): ?>
                        <tr>
                            <td><strong>
                                    <?php echo $row['fecha']; ?>
                                </strong></td>
                            <td>
                                <?php echo htmlspecialchars($row['vendedor']); ?>
                            </td>
                            <td class="num monto-positivo">
                                <?php echo formatPesos($row['total_contado']); ?>
                            </td>
                            <td class="num monto-alerta">
                                <?php echo formatPesos($row['total_credito']); ?>
                            </td>
                            <td class="num"><strong>
                                    <?php echo formatPesos($row['total_general']); ?>
                                </strong></td>
                            <td>
                                <?php if ($row['estado']): ?>
                                <span class="badge-tipo badge-cerrado"><i class="bi bi-check-circle-fill"></i>
                                    Cerrado</span>
                                <?php else: ?>
                                <span class="badge-tipo badge-abierto"><i class="bi bi-clock"></i> Abierto</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="tabla-footer">
                        <tr>
                            <td colspan="2"><strong>TOTAL (
                                    <?php echo $totales['cierres']; ?> cierres)
                                </strong></td>
                            <td class="num">
                                <?php echo formatPesos($totales['contado']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['credito']); ?>
                            </td>
                            <td class="num">
                                <?php echo formatPesos($totales['general']); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div><!-- /tabla-scroll -->
        </div><!-- /report-section -->

    </div><!-- /cont -->

    <script>
        // ── Constantes para exports ──────────────────────────────
        const REPORTE = '<?php echo $reporte; ?>';
        const DESDE = '<?php echo $desde; ?>';
        const HASTA = '<?php echo $hasta; ?>';
        const TITULO_TAB = <?php echo json_encode($tabs[$reporte]['label']); ?>;
        const EMPRESA = '<?php echo EMPRESA_NOMBRE; ?>';
        const NIT = '<?php echo EMPRESA_NIT; ?>';

        // ── Ordenar tabla ─────────────────────────────────────────
        let sortDir = {};

        function sortTabla(col) {
            const tabla = document.getElementById('tablaReporte');
            if (!tabla) return;
            const tbody = tabla.tBodies[0];
            const rows = Array.from(tbody.rows);

            sortDir[col] = sortDir[col] === 'asc' ? 'desc' : 'asc';
            const dir = sortDir[col] === 'asc' ? 1 : -1;

            rows.sort((a, b) => {
                const va = a.cells[col]?.textContent.trim().replace(/[$.,\s]/g, '') || '';
                const vb = b.cells[col]?.textContent.trim().replace(/[$.,\s]/g, '') || '';
                const na = parseFloat(va), nb = parseFloat(vb);
                if (!isNaN(na) && !isNaN(nb)) return (na - nb) * dir;
                return va.localeCompare(vb) * dir;
            });

            rows.forEach(r => tbody.appendChild(r));

            // Actualizar ícono
            document.querySelectorAll('.tabla-reporte thead th').forEach((th, i) => {
                th.classList.toggle('sorted', i === col);
                const ico = th.querySelector('.sort-icon');
                if (ico && i === col) {
                    ico.className = 'bi sort-icon ' + (sortDir[col] === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down');
                } else if (ico) {
                    ico.className = 'bi bi-chevron-expand sort-icon';
                }
            });
        }

        // ── Filtrar tabla con buscador ────────────────────────────
        function filtrarTabla(q) {
            const tabla = document.getElementById('tablaReporte');
            if (!tabla) return;
            const q_lower = q.toLowerCase();
            Array.from(tabla.tBodies[0].rows).forEach(row => {
                const texto = row.textContent.toLowerCase();
                row.style.display = texto.includes(q_lower) ? '' : 'none';
            });
        }

        // ── Exportar PDF ──────────────────────────────────────────
        function exportarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

            const azul = [24, 85, 207];
            const oscuro = [30, 42, 58];
            const gris = [100, 116, 139];

            // Header
            doc.setFillColor(...azul);
            doc.rect(0, 0, 297, 22, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text(EMPRESA + ' · Reporte: ' + TITULO_TAB, 12, 14);
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.text('NIT: ' + NIT + '   Período: ' + DESDE + ' → ' + HASTA, 12, 20);
            doc.text('Generado: ' + new Date().toLocaleString('es-CO'), 220, 20);

            // Tabla
            const tabla = document.getElementById('tablaReporte');
            if (!tabla) return;

            const headers = Array.from(tabla.querySelectorAll('thead th')).map(th => {
                return th.cloneNode(true).querySelector('.sort-icon')?.remove() || th.textContent.trim();
            });
            // Re-obtener después del remove
            const headData = Array.from(tabla.querySelectorAll('thead th')).map(th => {
                const clone = th.cloneNode(true);
                clone.querySelector('.sort-icon')?.remove();
                return clone.textContent.trim();
            });

            const bodyData = Array.from(tabla.querySelectorAll('tbody tr'))
                .filter(r => r.style.display !== 'none')
                .map(row => Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim().replace(/\s+/g, ' ')));

            doc.autoTable({
                head: [headData],
                body: bodyData,
                startY: 26,
                styles: { fontSize: 9, cellPadding: 3, textColor: oscuro },
                headStyles: { fillColor: [241, 245, 249], textColor: gris, fontStyle: 'bold', fontSize: 8 },
                alternateRowStyles: { fillColor: [248, 249, 251] },
                foot: [Array.from(tabla.querySelectorAll('tfoot td')).map(td => td.textContent.trim())],
                footStyles: { fillColor: [241, 245, 249], fontStyle: 'bold', textColor: oscuro },
                margin: { left: 10, right: 10 },
            });

            doc.save('reporte_' + REPORTE + '_' + DESDE + '_' + HASTA + '.pdf');
        }

        // ── Exportar Excel ────────────────────────────────────────
        function exportarExcel() {
            const tabla = document.getElementById('tablaReporte');
            if (!tabla) return;

            const wb = XLSX.utils.book_new();

            // Filtrar filas visibles
            const headRow = Array.from(tabla.querySelectorAll('thead th')).map(th => {
                const clone = th.cloneNode(true);
                clone.querySelector('.sort-icon')?.remove();
                return clone.textContent.trim();
            });
            const bodyRows = Array.from(tabla.querySelectorAll('tbody tr'))
                .filter(r => r.style.display !== 'none')
                .map(row => Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim().replace(/\s+/g, ' ')));
            const footRows = [Array.from(tabla.querySelectorAll('tfoot td')).map(td => td.textContent.trim())];

            const wsData = [headRow, ...bodyRows, [], ...footRows];
            const ws = XLSX.utils.aoa_to_sheet(wsData);

            // Ancho automático
            ws['!cols'] = headRow.map((_, i) => ({
                wch: Math.max(12, ...wsData.map(row => (row[i] || '').toString().length + 2))
            }));

            XLSX.utils.book_append_sheet(wb, ws, TITULO_TAB.substring(0, 31));
            XLSX.writeFile(wb, 'reporte_' + REPORTE + '_' + DESDE + '_' + HASTA + '.xlsx');
        }
    </script>

</body>

</html>