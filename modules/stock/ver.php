<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos completos del material
$query = "SELECT 
    m.*,
    ff.descripcion AS forma_farmaceutica,
    DATEDIFF(m.fecha_vencimiento, CURDATE()) AS dias_vencimiento,
    (m.stock_actual * m.precio_unitario) AS valor_total,
    CASE 
        WHEN m.stock_actual <= 0 THEN 'SIN_STOCK'
        WHEN m.stock_actual <= m.punto_pedido THEN 'PUNTO_PEDIDO'
        WHEN m.stock_actual <= m.stock_minimo THEN 'STOCK_BAJO'
        WHEN m.stock_actual >= m.stock_maximo THEN 'SOBRESTOCK'
        WHEN DATEDIFF(m.fecha_vencimiento, CURDATE()) <= 30 AND DATEDIFF(m.fecha_vencimiento, CURDATE()) >= 0 THEN 'PROXIMO_VENCER'
        WHEN m.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
        ELSE 'OK'
    END AS alerta
FROM material m
LEFT JOIN forma_farmaceutica ff ON m.id_forma_farmaceutica = ff.id_forma_farmaceutica
WHERE m.id_material = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$material) {
    header("Location: index.php?error=Material no encontrado");
    exit();
}

// Obtener presentaciones asignadas
$query_pres = "SELECT pm.*, p.descripcion
               FROM presentacion_material pm
               JOIN presentaciones p ON pm.id_presentacion = p.id_presentacion
               WHERE pm.id_material = :id AND pm.activo = 1 
               ORDER BY p.descripcion";
$stmt_pres = $db->prepare($query_pres);
$stmt_pres->bindParam(':id', $id);
$stmt_pres->execute();
$presentaciones = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los lotes con stock
$query_lotes = "SELECT 
    l.*,
    p.razon_social as proveedor,
    pres.descripcion as presentacion,
    DATEDIFF(l.fecha_vencimiento, CURDATE()) as dias_para_vencer,
    CASE
        WHEN l.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
        WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30 THEN 'PROXIMO_VENCER'
        ELSE 'VIGENTE'
    END as estado_lote
FROM lotes_material l
LEFT JOIN proveedor p ON l.id_proveedor = p.id_proveedor
LEFT JOIN presentacion_material pm ON l.id_presentacion_material = pm.id_presentacion_material
LEFT JOIN presentaciones pres ON pm.id_presentacion = pres.id_presentacion
WHERE l.id_material = :id AND l.cantidad_actual > 0
ORDER BY l.fecha_vencimiento ASC";

$stmt_lotes = $db->prepare($query_lotes);
$stmt_lotes->bindParam(':id', $id);
$stmt_lotes->execute();
$lotes = $stmt_lotes->fetchAll(PDO::FETCH_ASSOC);

// Obtener costos agrupados por proveedor y presentación
$query_costos = "SELECT 
    p.razon_social as proveedor,
    pres.descripcion as presentacion,
    AVG(di.precio_unitario) as precio_promedio,
    SUM(l.cantidad_actual) as stock_lotes,
    SUM(l.cantidad_actual * di.precio_unitario) as valor_total
FROM lotes_material l
LEFT JOIN proveedor p ON l.id_proveedor = p.id_proveedor
LEFT JOIN presentacion_material pm ON l.id_presentacion_material = pm.id_presentacion_material
LEFT JOIN presentaciones pres ON pm.id_presentacion = pres.id_presentacion
LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
WHERE l.id_material = :id AND l.cantidad_actual > 0
GROUP BY l.id_proveedor, pm.id_presentacion
ORDER BY p.razon_social, pres.descripcion";

$stmt_costos = $db->prepare($query_costos);
$stmt_costos->bindParam(':id', $id);
$stmt_costos->execute();
$costos = $stmt_costos->fetchAll(PDO::FETCH_ASSOC);

