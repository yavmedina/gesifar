<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Filtros
$id_material = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo_movimiento = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Si hay ID de material, obtener sus datos
$material = null;
if($id_material > 0) {
    $query = "SELECT * FROM material WHERE id_material = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id_material);
    $stmt->execute();
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Construir query de movimientos
$where = [];
$params = [];

if($id_material > 0) {
    $where[] = "ms.id_material = :id_material";
    $params[':id_material'] = $id_material;
}

if($tipo_movimiento) {
    $where[] = "ms.tipo_movimiento = :tipo";
    $params[':tipo'] = $tipo_movimiento;
}

if($fecha_desde) {
    $where[] = "ms.fecha >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if($fecha_hasta) {
    $where[] = "ms.fecha <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Paginación
$por_pagina = 50;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Contar total
$query_count = "SELECT COUNT(*) as total 
                FROM movimiento_stock ms 
                JOIN material m ON ms.id_material = m.id_material 
                $where_clause";
$stmt_count = $db->prepare($query_count);
foreach($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener movimientos
$query = "SELECT 
    ms.*,
    m.codigo,
    m.nombre AS material_nombre,
    CONCAT(p.apellido, ', ', p.nombre) AS responsable
FROM movimiento_stock ms
JOIN material m ON ms.id_material = m.id_material
LEFT JOIN personal p ON ms.id_personal = p.dni_personal
$where_clause
ORDER BY ms.fecha DESC, ms.hora DESC
LIMIT :offset, :limit";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$query_stats = "SELECT 
    SUM(CASE WHEN tipo_movimiento IN ('ingreso', 'ajuste_positivo') THEN cantidad ELSE 0 END) as total_ingresos,
    SUM(CASE WHEN tipo_movimiento IN ('egreso', 'ajuste_negativo', 'vencimiento') THEN cantidad ELSE 0 END) as total_egresos,
    COUNT(*) as total_movimientos
FROM movimiento_stock ms
JOIN material m ON ms.id_material = m.id_material
$where_clause";

$stmt_stats = $db->prepare($query_stats);
foreach($params as $key => $value) {
    $stmt_stats->bindValue($key, $value);
}
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Historial de Movimientos</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_movimientos.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📊 Historial de Movimientos</h1>
                    <p>Trazabilidad completa de ingresos y egresos</p>
                </div>
                <div>
                    <?php if($material): ?>
                        <a href="ver.php?id=<?php echo $material['id_material']; ?>" class="btn" style="background: #6c757d; color: white; margin-right: 10px;">← Volver al Material</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Ver Stock</a>
                </div>
            </div>
        </div>
        
        <!-- Header si es de un material específico -->
        <?php if($material): ?>
            <div class="material-header">
                <h3>📦 <?php echo htmlspecialchars($material['nombre']); ?></h3>
                <p><strong>Código:</strong> <?php echo htmlspecialchars($material['codigo']); ?> | <strong>Stock actual:</strong> <?php echo $material['stock_actual']; ?> unidades</p>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card stat-ingreso">
                <h3><?php echo number_format($stats['total_ingresos']); ?></h3>
                <p>➕ Total Ingresos</p>
            </div>
            
            <div class="stat-card stat-egreso">
                <h3><?php echo number_format($stats['total_egresos']); ?></h3>
                <p>➖ Total Egresos</p>
            </div>
            
            <div class="stat-card stat-total">
                <h3><?php echo number_format($stats['total_movimientos']); ?></h3>
                <p>📋 Total Movimientos</p>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="GET" action="" class="filters-bar">
            <?php if($id_material > 0): ?>
                <input type="hidden" name="id" value="<?php echo $id_material; ?>">
            <?php endif; ?>
            
            <div class="filter-group">
                <label>Tipo de Movimiento</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <option value="ingreso" <?php echo $tipo_movimiento == 'ingreso' ? 'selected' : ''; ?>>➕ Ingreso</option>
                    <option value="egreso" <?php echo $tipo_movimiento == 'egreso' ? 'selected' : ''; ?>>➖ Egreso</option>
                    <option value="ajuste_positivo" <?php echo $tipo_movimiento == 'ajuste_positivo' ? 'selected' : ''; ?>>🔼 Ajuste +</option>
                    <option value="ajuste_negativo" <?php echo $tipo_movimiento == 'ajuste_negativo' ? 'selected' : ''; ?>>🔽 Ajuste -</option>
                    <option value="vencimiento" <?php echo $tipo_movimiento == 'vencimiento' ? 'selected' : ''; ?>>⏰ Vencimiento</option>
                    <option value="devolucion" <?php echo $tipo_movimiento == 'devolucion' ? 'selected' : ''; ?>>↩️ Devolución</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Desde</label>
                <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
            </div>
            
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
            </div>
            
            <div class="filter-group" style="max-width: 100px;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Filtrar</button>
            </div>
            
            <div class="filter-group" style="max-width: 100px;">
                <label>&nbsp;</label>
                <a href="movimientos.php<?php echo $id_material > 0 ? '?id='.$id_material : ''; ?>" class="btn" style="width: 100%; text-align: center; background: #6c757d; color: white;">Limpiar</a>
            </div>
        </form>
        
        <!-- Tabla de movimientos -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <?php if($id_material == 0): ?>
                            <th>Material</th>
                        <?php endif; ?>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Stock</th>
                        <th>Lote</th>
                        <th>Motivo</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($movimientos) > 0): ?>
                        <?php foreach($movimientos as $mov): ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($mov['fecha'])); ?><br>
                                    <small style="color: #666;"><?php echo date('H:i', strtotime($mov['hora'])); ?>hs</small>
                                </td>
                                <?php if($id_material == 0): ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mov['material_nombre']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($mov['codigo']); ?></small>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge badge-<?php echo $mov['tipo_movimiento']; ?>">
                                        <?php echo str_replace('_', ' ', $mov['tipo_movimiento']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>
                                        <?php if(in_array($mov['tipo_movimiento'], ['ingreso', 'ajuste_positivo', 'devolucion'])): ?>
                                            <span style="color: #10b981;">+<?php echo $mov['cantidad']; ?></span>
                                        <?php else: ?>
                                            <span style="color: #ef4444;">-<?php echo $mov['cantidad']; ?></span>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo $mov['stock_anterior']; ?> → 
                                    <strong><?php echo $mov['stock_posterior']; ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($mov['lote']) ?: '-'; ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($mov['motivo'], 0, 50)); ?><?php echo strlen($mov['motivo']) > 50 ? '...' : ''; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($mov['responsable']) ?: '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $id_material == 0 ? '8' : '7'; ?>" style="text-align: center; padding: 40px; color: #999;">
                                No se encontraron movimientos con los filtros aplicados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if($total_paginas > 1): ?>
            <div class="pagination">
                <?php if($pagina > 1): ?>
                    <a href="?pagina=<?php echo $pagina-1; ?>&id=<?php echo $id_material; ?>&tipo=<?php echo $tipo_movimiento; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">« Anterior</a>
                <?php endif; ?>
                
                <?php for($i = max(1, $pagina-2); $i <= min($total_paginas, $pagina+2); $i++): ?>
                    <?php if($i == $pagina): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?php echo $i; ?>&id=<?php echo $id_material; ?>&tipo=<?php echo $tipo_movimiento; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($pagina < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina+1; ?>&id=<?php echo $id_material; ?>&tipo=<?php echo $tipo_movimiento; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">Siguiente »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
