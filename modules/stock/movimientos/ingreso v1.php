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
$items_temp = isset($_SESSION['ingreso_temp']) ? $_SESSION['ingreso_temp'] : ['medicamentos' => [], 'insumos' => []];

$med_seleccionado = isset($_GET['med']) ? (int)$_GET['med'] : 0;
$ins_seleccionado = isset($_GET['ins']) ? (int)$_GET['ins'] : 0;

// Procesar acciones
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'];
    
    if($accion == 'agregar_item') {
        $tipo = $_POST['tipo'];
        $id_material = (int)$_POST['id_material'];
        $id_presentacion = (int)$_POST['id_presentacion'];
        $lote = trim($_POST['lote']);
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        $cantidad_cajas = (int)$_POST['cantidad_cajas'];
        $precio_unitario = (float)$_POST['precio_unitario'];
        
        if($id_material > 0 && $id_presentacion > 0 && !empty($lote) && $cantidad_cajas > 0) {
            $query = "SELECT m.nombre, m.codigo, p.descripcion as presentacion, p.factor_conversion
                      FROM material m
                      JOIN presentacion_material p ON m.id_material = p.id_material
                      WHERE m.id_material = :mat AND p.id_presentacion_material = :pres";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':mat', $id_material);
            $stmt->bindParam(':pres', $id_presentacion);
            $stmt->execute();
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($info) {
                $cantidad_unidades = $cantidad_cajas * $info['factor_conversion'];
                
                $item = [
                    'id_material' => $id_material,
                    'id_presentacion' => $id_presentacion,
                    'nombre' => $info['nombre'],
                    'codigo' => $info['codigo'],
                    'presentacion' => $info['presentacion'],
                    'lote' => $lote,
                    'fecha_vencimiento' => $fecha_vencimiento,
                    'cantidad_cajas' => $cantidad_cajas,
                    'cantidad_unidades' => $cantidad_unidades,
                    'precio_unitario' => $precio_unitario
                ];
                
                $items_temp[$tipo][] = $item;
                $_SESSION['ingreso_temp'] = $items_temp;
                
                header("Location: ingreso.php");
                exit();
            }
        }
    } elseif($accion == 'quitar_item') {
        $tipo = $_POST['tipo'];
        $indice = (int)$_POST['indice'];
        
        if(isset($items_temp[$tipo][$indice])) {
            array_splice($items_temp[$tipo], $indice, 1);
            $_SESSION['ingreso_temp'] = $items_temp;
        }
        
        header("Location: ingreso.php");
        exit();
    } elseif($accion == 'registrar_ingreso') {
        $id_proveedor = (int)$_POST['id_proveedor'];
        $remito_factura = trim($_POST['remito_factura']);
        $observaciones = trim($_POST['observaciones']);
        
        if($id_proveedor > 0 && (count($items_temp['medicamentos']) > 0 || count($items_temp['insumos']) > 0)) {
            try {
                $db->beginTransaction();
                
                $query_last = "SELECT codigo_ingreso FROM ingreso_stock ORDER BY id_ingreso DESC LIMIT 1";
                $stmt_last = $db->query($query_last);
                $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
                
                if($last) {
                    $num = (int)substr($last['codigo_ingreso'], 4) + 1;
                    $codigo = 'ING-' . str_pad($num, 5, '0', STR_PAD_LEFT);
                } else {
                    $codigo = 'ING-00001';
                }
                
                $total_items = count($items_temp['medicamentos']) + count($items_temp['insumos']);
                
                $query = "INSERT INTO ingreso_stock (codigo_ingreso, fecha_ingreso, id_proveedor, remito_factura, observaciones, total_items, usuario_registro)
                          VALUES (:codigo, CURDATE(), :proveedor, :remito, :obs, :total, :usuario)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':codigo', $codigo);
                $stmt->bindParam(':proveedor', $id_proveedor);
                $stmt->bindParam(':remito', $remito_factura);
                $stmt->bindParam(':obs', $observaciones);
                $stmt->bindParam(':total', $total_items);
                $usuario = $_SESSION['username'];
                $stmt->bindParam(':usuario', $usuario);
                $stmt->execute();
                
                $id_ingreso = $db->lastInsertId();
                
                $todos_items = array_merge($items_temp['medicamentos'], $items_temp['insumos']);
                
                foreach($todos_items as $item) {
                    $query_lote = "INSERT INTO lotes_material (
                        id_material, id_presentacion_material, lote, fecha_vencimiento, 
                        cantidad_inicial, cantidad_actual, cantidad_cajas, cantidad_unidades_totales,
                        precio_unitario, id_proveedor, fecha_ingreso, activo
                    ) VALUES (
                        :material, :presentacion, :lote, :venc,
                        :cant, :cant, :cajas, :unidades,
                        :precio, :proveedor, CURDATE(), 1
                    )";
                    
                    $stmt_lote = $db->prepare($query_lote);
                    $stmt_lote->bindParam(':material', $item['id_material']);
                    $stmt_lote->bindParam(':presentacion', $item['id_presentacion']);
                    $stmt_lote->bindParam(':lote', $item['lote']);
                    $stmt_lote->bindParam(':venc', $item['fecha_vencimiento']);
                    $stmt_lote->bindParam(':cant', $item['cantidad_unidades']);
                    $stmt_lote->bindParam(':cajas', $item['cantidad_cajas']);
                    $stmt_lote->bindParam(':unidades', $item['cantidad_unidades']);
                    $stmt_lote->bindParam(':precio', $item['precio_unitario']);
                    $stmt_lote->bindParam(':proveedor', $id_proveedor);
                    $stmt_lote->execute();
                    
                    $id_lote = $db->lastInsertId();
                    
                    // Obtener stock actual
                    $query_stock = "SELECT stock_actual FROM material WHERE id_material = :id";
                    $stmt_stock = $db->prepare($query_stock);
                    $stmt_stock->bindParam(':id', $item['id_material']);
                    $stmt_stock->execute();
                    $stock_actual = $stmt_stock->fetchColumn();
                    
                    $stock_anterior = $stock_actual;
                    $stock_posterior = $stock_actual + $item['cantidad_unidades'];
                    
                    $query_det = "INSERT INTO detalle_ingreso_stock (
                        id_ingreso, id_material, id_presentacion_material, lote, fecha_vencimiento,
                        cantidad_cajas, cantidad_unidades, precio_unitario, id_lote_generado
                    ) VALUES (
                        :ingreso, :material, :presentacion, :lote, :venc,
                        :cajas, :unidades, :precio, :id_lote
                    )";
                    
                    $stmt_det = $db->prepare($query_det);
                    $stmt_det->bindParam(':ingreso', $id_ingreso);
                    $stmt_det->bindParam(':material', $item['id_material']);
                    $stmt_det->bindParam(':presentacion', $item['id_presentacion']);
                    $stmt_det->bindParam(':lote', $item['lote']);
                    $stmt_det->bindParam(':venc', $item['fecha_vencimiento']);
                    $stmt_det->bindParam(':cajas', $item['cantidad_cajas']);
                    $stmt_det->bindParam(':unidades', $item['cantidad_unidades']);
                    $stmt_det->bindParam(':precio', $item['precio_unitario']);
                    $stmt_det->bindParam(':id_lote', $id_lote);
                    $stmt_det->execute();
                    
                    $query_mov = "INSERT INTO movimiento_stock (
                        id_material, tipo_movimiento, cantidad, stock_anterior, stock_posterior,
                        fecha, hora, lote, id_lote, motivo, id_personal
                    ) VALUES (
                        :material, 'ingreso', :cant, :stock_ant, :stock_post,
                        CURDATE(), CURTIME(), :lote, :id_lote, :motivo, :usuario
                    )";
                    
                    $stmt_mov = $db->prepare($query_mov);
                    $motivo = "Ingreso $codigo - Remito: $remito_factura";
                    $id_personal = $_SESSION['username'];
                    $stmt_mov->bindParam(':material', $item['id_material']);
                    $stmt_mov->bindParam(':cant', $item['cantidad_unidades']);
                    $stmt_mov->bindParam(':stock_ant', $stock_anterior);
                    $stmt_mov->bindParam(':stock_post', $stock_posterior);
                    $stmt_mov->bindParam(':lote', $item['lote']);
                    $stmt_mov->bindParam(':id_lote', $id_lote);
                    $stmt_mov->bindParam(':motivo', $motivo);
                    $stmt_mov->bindParam(':usuario', $id_personal);
                    $stmt_mov->execute();
                }
                
                $db->commit();
                
                unset($_SESSION['ingreso_temp']);
                
                header("Location: ../index.php?msg=Ingreso registrado - Código: $codigo - Total items: $total_items");
                exit();
                
            } catch(Exception $e) {
                $db->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Debe seleccionar proveedor y agregar al menos un item";
        }
    } elseif($accion == 'cancelar') {
        unset($_SESSION['ingreso_temp']);
        header("Location: ../index.php");
        exit();
    }
}

