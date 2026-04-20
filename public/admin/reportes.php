<?php
require_once __DIR__ . '/../../middlewares/AuthMiddleware.php';
soloAdmin();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../models/reporte.php';

// ── Parámetros de filtro ───────────────────────────────────
$reporte    = $_GET['reporte']    ?? 'vendedor';
$desde      = $_GET['desde']      ?? date('Y-m-01');       // primer día del mes
$hasta      = $_GET['hasta']      ?? date('Y-m-d');        // hoy
$agrupacion = $_GET['agrupacion'] ?? 'dia';

$reportes_validos    = ['vendedor', 'periodo', 'productos', 'deuda', 'cierres', 'facturas'];
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
        $datos = getClientesConDeuda($conexion, $desde, $hasta);
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

    case 'facturas':
        $datos = getDetalleFacturas($conexion, null, $desde, $hasta);
        $totales = [
            'facturas' => count($datos),
            'contado'  => array_sum(array_column(array_filter($datos, fn($d) => $d['tipo_venta'] == 'contado'), 'total')),
            'credito'  => array_sum(array_column(array_filter($datos, fn($d) => $d['tipo_venta'] == 'credito'), 'total')),
            'general'  => array_sum(array_column($datos, 'total')),
            'recaudado'=> array_sum(array_column($datos, 'recaudado')),
        ];
        break;
}

