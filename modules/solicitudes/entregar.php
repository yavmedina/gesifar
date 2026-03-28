<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
verificarPermiso('solicitudes.entregar');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// Obtener solicitud aprobada
$query = "SELECT * FROM solicitud WHERE id_solicitud = :id AND id_estado IN (2,3)";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$solicitud) {
    header("Location: index.php?error=Solicitud no encontrada o no aprobada");
    exit();
}

// Obtener items aprobados
$query_items = "SELECT 
    d.*,
    m.nombre as material_nombre,
    m.stock_actual
FROM detalle_solicitud d
JOIN material m ON d.id_material = m.id_material
WHERE d.id_solicitud = :id AND d.aprobado = 1
ORDER BY d.numero_item";

$stmt_items = $db->prepare($query_items);
$stmt_items->bindParam(':id', $id);
$stmt_items->execute();
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

if(count($items) == 0) {
    header("Location: ver.php?id=$id&error=No hay items aprobados para entregar");
    exit();
}

// Obtener áreas
$areas = $db->query("SELECT * FROM area WHERE activo = 1 ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);

$error = '';

// Procesar entrega
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_area = (int)$_POST['id_area'];
    $observaciones = trim($_POST['observaciones']);
    
    if($id_area == 0) {
        $error = "Debe seleccionar un área destino";
    } else {
        try {
            $db->beginTransaction();
            
            // Generar código de egreso
            $query_last = "SELECT codigo_egreso FROM egreso_stock ORDER BY id_egreso DESC LIMIT 1";
            $stmt_last = $db->query($query_last);
            $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
            
            if($last) {
                $num = (int)substr($last['codigo_egreso'], 4) + 1;
                $codigo = 'EGR-' . str_pad($num, 6, '0', STR_PAD_LEFT);
            } else {
                $codigo = 'EGR-000001';
            }
            
            // Insertar cabecera de egreso
            $obs_egreso = "Entrega SOLPED " . $solicitud['codigo_solicitud'];
            if($observaciones) $obs_egreso .= " - " . $observaciones;
            
            $query_egr = "INSERT INTO egreso_stock (
                codigo_egreso, fecha_egreso, id_area_destino, observaciones, total_items, usuario_registro
            ) VALUES (
                :codigo, CURDATE(), :area, :obs, :total, :usuario
            )";
            
            $total_items = count($items);
            $stmt_egr = $db->prepare($query_egr);
            $stmt_egr->bindParam(':codigo', $codigo);
            $stmt_egr->bindParam(':area', $id_area);
            $stmt_egr->bindParam(':obs', $obs_egreso);
            $stmt_egr->bindParam(':total', $total_items);
            $stmt_egr->bindParam(':usuario', $_SESSION['usuario_id']);
            $stmt_egr->execute();
            
            $id_egreso = $db->lastInsertId();
            
            // Procesar cada item
            foreach($items as $item) {
                $cantidad = $item['cantidad_solicitada'];
                
                // Obtener lotes FIFO
                $query_lotes = "SELECT * FROM lotes_material 
                               WHERE id_material = :material AND cantidad_actual > 0 
                               ORDER BY fecha_vencimiento ASC";
                $stmt_lotes = $db->prepare($query_lotes);
                $stmt_lotes->bindParam(':material', $item['id_material']);
                $stmt_lotes->execute();
                $lotes = $stmt_lotes->fetchAll(PDO::FETCH_ASSOC);
                
                $cantidad_restante = $cantidad;
                
                foreach($lotes as $lote) {
                    if($cantidad_restante <= 0) break;
                    
                    $cantidad_lote = min($cantidad_restante, $lote['cantidad_actual']);
                    
                    // Insertar detalle
                    $query_det = "INSERT INTO detalle_egreso_stock (
                        id_egreso, id_material, id_lote, cantidad
                    ) VALUES (
                        :egreso, :material, :lote, :cant
                    )";
                    $stmt_det = $db->prepare($query_det);
                    $stmt_det->bindParam(':egreso', $id_egreso);
                    $stmt_det->bindParam(':material', $item['id_material']);
                    $stmt_det->bindParam(':lote', $lote['id_lote']);
                    $stmt_det->bindParam(':cant', $cantidad_lote);
                    $stmt_det->execute();
                    
                    // Actualizar lote
                    $query_upd_lote = "UPDATE lotes_material SET cantidad_actual = cantidad_actual - :cant WHERE id_lote = :lote";
                    $stmt_upd_lote = $db->prepare($query_upd_lote);
                    $stmt_upd_lote->bindParam(':cant', $cantidad_lote);
                    $stmt_upd_lote->bindParam(':lote', $lote['id_lote']);
                    $stmt_upd_lote->execute();
                    
                    $cantidad_restante -= $cantidad_lote;
                }
                
                // Actualizar stock material
                $query_upd_mat = "UPDATE material SET stock_actual = stock_actual - :cant WHERE id_material = :material";
                $stmt_upd_mat = $db->prepare($query_upd_mat);
                $stmt_upd_mat->bindParam(':cant', $cantidad);
                $stmt_upd_mat->bindParam(':material', $item['id_material']);
                $stmt_upd_mat->execute();
                
                // Actualizar item de solicitud
                $query_upd_item = "UPDATE detalle_solicitud SET cantidad_entregada = :cant WHERE id_detalle = :id";
                $stmt_upd_item = $db->prepare($query_upd_item);
                $stmt_upd_item->bindParam(':cant', $cantidad);
                $stmt_upd_item->bindParam(':id', $item['id_detalle']);
                $stmt_upd_item->execute();
            }
            
            // Actualizar solicitud
            $items_totales = (int)$db->query("SELECT COUNT(*) FROM detalle_solicitud WHERE id_solicitud = $id")->fetchColumn();
            $items_entregados = count($items);
            
            $nuevo_estado = ($items_totales == $items_entregados) ? 5 : 6; // 5=Entregada Total, 6=Entregada Parcial
            
            $query_upd_sol = "UPDATE solicitud 
                             SET id_estado = :estado, 
                                 id_egreso = :egreso,
                                 fecha_entrega = NOW()
                             WHERE id_solicitud = :id";
            $stmt_upd_sol = $db->prepare($query_upd_sol);
            $stmt_upd_sol->bindParam(':estado', $nuevo_estado);
            $stmt_upd_sol->bindParam(':egreso', $id_egreso);
            $stmt_upd_sol->bindParam(':id', $id);
            $stmt_upd_sol->execute();
            
            $db->commit();
            
            header("Location: ver.php?id=$id&msg=Entrega realizada correctamente - Egreso: $codigo");
            exit();
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "Error al procesar entrega: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Entregar Solicitud</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📦 Generar Entrega</h1>
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
        
        <!-- Items a entregar -->
        <div class="form-container">
            <h3>Items Aprobados para Entrega</h3>
            <table>
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Cantidad</th>
                        <th>Stock Disponible</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['material_nombre']); ?></strong></td>
                            <td><?php echo $item['cantidad_solicitada']; ?></td>
                            <td>
                                <?php if($item['stock_actual'] >= $item['cantidad_solicitada']): ?>
                                    <span style="color: #10b981;">✓ <?php echo $item['stock_actual']; ?></span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">⚠️ <?php echo $item['stock_actual']; ?> (Insuficiente)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Formulario de entrega -->
        <form method="POST" class="form-container">
            <h3>Datos de la Entrega</h3>
            
            <div class="form-group">
                <label>Área Destino <span class="required">*</span></label>
                <select name="id_area" required>
                    <option value="">Seleccione...</option>
                    <?php foreach($areas as $area): ?>
                        <option value="<?php echo $area['id_area']; ?>" <?php echo ($area['descripcion'] == $solicitud['servicio']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($area['descripcion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Información adicional sobre la entrega (opcional)"></textarea>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Nota:</strong> Se generará un egreso automático vinculado a esta solicitud. Los materiales se descontarán del stock usando el método FIFO (primero en vencer, primero en salir).
            </div>
            
            <div class="form-actions">
                <a href="ver.php?id=<?php echo $id; ?>" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                <button type="submit" class="btn btn-success">✅ Generar Egreso y Entregar</button>
            </div>
        </form>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>