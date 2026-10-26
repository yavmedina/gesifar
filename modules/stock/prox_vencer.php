<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener materiales próximos a vencer (30 días o menos)
$query = "SELECT 
    m.*,
    l.lote,
    l.fecha_vencimiento,
    l.cantidad_actual as stock_lote,
    DATEDIFF(l.fecha_vencimiento, CURDATE()) as dias_vencimiento
FROM material m
JOIN lotes_material l ON m.id_material = l.id_material
WHERE m.activo = 1 
AND l.cantidad_actual > 0
AND l.fecha_vencimiento >= CURDATE()
AND DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30
ORDER BY l.fecha_vencimiento ASC, m.nombre ASC";

$stmt = $db->query($query);
$proximos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Próximos a Vencer</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/components.css">
    <link rel="stylesheet" href="/gesifar/assets/css/utilities.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_alertas.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div class="flex-between">
                <div>
                    <h1>⚠️ Próximos a Vencer</h1>
                    <p>Deben ser utilizados o descartados 1 semana antes de su fecha de vencimiento</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">← Volver al Stock</a>
                </div>
            </div>
        </div>
        
        <div class="warning-box">
            <strong>⚠️ Atención:</strong> Mostrando materiales con vencimiento en los próximos 30 días. Planificar uso o descarte 7 días antes del vencimiento.
        </div>
        
        <!-- Tabla de próximos a vencer -->
        <?php if(count($proximos) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Lote</th>
                            <th>Stock Lote</th>
                            <th>Vencimiento</th>
                            <th>Días Restantes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($proximos as $mat): ?>
                            <tr class="<?php echo $mat['dias_vencimiento'] <= 7 ? 'row-danger' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($mat['nombre']); ?></strong><br>
                                    <small class="text-muted"><?php echo ucfirst($mat['tipo']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($mat['lote']); ?></td>
                                <td><strong><?php echo $mat['stock_lote']; ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($mat['fecha_vencimiento'])); ?></td>
                                <td>
                                    <?php if($mat['dias_vencimiento'] <= 7): ?>
                                        <span class="text-danger fw-bold">
                                            <?php echo $mat['dias_vencimiento']; ?> día(s) ⚠️
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning fw-bold">
                                            <?php echo $mat['dias_vencimiento']; ?> día(s)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="ver.php?id=<?php echo $mat['id_material']; ?>" class="btn btn-sm btn-info" title="Ver">👁️</a>
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
                    <p>No hay materiales próximos a vencer en los próximos 30 días</p>
                    <a href="index.php" class="btn btn-primary mt-20">Ver Stock Completo</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>