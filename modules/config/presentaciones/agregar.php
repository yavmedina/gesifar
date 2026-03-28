<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('admin');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descripcion = trim($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    
    if(empty($descripcion) || empty($tipo)) {
        $error = "Complete todos los campos";
    } else {
        try {
            $query = "INSERT INTO presentaciones (descripcion, tipo, activo) VALUES (:desc, :tipo, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':desc', $descripcion);
            $stmt->bindParam(':tipo', $tipo);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Presentación agregada correctamente");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Nueva Presentación</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>➕ Nueva Presentación</h1>
            <p>Agregar tipo de presentación</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Descripción <span class="required">*</span></label>
                    <input type="text" name="descripcion" required placeholder="Ej: Caja x 50 comprimidos">
                </div>
                
                <div class="form-group">
                    <label>Tipo <span class="required">*</span></label>
                    <select name="tipo" required>
                        <option value="">Seleccione...</option>
                        <option value="medicamento">Medicamento</option>
                        <option value="insumo">Insumo</option>
                        <option value="ambos">Ambos</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>