<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
verificarPermiso('usuarios.gestionar');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del usuario
$query = "SELECT * FROM usuarios WHERE id_usuario = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$usuario) {
    header("Location: index.php?error=Usuario no encontrado");
    exit();
}

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nueva_password = trim($_POST['nueva_password']);
    $confirmar_password = trim($_POST['confirmar_password']);
    
    if(empty($nueva_password)) {
        $error = "Por favor ingrese la nueva contraseña";
    } elseif(strlen($nueva_password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif($nueva_password !== $confirmar_password) {
        $error = "Las contraseñas no coinciden";
    } else {
        try {
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE usuarios SET password = :password WHERE id_usuario = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Contraseña actualizada correctamente");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Cambiar Contraseña</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/usuarios/usuarios_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔑 Cambiar Contraseña</h1>
            <p>Establecer nueva contraseña para el usuario</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="user-info">
                <strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['username']); ?>
                <br>
                <strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre']); ?>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nueva Contraseña <span class="required">*</span></label>
                    <input type="password" name="nueva_password" required minlength="6" id="password">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strength-bar"></div>
                    </div>
                    <small>Mínimo 6 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña <span class="required">*</span></label>
                    <input type="password" name="confirmar_password" required minlength="6">
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Indicador de fortaleza de contraseña
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const bar = document.getElementById('strength-bar');
        
        bar.className = 'password-strength-bar';
        
        if(password.length === 0) {
            bar.style.width = '0%';
        } else if(password.length < 6) {
            bar.classList.add('strength-weak');
        } else if(password.length < 10) {
            bar.classList.add('strength-medium');
        } else {
            bar.classList.add('strength-strong');
        }
    });
    </script>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
