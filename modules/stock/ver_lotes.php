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

$id_material = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id_material == 0) {
    header("Location: index.php");
    exit();
}

// Obtener material
$query_material = "SELECT * FROM material WHERE id_material = :id";
$stmt_material = $db->prepare($query_material);
$stmt_material->bindParam(':id', $id_material);
$stmt_material->execute();
$material = $stmt_material->fetch(PDO::FETCH_ASSOC);

if(!$material) {
    header("Location: index.php");
    exit();
}

// Obtener todos los lotes con stock
$query_lotes = "SELECT 
    l.*,
    p.razon_social as proveedor,
    DATEDIFF(l.fecha_vencimiento, CURDATE()) as dias_para_vencer,
    CASE
        WHEN l.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30 THEN 'PROXIMO_VENCER'
        ELSE 'VIGENTE'
    END as estado_lote
FROM lotes_material l
LEFT JOIN proveedor p ON l.id_proveedor = p.id_proveedor
WHERE l.id_material = :id AND l.cantidad_actual > 0
ORDER BY l.fecha_vencimiento ASC";

$stmt_lotes = $db->prepare($query_lotes);
$stmt_lotes->bindParam(':id', $id_material);
$stmt_lotes->execute();
$lotes = $stmt_lotes->fetchAll(PDO::FETCH_ASSOC);

$total_lotes = count($lotes);
$stock_total = $material['stock_actual'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Lotes del Material</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📦 Lotes del Material</h1>
                    <p>
                        <strong><?php echo htmlspecialchars($material['nombre']); ?></strong>
                        <?php if($material['nombre_comercial']): ?>
                            (<?php echo htmlspecialchars($material['nombre_comercial']); ?>)
                        <?php endif; ?>
                        - Código: <?php echo htmlspecialchars($material['codigo']); ?>
                    </p>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
                </div>
            </div>
        </div>
        
        <!-- Resumen -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <h3><?php echo $total_lotes; ?></h3>
                    <p>Lotes Activos</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php echo $stock_total; ?></h3>
                    <p>Stock Total</p>
                </div>
            </div>
        </div>
        
        <!-- Tabla de lotes -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Cantidad</th>
                        <th>Vencimiento</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th>Proveedor</th>
                        <th>Fecha Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($lotes) > 0): ?>
                        <?php foreach($lotes as $lote): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lote['lote']); ?></strong></td>
                                <td>
                                    <strong><?php echo $lote['cantidad_actual']; ?></strong>
                                    <?php if($lote['cantidad_actual'] != $lote['cantidad_inicial']): ?>
                                        <br><small style="color: #666;">Inicial: <?php echo $lote['cantidad_inicial']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($lote['fecha_vencimiento']): ?>
                                        <?php echo date('d/m/Y', strtotime($lote['fecha_vencimiento'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($lote['dias_para_vencer'] !== null): ?>
                                        <?php if($lote['dias_para_vencer'] < 0): ?>
                                            <span style="color: #ef4444; font-weight: bold;">
                                                Vencido hace <?php echo abs($lote['dias_para_vencer']); ?> días
                                            </span>
                                        <?php elseif($lote['dias_para_vencer'] <= 30): ?>
                                            <span style="color: #f59e0b; font-weight: bold;">
                                                <?php echo $lote['dias_para_vencer']; ?> días
                                            </span>
                                        <?php else: ?>
                                            <?php echo $lote['dias_para_vencer']; ?> días
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $estado_colors = [
                                        'VENCIDO' => '#ef4444',
                                        'PROXIMO_VENCER' => '#f59e0b',
                                        'VIGENTE' => '#10b981'
                                    ];
                                    $estado_labels = [
                                        'VENCIDO' => '✗ Vencido',
                                        'PROXIMO_VENCER' => '⚠️ Próx. Vencer',
                                        'VIGENTE' => '✓ Vigente'
                                    ];
                                    ?>
                                    <span style="color: <?php echo $estado_colors[$lote['estado_lote']]; ?>; font-weight: bold;">
                                        <?php echo $estado_labels[$lote['estado_lote']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $lote['proveedor'] ? htmlspecialchars($lote['proveedor']) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($lote['fecha_ingreso'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                No hay lotes con stock disponible
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="ver.php?id=<?php echo $id_material; ?>" class="btn btn-primary">👁️ Ver Detalle del Material</a>
            <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>