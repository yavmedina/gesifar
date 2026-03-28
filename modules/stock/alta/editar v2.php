<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('stock.editar');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: ../index.php");
    exit();
}

// Obtener material
$query = "SELECT * FROM material WHERE id_material = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$material) {
    header("Location: ../index.php?error=Material no encontrado");
    exit();
}

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $nombre_comercial = trim($_POST['nombre_comercial']);
    $principio_activo = trim($_POST['principio_activo']);
    $concentracion = trim($_POST['concentracion']);
    $tipo = $_POST['tipo'];
    $id_forma_farmaceutica = !empty($_POST['id_forma_farmaceutica']) ? (int)$_POST['id_forma_farmaceutica'] : null;
    $id_presentacion = !empty($_POST['id_presentacion']) ? (int)$_POST['id_presentacion'] : null;
    $id_proveedor = (int)$_POST['id_proveedor'];
    
    $stock_minimo = (int)$_POST['stock_minimo'];
    $punto_pedido = (int)$_POST['punto_pedido'];
    $stock_maximo = (int)$_POST['stock_maximo'];
    $clasificacion_abc = $_POST['clasificacion_abc'];
    
    $requiere_receta = isset($_POST['requiere_receta']) ? 1 : 0;
    $psicofarmacos = isset($_POST['psicofarmacos']) ? 1 : 0;
    $controlado = isset($_POST['controlado']) ? 1 : 0;
    
    if(empty($nombre) || empty($tipo) || $id_proveedor == 0) {
        $error = "Complete todos los campos obligatorios";
    } else {
        try {
            $query = "UPDATE material SET
                nombre = :nombre,
                nombre_comercial = :nombre_comercial,
                principio_activo = :principio_activo,
                concentracion = :concentracion,
                tipo = :tipo,
                id_forma_farmaceutica = :id_forma_farmaceutica,
                id_presentacion = :id_presentacion,
                id_proveedor = :id_proveedor,
                stock_minimo = :stock_minimo,
                stock_maximo = :stock_maximo,
                punto_pedido = :punto_pedido,
                clasificacion_abc = :clasificacion_abc,
                requiere_receta = :requiere_receta,
                psicofarmacos = :psicofarmacos,
                controlado = :controlado
            WHERE id_material = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':nombre_comercial', $nombre_comercial);
            $stmt->bindParam(':principio_activo', $principio_activo);
            $stmt->bindParam(':concentracion', $concentracion);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':id_forma_farmaceutica', $id_forma_farmaceutica);
            $stmt->bindParam(':id_presentacion', $id_presentacion);
            $stmt->bindParam(':id_proveedor', $id_proveedor);
            $stmt->bindParam(':stock_minimo', $stock_minimo);
            $stmt->bindParam(':stock_maximo', $stock_maximo);
            $stmt->bindParam(':punto_pedido', $punto_pedido);
            $stmt->bindParam(':clasificacion_abc', $clasificacion_abc);
            $stmt->bindParam(':requiere_receta', $requiere_receta);
            $stmt->bindParam(':psicofarmacos', $psicofarmacos);
            $stmt->bindParam(':controlado', $controlado);
            $stmt->bindParam(':id', $id);
            
            if($stmt->execute()) {
                header("Location: ../index.php?msg=Material actualizado correctamente");
                exit();
            }
        } catch(PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Obtener datos para selects
$formas = $db->query("SELECT * FROM forma_farmaceutica ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
$presentaciones = $db->query("SELECT * FROM presentaciones WHERE activo = 1 ORDER BY tipo, descripcion")->fetchAll(PDO::FETCH_ASSOC);
$proveedores = $db->query("SELECT * FROM proveedor WHERE activo = 1 ORDER BY razon_social")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Editar Material</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">

    <!-- script de Laura Schell para bloquear forma farmacéutica -->
    <script>
        // Datos de presentaciones en JSON
        const presentaciones = <?php echo json_encode($presentaciones); ?>;
        
        function toggleFormaFarmaceutica() {
            const tipo = document.getElementById('tipo').value;
            const ffSelect = document.getElementById('forma_farmaceutica');
            const presSelect = document.getElementById('presentacion');
            
            // Manejar Forma Farmacéutica
            if(tipo === 'insumo') {
                ffSelect.disabled = true;
                ffSelect.value = '';
                ffSelect.style.background = '#f3f4f6';
            } else {
                ffSelect.disabled = false;
                ffSelect.style.background = 'white';
            }
            
            // Filtrar Presentaciones
            const valorActual = presSelect.value;
            presSelect.innerHTML = '<option value="">Seleccione...</option>';
            
            if(tipo) {
                presentaciones.forEach(pres => {
                    if(pres.tipo === tipo || pres.tipo === 'ambos') {
                        const option = document.createElement('option');
                        option.value = pres.id_presentacion;
                        option.textContent = pres.descripcion;
                        if(pres.id_presentacion == valorActual) {
                            option.selected = true;
                        }
                        presSelect.appendChild(option);
                    }
                });
            }
        }
        
        // Ejecutar al cargar la página
        window.addEventListener('DOMContentLoaded', function() {
            toggleFormaFarmaceutica();
        });
    </script>
<!-- fin del scrip bloquea menú desplegable forma farmacéutica -->


</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>✏️ Editar Material</h1>
            <p>Modificar ficha técnica del material</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="info-box">
                <strong>Código:</strong> <?php echo htmlspecialchars($material['codigo']); ?><br>
                <strong>Stock actual:</strong> <?php echo $material['stock_actual']; ?> unidades<br>
                <small>⚠️ El stock NO se modifica desde aquí. Use Ingreso/Egreso/Ajuste.</small>
            </div>
            
            <form method="POST" action="">
                <h3 class="section-title">Información Básica</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Genérico <span class="required">*</span></label>
                        <input type="text" name="nombre" required value="<?php echo htmlspecialchars($material['nombre']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" value="<?php echo htmlspecialchars($material['nombre_comercial']); ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Principio Activo</label>
                        <input type="text" name="principio_activo" value="<?php echo htmlspecialchars($material['principio_activo']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Concentración</label>
                        <input type="text" name="concentracion" value="<?php echo htmlspecialchars($material['concentracion']); ?>">
                    </div>
                </div>
                
                <h3 class="section-title">Clasificación</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tipo de Material <span class="required">*</span></label>
                        <select name="tipo" id="tipo" required onchange="toggleFormaFarmaceutica()">
                            <option value="">Seleccione...</option>
                            <option value="medicamento" <?php echo $material['tipo'] == 'medicamento' ? 'selected' : ''; ?>>Medicamento</option>
                            <option value="insumo" <?php echo $material['tipo'] == 'insumo' ? 'selected' : ''; ?>>Insumo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Forma Farmacéutica</label>
                        <select name="id_forma_farmaceutica" id="forma_farmaceutica">
                            <option value="">Seleccione...</option>
                            <?php foreach($formas as $forma): ?>
                                <option value="<?php echo $forma['id_forma_farmaceutica']; ?>"
                                    <?php echo $material['id_forma_farmaceutica'] == $forma['id_forma_farmaceutica'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($forma['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">Solo para medicamentos</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Presentación</label>
                        <select name="id_presentacion" id="presentacion">
                            <option value="">Cargando...</option>
                        </select>
                        <small style="color: #6b7280;">Se filtra según tipo de material</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Proveedor Principal <span class="required">*</span></label>
                        <select name="id_proveedor" required>
                            <?php foreach($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>"
                                    <?php echo $material['id_proveedor'] == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['razon_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <script>
                    // Guardar valor actual de presentación
                    const presentacionActual = <?php echo $material['id_presentacion'] ?: 'null'; ?>;
                </script>
                
                <h3 class="section-title">Control de Stock</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Stock Mínimo <span class="required">*</span></label>
                        <input type="number" name="stock_minimo" required min="0" value="<?php echo $material['stock_minimo']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Punto de Pedido <span class="required">*</span></label>
                        <input type="number" name="punto_pedido" required min="0" value="<?php echo $material['punto_pedido']; ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Stock Máximo <span class="required">*</span></label>
                        <input type="number" name="stock_maximo" required min="0" value="<?php echo $material['stock_maximo']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Clasificación ABC</label>
                        <select name="clasificacion_abc">
                            <option value="C" <?php echo $material['clasificacion_abc'] == 'C' ? 'selected' : ''; ?>>C - Bajo valor/volumen</option>
                            <option value="B" <?php echo $material['clasificacion_abc'] == 'B' ? 'selected' : ''; ?>>B - Valor/volumen medio</option>
                            <option value="A" <?php echo $material['clasificacion_abc'] == 'A' ? 'selected' : ''; ?>>A - Alto valor/volumen</option>
                        </select>
                    </div>
                </div>
                
                <h3 class="section-title">Control Normativo</h3>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="requiere_receta" <?php echo $material['requiere_receta'] ? 'checked' : ''; ?>>
                        Requiere Receta
                    </label>
                    
                    <label>
                        <input type="checkbox" name="psicofarmacos" <?php echo $material['psicofarmacos'] ? 'checked' : ''; ?>>
                        Psicofármaco
                    </label>
                    
                    <label>
                        <input type="checkbox" name="controlado" <?php echo $material['controlado'] ? 'checked' : ''; ?>>
                        Controlado
                    </label>
                </div>
                
                <div class="form-actions">
                <a href="../index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                <a href="presentaciones.php?id=<?php echo $id; ?>" class="btn" style="background: #8b5cf6; color: white;">📦 Presentaciones</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>