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

// Obtener materiales SIN STOCK
$query = "SELECT 
    m.*,
    CASE 
        WHEN m.tipo = 'medicamento' THEN '💊'
        WHEN m.tipo = 'insumo' THEN '🔧'
        ELSE '📦'
    END as icono
FROM material m
WHERE m.activo = 1 AND m.stock_actual = 0
ORDER BY m.tipo, m.nombre";

$stmt = $db->query($query);
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($materiales);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Materiales Sin Stock</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🚫 Materiales Sin Stock</h1>
                    <p>Listado de materiales con stock_actual = 0</p>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
                </div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>
        
        <div class="alert" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
            <strong>Total de materiales sin stock:</strong> <?php echo $total; ?>
        </div>
        
        <!-- Tabla -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Material</th>
                        <th>Tipo</th>
                        <th>Clasificación ABC</th>
                        <th>Lote</th>
                        <th>Vencimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($materiales) > 0): ?>
                        <?php foreach($materiales as $material): ?>
                            <tr style="background: #fee2e2;">
                                <td><strong><?php echo htmlspecialchars($material['codigo']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($material['nombre']); ?></strong>
                                    <?php if(!empty($material['nombre_comercial'])): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($material['nombre_comercial']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $material['icono']; ?> <?php echo ucfirst($material['tipo']); ?></td>
                                <td>
                                    <span class="clasificacion-<?php echo $material['clasificacion_abc']; ?>">
                                        <?php echo $material['clasificacion_abc']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($material['lote']) ?: '-'; ?></td>
                                <td>
                                    <?php if($material['fecha_vencimiento']): ?>
                                        <?php echo date('d/m/Y', strtotime($material['fecha_vencimiento'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="ver.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-info" title="Ver">👁️ Ver</a>
                                        
                                        <?php if(tienePermiso('stock.ingreso')): ?>
                                            <a href="movimientos/ingreso.php?id_material=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-primary" title="Ingresar Stock">📦 Ingresar</a>
                                        <?php endif; ?>
                                        
                                        <?php if(tienePermiso('stock.eliminar')): ?>
                                            <a href="alta/eliminar.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Desactivar este material?')">🗑️ Eliminar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                ✅ ¡Excelente! No hay materiales sin stock
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>