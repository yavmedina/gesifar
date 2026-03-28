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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// No permitir eliminar el propio usuario
if($id == $_SESSION['usuario_id']) {
    header("Location: index.php?error=No puede eliminar su propio usuario");
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

// Procesar eliminación
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $confirmar = isset($_POST['confirmar']) ? $_POST['confirmar'] : '';
    
    if($confirmar == 'SI') {
        try {
            // Eliminación lógica
            $query = "UPDATE usuarios SET activo = 0 WHERE id_usuario = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Usuario desactivado correctamente");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    } else {
        header("Location: index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Eliminar Usuario</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/usuarios/usuarios_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="confirm-container">
            <div class="icon-warning">⚠️</div>
            <h1>¿Desactivar este usuario?</h1>
            
            <div class="usuario-info">
                <h3>👤 <?php echo htmlspecialchars($usuario['nombre']); ?></h3>
                <p><strong>Usuario:</strong> <span><?php echo htmlspecialchars($usuario['username']); ?></span></p>
                <p><strong>Email:</strong> <span><?php echo htmlspecialchars($usuario['email']) ?: 'No registrado'; ?></span></p>
                <p><strong>Rol:</strong> <span><?php echo nombreRol($usuario['rol']); ?></span></p>
            </div>
            
            <div class="warning-box">
                <strong>ℹ️ Importante:</strong> El usuario será <strong>desactivado</strong> (no eliminado). No podrá iniciar sesión pero se mantendrá en el historial del sistema.
            </div>
            
            <form method="POST" action="">
                <div class="actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white; padding: 12px 30px;">
                        ← Cancelar
                    </a>
                    <button type="submit" name="confirmar" value="SI" class="btn btn-danger" style="padding: 12px 30px;">
                        🗑️ Sí, Desactivar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
