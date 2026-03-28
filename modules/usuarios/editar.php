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
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = trim($_POST['rol']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if(empty($nombre) || empty($rol)) {
        $error = "Por favor complete todos los campos obligatorios";
    } else {
        try {
            $query = "UPDATE usuarios SET
                nombre = :nombre,
                email = :email,
                rol = :rol,
                activo = :activo
            WHERE id_usuario = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':rol', $rol);
            $stmt->bindParam(':activo', $activo);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Usuario actualizado correctamente");
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
    <title>GESIFAR - Editar Usuario</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/usuarios/usuarios_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>✏️ Editar Usuario</h1>
            <p>Modificar datos del usuario</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="info-box">
                <strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['username']); ?>
                <br><small>El nombre de usuario no se puede modificar</small>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nombre Completo <span class="required">*</span></label>
                    <input type="text" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Rol <span class="required">*</span></label>
                    <select name="rol" required>
                        <option value="admin" <?php echo $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="farmaceutico_jefe" <?php echo $usuario['rol'] == 'farmaceutico_jefe' ? 'selected' : ''; ?>>Farmacéutico Jefe</option>
                        <option value="farmaceutico" <?php echo $usuario['rol'] == 'farmaceutico' ? 'selected' : ''; ?>>Farmacéutico</option>
                        <option value="auxiliar_farmacia" <?php echo $usuario['rol'] == 'auxiliar_farmacia' ? 'selected' : ''; ?>>Auxiliar de Farmacia</option>
                        <option value="responsable_stock" <?php echo $usuario['rol'] == 'responsable_stock' ? 'selected' : ''; ?>>Responsable de Stock</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="activo" id="activo" <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
                        <label for="activo" style="margin: 0;">Usuario Activo</label>
                    </div>
                    <small>Desmarcar para desactivar el acceso al sistema</small>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <a href="cambiar_password.php?id=<?php echo $id; ?>" class="btn" style="background: #0891b2; color: white;">🔑 Cambiar Contraseña</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
