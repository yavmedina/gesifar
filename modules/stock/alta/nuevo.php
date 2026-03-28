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

$mensaje = '';
$error = '';

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener datos
    $nombre = trim($_POST['nombre']);
    $nombre_comercial = trim($_POST['nombre_comercial']);
    $principio_activo = trim($_POST['principio_activo']);
    $concentracion = trim($_POST['concentracion']);
    $tipo = $_POST['tipo']; // medicamento o insumo
    $id_forma_farmaceutica = !empty($_POST['id_forma_farmaceutica']) ? (int)$_POST['id_forma_farmaceutica'] : null;
    $id_presentacion = !empty($_POST['id_presentacion']) ? (int)$_POST['id_presentacion'] : null;
    
    $stock_minimo = (int)$_POST['stock_minimo'];
    $punto_pedido = (int)$_POST['punto_pedido'];
    $stock_maximo = (int)$_POST['stock_maximo'];
    $clasificacion_abc = $_POST['clasificacion_abc'];
    
    $requiere_receta = isset($_POST['requiere_receta']) ? 1 : 0;
    $psicofarmacos = isset($_POST['psicofarmacos']) ? 1 : 0;
    $controlado = isset($_POST['controlado']) ? 1 : 0;
    
    // Validar
    if(empty($nombre) || empty($tipo) || empty($id_presentacion)) {
        $error = "Complete todos los campos obligatorios (nombre, tipo, presentación)";
    } else {
        try {
            // Generar código automático según tipo
            $prefijo = ($tipo == 'medicamento') ? 'MED' : 'INS';
            
            $query_last = "SELECT codigo FROM material WHERE codigo LIKE :prefijo ORDER BY id_material DESC LIMIT 1";
            $stmt_last = $db->prepare($query_last);
            $prefijo_busqueda = $prefijo . '-%';
            $stmt_last->bindParam(':prefijo', $prefijo_busqueda);
            $stmt_last->execute();
            $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
            
            if($last) {
                $num = (int)substr($last['codigo'], 4) + 1;
                $codigo = $prefijo . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
            } else {
                $codigo = $prefijo . '-001';
            }
            
            // Insertar material
            $db->beginTransaction();
            
            $query = "INSERT INTO material (
                codigo, nombre, nombre_comercial, principio_activo, concentracion,
                stock_actual, stock_minimo, stock_maximo, punto_pedido,
                clasificacion_abc, requiere_receta, psicofarmacos, controlado,
                tipo, id_forma_farmaceutica,
                activo
            ) VALUES (
                :codigo, :nombre, :nombre_comercial, :principio_activo, :concentracion,
                0, :stock_minimo, :stock_maximo, :punto_pedido,
                :clasificacion_abc, :requiere_receta, :psicofarmacos, :controlado,
                :tipo, :id_forma_farmaceutica,
                1
            )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':nombre_comercial', $nombre_comercial);
            $stmt->bindParam(':principio_activo', $principio_activo);
            $stmt->bindParam(':concentracion', $concentracion);
            $stmt->bindParam(':stock_minimo', $stock_minimo);
            $stmt->bindParam(':stock_maximo', $stock_maximo);
            $stmt->bindParam(':punto_pedido', $punto_pedido);
            $stmt->bindParam(':clasificacion_abc', $clasificacion_abc);
            $stmt->bindParam(':requiere_receta', $requiere_receta);
            $stmt->bindParam(':psicofarmacos', $psicofarmacos);
            $stmt->bindParam(':controlado', $controlado);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':id_forma_farmaceutica', $id_forma_farmaceutica);
            
            $stmt->execute();
            $id_material = $db->lastInsertId();
            
            // Asignar presentación automáticamente
            $query_pres = "INSERT INTO presentacion_material (id_material, id_presentacion, activo)
                          VALUES (:material, :presentacion, 1)";
            $stmt_pres = $db->prepare($query_pres);
            $stmt_pres->bindParam(':material', $id_material);
            $stmt_pres->bindParam(':presentacion', $id_presentacion);
            $stmt_pres->execute();
            
            $db->commit();
            
            header("Location: ../index.php?msg=Material dado de alta correctamente - Código: $codigo");
            exit();
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Obtener datos para selects
$formas = $db->query("SELECT * FROM forma_farmaceutica ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
$presentaciones = $db->query("SELECT * FROM presentaciones WHERE activo = 1 ORDER BY tipo, descripcion")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Dar de Alta Material</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">

<!--script de Laura Schell para bloquear el menú desplegable -->
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
            presSelect.innerHTML = '<option value="">Seleccione...</option>';
            
            if(tipo) {
                presentaciones.forEach(pres => {
                    if(pres.tipo === tipo || pres.tipo === 'ambos') {
                        const option = document.createElement('option');
                        option.value = pres.id_presentacion;
                        option.textContent = pres.descripcion;
                        presSelect.appendChild(option);
                    }
                });
            }
        }
    </script>
