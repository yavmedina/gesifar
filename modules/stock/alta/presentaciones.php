<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('stock.alta');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id_material = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id_material == 0) {
    header("Location: ../index.php");
    exit();
}

// Obtener material
$query = "SELECT * FROM material WHERE id_material = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id_material);
$stmt->execute();
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$material) {
    header("Location: ../index.php?error=Material no encontrado");
    exit();
}

$mensaje = '';
$error = '';

// Agregar presentación al material
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    if($_POST['accion'] == 'agregar') {
        $id_presentacion = (int)$_POST['id_presentacion'];
        
        if($id_presentacion == 0) {
            $error = "Seleccione una presentación";
        } else {
            try {
                $query = "INSERT INTO presentacion_material (id_material, id_presentacion, activo) 
                          VALUES (:id_material, :id_pres, 1)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_material', $id_material);
                $stmt->bindParam(':id_pres', $id_presentacion);
                
                if($stmt->execute()) {
                    $mensaje = "Presentación agregada correctamente";
                }
            } catch(PDOException $e) {
                if(strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = "Esta presentación ya está asignada a este material";
                } else {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    } elseif($_POST['accion'] == 'eliminar') {
        $id_pres = (int)$_POST['id_presentacion_material'];
        
        try {
            $query = "UPDATE presentacion_material SET activo = 0 WHERE id_presentacion_material = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_pres);
            
            if($stmt->execute()) {
                $mensaje = "Presentación desactivada";
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif($_POST['accion'] == 'activar') {
        $id_pres = (int)$_POST['id_presentacion_material'];
        
        try {
            $query = "UPDATE presentacion_material SET activo = 1 WHERE id_presentacion_material = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_pres);
            
            if($stmt->execute()) {
                $mensaje = "Presentación activada";
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Obtener presentaciones YA asignadas al material
$query = "SELECT pm.*, p.descripcion 
          FROM presentacion_material pm
          JOIN presentaciones p ON pm.id_presentacion = p.id_presentacion
          WHERE pm.id_material = :id 
          ORDER BY pm.activo DESC, p.descripcion";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id_material);
$stmt->execute();
$presentaciones_asignadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener presentaciones disponibles (que coincidan con el tipo del material)
$query = "SELECT * FROM presentaciones 
          WHERE (tipo = :tipo OR tipo = 'ambos') AND activo = 1
          ORDER BY descripcion";
$stmt = $db->prepare($query);
$stmt->bindParam(':tipo', $material['tipo']);
$stmt->execute();
$presentaciones_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Presentaciones del Material</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📦 Presentaciones del Material</h1>
            <p><?php echo htmlspecialchars($material['nombre']); ?> (<?php echo htmlspecialchars($material['codigo']); ?>)</p>
        </div>
        
        <?php if($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h3 class="section-title">➕ Asignar Presentación</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="accion" value="agregar">
                
                <div class="form-group">
                    <label>Presentación <span class="required">*</span></label>
                    <select name="id_presentacion" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($presentaciones_disponibles as $pres): ?>
                            <option value="<?php echo $pres['id_presentacion']; ?>">
                                <?php echo htmlspecialchars($pres['descripcion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Solo se muestran presentaciones de tipo "<?php echo ucfirst($material['tipo']); ?>" o "Ambos"</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Asignar Presentación</button>
                </div>
            </form>
        </div>
        
        <div style="margin-top: 40px;">
            <h3 class="section-title">📋 Presentaciones Asignadas</h3>
            
            <?php if(count($presentaciones_asignadas) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($presentaciones_asignadas as $pres): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pres['descripcion']); ?></strong></td>
                                    <td>
                                        <?php if($pres['activo']): ?>
                                            <span style="color: #10b981; font-weight: bold;">✓ Activo</span>
                                        <?php else: ?>
                                            <span style="color: #ef4444; font-weight: bold;">✗ Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($pres['activo']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_presentacion_material" value="<?php echo $pres['id_presentacion_material']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar?')">🗑️ Desactivar</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="activar">
                                                <input type="hidden" name="id_presentacion_material" value="<?php echo $pres['id_presentacion_material']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">✓ Activar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="info-box">
                    No hay presentaciones asignadas. Asigne al menos una para poder registrar ingresos/egresos.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="form-actions" style="margin-top: 30px;">
            <a href="../../stock/index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
            <a href="editar.php?id=<?php echo $id_material; ?>" class="btn btn-primary">✏️ Editar Material</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>