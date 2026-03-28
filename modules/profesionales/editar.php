<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = '';
$error = '';

// Obtener DNI del profesional
$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if(empty($dni)) {
    header("Location: index.php");
    exit();
}

// Obtener datos del profesional
$query = "SELECT * FROM profesional_solicitante WHERE dni_profesional_solicitante = :dni";
$stmt = $db->prepare($query);
$stmt->bindParam(':dni', $dni);
$stmt->execute();
$profesional = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$profesional) {
    header("Location: index.php?error=Profesional no encontrado");
    exit();
}

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $matricula = trim($_POST['matricula']);
    $id_profesion = (int)$_POST['id_profesion'];
    
    if(empty($nombre) || empty($apellido) || $id_profesion == 0) {
        $error = "Por favor complete todos los campos obligatorios";
    } else {
        try {
            $query = "UPDATE profesional_solicitante SET
                nombre = :nombre,
                apellido = :apellido,
                matricula = :matricula,
                id_profesion = :id_profesion
            WHERE dni_profesional_solicitante = :dni";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->bindParam(':id_profesion', $id_profesion);
            $stmt->bindParam(':dni', $dni);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Profesional actualizado correctamente");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Obtener profesiones
$query_prof = "SELECT * FROM profesion ORDER BY descripcion";
$profesiones = $db->query($query_prof)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Editar Profesional</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/profesionales/profesionales_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>✏️ Editar Profesional</h1>
            <p>Modificar datos del profesional solicitante</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="info-box">
                    <!--echo htmlspecialchars  -->
                    <strong>DNI:</strong> <?php echo htmlspecialchars($profesional['dni_profesional_solicitante']); ?>
                    <br><small>El DNI no se puede modificar</small>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" name="nombre" required value="<?php echo htmlspecialchars($profesional['nombre']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido <span class="required">*</span></label>
                        <input type="text" name="apellido" required value="<?php echo htmlspecialchars($profesional['apellido']); ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Matrícula Profesional</label>
                        <input type="text" name="matricula" value="<?php echo htmlspecialchars($profesional['matricula']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Profesión <span class="required">*</span></label>
                        <select name="id_profesion" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($profesiones as $prof): ?>
                                <option value="<?php echo $prof['id_profesion']; ?>" 
                                    <?php echo $profesional['id_profesion'] == $prof['id_profesion'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prof['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
