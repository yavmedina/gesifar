<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('stock.ajuste_individual');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: ../index.php");
    exit();
}

$query = "SELECT * FROM material WHERE id_material = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$material) {
    header("Location: ../index.php?error=Material no encontrado");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_movimiento = $_POST['tipo_movimiento'];
    $cantidad = (int)$_POST['cantidad'];
    $motivo = trim($_POST['motivo']);
    
    if(empty($motivo) || $cantidad <= 0) {
        $error = "Complete todos los campos obligatorios";
    } else {
        try {
            $db->beginTransaction();
            
            $stock_anterior = $material['stock_actual'];
            
            if($tipo_movimiento == 'ingreso' || $tipo_movimiento == 'ajuste_positivo') {
                $stock_posterior = $stock_anterior + $cantidad;
            } else {
                if($cantidad > $stock_anterior) {
                    throw new Exception("No puede registrar más del stock disponible ($stock_anterior unidades)");
                }
                $stock_posterior = $stock_anterior - $cantidad;
            }
            
            $query_mov = "INSERT INTO movimiento_stock (
                id_material, tipo_movimiento, cantidad, stock_anterior, stock_posterior,
                fecha, hora, motivo, id_personal
            ) VALUES (
                :id, :tipo, :cant, :stock_ant, :stock_post,
                CURDATE(), CURTIME(), :motivo, :usuario
            )";
            
            $stmt_mov = $db->prepare($query_mov);
            $stmt_mov->bindParam(':id', $id);
            $stmt_mov->bindParam(':tipo', $tipo_movimiento);
            $stmt_mov->bindParam(':cant', $cantidad);
            $stmt_mov->bindParam(':stock_ant', $stock_anterior);
            $stmt_mov->bindParam(':stock_post', $stock_posterior);
            $stmt_mov->bindParam(':motivo', $motivo);
            $stmt_mov->bindParam(':usuario', $_SESSION['username']);
            $stmt_mov->execute();
            
            $db->commit();
            
            header("Location: ../index.php?msg=Movimiento registrado - Stock: $stock_posterior unidades");
            exit();
            
        } catch(Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Ajuste Individual</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css?v=2">
    <!-- <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_movimiento.css"> -->
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔧 Ajuste Individual</h1>
            <p>Corrección de stock</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="info-box">
                <strong>Material:</strong> <?php echo htmlspecialchars($material['nombre']); ?><br>
                <strong>Código:</strong> <?php echo htmlspecialchars($material['codigo']); ?><br>
                <strong>Stock actual:</strong> <?php echo $material['stock_actual']; ?> unidades
            </div>
            
            <div class="info-box" style="background: #fef3c7; border-left-color: #f59e0b;">
                <strong>⚠️ IMPORTANTE:</strong> Esta acción NO modifica el ingreso original. Se registra como movimiento independiente con motivo obligatorio para trazabilidad.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Tipo de Movimiento <span class="required">*</span></label>
                    <div class="tipo-movimiento-grid">
                        <div class="tipo-option tipo-ingreso">
                            <label>
                                <input type="radio" name="tipo_movimiento" value="ingreso" required>
                                <div>
                                    <strong>➕ Ingreso</strong><br>
                                    <small>Compra o recepción</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="tipo-option tipo-egreso">
                            <label>
                                <input type="radio" name="tipo_movimiento" value="egreso" required>
                                <div>
                                    <strong>➖ Egreso</strong><br>
                                    <small>Entrega o consumo</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="tipo-option tipo-ajuste_positivo">
                            <label>
                                <input type="radio" name="tipo_movimiento" value="ajuste_positivo" required>
                                <div>
                                    <strong>🔼 Ajuste Positivo</strong><br>
                                    <small>Corrección inventario +</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="tipo-option tipo-ajuste_negativo">
                            <label>
                                <input type="radio" name="tipo_movimiento" value="ajuste_negativo" required>
                                <div>
                                    <strong>🔽 Ajuste Negativo</strong><br>
                                    <small>Corrección inventario -</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="tipo-option tipo-baja">
                            <label>
                                <input type="radio" name="tipo_movimiento" value="baja" required>
                                <div>
                                    <strong>⏰ Vencimiento</strong><br>
                                    <small>Baja por vencimiento</small>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Cantidad <span class="required">*</span></label>
                    <input type="number" name="cantidad" required min="1">
                </div>
                
                <div class="form-group">
                    <label>Motivo / Observaciones <span class="required">*</span></label>
                    <textarea name="motivo" rows="4" required placeholder="Ej: Error de carga en ingreso ING-00045 - Cantidad real 100 cajas, no 1000"></textarea>
                    <small style="color: #ef4444;">⚠️ El motivo es OBLIGATORIO para trazabilidad</small>
                </div>
                
                <div class="form-actions">
                    <a href="../index.php" class="btn" style="background: #6c757d; color: white;">← Volver</a>
                    <button type="submit" class="btn btn-warning">🔧 Registrar Movimiento</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>