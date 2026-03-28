<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('stock.egreso');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$mensaje = '';

// Inicializar sesión temporal
if(!isset($_SESSION['egreso_temp'])) {
    $_SESSION['egreso_temp'] = ['medicamentos' => [], 'insumos' => []];
}

$items_temp = $_SESSION['egreso_temp'];

// Agregar item al egreso temporal
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $id_material = (int)$_POST['id_material'];
    $id_presentacion_material = (int)$_POST['id_presentacion_material'];
    $cantidad = (int)$_POST['cantidad'];
    $tipo = $_POST['tipo'];
    
    if($id_material > 0 && $id_presentacion_material > 0 && $cantidad > 0) {
        // Obtener info del material y presentación
        $query = "SELECT m.*, p.descripcion as presentacion_desc
                  FROM material m
                  JOIN presentacion_material pm ON m.id_material = pm.id_material
                  JOIN presentaciones p ON pm.id_presentacion = p.id_presentacion
                  WHERE m.id_material = :mat AND pm.id_presentacion_material = :pres";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mat', $id_material);
        $stmt->bindParam(':pres', $id_presentacion_material);
        $stmt->execute();
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($info) {
            if($cantidad > $info['stock_actual']) {
                $error = "Stock insuficiente. Disponible: " . $info['stock_actual'];
            } else {
                $item = [
                    'id_material' => $id_material,
                    'id_presentacion_material' => $id_presentacion_material,
                    'nombre' => $info['nombre'],
                    'codigo' => $info['codigo'],
                    'presentacion' => $info['presentacion_desc'],
                    'cantidad' => $cantidad,
                    'stock_disponible' => $info['stock_actual']
                ];
                
                $items_temp[$tipo][] = $item;
                $_SESSION['egreso_temp'] = $items_temp;
                $mensaje = "Material agregado al egreso";
            }
        } else {
            $error = "Material o presentación no encontrados";
        }
    }
}

// Eliminar item del egreso temporal
if(isset($_GET['eliminar'])) {
    $tipo = $_GET['tipo'];
    $indice = (int)$_GET['eliminar'];
    if(isset($items_temp[$tipo][$indice])) {
        unset($items_temp[$tipo][$indice]);
        $items_temp[$tipo] = array_values($items_temp[$tipo]);
        $_SESSION['egreso_temp'] = $items_temp;
        header("Location: egreso.php");
        exit();
    }
}

