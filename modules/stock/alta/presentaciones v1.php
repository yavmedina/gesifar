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

// Agregar presentación
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    if($_POST['accion'] == 'agregar') {
        $descripcion = trim($_POST['descripcion']);
        $unidad_base = trim($_POST['unidad_base']);
        $factor = (int)$_POST['factor_conversion'];
        
        if(empty($descripcion) || empty($unidad_base) || $factor <= 0) {
            $error = "Complete todos los campos";
        } else {
            try {
                $query = "INSERT INTO presentacion_material (id_material, descripcion, unidad_base, factor_conversion, activo) 
                          VALUES (:id_material, :descripcion, :unidad_base, :factor, 1)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id_material', $id_material);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->bindParam(':unidad_base', $unidad_base);
                $stmt->bindParam(':factor', $factor);
                
                if($stmt->execute()) {
                    $mensaje = "Presentación agregada correctamente";
                }
            } catch(PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    } elseif($_POST['accion'] == 'eliminar') {
        $id_pres = (int)$_POST['id_presentacion'];
        
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
    }
}

// Obtener presentaciones
$query = "SELECT * FROM presentacion_material WHERE id_material = :id ORDER BY activo DESC, descripcion";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id_material);
$stmt->execute();
$presentaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <h3 class="section-title">➕ Agregar Nueva Presentación</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="accion" value="agregar">
                
                <div class="form-group">
                    <label>Descripción <span class="required">*</span></label>
                    <input type="text" name="descripcion" required placeholder="Ej: Caja x 10 blister x 10 comprimidos">
                    <small>Describe cómo se presenta el material (empaque completo)</small>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Unidad Base <span class="required">*</span></label>
                        <input type="text" name="unidad_base" required placeholder="Ej: comprimido, ml, unidad">
                        <small>La unidad más pequeña (comprimido, cápsula, ml, unidad)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Factor de Conversión <span class="required">*</span></label>
                        <input type="number" name="factor_conversion" required min="1" value="1">
                        <small>¿Cuántas unidades base hay? (Ej: 100 comprimidos)</small>
                    </div>
                </div>
                
                <div class="info-box">
                    <strong>Ejemplo:</strong> Si vendés "Caja x 10 blister x 10 comprimidos":<br>
                    - Descripción: Caja x 10 blister x 10 comprimidos<br>
                    - Unidad base: comprimido<br>
                    - Factor: 100 (porque 10 blister × 10 comp = 100 comprimidos)
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Agregar Presentación</button>
                </div>
            </form>
        </div>
        
        <div style="margin-top: 40px;">
            <h3 class="section-title">📋 Presentaciones Registradas</h3>
            
            <?php if(count($presentaciones) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Unidad Base</th>
                                <th>Factor</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($presentaciones as $pres): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pres['descripcion']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pres['unidad_base']); ?></td>
                                    <td><?php echo $pres['factor_conversion']; ?> unidades</td>
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
                                                <input type="hidden" name="id_presentacion" value="<?php echo $pres['id_presentacion_material']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar?')">🗑️</button>
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
                    No hay presentaciones registradas. Agregue al menos una para poder registrar ingresos.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="form-actions" style="margin-top: 30px;">
            <a href="../index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Listado</a>
            <a href="editar.php?id=<?php echo $id_material; ?>" class="btn btn-primary">Editar Material</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>