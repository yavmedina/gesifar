<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if(empty($dni)) {
    header("Location: index.php");
    exit();
}

// Obtener datos del profesional
$query = "SELECT ps.*, p.descripcion AS profesion_nombre
          FROM profesional_solicitante ps
          JOIN profesion p ON ps.id_profesion = p.id_profesion
          WHERE ps.dni_profesional_solicitante = :dni";
$stmt = $db->prepare($query);
$stmt->bindParam(':dni', $dni);
$stmt->execute();
$profesional = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$profesional) {
    header("Location: index.php?error=Profesional no encontrado");
    exit();
}

// Verificar si tiene solicitudes
$query_sol = "SELECT COUNT(*) as total FROM solicitud WHERE id_profesional_solicitante = :dni";
$stmt_sol = $db->prepare($query_sol);
$stmt_sol->bindParam(':dni', $dni);
$stmt_sol->execute();
$tiene_solicitudes = $stmt_sol->fetch(PDO::FETCH_ASSOC)['total'] > 0;

// Procesar eliminación
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $confirmar = isset($_POST['confirmar']) ? $_POST['confirmar'] : '';
    
    if($confirmar == 'SI') {
        try {
            // Eliminación lógica
            $query = "UPDATE profesional_solicitante SET activo = 0 WHERE dni_profesional_solicitante = :dni";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dni', $dni);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Profesional eliminado correctamente");
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
    <title>GESIFAR - Eliminar Profesional</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/profesionales/profesionales_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="confirm-container">
            <div class="icon-warning">⚠️</div>
            <h1>¿Eliminar este profesional?</h1>
            
            <div class="profesional-info">
                <h3>👨‍⚕️ <?php echo htmlspecialchars($profesional['apellido'] . ', ' . $profesional['nombre']); ?></h3>
                <p><strong>DNI:</strong> <span><?php echo htmlspecialchars($profesional['dni_profesional_solicitante']); ?></span></p>
                <p><strong>Profesión:</strong> <span><?php echo htmlspecialchars($profesional['profesion_nombre']); ?></span></p>
                <p><strong>Matrícula:</strong> <span><?php echo htmlspecialchars($profesional['matricula']) ?: 'N/A'; ?></span></p>
            </div>
            
            <?php if($tiene_solicitudes): ?>
                <div class="warning-box">
                    <strong>⚠️ Advertencia:</strong> Este profesional tiene solicitudes registradas. La eliminación será <strong>lógica</strong> (se marcará como inactivo pero se mantendrá en el historial por trazabilidad).
                </div>
            <?php endif; ?>
            
            <div class="danger-box">
                <strong>🗑️ Importante:</strong> Esta acción marcará al profesional como inactivo. No aparecerá en los listados pero se conservará en el historial. No se puede deshacer.
            </div>
            
            <form method="POST" action="">
                <div class="actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white; padding: 12px 30px;">
                        ← Cancelar
                    </a>
                    <button type="submit" name="confirmar" value="SI" class="btn btn-danger" style="padding: 12px 30px;">
                        🗑️ Sí, Eliminar Profesional
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