// Procesar egreso final
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'finalizar') {
    $id_area_destino = (int)$_POST['id_area_destino'];
    $observaciones = trim($_POST['observaciones']);
    
    $todos_items = array_merge($items_temp['medicamentos'], $items_temp['insumos']);
    
    if(count($todos_items) == 0) {
        $error = "Debe agregar al menos un material";
    } elseif($id_area_destino == 0) {
        $error = "Seleccione el área de destino";
    } else {
        try {
            $db->beginTransaction();
            
            // Generar código de egreso
            $query_last = "SELECT codigo_egreso FROM egreso_stock ORDER BY id_egreso DESC LIMIT 1";
            $last = $db->query($query_last)->fetch(PDO::FETCH_ASSOC);
            
            if($last) {
                $num = (int)substr($last['codigo_egreso'], 4) + 1;
                $codigo = 'EGR-' . str_pad($num, 6, '0', STR_PAD_LEFT);
            } else {
                $codigo = 'EGR-000001';
            }
            
            // Insertar egreso
            $query = "INSERT INTO egreso_stock (codigo_egreso, fecha_egreso, id_area_destino, observaciones, total_items, usuario_registro)
                      VALUES (:codigo, CURDATE(), :area, :obs, :total, :usuario)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':area', $id_area_destino);
            $stmt->bindParam(':obs', $observaciones);
            $total = count($todos_items);
            $stmt->bindParam(':total', $total);
            $usuario = $_SESSION['username'];
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            $id_egreso = $db->lastInsertId();
            
            // Insertar detalles y actualizar stock
            foreach($todos_items as $item) {
                // Detalle del egreso
                $query_det = "INSERT INTO detalle_egreso_stock (
                    id_egreso, id_material, id_presentacion_material, cantidad_unidades
                ) VALUES (
                    :egreso, :material, :pres, :cantidad
                )";
                $stmt_det = $db->prepare($query_det);
                $stmt_det->bindParam(':egreso', $id_egreso);
                $stmt_det->bindParam(':material', $item['id_material']);
                $stmt_det->bindParam(':pres', $item['id_presentacion_material']);
                $stmt_det->bindParam(':cantidad', $item['cantidad']);
                $stmt_det->execute();
                
                // Obtener stock actual
                $query_stock = "SELECT stock_actual FROM material WHERE id_material = :id";
                $stmt_stock = $db->prepare($query_stock);
                $stmt_stock->bindParam(':id', $item['id_material']);
                $stmt_stock->execute();
                $stock_actual = $stmt_stock->fetchColumn();
                
                $stock_anterior = $stock_actual;
                $stock_posterior = $stock_actual - $item['cantidad'];
                
                // Registrar en movimiento_stock (el trigger actualiza el stock)
                $query_mov = "INSERT INTO movimiento_stock (
                    id_material, tipo_movimiento, cantidad, stock_anterior, stock_posterior,
                    fecha, hora, motivo, id_personal
                ) VALUES (
                    :material, 'egreso', :cant, :stock_ant, :stock_post,
                    CURDATE(), CURTIME(), :motivo, :usuario
                )";
                
                $stmt_mov = $db->prepare($query_mov);
                $motivo = "Egreso $codigo a área";
                $id_personal = $_SESSION['username'];
                $stmt_mov->bindParam(':material', $item['id_material']);
                $stmt_mov->bindParam(':cant', $item['cantidad']);
                $stmt_mov->bindParam(':stock_ant', $stock_anterior);
                $stmt_mov->bindParam(':stock_post', $stock_posterior);
                $stmt_mov->bindParam(':motivo', $motivo);
                $stmt_mov->bindParam(':usuario', $id_personal);
                $stmt_mov->execute();
                
                // Actualizar stock del área
                $query_area = "INSERT INTO stock_area (id_material, id_area, cantidad) 
                              VALUES (:material, :area, :cantidad)
                              ON DUPLICATE KEY UPDATE cantidad = cantidad + :cantidad2";
                $stmt_area = $db->prepare($query_area);
                $stmt_area->bindParam(':material', $item['id_material']);
                $stmt_area->bindParam(':area', $id_area_destino);
                $stmt_area->bindParam(':cantidad', $item['cantidad']);
                $stmt_area->bindParam(':cantidad2', $item['cantidad']);
                $stmt_area->execute();
            }
            
            $db->commit();
            
            // Limpiar sesión temporal
            unset($_SESSION['egreso_temp']);
            
            header("Location: ../../stock/index.php?msg=Egreso $codigo registrado correctamente");
            exit();
            
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Obtener áreas activas
$areas = $db->query("SELECT * FROM area WHERE activo = 1 ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);

// Obtener materiales con stock disponible
$medicamentos = $db->query("SELECT * FROM material WHERE tipo = 'medicamento' AND activo = 1 AND stock_actual > 0 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$insumos = $db->query("SELECT * FROM material WHERE tipo = 'insumo' AND activo = 1 AND stock_actual > 0 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener TODAS las presentaciones de materiales (para el JS)
$query_pres = "SELECT pm.*, p.descripcion, m.id_material as material_id, m.stock_actual
               FROM presentacion_material pm
               JOIN presentaciones p ON pm.id_presentacion = p.id_presentacion
               JOIN material m ON pm.id_material = m.id_material
               WHERE pm.activo = 1 AND m.activo = 1
               ORDER BY p.descripcion";
$todas_presentaciones = $db->query($query_pres)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Egreso de Stock a Área</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
    <script>
        // Presentaciones en JSON
        const presentaciones = <?php echo json_encode($todas_presentaciones); ?>;
        
        function filtrarPresentaciones(tipo) {
            const materialSelect = document.getElementById('material_' + tipo);
            const presSelect = document.getElementById('presentacion_' + tipo);
            const cantidadInput = document.getElementById('cantidad_' + tipo);
            const stockSpan = document.getElementById('stock_' + tipo);
            
            const materialId = materialSelect.value;
            
            // Limpiar presentaciones
            presSelect.innerHTML = '<option value="">Seleccione...</option>';
            cantidadInput.value = '';
            cantidadInput.max = '';
            stockSpan.textContent = '';
            
            if(materialId) {
                // Filtrar presentaciones de ese material
                const presMaterial = presentaciones.filter(p => p.material_id == materialId);
                
                if(presMaterial.length > 0) {
                    presMaterial.forEach(pres => {
                        const option = document.createElement('option');
                        option.value = pres.id_presentacion_material;
                        option.textContent = pres.descripcion;
                        option.dataset.stock = pres.stock_actual;
                        presSelect.appendChild(option);
                    });
                    
                    // Mostrar stock del material
                    stockSpan.textContent = 'Stock disponible: ' + presMaterial[0].stock_actual;
                } else {
                    presSelect.innerHTML = '<option value="">Sin presentaciones registradas</option>';
                }
            }
        }
        
        function actualizarMax(tipo) {
            const presSelect = document.getElementById('presentacion_' + tipo);
            const cantidadInput = document.getElementById('cantidad_' + tipo);
            
            const selectedOption = presSelect.options[presSelect.selectedIndex];
            if(selectedOption && selectedOption.dataset.stock) {
                const stock = parseInt(selectedOption.dataset.stock);
                cantidadInput.max = stock;
            }
        }
    </script>
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📤 Egreso de Stock a Área</h1>
            <p>Registrar salida de materiales hacia áreas del hospital</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <!-- Formulario de egreso -->
        <div class="form-container">
            <h3>Agregar Materiales al Egreso</h3>
            
            <!-- Medicamentos -->
            <div style="margin-bottom: 30px;">
                <h4>💊 Medicamentos</h4>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="hidden" name="tipo" value="medicamentos">
                    
                    <?php if(count($medicamentos) > 0): ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Medicamento <span class="required">*</span></label>
                                <select name="id_material" id="material_medicamentos" required onchange="filtrarPresentaciones('medicamentos')">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($medicamentos as $med): ?>
                                        <option value="<?php echo $med['id_material']; ?>">
                                            <?php echo htmlspecialchars($med['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Presentación <span class="required">*</span></label>
                                <select name="id_presentacion_material" id="presentacion_medicamentos" required onchange="actualizarMax('medicamentos')">
                                    <option value="">Seleccione...</option>
                                </select>
                                <small id="stock_medicamentos" style="color: #666;"></small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad <span class="required">*</span></label>
                            <input type="number" name="cantidad" id="cantidad_medicamentos" required min="1">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">➕ Agregar</button>
                    <?php else: ?>
                        <p style="color: #666;">No hay medicamentos con stock disponible</p>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Insumos -->
            <div style="margin-bottom: 30px;">
                <h4>🔧 Insumos</h4>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="hidden" name="tipo" value="insumos">
                    
                    <?php if(count($insumos) > 0): ?>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Insumo <span class="required">*</span></label>
                                <select name="id_material" id="material_insumos" required onchange="filtrarPresentaciones('insumos')">
                                    <option value="">Seleccione...</option>
                                    <?php foreach($insumos as $ins): ?>
                                        <option value="<?php echo $ins['id_material']; ?>">
                                            <?php echo htmlspecialchars($ins['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Presentación <span class="required">*</span></label>
                                <select name="id_presentacion_material" id="presentacion_insumos" required onchange="actualizarMax('insumos')">
                                    <option value="">Seleccione...</option>
                                </select>
                                <small id="stock_insumos" style="color: #666;"></small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad <span class="required">*</span></label>
                            <input type="number" name="cantidad" id="cantidad_insumos" required min="1">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">➕ Agregar</button>
                    <?php else: ?>
                        <p style="color: #666;">No hay insumos con stock disponible</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Items agregados -->
        <?php if(count($items_temp['medicamentos']) > 0 || count($items_temp['insumos']) > 0): ?>
            <div class="form-container" style="margin-top: 30px;">
                <h3>📋 Items a Egresar</h3>
                
                <?php if(count($items_temp['medicamentos']) > 0): ?>
                    <h4>💊 Medicamentos</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Presentación</th>
                                <th>Cantidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items_temp['medicamentos'] as $idx => $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($item['presentacion']); ?></td>
                                    <td><?php echo $item['cantidad']; ?></td>
                                    <td>
                                        <a href="?eliminar=<?php echo $idx; ?>&tipo=medicamentos" class="btn btn-sm btn-danger">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if(count($items_temp['insumos']) > 0): ?>
                    <h4>🔧 Insumos</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Presentación</th>
                                <th>Cantidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items_temp['insumos'] as $idx => $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($item['presentacion']); ?></td>
                                    <td><?php echo $item['cantidad']; ?></td>
                                    <td>
                                        <a href="?eliminar=<?php echo $idx; ?>&tipo=insumos" class="btn btn-sm btn-danger">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <!-- Finalizar egreso -->
                <form method="POST" style="margin-top: 30px;">
                    <input type="hidden" name="accion" value="finalizar">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Área Destino <span class="required">*</span></label>
                            <select name="id_area_destino" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($areas as $area): ?>
                                    <option value="<?php echo $area['id_area']; ?>">
                                        <?php echo htmlspecialchars($area['descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Observaciones</label>
                            <textarea name="observaciones" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="egreso.php?limpiar=1" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                        <button type="submit" class="btn btn-primary">✅ Registrar Egreso</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['limpiar'])): ?>
            <?php unset($_SESSION['egreso_temp']); header("Location: egreso.php"); exit(); ?>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="../../stock/index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>