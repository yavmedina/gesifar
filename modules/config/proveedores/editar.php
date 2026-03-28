<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('config.ver');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if($id == 0) {
    header("Location: index.php");
    exit();
}

$query = "SELECT * FROM proveedor WHERE id_proveedor = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$prov = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$prov) {
    header("Location: index.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $razon_social = trim($_POST['razon_social']);
    $cuit = trim($_POST['cuit']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $contacto = trim($_POST['contacto']);
    
    if(empty($razon_social) || empty($cuit)) {
        $error = "Complete los campos obligatorios";
    } else {
        try {
            $query = "UPDATE proveedor SET 
                      razon_social = :razon_social, 
                      cuit = :cuit, 
                      direccion = :direccion, 
                      telefono = :telefono, 
                      email = :email, 
                      contacto = :contacto 
                      WHERE id_proveedor = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':razon_social', $razon_social);
            $stmt->bindParam(':cuit', $cuit);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contacto', $contacto);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Proveedor actualizado");
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
    <title>GESIFAR - Editar Proveedor</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>✏️ Editar Proveedor</h1>
            <p>Modificar datos del proveedor</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Razón Social <span class="required">*</span></label>
                    <input type="text" name="razon_social" required value="<?php echo htmlspecialchars($prov['razon_social']); ?>">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>CUIT <span class="required">*</span></label>
                        <input type="text" name="cuit" required value="<?php echo htmlspecialchars($prov['cuit']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($prov['telefono']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?php echo htmlspecialchars($prov['direccion']); ?>">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($prov['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Persona de Contacto</label>
                        <input type="text" name="contacto" value="<?php echo htmlspecialchars($prov['contacto']); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>