$medicamentos = $db->query("SELECT * FROM material WHERE tipo = 'medicamento' AND activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$insumos = $db->query("SELECT * FROM material WHERE tipo = 'insumo' AND activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$proveedores = $db->query("SELECT * FROM proveedor WHERE activo = 1 ORDER BY razon_social")->fetchAll(PDO::FETCH_ASSOC);

$presentaciones_med = [];
if($med_seleccionado > 0) {
    $query_pres = "SELECT * FROM presentacion_material WHERE id_material = :id AND activo = 1 ORDER BY descripcion";
    $stmt_pres = $db->prepare($query_pres);
    $stmt_pres->bindParam(':id', $med_seleccionado);
    $stmt_pres->execute();
    $presentaciones_med = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);
}

$presentaciones_ins = [];
if($ins_seleccionado > 0) {
    $query_pres = "SELECT * FROM presentacion_material WHERE id_material = :id AND activo = 1 ORDER BY descripcion";
    $stmt_pres = $db->prepare($query_pres);
    $stmt_pres->bindParam(':id', $ins_seleccionado);
    $stmt_pres->execute();
    $presentaciones_ins = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);
}
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
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_movimiento.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📦 Ingreso de Stock</h1>
            <p>Recepción de mercadería</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- MEDICAMENTOS -->
        <div class="form-container">
            <h3 class="section-title">💊 AGREGAR MEDICAMENTOS</h3>
            
            <form method="GET" action="">
                <div class="form-group">
                    <label>Seleccione Medicamento</label>
                    <select name="med" onchange="this.form.submit()">
                        <option value="0">Seleccione...</option>
                        <?php foreach($medicamentos as $med): ?>
                            <option value="<?php echo $med['id_material']; ?>" <?php echo $med_seleccionado == $med['id_material'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($med['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <?php if($med_seleccionado > 0 && count($presentaciones_med) > 0): ?>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_item">
                    <input type="hidden" name="tipo" value="medicamentos">
                    <input type="hidden" name="id_material" value="<?php echo $med_seleccionado; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Presentación <span class="required">*</span></label>
                            <select name="id_presentacion" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($presentaciones_med as $pres): ?>
                                    <option value="<?php echo $pres['id_presentacion_material']; ?>">
                                        <?php echo htmlspecialchars($pres['descripcion']); ?> (<?php echo $pres['factor_conversion']; ?> un)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Lote <span class="required">*</span></label>
                            <input type="text" name="lote" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Vencimiento</label>
                            <input type="date" name="fecha_vencimiento">
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad (cajas) <span class="required">*</span></label>
                            <input type="number" name="cantidad_cajas" required min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Precio Unitario</label>
                        <input type="number" name="precio_unitario" step="0.01" min="0" value="0">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">➕ Agregar Medicamento</button>
                </form>
            <?php elseif($med_seleccionado > 0): ?>
                <div class="info-box">
                    Este medicamento no tiene presentaciones registradas. 
                    <a href="../alta/presentaciones.php?id=<?php echo $med_seleccionado; ?>">Agregar presentación</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- MEDICAMENTOS AGREGADOS -->
        <?php if(count($items_temp['medicamentos']) > 0): ?>
            <div class="items-container">
                <h4>💊 Medicamentos Agregados: <?php echo count($items_temp['medicamentos']); ?></h4>
                <?php foreach($items_temp['medicamentos'] as $i => $item): ?>
                    <div class="item-row item-medicamento">
                        <div>
                            <strong><?php echo $item['nombre']; ?></strong> - <?php echo $item['presentacion']; ?><br>
                            <small>Lote: <?php echo $item['lote']; ?> | Venc: <?php echo $item['fecha_vencimiento'] ?: 'N/A'; ?> | 
                            <?php echo $item['cantidad_cajas']; ?> cajas = <?php echo $item['cantidad_unidades']; ?> unidades</small>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="accion" value="quitar_item">
                            <input type="hidden" name="tipo" value="medicamentos">
                            <input type="hidden" name="indice" value="<?php echo $i; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">❌</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- INSUMOS -->
        <div class="form-container">
            <h3 class="section-title">🏥 AGREGAR INSUMOS</h3>
            
            <form method="GET" action="">
                <?php if($med_seleccionado > 0): ?>
                    <input type="hidden" name="med" value="<?php echo $med_seleccionado; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Seleccione Insumo</label>
                    <select name="ins" onchange="this.form.submit()">
                        <option value="0">Seleccione...</option>
                        <?php foreach($insumos as $ins): ?>
                            <option value="<?php echo $ins['id_material']; ?>" <?php echo $ins_seleccionado == $ins['id_material'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ins['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <?php if($ins_seleccionado > 0 && count($presentaciones_ins) > 0): ?>
                <form method="POST">
                    <input type="hidden" name="accion" value="agregar_item">
                    <input type="hidden" name="tipo" value="insumos">
                    <input type="hidden" name="id_material" value="<?php echo $ins_seleccionado; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Presentación <span class="required">*</span></label>
                            <select name="id_presentacion" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($presentaciones_ins as $pres): ?>
                                    <option value="<?php echo $pres['id_presentacion_material']; ?>">
                                        <?php echo htmlspecialchars($pres['descripcion']); ?> (<?php echo $pres['factor_conversion']; ?> un)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Lote <span class="required">*</span></label>
                            <input type="text" name="lote" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Vencimiento (opcional)</label>
                            <input type="date" name="fecha_vencimiento">
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad (cajas) <span class="required">*</span></label>
                            <input type="number" name="cantidad_cajas" required min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Precio Unitario</label>
                        <input type="number" name="precio_unitario" step="0.01" min="0" value="0">
                    </div>
                    
                    <button type="submit" class="btn btn-success">➕ Agregar Insumo</button>
                </form>
            <?php elseif($ins_seleccionado > 0): ?>
                <div class="info-box">
                    Este insumo no tiene presentaciones registradas. 
                    <a href="../alta/presentaciones.php?id=<?php echo $ins_seleccionado; ?>">Agregar presentación</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- INSUMOS AGREGADOS -->
        <?php if(count($items_temp['insumos']) > 0): ?>
            <div class="items-container">
                <h4>🏥 Insumos Agregados: <?php echo count($items_temp['insumos']); ?></h4>
                <?php foreach($items_temp['insumos'] as $i => $item): ?>
                    <div class="item-row item-insumo">
                        <div>
                            <strong><?php echo $item['nombre']; ?></strong> - <?php echo $item['presentacion']; ?><br>
                            <small>Lote: <?php echo $item['lote']; ?> | Venc: <?php echo $item['fecha_vencimiento'] ?: 'N/A'; ?> | 
                            <?php echo $item['cantidad_cajas']; ?> cajas = <?php echo $item['cantidad_unidades']; ?> unidades</small>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="accion" value="quitar_item">
                            <input type="hidden" name="tipo" value="insumos">
                            <input type="hidden" name="indice" value="<?php echo $i; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">❌</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- FINALIZAR -->
        <?php if(count($items_temp['medicamentos']) > 0 || count($items_temp['insumos']) > 0): ?>
            <div class="form-container">
                <h3 class="section-title">✅ FINALIZAR INGRESO</h3>
                
                <form method="POST">
                    <input type="hidden" name="accion" value="registrar_ingreso">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Proveedor <span class="required">*</span></label>
                            <select name="id_proveedor" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id_proveedor']; ?>">
                                        <?php echo htmlspecialchars($prov['razon_social']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Remito/Factura</label>
                            <input type="text" name="remito_factura">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" rows="2"></textarea>
                    </div>
                    
                    <div class="info-box">
                        <strong>Total items:</strong> <?php echo count($items_temp['medicamentos']) + count($items_temp['insumos']); ?><br>
                        <strong>Medicamentos:</strong> <?php echo count($items_temp['medicamentos']); ?><br>
                        <strong>Insumos:</strong> <?php echo count($items_temp['insumos']); ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="accion" value="cancelar" class="btn" style="background: #6c757d; color: white;">Cancelar Todo</button>
                        <button type="submit" class="btn btn-primary">📦 Registrar Ingreso</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>