<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('config.editar');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descripcion = trim($_POST['descripcion']);
    
    if(empty($descripcion)) {
        $error = "La descripción es obligatoria";
    } else {
        try {
            $query = "INSERT INTO forma_farmaceutica (descripcion, activo) VALUES (:desc, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':desc', $descripcion);
            $stmt->execute();
            
            header("Location: index.php?msg=Forma farmacéutica agregada correctamente");
            exit();
        } catch(PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Agregar Forma Farmacéutica</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>➕ Agregar Forma Farmacéutica</h1>
            <p>Registrar nueva forma farmacéutica en el sistema</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Descripción <span class="required">*</span></label>
                    <input type="text" name="descripcion" required placeholder="Ej: Comprimidos, Jarabe, Inyectable">
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