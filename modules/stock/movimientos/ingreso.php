<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('stock.ingreso');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$mensaje = '';

// Guardar proveedor
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'set_proveedor') {
    $_SESSION['proveedor_ingreso'] = (int)$_POST['id_proveedor'];
    $_SESSION['observaciones_ingreso'] = trim($_POST['observaciones']);
    $mensaje = "Proveedor confirmado";
}

// Inicializar sesión temporal
if(!isset($_SESSION['ingreso_temp'])) {
    $_SESSION['ingreso_temp'] = ['medicamentos' => [], 'insumos' => []];
}

$items_temp = $_SESSION['ingreso_temp'];

// Agregar item al ingreso temporal
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $id_material = (int)$_POST['id_material'];
    $id_presentacion_material = (int)$_POST['id_presentacion_material'];
    $cantidad = (int)$_POST['cantidad'];
    $lote = trim($_POST['lote']);
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $precio = (float)$_POST['precio'];
    $tipo = $_POST['tipo'];
    
    if($id_material > 0 && $id_presentacion_material > 0 && $cantidad > 0 && !empty($lote)) {
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
            $item = [
                'id_material' => $id_material,
                'id_presentacion_material' => $id_presentacion_material,
                'nombre' => $info['nombre'],
                'codigo' => $info['codigo'],
                'presentacion' => $info['presentacion_desc'],
                'cantidad' => $cantidad,
                'lote' => $lote,
                'fecha_vencimiento' => $fecha_vencimiento,
                'precio' => $precio
            ];
            
            $items_temp[$tipo][] = $item;
            $_SESSION['ingreso_temp'] = $items_temp;
            $mensaje = "Material agregado al ingreso";
        } else {
            $error = "Material o presentación no encontrados";
        }
    } else {
        $error = "Complete todos los campos obligatorios";
    }
}

// Eliminar item del ingreso temporal
if(isset($_GET['eliminar'])) {
    $tipo = $_GET['tipo'];
    $indice = (int)$_GET['eliminar'];
    if(isset($items_temp[$tipo][$indice])) {
        unset($items_temp[$tipo][$indice]);
        $items_temp[$tipo] = array_values($items_temp[$tipo]);
        $_SESSION['ingreso_temp'] = $items_temp;
        header("Location: ingreso.php");
        exit();
    }
}