<!-- fin del script bloquea menu desplegable -->

</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>➕ Dar de Alta Nuevo Material</h1>
            <p>Registrar nuevo material en el catálogo del sistema</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <h3 class="section-title">Información Básica</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Genérico <span class="required">*</span></label>
                        <input type="text" name="nombre" required placeholder="Ej: Paracetamol 500mg">
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" placeholder="Ej: Tylenol">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Principio Activo</label>
                        <input type="text" name="principio_activo" placeholder="Ej: Paracetamol">
                    </div>
                    
                    <div class="form-group">
                        <label>Concentración</label>
                        <input type="text" name="concentracion" placeholder="Ej: 500mg">
                    </div>
                </div>
                
                <h3 class="section-title">Clasificación</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tipo de Material <span class="required">*</span></label>
                        <select name="tipo" id="tipo" required onchange="toggleFormaFarmaceutica()">
                            <option value="">Seleccione...</option>
                            <option value="medicamento">Medicamento</option>
                            <option value="insumo">Insumo</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Forma Farmacéutica</label>
                        <select name="id_forma_farmaceutica" id="forma_farmaceutica">
                            <option value="">Seleccione...</option>
                            <?php foreach($formas as $forma): ?>
                                <option value="<?php echo $forma['id_forma_farmaceutica']; ?>">
                                    <?php echo htmlspecialchars($forma['descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6b7280;">Solo para medicamentos</small>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Presentación <span class="required">*</span></label>
                        <select name="id_presentacion" id="presentacion" required>
                            <option value="">Seleccione primero el tipo de material...</option>
                        </select>
                        <small style="color: #6b7280;">Se filtra según tipo de material</small>
                    </div>
                </div>
                
                <h3 class="section-title">Control de Stock</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Stock Mínimo <span class="required">*</span></label>
                        <input type="number" name="stock_minimo" required min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Punto de Pedido <span class="required">*</span></label>
                        <input type="number" name="punto_pedido" required min="0" value="0">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Stock Máximo <span class="required">*</span></label>
                        <input type="number" name="stock_maximo" required min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Clasificación ABC</label>
                        <select name="clasificacion_abc">
                            <option value="C">C - Bajo valor/volumen</option>
                            <option value="B">B - Valor/volumen medio</option>
                            <option value="A">A - Alto valor/volumen</option>
                        </select>
                    </div>
                </div>
                
                <h3 class="section-title">Control Normativo</h3>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="requiere_receta">
                        Requiere Receta Médica
                    </label>
                    
                    <label>
                        <input type="checkbox" name="psicofarmacos">
                        Psicofármaco
                    </label>
                    
                    <label>
                        <input type="checkbox" name="controlado">
                        Medicamento Controlado
                    </label>
                </div>
                
                <div class="form-actions">
                    <a href="../index.php" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Dar de Alta Material</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>