// Obtener últimos movimientos
$query_mov = "SELECT 
    m.*,
    CASE 
        WHEN m.tipo_movimiento = 'egreso' THEN 
            (SELECT a.descripcion FROM detalle_egreso_stock de
             JOIN egreso_stock e ON de.id_egreso = e.id_egreso
             JOIN area a ON e.id_area_destino = a.id_area
             WHERE de.id_material = :id_mat
             AND e.fecha_egreso = m.fecha
             LIMIT 1)
        ELSE NULL
    END as area
FROM vista_movimientos_stock m 
WHERE m.codigo = :codigo 
ORDER BY m.fecha DESC, m.hora DESC 
LIMIT 10";

$stmt_mov = $db->prepare($query_mov);
$stmt_mov->bindParam(':codigo', $material['codigo']);
$stmt_mov->bindParam(':id_mat', $id);
$stmt_mov->execute();
$movimientos = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Detalle Material</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_detail.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Detalle de <strong><?php echo htmlspecialchars($material['nombre']); ?></strong></h1>

                </div>
                <div style="display: flex; gap: 10px;">
                    <?php if(tienePermiso('stock.ajuste_individual')): ?>
                        <a href="movimientos/ajuste_individual.php?id=<?php echo $material['id_material']; ?>" class="btn" style="background: #10b981; color: white;">🔧 Ajuste</a>
                    <?php endif; ?>

                    <!--
                    <?php if(tienePermiso('stock.editar')): ?>
                        <a href="alta/editar.php?id=<?php echo $id; ?>" class="btn" style="background: #f59e0b; color: white;">✏️ Editar</a>
                    <?php endif; ?>
                    -->
                        <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver</a>
                </div>
            </div>
        </div>
        
        <!-- FILA 1 INICIO: Información General y Control de Stock -->
        <div class="detail-grid">
            <!--INICIO Información General -->
            <div class="detail-card">
                <h3>ℹ️ Información General</h3>
                <div class="detail-row">
                    <span class="detail-label">Tipo:</span>
                    <span class="detail-value"><?php echo ucfirst($material['tipo']); ?></span>
                </div>
                    <div class="detail-row">
                    <span class="detail-label">Código:</span>
                    <span class="detail-value"><strong><?php echo htmlspecialchars($material['codigo']); ?></strong></span>
                </div>
                <?php if($material['principio_activo']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Principio Activo:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($material['principio_activo']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if($material['concentracion']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Concentración:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($material['concentracion']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if($material['forma_farmaceutica']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Forma Farmacéutica:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($material['forma_farmaceutica']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if($material['descripcion']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Descripción:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($material['descripcion']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <!--FIN Información General -->

            <!-- Stock -->
            <div class="detail-card">
                <h3>📦 Control de Stock</h3>
                <div class="detail-row">
                    <span class="detail-label">Stock Actual:</span>
                    <span class="detail-value" style="font-size: 20px; color: #3b82f6;"><?php echo $material['stock_actual']; ?> unidades</span>
                </div>
                
                <?php 
                $porcentaje = $material['stock_maximo'] > 0 ? ($material['stock_actual'] / $material['stock_maximo']) * 100 : 0;
                ?>
                <div class="stock-visual">
                    <div class="stock-bar" style="width: <?php echo min($porcentaje, 100); ?>%"></div>
                </div>
                
                <div class="detail-row" style="padding-bottom: 0" !important;>
                    <span class="detail-label">Stock Mínimo:</span>
                    <span class="detail-value"><?php echo $material['stock_minimo']; ?></span>
                </div>
                <div class="detail-row" style="padding-bottom: 0" !important;>
                    <span class="detail-label">Stock Máximo:</span>
                    <span class="detail-value"><?php echo $material['stock_maximo']; ?></span>
                </div>
                <div class="detail-row" style="padding-bottom: 0" !important;>
                    <span class="detail-label">Punto de Pedido:</span>
                    <span class="detail-value"><?php echo $material['punto_pedido']; ?></span>
                </div>
                <div class="detail-row" style="padding-bottom: 0" !important;>
                    <span class="detail-label">Clasificación ABC:</span>
                    <span class="clasificacion-<?php echo $material['clasificacion_abc']; ?>" style="font-size: 18px;">
                        <?php echo $material['clasificacion_abc']; ?>
                    </span>
                </div>
            </div>
        </div>
        <!-- FIN FILA 1 -->

        <!-- FILA 2 INICIO: Presentaciones y Control Normativo -->
        <div class="detail-grid">
            <!-- Presentaciones -->
            <div class="detail-card">
                <h3>📦 Presentaciones Disponibles</h3>
                <?php if(count($presentaciones) > 0): ?>
                    <table style="width: 100%; font-size: 14px;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 8px; text-align: left;">Descripción</th>
                                <th style="padding: 8px; text-align: center;">Nombre comercial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($presentaciones as $pres): ?>
                                <tr>
                                    <td style="padding: 8px;"><strong><?php echo htmlspecialchars($pres['descripcion']); ?></strong></td>
                                    <td style="padding: 8px; text-align: center;">
                                        <span>
                                            <?php if($material['nombre_comercial']): ?>
                                                <?php echo htmlspecialchars($material['nombre_comercial']); ?>
                                                <?php else: ?>
                                                    Genérico
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #999;">No hay presentaciones registradas.</p>
                <?php endif; ?>
                <div style="margin-top: 10px;">
                    <a href="alta/presentaciones.php?id=<?php echo $id; ?>" class="btn btn-sm btn-primary">+ Gestionar</a>
                </div>
            </div>
            
            <!-- Control Normativo -->
            <div class="detail-card">
                <h3>⚕️ Control Normativo</h3>
                    <div class="detail-row">
                        <?php if($material['requiere_receta']): ?>
                            ✅  Requiere receta médica
                        <?php else: ?>
                            ❌  No requiere receta
                        <?php endif; ?>
                    </div>
                    <div class="detail-row">
                        <?php if($material['psicofarmacos']): ?>
                            ✅  Psicofármaco
                        <?php else: ?>
                            ❌  No es psicofármaco
                        <?php endif; ?>
                    </div>
                    <div class="detail-row">
                        <?php if($material['controlado']): ?>
                            ✅  Medicamento controlado
                        <?php else: ?>
                            ❌  No es controlado
                        <?php endif; ?>
                    </div>
            </div>
        </div>
        <!-- FILA 2 - FIN -->
    
        <!-- FILA 3: Lotes y Vencimientos -->
        <div class="detail-card" style="margin-bottom: 20px;">
            <h3>📅 Lotes y Vencimientos</h3>
            <?php if(count($lotes) > 0): ?>
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Cantidad</th>
                            <th>Vencimiento</th>
                            <th>Días</th>
                            <th>Estado</th>
                            <th>Proveedor</th>
                            <th>Presentación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lotes as $lote): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lote['lote']); ?></strong></td>
                                <td><?php echo $lote['cantidad_actual']; ?></td>
                                <td><?php echo $lote['fecha_vencimiento'] ? date('d/m/Y', strtotime($lote['fecha_vencimiento'])) : '-'; ?></td>
                                <td>
                                    <?php if($lote['dias_para_vencer'] !== null): ?>
                                        <?php if($lote['dias_para_vencer'] < 0): ?>
                                            <span style="color: #ef4444; font-weight: bold;">Vencido hace <?php echo abs($lote['dias_para_vencer']); ?> días</span>
                                        <?php elseif($lote['dias_para_vencer'] <= 30): ?>
                                            <span style="color: #f59e0b; font-weight: bold;"><?php echo $lote['dias_para_vencer']; ?> días</span>
                                        <?php else: ?>
                                            <?php echo $lote['dias_para_vencer']; ?> días
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $estado_colors = [
                                        'VENCIDO' => '#ef4444',
                                        'PROXIMO_VENCER' => '#f59e0b',
                                        'VIGENTE' => '#10b981'
                                    ];
                                    $estado_labels = [
                                        'VENCIDO' => '✗ Vencido',
                                        'PROXIMO_VENCER' => '⚠️ Próx. Vencer',
                                        'VIGENTE' => '✓ Vigente'
                                    ];
                                    ?>
                                    <span style="color: <?php echo $estado_colors[$lote['estado_lote']]; ?>; font-weight: bold;">
                                        <?php echo $estado_labels[$lote['estado_lote']]; ?>
                                    </span>
                                </td>
                                <td><?php echo $lote['proveedor'] ? htmlspecialchars($lote['proveedor']) : '-'; ?></td>
                                <td><?php echo $lote['presentacion'] ? htmlspecialchars($lote['presentacion']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No hay lotes con stock disponible.</p>
            <?php endif; ?>
        </div>
        <!-- FILA 3 FIN -->
        
        <!-- FILA 4 INICIO: Costos por Proveedor, con Presentación -->
        <div class="detail-card" style="margin-bottom: 20px;">
            <h3>💰 Costos por Proveedor y Presentación</h3>
            <?php if(count($costos) > 0): ?>
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Presentación</th>
                            <th>Precio Unitario</th>
                            <th>Stock</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($costos as $costo): ?>
                            <tr>
                                <td><?php echo $costo['proveedor'] ? htmlspecialchars($costo['proveedor']) : '-'; ?></td>
                                <td><?php echo $costo['presentacion'] ? htmlspecialchars($costo['presentacion']) : '-'; ?></td>
                                <td><strong>$<?php echo number_format($costo['precio_promedio'], 2); ?></strong></td>
                                <td><?php echo $costo['stock_lotes']; ?></td>
                                <td><strong>$<?php echo number_format($costo['valor_total'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="4" style="text-align: right; padding: 10px;">TOTAL:</td>
                            <td style="padding: 10px;">$<?php echo number_format(array_sum(array_column($costos, 'valor_total')), 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No hay información de costos disponible.</p>
            <?php endif; ?>
        </div>
        <!-- FIN FILA 4 -->
        
        <!-- FILA 5 INICIO: Últimos movimientos -->
        <div class="detail-card">
            <h3>📊 Últimos Movimientos</h3>
            
            <?php if(count($movimientos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Stock</th>
                            <th>Lote</th>
                            <th>Área</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($movimientos as $mov): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha'] . ' ' . $mov['hora'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $mov['tipo_movimiento']; ?>">
                                        <?php echo str_replace('_', ' ', $mov['tipo_movimiento']); ?>
                                    </span>
                                </td>
                                <td><?php echo $mov['cantidad']; ?></td>
                                <td><?php echo $mov['stock_anterior']; ?> → <?php echo $mov['stock_posterior']; ?></td>
                                <td><?php echo htmlspecialchars($mov['lote']) ?: '-'; ?></td>
                                <td><?php echo htmlspecialchars($mov['area']) ?: '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="movimientos.php?id=<?php echo $material['id_material']; ?>" class="btn btn-info">Ver Historial Completo</a>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px;">No hay movimientos registrados</p>
            <?php endif; ?>
        </div>

        <!-- Acciones -->
        <div class="actions-bar">
            <a href="movimientos/ajuste_individual.php?id=<?php echo $material['id_material']; ?>" class="btn btn-success">📝 Registrar Movimiento</a>
            <a href="alta/editar.php?id=<?php echo $material['id_material']; ?>" class="btn btn-warning">✏️ Editar Material</a>
            <a href="alta/eliminar.php?id=<?php echo $material['id_material']; ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro de eliminar este material?')">🗑️ Eliminar</a>
            <div style="display: flex; gap: 10px;">
                <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver</a>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>