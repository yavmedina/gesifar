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

$mensaje = '';
$error = '';

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $password_confirm = trim($_POST['password_confirm']);
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = trim($_POST['rol']);
    
    // Validaciones
    if(empty($username) || empty($password) || empty($nombre) || empty($rol)) {
        $error = "Por favor complete todos los campos obligatorios";
    } elseif($password !== $password_confirm) {
        $error = "Las contraseñas no coinciden";
    } elseif(strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {
        try {
            // Verificar si el username ya existe
            $query_check = "SELECT COUNT(*) as existe FROM usuarios WHERE username = :username";
            $stmt_check = $db->prepare($query_check);
            $stmt_check->bindParam(':username', $username);
            $stmt_check->execute();
            
            if($stmt_check->fetch(PDO::FETCH_ASSOC)['existe'] > 0) {
                $error = "Ya existe un usuario con ese nombre de usuario";
            } else {
                // Encriptar contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insertar usuario
                $query = "INSERT INTO usuarios (username, password, nombre, email, rol, activo) 
                          VALUES (:username, :password, :nombre, :email, :rol, 1)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password_hash);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':rol', $rol);
                
                if($stmt->execute()) {
                    header("Location: index.php?msg=Usuario creado correctamente");
                    exit();
                }
            }
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
    <title>GESIFAR - Agregar Usuario</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/usuarios/usuarios_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>➕ Agregar Usuario</h1>
            <p>Crear nuevo usuario del sistema</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="info-box">
                <strong>ℹ️ Información:</strong> El usuario podrá iniciar sesión con el nombre de usuario y contraseña creados.
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre de Usuario <span class="required">*</span></label>
                        <input type="text" name="username" required placeholder="usuario123" maxlength="50">
                        <small>Sin espacios, letras y números únicamente</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="usuario@hospital.com">
                        <small>Opcional - para recuperación de contraseña</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Completo <span class="required">*</span></label>
                        <input type="text" name="nombre" required placeholder="Juan Pérez">
                    </div>
                    
                    <div class="form-group">
                        <label>Rol <span class="required">*</span></label>
                        <select name="rol" required>
                            <option value="">Seleccione un rol...</option>
                            <option value="admin">Administrador</option>
                            <option value="farmaceutico_jefe">Farmacéutico Jefe</option>
                            <option value="farmaceutico">Farmacéutico</option>
                            <option value="auxiliar_farmacia">Auxiliar de Farmacia</option>
                            <option value="responsable_stock">Responsable de Stock</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contraseña <span class="required">*</span></label>
                        <input type="password" name="password" required minlength="6" id="password">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strength-bar"></div>
                        </div>
                        <small>Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmar Contraseña <span class="required">*</span></label>
                        <input type="password" name="password_confirm" required minlength="6">
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Muestra que tan débil o fuerte es la contraseña - Si no funciona, se puede comentar todo este bloque
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
