<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener materiales con alertas
$query = "SELECT 
    m.*,
    (SELECT MIN(fecha_vencimiento) FROM lotes_material 
     WHERE id_material = m.id_material AND cantidad_actual > 0) as fecha_vencimiento,
    DATEDIFF(
        (SELECT MIN(fecha_vencimiento) FROM lotes_material 
         WHERE id_material = m.id_material AND cantidad_actual > 0),
        CURDATE()
    ) as dias_vencimiento,
    CASE 
        WHEN m.stock_actual <= 0 THEN 'SIN_STOCK'
        WHEN (SELECT MIN(fecha_vencimiento) FROM lotes_material 
              WHERE id_material = m.id_material AND cantidad_actual > 0) < CURDATE() THEN 'VENCIDO'
        WHEN DATEDIFF(
            (SELECT MIN(fecha_vencimiento) FROM lotes_material 
             WHERE id_material = m.id_material AND cantidad_actual > 0),
            CURDATE()
        ) <= 30 THEN 'PROXIMO_VENCER'
        WHEN m.stock_actual <= m.punto_pedido THEN 'PUNTO_PEDIDO'
        WHEN m.stock_actual <= m.stock_minimo THEN 'STOCK_BAJO'
        WHEN m.stock_actual >= m.stock_maximo THEN 'SOBRESTOCK'
        ELSE 'OK'
    END as tipo_alerta
FROM material m
WHERE m.activo = 1
HAVING tipo_alerta != 'OK'
ORDER BY
    CASE tipo_alerta
        WHEN 'SIN_STOCK' THEN 1
        WHEN 'VENCIDO' THEN 2
        WHEN 'PROXIMO_VENCER' THEN 3
        WHEN 'PUNTO_PEDIDO' THEN 4
        WHEN 'STOCK_BAJO' THEN 5
        WHEN 'SOBRESTOCK' THEN 6
        ELSE 7
    END,
    m.nombre ASC";

$stmt = $db->query($query);
$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar por tipo de alerta
$stats = [
    'sin_stock' => 0,
    'vencido' => 0,
    'proximo_vencer' => 0,
    'punto_pedido' => 0,
    'stock_bajo' => 0,
    'sobrestock' => 0
];

foreach($alertas as $alerta) {
    switch($alerta['tipo_alerta']) {
        case 'SIN_STOCK': $stats['sin_stock']++; break;
        case 'VENCIDO': $stats['vencido']++; break;
        case 'PROXIMO_VENCER': $stats['proximo_vencer']++; break;
        case 'PUNTO_PEDIDO': $stats['punto_pedido']++; break;
        case 'STOCK_BAJO': $stats['stock_bajo']++; break;
        case 'SOBRESTOCK': $stats['sobrestock']++; break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Alertas de Stock</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_alertas.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>⚠️ Alertas de Stock</h1>
                    <p>Materiales que requieren atención inmediata</p>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas de alertas -->
        <div class="alert-stats">
            <div class="alert-stat-card stat-sin-stock">
                <h3><?php echo $stats['sin_stock']; ?></h3>
                <p>🚨 Sin Stock</p>
            </div>
            
            <div class="alert-stat-card stat-stock-bajo">
                <h3><?php echo $stats['stock_bajo']; ?></h3>
                <p>📉 Stock Bajo</p>
            </div>
            
            <div class="alert-stat-card stat-sobrestock">
                <h3><?php echo $stats['sobrestock']; ?></h3>
                <p>📈 Sobrestock</p>
            </div>
            <!--
            </div>
            <div class="alert-stats">
            -->
            <div class="alert-stat-card stat-vencido">
                <h3><?php echo $stats['vencido']; ?></h3>
                <p>💀 Vencidos</p>
            </div>
            
            <div class="alert-stat-card stat-punto-pedido">
                <h3><?php echo $stats['punto_pedido']; ?></h3>
                <p>🛒 Punto de Pedido</p>
            </div>
            
            <div class="alert-stat-card stat-proximo-vencer">
                <h3><?php echo $stats['proximo_vencer']; ?></h3>
                <p>⏰ Próximos a Vencer</p>
            </div>
            
        </div>
        
        <!-- Tabla de alertas -->
        <?php if(count($alertas) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Stock</th>
                            <th>ABC</th>
                            <th>Vencimiento</th>
                            <th>Alerta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($alertas as $mat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($mat['nombre']); ?></strong><br>
                                    <small style="color: #666;"><?php echo ucfirst($mat['tipo']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo $mat['stock_actual']; ?></strong> / <?php echo $mat['stock_maximo']; ?><br>
                                    <small style="color: #666;">
                                        Mín: <?php echo $mat['stock_minimo']; ?> | 
                                        PP: <?php echo $mat['punto_pedido']; ?>
                                    </small>
                                </td>
                                <td class="clasificacion-<?php echo $mat['clasificacion_abc']; ?>">
                                    <?php echo $mat['clasificacion_abc']; ?>
                                </td>
                                <td>
                                    <?php if($mat['fecha_vencimiento']): ?>
                                        <?php echo date('d/m/Y', strtotime($mat['fecha_vencimiento'])); ?><br>
                                        <small style="color: <?php echo $mat['dias_vencimiento'] < 0 ? '#ef4444' : ($mat['dias_vencimiento'] <= 30 ? '#f59e0b' : '#666'); ?>;">
                                            (<?php echo $mat['dias_vencimiento']; ?> días)
                                        </small>
                                    <?php else: ?>
                                        <small>N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="alert-badge alert-<?php echo $mat['tipo_alerta']; ?>">
                                        <?php echo str_replace('_', ' ', $mat['tipo_alerta']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="ver.php?id=<?php echo $mat['id_material']; ?>" class="btn btn-sm btn-info" title="Ver">👁️</a>
                                        <a href="movimientos/ajuste_individual.php?id=<?php echo $mat['id_material']; ?>" class="btn btn-sm btn-success" title="Movimiento">📝</a>
                                        <a href="alta/editar.php?id=<?php echo $mat['id_material']; ?>" class="btn btn-sm btn-warning" title="Editar">✏️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <h2>¡Todo en orden!</h2>
                    <p>No hay materiales con alertas en este momento</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">Ver Stock Completo</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>