<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
verificarPermiso('solicitudes.aprobar');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// Obtener solicitud
$query = "SELECT * FROM solicitud WHERE id_solicitud = :id AND id_estado = 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$solicitud) {
    header("Location: index.php?error=Solicitud no encontrada o ya procesada");
    exit();
}

// Obtener items
$query_items = "SELECT 
    d.*,
    m.nombre as material_nombre,
    m.stock_actual,
    m.id_material as mat_id
FROM detalle_solicitud d
LEFT JOIN material m ON d.id_material = m.id_material
WHERE d.id_solicitud = :id
ORDER BY d.numero_item";

$stmt_items = $db->prepare($query_items);
$stmt_items->bindParam(':id', $id);
$stmt_items->execute();
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$error = '';

// Procesar revisión
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $observaciones_farmacia = trim($_POST['observaciones_farmacia']);
    $items_aprobados = 0;
    $items_rechazados = 0;
    
    try {
        $db->beginTransaction();
        
        // Procesar cada item
        foreach($items as $item) {
            $aprobado = isset($_POST["aprobado_{$item['id_detalle']}"]) ? 1 : 0;
            $motivo_rechazo = trim($_POST["motivo_{$item['id_detalle']}"]);
            
            if($aprobado) {
                $items_aprobados++;
            } else {
                $items_rechazados++;
            }
            
            $query_upd = "UPDATE detalle_solicitud 
                         SET aprobado = :aprobado, motivo_rechazo = :motivo
                         WHERE id_detalle = :id";
            $stmt_upd = $db->prepare($query_upd);
            $stmt_upd->bindParam(':aprobado', $aprobado);
            $stmt_upd->bindParam(':motivo', $motivo_rechazo);
            $stmt_upd->bindParam(':id', $item['id_detalle']);
            $stmt_upd->execute();
        }
        
        // Determinar estado de la solicitud
        $total_items = count($items);
        if($items_aprobados == $total_items) {
            $nuevo_estado = 2; // Aprobada Total
        } elseif($items_aprobados > 0) {
            $nuevo_estado = 3; // Aprobada Parcial
        } else {
            $nuevo_estado = 4; // Rechazada
        }
        
        // Actualizar solicitud
        $query_sol = "UPDATE solicitud 
                     SET id_estado = :estado,
                         observaciones_farmacia = :obs,
                         usuario_aprobador = :usuario,
                         fecha_aprobacion = NOW()
                     WHERE id_solicitud = :id";
        
        $stmt_sol = $db->prepare($query_sol);
        $stmt_sol->bindParam(':estado', $nuevo_estado);
        $stmt_sol->bindParam(':obs', $observaciones_farmacia);
        $stmt_sol->bindParam(':usuario', $_SESSION['usuario_id']);
        $stmt_sol->bindParam(':id', $id);
        $stmt_sol->execute();
        
        $db->commit();
        
        header("Location: ver.php?id=$id&msg=Solicitud procesada correctamente");
        exit();
    } catch(PDOException $e) {
        $db->rollBack();
        $error = "Error al procesar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Revisar Solicitud</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <style>
        .item-revisar {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .checkbox-aprobar {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        .checkbox-aprobar input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📝 Revisar Solicitud</h1>
                    <p><?php echo htmlspecialchars($solicitud['codigo_solicitud']); ?> - <?php echo htmlspecialchars($solicitud['nombre_profesional']); ?></p>
                </div>
                <div>
                    <a href="ver.php?id=<?php echo $id; ?>" class="btn" style="background: #6c757d; color: white;">← Volver</a>
                </div>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="info-box" style="margin-bottom: 20px;">
            <strong>ℹ️ Instrucciones:</strong> Marque los items que aprueba. Los no marcados se considerarán rechazados. Indique el motivo de rechazo cuando corresponda.
        </div>
        
        <form method="POST">
            <!-- Items -->
            <?php foreach($items as $item): ?>
                <div class="item-revisar">
                    <div class="item-header">
                        <div>
                            <strong>Item <?php echo $item['numero_item']; ?>: <?php echo htmlspecialchars($item['nombre_solicitado']); ?></strong>
                            <?php if($item['material_nombre']): ?>
                                <br><small style="color: #10b981;">✓ <?php echo htmlspecialchars($item['material_nombre']); ?> | Stock: <?php echo $item['stock_actual']; ?></small>
                            <?php else: ?>
                                <br><small style="color: #f59e0b;">⚠️ No identificado en sistema</small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong style="font-size: 18px;">Cantidad: <?php echo $item['cantidad_solicitada']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="checkbox-aprobar">
                        <input type="checkbox" name="aprobado_<?php echo $item['id_detalle']; ?>" id="aprobado_<?php echo $item['id_detalle']; ?>" 
                               <?php echo ($item['material_nombre'] && $item['stock_actual'] >= $item['cantidad_solicitada']) ? 'checked' : ''; ?>>
                        <label for="aprobado_<?php echo $item['id_detalle']; ?>" style="margin: 0; font-weight: bold; color: #10b981;">
                            ✓ Aprobar este item
                        </label>
                    </div>
                    
                    <div class="form-group" style="margin-top: 10px;">
                        <label>Motivo de rechazo (si no se aprueba)</label>
                        <input type="text" name="motivo_<?php echo $item['id_detalle']; ?>" 
                               placeholder="Ej: Sin stock, Incluido en reposición mensual, No corresponde al servicio">
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Observaciones generales -->
            <div class="form-container">
                <h3>💊 Observaciones de Farmacia</h3>
                <div class="form-group">
                    <textarea name="observaciones_farmacia" rows="3" placeholder="Observaciones generales sobre la solicitud (opcional)"></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="form-actions" style="margin-top: 30px;">
                <a href="ver.php?id=<?php echo $id; ?>" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                <button type="submit" class="btn btn-primary">✅ Confirmar Revisión</button>
            </div>
        </form>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>