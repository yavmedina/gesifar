<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
verificarPermiso('solicitudes.ver');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// Obtener solicitud
$query = "SELECT 
    s.*,
    e.descripcion as estado_desc,
    u.nombre as aprobador_nombre
FROM solicitud s
JOIN estado_solicitud e ON s.id_estado = e.id_estado
LEFT JOIN usuario u ON s.usuario_aprobador = u.id_usuario
WHERE s.id_solicitud = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$solicitud) {
    header("Location: index.php?error=Solicitud no encontrada");
    exit();
}

// Obtener items
$query_items = "SELECT 
    d.*,
    m.nombre as material_nombre,
    m.stock_actual
FROM detalle_solicitud d
LEFT JOIN material m ON d.id_material = m.id_material
WHERE d.id_solicitud = :id
ORDER BY d.numero_item";

$stmt_items = $db->prepare($query_items);
$stmt_items->bindParam(':id', $id);
$stmt_items->execute();
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Detalle Solicitud</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_detail.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📋 <?php echo htmlspecialchars($solicitud['codigo_solicitud']); ?></h1>
                    <p>Detalle completo de la solicitud</p>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver</a>
                </div>
            </div>
        </div>
        
        <!-- Estado -->
        <div style="margin-bottom: 20px;">
            <?php
            $color = '#666';
            if($solicitud['id_estado'] == 1) $color = '#f59e0b';
            if($solicitud['id_estado'] == 2 || $solicitud['id_estado'] == 3) $color = '#10b981';
            if($solicitud['id_estado'] == 4) $color = '#ef4444';
            if($solicitud['id_estado'] == 5 || $solicitud['id_estado'] == 6) $color = '#3b82f6';
            ?>
            <span style="background: <?php echo $color; ?>; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; font-size: 16px;">
                <?php echo htmlspecialchars($solicitud['estado_desc']); ?>
            </span>
        </div>
        
        <!-- Grid de información -->
        <div class="detail-grid">
            <!-- Profesional -->
            <div class="detail-card">
                <h3>👨‍⚕️ Profesional Solicitante</h3>
                <div class="detail-row">
                    <span class="detail-label">Nombre:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($solicitud['nombre_profesional']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">DNI:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($solicitud['dni_profesional']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Servicio:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($solicitud['servicio']); ?></span>
                </div>
            </div>
            
            <!-- Datos de la solicitud -->
            <div class="detail-card">
                <h3>📅 Datos de la Solicitud</h3>
                <div class="detail-row">
                    <span class="detail-label">Fecha:</span>
                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></span>
                </div>
                <?php if($solicitud['fecha_aprobacion']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Aprobada por:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($solicitud['aprobador_nombre']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Fecha aprobación:</span>
                        <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_aprobacion'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if($solicitud['fecha_entrega']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Fecha entrega:</span>
                        <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_entrega'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Observaciones -->
        <?php if($solicitud['observaciones_profesional']): ?>
            <div class="detail-card">
                <h3>📝 Observaciones del Profesional</h3>
                <p><?php echo nl2br(htmlspecialchars($solicitud['observaciones_profesional'])); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($solicitud['observaciones_farmacia']): ?>
            <div class="detail-card">
                <h3>💊 Observaciones de Farmacia</h3>
                <p><?php echo nl2br(htmlspecialchars($solicitud['observaciones_farmacia'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Items solicitados -->
        <div class="detail-card">
            <h3>📦 Items Solicitados</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Material</th>
                        <th>Cantidad Solicitada</th>
                        <th>Estado</th>
                        <th>Cantidad Entregada</th>
                        <th>Motivo Rechazo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td><?php echo $item['numero_item']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['nombre_solicitado']); ?></strong>
                                <?php if($item['material_nombre']): ?>
                                    <br><small style="color: #10b981;">✓ Identificado: <?php echo htmlspecialchars($item['material_nombre']); ?></small>
                                    <br><small style="color: #666;">Stock: <?php echo $item['stock_actual']; ?></small>
                                <?php else: ?>
                                    <br><small style="color: #f59e0b;">⚠️ No identificado en sistema</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['cantidad_solicitada']; ?></td>
                            <td>
                                <?php if($item['aprobado'] === null): ?>
                                    <span style="color: #f59e0b;">⏳ Pendiente</span>
                                <?php elseif($item['aprobado'] == 1): ?>
                                    <span style="color: #10b981;">✓ Aprobado</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">✗ Rechazado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($item['cantidad_entregada'] > 0): ?>
                                    <strong><?php echo $item['cantidad_entregada']; ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($item['motivo_rechazo']): ?>
                                    <small><?php echo htmlspecialchars($item['motivo_rechazo']); ?></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Acciones -->
        <div class="actions-bar">
            <?php if($solicitud['id_estado'] == 1): ?>
                <a href="revisar.php?id=<?php echo $id; ?>" class="btn btn-warning">📝 Revisar y Aprobar</a>
            <?php endif; ?>
            
            <?php if($solicitud['id_estado'] == 2 || $solicitud['id_estado'] == 3): ?>
                <a href="entregar.php?id=<?php echo $id; ?>" class="btn btn-success">📦 Generar Entrega</a>
            <?php endif; ?>
            
            <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>