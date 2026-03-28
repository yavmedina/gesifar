<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if(isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config/database.php';

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(!empty($username) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM usuarios WHERE username = :username AND activo = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $usuario['password'])) {
                // Login exitoso
                $_SESSION['usuario_id'] = $usuario['id_usuario'];
                $_SESSION['username'] = $usuario['username'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol'];
                
                // Actualizar último acceso
                $update = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id";
                $stmt_update = $db->prepare($update);
                $stmt_update->bindParam(':id', $usuario['id_usuario']);
                $stmt_update->execute();
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
    } else {
        $error = "Por favor complete todos los campos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>🏥 GESIFAR</h1>
                <p>Sistema de Gestión de Farmacia Hospitalaria</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- INICIO link para pedido de materialse -->
            <div style="text-align: center; margin-bottom: 20px;">
                <a href="portal_solicitudes.php" class="btn" style="background: #3b82f6; color: white;">📋 Realizar Solicitud de Materiales</a>
            </div>
            <!-- FIN link -->

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
            </form>
            
            <div class="login-footer">
                <!--<p><small>Usuario de prueba: <strong>admin</strong></p><p>Contraseña: <strong>password</strong></small></p> -->
                <p><small>Usuario de prueba: <strong>CONSULTAR</strong></p><p>Contraseña: <strong></strong></small></p>
            </div>
        </div>
    </div>
</body>
</html>