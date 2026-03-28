<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('stock.ajuste');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_material = (int)$_POST['id_material'];
    $tipo_ajuste = $_POST['tipo_ajuste'];
    $cantidad = (int)$_POST['cantidad'];
    $motivo = trim($_POST['motivo']);
    
    if($id_material == 0 || $cantidad <= 0 || empty($motivo)) {
        $error = "Complete todos los campos obligatorios";
    } else {
        try {
            $db->beginTransaction();
            
            $query_mat = "SELECT stock_actual FROM material WHERE id_material = :id";
            $stmt_mat = $db->prepare($query_mat);
            $stmt_mat->bindParam(':id', $id_material);
            $stmt_mat->execute();
            $material = $stmt_mat->fetch(PDO::FETCH_ASSOC);
            
            $stock_anterior = $material['stock_actual'];
            
            if($tipo_ajuste == 'ajuste_positivo') {
                $stock_posterior = $stock_anterior + $cantidad;
            } else {
                if($cantidad > $stock_anterior) {
                    throw new Exception("No puede ajustar negativamente más del stock disponible");
                }
                $stock_posterior = $stock_anterior - $cantidad;
            }
            
            $query_update = "UPDATE material SET stock_actual = :nuevo WHERE id_material = :id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':nuevo', $stock_posterior);
            $stmt_update->bindParam(':id', $id_material);
            $stmt_update->execute();
            
            $fecha_mov = date('Y-m-d');
            $hora_mov = date('H:i:s');
            $dni_personal = $_SESSION['username'];
            
            $query_mov = "INSERT INTO movimiento_stock (
                id_material, tipo_movimiento, cantidad, stock_anterior, stock_posterior,
                fecha, hora, motivo, id_personal
            ) VALUES (
                :id_material, :tipo, :cantidad, :stock_ant, :stock_post,
                :fecha, :hora, :motivo, :personal
            )";
            
            $stmt_mov = $db->prepare($query_mov);
            $stmt_mov->bindParam(':id_material', $id_material);
            $stmt_mov->bindParam(':tipo', $tipo_ajuste);
            $stmt_mov->bindParam(':cantidad', $cantidad);
            $stmt_mov->bindParam(':stock_ant', $stock_anterior);
            $stmt_mov->bindParam(':stock_post', $stock_posterior);
            $stmt_mov->bindParam(':fecha', $fecha_mov);
            $stmt_mov->bindParam(':hora', $hora_mov);
            $stmt_mov->bindParam(':motivo', $motivo);
            $stmt_mov->bindParam(':personal', $dni_personal);
            $stmt_mov->execute();
            
            $db->commit();
            
            header("Location: ../index.php?msg=Ajuste de inventario registrado correctamente");
            exit();
            
        } catch(Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

$materiales = $db->query("SELECT id_material, codigo, nombre, stock_actual FROM material WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Ajuste de Inventario</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_movimiento.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔧 Ajuste de Inventario</h1>
            <p>Correcciones de stock por conteo físico o errores</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Material <span class="required">*</span></label>
                    <select name="id_material" id="selectMaterial" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($materiales as $mat): ?>
                            <option value="<?php echo $mat['id_material']; ?>" 
                                    data-stock="<?php echo $mat['stock_actual']; ?>">
                                <?php echo htmlspecialchars($mat['nombre']); ?> (Stock: <?php echo $mat['stock_actual']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Ajuste <span class="required">*</span></label>
                    <div class="tipo-movimiento-grid">
                        <div class="tipo-option tipo-ajuste_positivo">
                            <label>
                                <input type="radio" name="tipo_ajuste" value="ajuste_positivo" required>
                                🔼 Ajuste Positivo (Agregar)
                            </label>
                        </div>
                        <div class="tipo-option tipo-ajuste_negativo">
                            <label>
                                <input type="radio" name="tipo_ajuste" value="ajuste_negativo" required>
                                🔽 Ajuste Negativo (Quitar)
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Cantidad <span class="required">*</span></label>
                    <input type="number" name="cantidad" id="inputCantidad" required min="1" value="0">
                </div>
                
                <div class="form-group">
                    <label>Motivo del Ajuste <span class="required">*</span></label>
                    <textarea name="motivo" rows="3" required placeholder="Ej: Conteo físico - diferencia encontrada en inventario"></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="../index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-warning">Registrar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>