// Procesar ingreso final
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'finalizar') {
    $id_proveedor = $_SESSION['proveedor_ingreso'];
    $observaciones = $_SESSION['observaciones_ingreso'];
    
    $todos_items = array_merge($items_temp['medicamentos'], $items_temp['insumos']);
    
    if(count($todos_items) == 0) {
        $error = "Debe agregar al menos un material";
    } elseif($id_proveedor == 0) {
        $error = "Seleccione el proveedor";
    } else {
        try {
            $db->beginTransaction();
            
            // Generar código de ingreso
            $query_last = "SELECT codigo_ingreso FROM ingreso_stock ORDER BY id_ingreso DESC LIMIT 1";
            $last = $db->query($query_last)->fetch(PDO::FETCH_ASSOC);
            
            if($last) {
                $num = (int)substr($last['codigo_ingreso'], 4) + 1;
                $codigo = 'ING-' . str_pad($num, 6, '0', STR_PAD_LEFT);
            } else {
                $codigo = 'ING-000001';
            }
            
            // Insertar ingreso
            $query = "INSERT INTO ingreso_stock (codigo_ingreso, fecha_ingreso, id_proveedor, observaciones, total_items, usuario_registro)
                      VALUES (:codigo, CURDATE(), :prov, :obs, :total, :usuario)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':prov', $id_proveedor);
            $stmt->bindParam(':obs', $observaciones);
            $total = count($todos_items);
            $stmt->bindParam(':total', $total);
            $usuario = $_SESSION['username'];
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            $id_ingreso = $db->lastInsertId();
            
            // Insertar detalles y actualizar stock
            foreach($todos_items as $item) {
                // Crear lote
                $query_lote = "INSERT INTO lotes_material (
                    id_material, lote, fecha_ingreso, fecha_vencimiento,
                    cantidad_inicial, cantidad_actual, id_proveedor, id_presentacion_material
                ) VALUES (
                    :material, :lote, CURDATE(), :venc, :cant, :cant, :prov, :id_pres_mat
                )";
                $stmt_lote = $db->prepare($query_lote);
                $stmt_lote->bindParam(':material', $item['id_material']);
                $stmt_lote->bindParam(':lote', $item['lote']);
                $stmt_lote->bindParam(':venc', $item['fecha_vencimiento']);
                $stmt_lote->bindParam(':cant', $item['cantidad']);
                $stmt_lote->bindParam(':prov', $id_proveedor);
                $stmt_lote->bindParam(':id_pres_mat', $item['id_presentacion_material']);
                $stmt_lote->execute();
                
                $id_lote = $db->lastInsertId();
                
                // Detalle del ingreso
                $query_det = "INSERT INTO detalle_ingreso_stock (
                    id_ingreso, id_material, id_presentacion_material, cantidad_unidades, precio_unitario, id_lote_generado
                ) VALUES (
                    :ingreso, :material, :pres, :cantidad, :precio, :lote
                )";
                $stmt_det = $db->prepare($query_det);
                $stmt_det->bindParam(':ingreso', $id_ingreso);
                $stmt_det->bindParam(':material', $item['id_material']);
                $stmt_det->bindParam(':pres', $item['id_presentacion_material']);
                $stmt_det->bindParam(':cantidad', $item['cantidad']);
                $stmt_det->bindParam(':precio', $item['precio']);
                $stmt_det->bindParam(':lote', $id_lote);
                $stmt_det->execute();
                
                // Obtener stock actual
                $query_stock = "SELECT stock_actual FROM material WHERE id_material = :id";
                $stmt_stock = $db->prepare($query_stock);
                $stmt_stock->bindParam(':id', $item['id_material']);
                $stmt_stock->execute();
                $stock_actual = $stmt_stock->fetchColumn();
                
                $stock_anterior = $stock_actual;
                $stock_posterior = $stock_actual + $item['cantidad'];
                
                // Registrar en movimiento_stock
                $query_mov = "INSERT INTO movimiento_stock (
                    id_material, tipo_movimiento, cantidad, stock_anterior, stock_posterior,
                    fecha, hora, lote, motivo, id_personal, id_lote
                ) VALUES (
                    :material, 'ingreso', :cant, :stock_ant, :stock_post,
                    CURDATE(), CURTIME(), :lote, :motivo, :usuario, :id_lote
                )";
                
                $stmt_mov = $db->prepare($query_mov);
                $motivo = "Ingreso $codigo";
                $id_personal = $_SESSION['username'];
                $stmt_mov->bindParam(':material', $item['id_material']);
                $stmt_mov->bindParam(':cant', $item['cantidad']);
                $stmt_mov->bindParam(':stock_ant', $stock_anterior);
                $stmt_mov->bindParam(':stock_post', $stock_posterior);
                $stmt_mov->bindParam(':lote', $item['lote']);
                $stmt_mov->bindParam(':motivo', $motivo);
                $stmt_mov->bindParam(':usuario', $id_personal);
                $stmt_mov->bindParam(':id_lote', $id_lote);
                $stmt_mov->execute();
            }
            
            $db->commit();
            
            // Limpiar sesión temporal
            unset($_SESSION['ingreso_temp']);
            unset($_SESSION['proveedor_ingreso']);
            unset($_SESSION['observaciones_ingreso']);
            
            header("Location: ../../stock/index.php?msg=Ingreso $codigo registrado correctamente");
            exit();
            
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Obtener proveedores activos
$proveedores = $db->query("SELECT * FROM proveedor WHERE activo = 1 ORDER BY razon_social")->fetchAll(PDO::FETCH_ASSOC);

