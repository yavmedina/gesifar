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

$error = '';

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
            $query = "INSERT INTO proveedor (razon_social, cuit, direccion, telefono, email, contacto, activo) 
                      VALUES (:razon_social, :cuit, :direccion, :telefono, :email, :contacto, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':razon_social', $razon_social);
            $stmt->bindParam(':cuit', $cuit);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':contacto', $contacto);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Proveedor agregado correctamente");
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
    <title>GESIFAR - Nuevo Proveedor</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>➕ Nuevo Proveedor</h1>
            <p>Registrar nuevo proveedor en el sistema</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Razón Social <span class="required">*</span></label>
                    <input type="text" name="razon_social" required placeholder="Ej: Droguería XYZ S.A.">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>CUIT <span class="required">*</span></label>
                        <input type="text" name="cuit" required placeholder="XX-XXXXXXXX-X">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" placeholder="011-4567-8900">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" placeholder="Calle, número, localidad">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="contacto@proveedor.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Persona de Contacto</label>
                        <input type="text" name="contacto" placeholder="Nombre del contacto">
                    </div>
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