// ── Etiquetas de tabs ──────────────────────────────────────
$tabs = [
    'facturas'  => ['icon' => 'bi-receipt',             'label' => 'Historial Ventas'],
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
    <style>
        /* Quick Date Filters */
        .btn-quick-date {
            background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 0 16px;
            border-radius: 12px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;
            height: 42px; display: flex; align-items: center; justify-content: center;
        }
        .btn-quick-date:hover { background: #e2e8f0; color: #0f172a; }

        /* Sleek Button */
        .btn-sleek {
            background: rgba(24, 85, 207, 0.1); color: #1855CF; border: none; padding: 6px 14px;
            border-radius: 20px; font-weight: 600; font-size: 0.8rem; cursor: pointer;
            transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-sleek:hover { background: #1855CF; color: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(24,85,207,0.2); }
        
        /* Premium Modal */
        .modal-factura-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px); z-index: 1000; display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s ease; padding: 20px;
        }
        .modal-factura-overlay.show { display: flex; opacity: 1; }
        .modal-factura-content {
            background: #ffffff; border-radius: 20px; width: 100%; max-width: 600px;
            max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: translateY(20px) scale(0.95);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-factura-overlay.show .modal-factura-content { transform: translateY(0) scale(1); }
        .mf-header {
            padding: 24px 30px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between;
            align-items: center; background: #f8fafc;
        }
        .mf-title-wrap { display: flex; flex-direction: column; gap: 4px; }
        .mf-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; display:flex; align-items:center; gap:8px;}
        .mf-subtitle { font-size: 0.85rem; color: #64748b; font-weight: 500; }
        .mf-close {
            background: white; border: 1px solid #e2e8f0; width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b;
            transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .mf-close:hover { background: #f1f5f9; color: #ef4444; border-color: #ef4444; transform: rotate(90deg); }
        .mf-body { padding: 30px; overflow-y: auto; flex: 1; }
        .mf-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px; }
        .mf-info-item { background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #f1f5f9; }
        .mf-info-item label { display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        .mf-info-item .value { font-size: 0.95rem; font-weight: 600; color: #0f172a; display:flex; align-items:center; gap:6px; }
        .mf-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .mf-table th { background: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 12px 16px; text-align: left; }
        .mf-table th:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
        .mf-table th:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; text-align: right;}
        .mf-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .mf-prod-name { font-weight: 600; color: #1e293b; font-size: 0.9rem;}
        .mf-prod-price { font-size: 0.8rem; color: #64748b; margin-top: 2px;}
        .mf-footer { padding: 24px 30px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .mf-total-wrap { display: flex; flex-direction: column; }
        .mf-total-label { font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .mf-total-monto { font-size: 1.8rem; font-weight: 800; color: #1855CF; line-height: 1; margin-top: 4px;}
        .btn-download-pdf {
            background: linear-gradient(135deg, #1855CF 0%, #113a91 100%); color: white; border: none; padding: 12px 24px;
            border-radius: 12px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 10px 20px -10px rgba(24,85,207,0.5);
        }
        .btn-download-pdf:hover { transform: translateY(-2px); box-shadow: 0 15px 25px -10px rgba(24,85,207,0.6); }
        .btn-download-pdf:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
        .loader-wrap { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; color: #64748b; }
        .loader-spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #1855CF; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
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
        <form method="GET" action="" class="filtros-panel" id="formFiltros" style="align-items: flex-end;">
            <input type="hidden" name="reporte" value="<?php echo $reporte; ?>">

            <div class="filtro-grupo" style="flex-direction: row; align-items: flex-end; gap: 8px; margin-right: 15px;">
                <button type="button" class="btn-quick-date" onclick="setQuickDate('hoy')">Hoy</button>
                <button type="button" class="btn-quick-date" onclick="setQuickDate('ayer')">Ayer</button>
                <button type="button" class="btn-quick-date" onclick="setQuickDate('mes')">Este Mes</button>
            </div>

            <div class="filtro-grupo">
                <label for="desde">Desde</label>
                <input type="date" id="desde" name="desde" value="<?php echo htmlspecialchars($desde); ?>" onchange="document.getElementById('formFiltros').submit()">
            </div>

            <div class="filtro-grupo">
                <label for="hasta">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>" onchange="document.getElementById('formFiltros').submit()">
            </div>

            <?php if ($reporte === 'periodo'): ?>
            <div class="filtro-grupo">
                <label for="agrupacion">Agrupar por</label>
                <select id="agrupacion" name="agrupacion" onchange="document.getElementById('formFiltros').submit()">
                    <option value="dia" <?php echo $agrupacion==='dia' ? 'selected' : '' ; ?>>Día</option>
                    <option value="semana" <?php echo $agrupacion==='semana' ? 'selected' : '' ; ?>>Semana</option>
                    <option value="mes" <?php echo $agrupacion==='mes' ? 'selected' : '' ; ?>>Mes</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="filtros-acciones">
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

            <?php elseif ($reporte === 'facturas'): ?>
            <div class="kpi-card accent">
                <div class="kpi-label">Total Vendido</div>
                <div class="kpi-value"><?php echo formatPesos($totales['general']); ?></div>
                <div class="kpi-sub"><?php echo $totales['facturas']; ?> facturas en el período</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Contado</div>
                <div class="kpi-value"><?php echo formatPesos($totales['contado']); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Crédito</div>
                <div class="kpi-value"><?php echo formatPesos($totales['credito']); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Recaudado</div>
                <div class="kpi-value"><?php echo formatPesos($totales['recaudado']); ?></div>
                <div class="kpi-sub">Total dinero ingresado</div>
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

                <?php elseif ($reporte === 'facturas'): ?>
                <table class="tabla-reporte" id="tablaReporte">
                    <thead>
                        <tr>
                            <th onclick="sortTabla(0)">Factura <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th onclick="sortTabla(1)">Fecha <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th onclick="sortTabla(2)">Cliente <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th onclick="sortTabla(3)">Vendedor <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th onclick="sortTabla(4)">Tipo <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(5)">Total <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th class="num" onclick="sortTabla(6)">Recaudado <i class="bi bi-chevron-expand sort-icon"></i></th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos as $row): ?>
                        <tr>
                            <?php $anio = date('Y', strtotime($row['fecha'])); ?>
                            <td><strong>#ORD-<?php echo $anio . '-' . str_pad($row['id_venta'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td class="muted"><?php echo $row['fecha']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['cliente'] ?? 'Consumidor Final'); ?></strong></td>
                            <td class="muted"><?php echo htmlspecialchars($row['vendedor']); ?></td>
                            <td>
                                <?php if ($row['tipo_venta'] === 'contado'): ?>
                                <span style="color:#10b981;background:#ecfdf5;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600;">Contado</span>
                                <?php else: ?>
                                <span style="color:#f59e0b;background:#fffbeb;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600;">Crédito</span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><strong><?php echo formatPesos($row['total']); ?></strong></td>
                            <td class="num <?php echo $row['recaudado'] >= $row['total'] ? 'monto-positivo' : 'monto-alerta'; ?>">
                                <?php echo formatPesos($row['recaudado']); ?>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" onclick="verFactura(<?php echo $row['id_venta']; ?>, '<?php echo $anio; ?>')" class="btn-sleek">
                                    <i class="bi bi-eye"></i> Ver Detalle
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="tabla-footer">
                        <tr>
                            <td colspan="5"><strong>TOTAL (<?php echo $totales['facturas']; ?> facturas)</strong></td>
                            <td class="num"><?php echo formatPesos($totales['general']); ?></td>
                            <td class="num"><?php echo formatPesos($totales['recaudado']); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div><!-- /tabla-scroll -->
        </div><!-- /report-section -->

    </div><!-- /cont -->

    <!-- ══ MODAL FACTURA ══════════════════════════════════════════ -->
    <div class="modal-factura-overlay" id="modalFactura" onclick="if(event.target === this) cerrarModalFactura()">
        <div class="modal-factura-content" style="max-width: 420px; padding: 0; background: transparent; box-shadow: none;">
            <div style="background: #fff; border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; height: 85vh; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                <div class="mf-header" style="padding: 16px 20px;">
                    <div class="mf-title-wrap">
                        <h3 class="mf-title" id="mfIdFactura"><i class="bi bi-receipt"></i> Ticket</h3>
                    </div>
                    <button class="mf-close" onclick="cerrarModalFactura()"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="mf-body" id="mfBodyContent" style="padding: 0; flex: 1; overflow: hidden; background: #f4f5f7;">
                    <!-- Iframe se inyectará aquí -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function setQuickDate(rango) {
            const form = document.getElementById('formFiltros');
            const dDesde = document.getElementById('desde');
            const dHasta = document.getElementById('hasta');
            
            const hoy = new Date();
            const format = date => {
                const tzoffset = date.getTimezoneOffset() * 60000; 
                return new Date(date - tzoffset).toISOString().split('T')[0];
            };

            if (rango === 'hoy') {
                dDesde.value = format(hoy);
                dHasta.value = format(hoy);
            } else if (rango === 'ayer') {
                const ayer = new Date(hoy);
                ayer.setDate(ayer.getDate() - 1);
                dDesde.value = format(ayer);
                dHasta.value = format(ayer);
            } else if (rango === 'mes') {
                const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                dDesde.value = format(primerDia);
                dHasta.value = format(hoy);
            }
            
            form.submit();
        }

        function verFactura(id, anioVenta = '') {
            const overlay = document.getElementById('modalFactura');
            const bodyContent = document.getElementById('mfBodyContent');
            const title = document.getElementById('mfIdFactura');

            title.innerHTML = `<i class="bi bi-receipt"></i> Ticket #ORD-${anioVenta}-${String(id).padStart(4, '0')}`;
            
            bodyContent.innerHTML = `
                <div class="loader-wrap" style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div class="loader-spinner"></div>
                    <div>Cargando ticket...</div>
                </div>
            `;
            
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Usar setTimeout para permitir que el modal se anime antes de inyectar el iframe
            setTimeout(() => {
                bodyContent.innerHTML = `<iframe src="comprobante_ticket.php?id=${id}&iframe=1" style="width: 100%; height: 100%; border: none; background: #fff;"></iframe>`;
            }, 300);
        }

        function cerrarModalFactura() {
            document.getElementById('modalFactura').classList.remove('show');
            document.body.style.overflow = '';
            setTimeout(() => {
                document.getElementById('mfBodyContent').innerHTML = ''; // Limpiar iframe al cerrar
            }, 300);
        }

        function descargarFacturaPDF() {
            if (!facturaActualData) return;
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });
            
            const f = facturaActualData.factura;
            const d = facturaActualData.detalles;
            const formatPesos = val => '$ ' + parseFloat(val).toLocaleString('es-CO');

            // Header
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(22);
            doc.setTextColor(24, 85, 207);
            doc.text(EMPRESA, 15, 20);
            
            doc.setFontSize(10);
            doc.setTextColor(100, 116, 139);
            doc.text('NIT: ' + NIT, 15, 26);
            
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(15, 23, 42);
            doc.text('Factura: ', 140, 20);
            doc.setFont('helvetica', 'bold');
            doc.text('FAC-' + String(f.id_venta).padStart(3, '0'), 160, 20);
            
            doc.setFont('helvetica', 'normal');
            doc.text('Fecha: ', 140, 26);
            doc.setFont('helvetica', 'bold');
            doc.text(f.fecha, 160, 26);

            doc.setFont('helvetica', 'normal');
            doc.text('Tipo: ', 140, 32);
            doc.setFont('helvetica', 'bold');
            doc.text(f.tipo_venta.toUpperCase(), 160, 32);

            // Line
            doc.setDrawColor(226, 232, 240);
            doc.line(15, 40, 200, 40);

            // Info Box
            doc.setFontSize(11);
            doc.setTextColor(100, 116, 139);
            doc.setFont('helvetica', 'normal');
            doc.text('CLIENTE', 15, 50);
            doc.setTextColor(15, 23, 42);
            doc.setFont('helvetica', 'bold');
            doc.text(f.cliente_nombre, 15, 56);
            if (f.telefono) {
                doc.setFont('helvetica', 'normal');
                doc.text(f.telefono, 15, 62);
            }

            doc.setTextColor(100, 116, 139);
            doc.setFont('helvetica', 'normal');
            doc.text('VENDEDOR', 110, 50);
            doc.setTextColor(15, 23, 42);
            doc.setFont('helvetica', 'bold');
            doc.text(f.vendedor_nombre, 110, 56);

            // Table
            const bodyData = d.map(prod => [
                prod.producto,
                prod.cantidad,
                formatPesos(prod.precio_unitario),
                formatPesos(prod.subtotal)
            ]);

            doc.autoTable({
                startY: 75,
                head: [['Producto', 'Cant.', 'V. Unitario', 'Subtotal']],
                body: bodyData,
                theme: 'striped',
                headStyles: { fillColor: [24, 85, 207], textColor: 255, fontStyle: 'bold' },
                columnStyles: {
                    1: { halign: 'center' },
                    2: { halign: 'right' },
                    3: { halign: 'right', fontStyle: 'bold' }
                },
                styles: { fontSize: 10, cellPadding: 6 }
            });

            const finalY = doc.lastAutoTable.finalY + 15;
            
            doc.setFontSize(12);
            doc.setTextColor(100, 116, 139);
            doc.setFont('helvetica', 'normal');
            doc.text('TOTAL A PAGAR:', 130, finalY);
            doc.setFontSize(16);
            doc.setTextColor(24, 85, 207);
            doc.setFont('helvetica', 'bold');
            doc.text(formatPesos(f.total), 170, finalY, { align: 'left' });

            if (f.tipo_venta === 'credito') {
                doc.setFontSize(10);
                doc.setTextColor(22, 101, 52); // green
                doc.text('Recaudado: ' + formatPesos(f.recaudado), 130, finalY + 8);
                doc.setTextColor(153, 27, 27); // red
                doc.text('Saldo Pendiente: ' + formatPesos(f.total - f.recaudado), 130, finalY + 14);
            }

            doc.save(`Factura_FAC-${String(f.id_venta).padStart(3, '0')}.pdf`);
        }

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