// Obtener materiales activos
$medicamentos = $db->query("SELECT * FROM material WHERE tipo = 'medicamento' AND activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$insumos = $db->query("SELECT * FROM material WHERE tipo = 'insumo' AND activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener TODAS las presentaciones de materiales
$query_pres = "SELECT pm.*, p.descripcion, m.id_material as material_id
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
    <title>GESIFAR - Ingreso de Stock</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_form.css">
    <script>
        const presentaciones = <?php echo json_encode($todas_presentaciones); ?>;
        
        function filtrarPresentaciones(tipo) {
            const materialSelect = document.getElementById('material_' + tipo);
            const presSelect = document.getElementById('presentacion_' + tipo);
            
            const materialId = materialSelect.value;
            
            presSelect.innerHTML = '<option value="">Seleccione...</option>';
            
            if(materialId) {
                const presMaterial = presentaciones.filter(p => p.material_id == materialId);
                
                if(presMaterial.length > 0) {
                    presMaterial.forEach(pres => {
                        const option = document.createElement('option');
                        option.value = pres.id_presentacion_material;
                        option.textContent = pres.descripcion;
                        presSelect.appendChild(option);
                    });
                } else {
                    presSelect.innerHTML = '<option value="">Sin presentaciones registradas</option>';
                }
            }
        }
    </script>
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📦 Ingreso de Stock</h1>
            <p>Registrar entrada de materiales desde proveedor</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <!-- Proveedor -->
        <div class="form-container">
            <h3>🏢 Proveedor</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="set_proveedor">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Proveedor <span class="required">*</span></label>
                        <select name="id_proveedor" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>" 
                                    <?php echo (isset($_SESSION['proveedor_ingreso']) && $_SESSION['proveedor_ingreso'] == $prov['id_proveedor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['razon_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="2"><?php echo isset($_SESSION['observaciones_ingreso']) ? htmlspecialchars($_SESSION['observaciones_ingreso']) : ''; ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">✓ Confirmar Proveedor</button>
            </form>
        </div>
        
        <?php if(!isset($_SESSION['proveedor_ingreso'])): ?>
            <div class="info-box" style="margin-top: 20px;">
                <strong>⚠️ Debe seleccionar el proveedor antes de agregar materiales</strong>
            </div>
        <?php else: ?>
            <div class="alert alert-success" style="margin-top: 20px;">
                <strong>✓ Proveedor seleccionado:</strong> 
                <?php 
                $prov_sel = array_filter($proveedores, function($p) { return $p['id_proveedor'] == $_SESSION['proveedor_ingreso']; });
                echo $prov_sel ? htmlspecialchars(reset($prov_sel)['razon_social']) : '';
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulario de ingreso -->
        <?php if(isset($_SESSION['proveedor_ingreso'])): ?>
        <div class="form-container">
            <h3>Agregar Materiales al Ingreso</h3>
            
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
                                <select name="id_presentacion_material" id="presentacion_medicamentos" required>
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cantidad <span class="required">*</span></label>
                                <input type="number" name="cantidad" required min="1">
                            </div>
                            
                            <div class="form-group">
                                <label>Lote <span class="required">*</span></label>
                                <input type="text" name="lote" required placeholder="Ej: L12345">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Fecha Vencimiento <span class="required">*</span></label>
                                <input type="date" name="fecha_vencimiento" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Precio Unitario <span class="required">*</span></label>
                                <input type="number" name="precio" required min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">➕ Agregar</button>
                    <?php else: ?>
                        <p style="color: #666;">No hay medicamentos activos</p>
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
                                <select name="id_presentacion_material" id="presentacion_insumos" required>
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cantidad <span class="required">*</span></label>
                                <input type="number" name="cantidad" required min="1">
                            </div>
                            
                            <div class="form-group">
                                <label>Lote <span class="required">*</span></label>
                                <input type="text" name="lote" required placeholder="Ej: L12345">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Fecha Vencimiento <span class="required">*</span></label>
                                <input type="date" name="fecha_vencimiento" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Precio Unitario <span class="required">*</span></label>
                                <input type="number" name="precio" required min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">➕ Agregar</button>
                    <?php else: ?>
                        <p style="color: #666;">No hay insumos activos</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?> <!-- Fin if proveedor seleccionado -->
        
        <!-- Items agregados -->
        <?php if(count($items_temp['medicamentos']) > 0 || count($items_temp['insumos']) > 0): ?>
            <div class="form-container" style="margin-top: 30px;">
                <h3>📋 Items a Ingresar</h3>
                
                <?php if(count($items_temp['medicamentos']) > 0): ?>
                    <h4>💊 Medicamentos</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Presentación</th>
                                <th>Cantidad</th>
                                <th>Lote</th>
                                <th>Vencimiento</th>
                                <th>Precio</th>
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
                                    <td><?php echo htmlspecialchars($item['lote']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?></td>
                                    <td>$<?php echo number_format($item['precio'], 2); ?></td>
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
                                <th>Lote</th>
                                <th>Vencimiento</th>
                                <th>Precio</th>
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
                                    <td><?php echo htmlspecialchars($item['lote']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['fecha_vencimiento'])); ?></td>
                                    <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                    <td>
                                        <a href="?eliminar=<?php echo $idx; ?>&tipo=insumos" class="btn btn-sm btn-danger">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <!-- Finalizar ingreso -->
                <form method="POST" style="margin-top: 30px;">
                    <input type="hidden" name="accion" value="finalizar">
                    
                    <div class="form-actions">
                        <a href="ingreso.php?limpiar=1" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                        <button type="submit" class="btn btn-primary">✅ Registrar Ingreso</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['limpiar'])): ?>
            <?php 
            unset($_SESSION['ingreso_temp']); 
            unset($_SESSION['proveedor_ingreso']);
            unset($_SESSION['observaciones_ingreso']);
            header("Location: ingreso.php"); 
            exit(); 
            ?>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="../../stock/index.php" class="btn" style="background: #6c757d; color: white;">← Volver al Stock</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>