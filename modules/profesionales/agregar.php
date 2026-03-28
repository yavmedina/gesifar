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

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener datos del formulario
    $dni = trim($_POST['dni']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $matricula = trim($_POST['matricula']);
    $id_profesion = (int)$_POST['id_profesion'];
    
    // Validar campos obligatorios
    if(empty($dni) || empty($nombre) || empty($apellido) || $id_profesion == 0) {
        $error = "Por favor complete todos los campos obligatorios";
    } else {
        try {
            // Insertar profesional
            $query = "INSERT INTO profesional_solicitante (
                dni_profesional_solicitante, nombre, apellido, matricula, id_profesion
            ) VALUES (
                :dni, :nombre, :apellido, :matricula, :id_profesion
            )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->bindParam(':id_profesion', $id_profesion);
            
            if($stmt->execute()) {
                header("Location: index.php?msg=Profesional agregado correctamente");
                exit();
            }
        } catch(PDOException $e) {
            if($e->getCode() == 23000) {
                $error = "Ya existe un profesional con ese DNI";
            } else {
                $error = "Error al guardar: " . $e->getMessage();
            }
        }
    }
}

// Obtener profesiones para el select
$query_prof = "SELECT * FROM profesion ORDER BY descripcion";
$profesiones = $db->query($query_prof)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Agregar Profesional</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/profesionales/profesionales_form.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>➕ Agregar Profesional Solicitante</h1>
            <p>Registrar nuevo profesional autorizado</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>DNI <span class="required">*</span></label>
                        <input type="text" name="dni" required placeholder="Ej: 12345678" maxlength="20">
                        <small>Solo números, sin puntos ni guiones</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Matrícula Profesional</label>
                        <input type="text" name="matricula" placeholder="Ej: MP 12345">
                        <small>Opcional - según corresponda</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" name="nombre" required placeholder="Ej: Juan">
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido <span class="required">*</span></label>
                        <input type="text" name="apellido" required placeholder="Ej: Pérez">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Profesión <span class="required">*</span></label>
                    <select name="id_profesion" required>
                        <option value="">Seleccione una profesión...</option>
                        <?php foreach($profesiones as $prof): ?>
                            <option value="<?php echo $prof['id_profesion']; ?>">
                                <?php echo htmlspecialchars($prof['descripcion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Profesional</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
