<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Filtros
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$clasificacion = isset($_GET['clasificacion']) ? $_GET['clasificacion'] : '';

// Paginación
$por_pagina = 15;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// WHERE
$where = ["m.activo = 1"];
$params = [];

// Ocultar sin stock por defecto (a menos que venga de sin_stock.php)
if(!isset($_GET['mostrar_sin_stock'])) {
    $where[] = "m.stock_actual > 0";
}

if($busqueda) {
    $where[] = "(m.codigo LIKE :busqueda OR m.nombre LIKE :busqueda OR m.principio_activo LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

if($clasificacion) {
    $where[] = "m.clasificacion_abc = :clasificacion";
    $params[':clasificacion'] = $clasificacion;
}

$where_clause = implode(" AND ", $where);

// Contar total
$query_count = "SELECT COUNT(*) as total FROM material m WHERE $where_clause";
$stmt_count = $db->prepare($query_count);
foreach($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener materiales
$query = "SELECT 
    m.*,
    DATEDIFF(m.fecha_vencimiento, CURDATE()) AS dias_vencimiento,
    CASE 
        WHEN m.stock_actual <= 0 THEN 'SIN_STOCK'
        WHEN m.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
        WHEN DATEDIFF(m.fecha_vencimiento, CURDATE()) <= 30 THEN 'PROXIMO_VENCER'
        WHEN m.stock_actual <= m.punto_pedido THEN 'PUNTO_PEDIDO'
        WHEN m.stock_actual <= m.stock_minimo THEN 'STOCK_BAJO'
        WHEN m.stock_actual >= m.stock_maximo THEN 'SOBRESTOCK'
        ELSE 'OK'
    END AS alerta
FROM material m
WHERE $where_clause
ORDER BY 
    CASE 
        WHEN m.stock_actual <= 0 THEN 1
        WHEN DATEDIFF(m.fecha_vencimiento, CURDATE()) <= 30 THEN 2
        WHEN m.stock_actual <= m.punto_pedido THEN 3
        ELSE 4
    END,
    m.nombre ASC
LIMIT :offset, :limit";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$query_stats = "SELECT 
    COUNT(DISTINCT m.tipo) as tipos,
    SUM(m.stock_actual) as unidades,
    SUM(m.stock_actual * m.precio_unitario) as valor,
    COUNT(CASE WHEN DATEDIFF(m.fecha_vencimiento, CURDATE()) <= 30 AND DATEDIFF(m.fecha_vencimiento, CURDATE()) >= 0 THEN 1 END) as proximos_vencer
FROM material m
WHERE m.activo = 1";

$stats = $db->query($query_stats)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Gestión de Stock</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📦 Gestión de Stock</h1>
                    <p>Control de materiales, medicamentos e insumos</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if(tienePermiso('stock.alta')): ?>
                        <a href="alta/nuevo.php" class="btn" style="background: #10b981; color: white;">➕ Alta Material</a>
                    <?php endif; ?>
                    
                    <?php if(tienePermiso('stock.ingreso')): ?>
                        <a href="movimientos/ingreso.php" class="btn" style="background: #3b82f6; color: white;">📦 Ingreso</a>
                    <?php endif; ?>
                    
                    <?php if(tienePermiso('stock.egreso')): ?>
                        <a href="movimientos/egreso.php" class="btn" style="background: #ef4444; color: white;">📤 Egreso</a>
                    <?php endif; ?>
                    
                    <a href="alertas.php" class="btn" style="background: #f59e0b; color: white;">⚠️ Alertas</a>
                    <a href="abc_analysis.php" class="btn" style="background: #8b5cf6; color: white;">📊 ABC</a>
                </div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <?php if(tieneAlgunPermiso(['usuarios.gestionar', 'stock.alta'])): ?>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>$<?php echo number_format($stats['valor'], 2); ?></h3>
                    <p>Valor Total Stock</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <h3><?php echo $stats['tipos']; ?></h3>
                    <p>Tipos de Materiales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['unidades']); ?></h3>
                    <p>Unidades en Stock</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-content">
                    <h3><?php echo $stats['proximos_vencer']; ?></h3>
                    <p>Próximos a Vencer (30d)</p>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label>🔍 Buscar</label>
                <input type="text" name="busqueda" placeholder="Código, nombre, principio activo..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            
            <!-- CLASIFICACIÓN ABC -->
            <div class="filter-group" style="max-width: 200px;">
                <label>Clasificación ABC</label>
                <select name="clasificacion">
                    <option value="">Todas</option>
                    <option value="A" <?php echo $clasificacion == 'A' ? 'selected' : ''; ?>>A - Alta</option>
                    <option value="B" <?php echo $clasificacion == 'B' ? 'selected' : ''; ?>>B - Media</option>
                    <option value="C" <?php echo $clasificacion == 'C' ? 'selected' : ''; ?>>C - Baja</option>
                </select>
            </div>

            <!-- BOTÓN FILTRAR -->
            <div class="filter-group" style="max-width: 70px;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Filtrar</button>
            </div>
            
            <!-- BOTÓN LIMPIAR -->
            <div class="filter-group" style="max-width: 50px;">
                <label>&nbsp;</label>
                <a href="index.php" class="btn" style="width: 100%; text-align: center; background: #6c757d; color: white;">Limpiar</a>
            </div>
            
            <!-- BOTÓN VER SIN STOCK -->
            <div class="filter-group" style="max-width: 140px;">
                <label>&nbsp;</label>
                <a href="sin_stock.php" class="btn" style="width: 100%; text-align: center; background: #ef4444; color: white;">Ver Sin Stock</a>
            </div>
        </form>
        
        <!-- Tabla -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Material</th>
                        <th>Tipo</th>
                        <th>Stock</th>
                        <th>ABC</th>
                        <th>Lote</th>
                        <th>Vencimiento</th>
                        <th>Alerta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($materiales) > 0): ?>
                        <?php foreach($materiales as $material): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($material['codigo']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($material['nombre']); ?></strong>
                                    <?php if(!empty($material['nombre_comercial'])): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($material['nombre_comercial']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ucfirst($material['tipo']); ?></td>
                                <td>
                                    <strong><?php echo $material['stock_actual']; ?></strong> / <?php echo $material['stock_maximo']; ?>
                                    <br><small style="color: #666;">Mín: <?php echo $material['stock_minimo']; ?> | PP: <?php echo $material['punto_pedido']; ?></small>
                                </td>
                                <td>
                                    <span class="clasificacion-<?php echo $material['clasificacion_abc']; ?>">
                                        <?php echo $material['clasificacion_abc']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($material['lote']) ?: '-'; ?></td>
                                <td>
                                    <?php if($material['fecha_vencimiento']): ?>
                                        <?php echo date('d/m/Y', strtotime($material['fecha_vencimiento'])); ?>
                                        <br><small style="color: #666;">(<?php echo $material['dias_vencimiento']; ?> días)</small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="alert-badge alert-<?php echo $material['alerta']; ?>">
                                        <?php echo str_replace('_', ' ', $material['alerta']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="ver.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-info" title="Ver">👁️</a>
                                        
                                        <?php if(tienePermiso('stock.ajuste_individual')): ?>
                                            <a href="movimientos/ajuste_individual.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-success" title="Ajuste Individual">🔧</a>
                                        <?php endif; ?>
                                        
                                        <?php if(tienePermiso('stock.editar')): ?>
                                            <a href="alta/editar.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-warning" title="Editar">✏️</a>
                                        <?php endif; ?>
                                        
                                        <?php if(tienePermiso('stock.eliminar')): ?>
                                            <a href="alta/eliminar.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Eliminar?')">🗑️</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                No se encontraron materiales
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
                    <a href="?pagina=<?php echo $pagina-1; ?>&busqueda=<?php echo urlencode($busqueda); ?>&clasificacion=<?php echo $clasificacion; ?>">« Anterior</a>
                <?php endif; ?>
                
                <?php for($i = max(1, $pagina-2); $i <= min($total_paginas, $pagina+2); $i++): ?>
                    <?php if($i == $pagina): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?php echo $i; ?>&busqueda=<?php echo urlencode($busqueda); ?>&clasificacion=<?php echo $clasificacion; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($pagina < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina+1; ?>&busqueda=<?php echo urlencode($busqueda); ?>&clasificacion=<?php echo $clasificacion; ?>">Siguiente »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>