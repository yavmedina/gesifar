<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del material
$query = "SELECT * FROM material WHERE id_material = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$material) {
    header("Location: index.php?error=Material no encontrado");
    exit();
}

// Verificar si tiene movimientos
$query_mov = "SELECT COUNT(*) as total FROM movimiento_stock WHERE id_material = :id";
$stmt_mov = $db->prepare($query_mov);
$stmt_mov->bindParam(':id', $id);
$stmt_mov->execute();
$tiene_movimientos = $stmt_mov->fetch(PDO::FETCH_ASSOC)['total'] > 0;

// Procesar eliminación
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $confirmar = isset($_POST['confirmar']) ? $_POST['confirmar'] : '';
    
    if($confirmar == 'SI') {
        try {
            // Eliminación lógica (marcar como inactivo)
            $query = "UPDATE material SET activo = 0 WHERE id_material = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                header("Location: ../index.php?msg=Material eliminado correctamente");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    } else {
        header("Location: ../index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Eliminar Material</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_eliminar.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="confirm-container">
            <div class="icon-warning">⚠️</div>
            <h1>¿Está seguro de eliminar este material?</h1>
            
            <div class="material-info">
                <h3>📦 <?php echo htmlspecialchars($material['nombre']); ?></h3>
                <p><strong>Código:</strong> <span><?php echo htmlspecialchars($material['codigo']); ?></span></p>
                <p><strong>Stock actual:</strong> <span><?php echo $material['stock_actual']; ?> unidades</span></p>
                <p><strong>Clasificación:</strong> <span><?php echo $material['clasificacion_abc']; ?></span></p>
                <p><strong>Valor en stock:</strong> <span>$<?php echo number_format($material['stock_actual'] * $material['precio_unitario'], 2); ?></span></p>
            </div>
            
            <?php if($tiene_movimientos): ?>
                <div class="warning-box">
                    <strong>⚠️ Advertencia:</strong> Este material tiene movimientos registrados en el historial. La eliminación será <strong>lógica</strong> (el material se marcará como inactivo pero se mantendrá en el sistema por trazabilidad).
                </div>
            <?php endif; ?>
            
            <div class="danger-box">
                <strong>🗑️ Importante:</strong> Esta acción marcará el material como inactivo. No aparecerá en los listados pero se conservará en el historial. No se puede deshacer.
            </div>
            
            <form method="POST" action="">
                <div class="actions">
                    <a href="../index.php" class="btn" style="background: #6c757d; color: white; padding: 12px 30px;">
                        ← Cancelar
                    </a>
                    <button type="submit" name="confirmar" value="SI" class="btn btn-danger" style="padding: 12px 30px;">
                        🗑️ Sí, Eliminar Material
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>
