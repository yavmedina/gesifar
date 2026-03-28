<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener resumen ABC
$query_resumen = "SELECT 
    m.clasificacion_abc,
    COUNT(*) as cantidad_items,
    COALESCE(SUM((SELECT SUM(l.cantidad_actual * COALESCE(di.precio_unitario, 0))
         FROM lotes_material l
         LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
         WHERE l.id_material = m.id_material AND l.cantidad_actual > 0)), 0) as valor_total_stock
FROM material m
WHERE m.activo = 1
GROUP BY m.clasificacion_abc
ORDER BY m.clasificacion_abc";
$stmt_resumen = $db->query($query_resumen);
$resumen = $stmt_resumen->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales y porcentajes
$total_items = 0;
$total_valor = 0;
foreach($resumen as $r) {  // SIN &
    $total_items += $r['cantidad_items'];
    $total_valor += $r['valor_total_stock'];
}

// Calcular porcentajes
foreach($resumen as $key => $r) {  // Usar $key en vez de &
    $resumen[$key]['porcentaje_valor'] = $total_valor > 0 ? ($r['valor_total_stock'] / $total_valor) * 100 : 0;
}
unset($r); // Limpiar referencia

// Obtener materiales Clasificación A (más importantes)
$query_a = "SELECT 
    m.*,
    (SELECT SUM(l.cantidad_actual * COALESCE(di.precio_unitario, 0))
     FROM lotes_material l
     LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
     WHERE l.id_material = m.id_material AND l.cantidad_actual > 0) as valor_stock,
    (SELECT AVG(di.precio_unitario)
     FROM lotes_material l
     LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
     WHERE l.id_material = m.id_material AND l.cantidad_actual > 0) as precio_unitario
FROM material m
WHERE m.clasificacion_abc = 'A' AND m.activo = 1
ORDER BY valor_stock DESC";
$stmt_a = $db->query($query_a);
$materiales_a = $stmt_a->fetchAll(PDO::FETCH_ASSOC);

// Obtener materiales Clasificación B
$query_b = "SELECT 
    m.*,
    (SELECT SUM(l.cantidad_actual * COALESCE(di.precio_unitario, 0))
     FROM lotes_material l
     LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
     WHERE l.id_material = m.id_material AND l.cantidad_actual > 0) as valor_stock,
    (SELECT AVG(di.precio_unitario)
     FROM lotes_material l
     LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
     WHERE l.id_material = m.id_material AND l.cantidad_actual > 0) as precio_unitario
FROM material m
WHERE m.clasificacion_abc = 'B' AND m.activo = 1
ORDER BY valor_stock DESC
LIMIT 10";
$stmt_b = $db->query($query_b);
$materiales_b = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

// Obtener materiales Clasificación C (muestra limitada)
$query_c = "SELECT 
    m.*,
    (SELECT SUM(l.cantidad_actual * COALESCE(di.precio_unitario, 0))
     FROM lotes_material l
     LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
     WHERE l.id_material = m.id_material AND l.cantidad_actual > 0) as valor_stock,
    (SELECT AVG(di.precio_unitario)
     FROM lotes_material l
     LEFT JOIN detalle_ingreso_stock di ON l.id_lote = di.id_lote_generado
     WHERE l.id_material = m.id_material AND l.cantidad_actual > 0) as precio_unitario
FROM material m
WHERE m.clasificacion_abc = 'C' AND m.activo = 1
ORDER BY valor_stock DESC
LIMIT 10";
$stmt_c = $db->query($query_c);
$materiales_c = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Análisis ABC</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock_abc.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="abc-container">
            <div class="page-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>📊 Análisis ABC (Ley de Pareto)</h1>
                        <p>Clasificación de materiales según su valor en inventario</p>
                    </div>
                    <div>
                        <a href="index.php" class="btn" style="background: #6b7280; color: white;">← Volver</a>
                    </div>
                </div>
            </div>
            
            <!-- Explicación -->
            <div class="info-box">
                <h3>📚 ¿Qué es el Análisis ABC?</h3>
                <p>El análisis ABC (o Ley de Pareto) clasifica los materiales en tres categorías según su valor en el inventario:</p>
                <ul style="margin: 10px 0 0 20px;">
                    <li><strong style="color: #ef4444;">Clase A:</strong> 5-20% de los ítems representan el 60-80% del valor total (alta rotación/valor)</li>
                    <li><strong style="color: #f59e0b;">Clase B:</strong> 15-25% de los ítems representan el 15-25% del valor (rotación media)</li>
                    <li><strong style="color: #6b7280;">Clase C:</strong> 60-70% de los ítems representan el 5-10% del valor (baja rotación/valor)</li>
                </ul>
                <p style="margin-top: 10px;"><strong>Uso:</strong> Permite enfocar esfuerzos de control en los materiales más valiosos (Clase A).</p>
            </div>
            
            <!-- Cards de resumen -->
            <div class="abc-cards">
                <?php foreach($resumen as $r): ?>
                    <?php 
                    $porcentaje_items = $total_items > 0 ? ($r['cantidad_items'] / $total_items) * 100 : 0;
                    ?>
                    <div class="abc-card abc-card-<?php echo $r['clasificacion_abc']; ?>">
                        <h2><?php echo $r['clasificacion_abc']; ?></h2>
                        
                        <p><strong>Cantidad de ítems:</strong></p>
                        <div class="big-number"><?php echo $r['cantidad_items']; ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-<?php echo $r['clasificacion_abc']; ?>" 
                                 style="width: <?php echo $porcentaje_items; ?>%">
                                <?php echo number_format($porcentaje_items, 1); ?>%
                            </div>
                        </div>
                        
                        <p><strong>Valor en stock:</strong></p>
                        <div class="big-number">$<?php echo number_format($r['valor_total_stock'], 0); ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-<?php echo $r['clasificacion_abc']; ?>" 
                                 style="width: <?php echo $r['porcentaje_valor'] ?: 0; ?>%">
                                <?php echo number_format($r['porcentaje_valor'] ?: 0, 1); ?>%
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Totales -->
            <div class="chart-container">
                <h3 style="margin: 0 0 20px 0;">📈 Resumen General</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px;">
                    <div>
                        <p style="color: #6b7280; margin: 0;">Total de Ítems</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 5px 0; color: #1f2937;">
                            <?php echo number_format($total_items); ?>
                        </p>
                    </div>
                    <div>
                        <p style="color: #6b7280; margin: 0;">Valor Total en Stock</p>
                        <p style="font-size: 32px; font-weight: bold; margin: 5px 0; color: #10b981;">
                            $<?php echo number_format($total_valor, 2); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Materiales Clase A -->
            <div class="section-title" style="color: #ef4444;">
                🔴 Materiales Clase A (Alta Prioridad)
            </div>
            <?php if(count($materiales_a) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Stock</th>
                            <th>Precio Unit.</th>
                            <th>Valor Stock</th>
                            <th>% del Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($materiales_a as $mat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td>
                                    <a href="ver.php?id=<?php echo $mat['id_material']; ?>" style="text-decoration: none; color: #1f2937;">
                                        <strong><?php echo htmlspecialchars($mat['nombre']); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo $mat['stock_actual']; ?></td>
                                <td>$<?php echo number_format($mat['precio_unitario'] ?? 0, 2); ?></td>
                                <td><strong>$<?php echo number_format($mat['valor_stock'] ?? 0, 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $porcentaje_valor = $total_valor > 0 ? ($mat['valor_stock'] / $total_valor) * 100 : 0;
                                    echo number_format($porcentaje_valor, 2); 
                                    ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">No hay materiales en Clase A</p>
            <?php endif; ?>
            
            <!-- Materiales Clase B (top 10) -->
            <div class="section-title" style="color: #f59e0b;">
                🟡 Materiales Clase B (Prioridad Media) - Top 10
            </div>
            <?php if(count($materiales_b) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Stock</th>
                            <th>Precio Unit.</th>
                            <th>Valor Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($materiales_b as $mat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td>
                                    <a href="ver.php?id=<?php echo $mat['id_material']; ?>" style="text-decoration: none; color: #1f2937;">
                                        <strong><?php echo htmlspecialchars($mat['nombre']); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo $mat['stock_actual']; ?></td>
                                <td>$<?php echo number_format($mat['precio_unitario'] ?? 0, 2); ?></td>
                                <td><strong>$<?php echo number_format($mat['valor_stock'] ?? 0, 2); ?></strong></td>
                            <!--
                                <td>$<?php echo number_format($mat['precio_unitario'], 2); ?></td>
                                <td><strong>$<?php echo number_format($mat['valor_stock'], 2); ?></strong></td>
                            -->
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">No hay materiales en Clase B</p>
            <?php endif; ?>
            
            <!-- Materiales Clase C (top 10) -->
            <div class="section-title" style="color: #6b7280;">
                ⚪ Materiales Clase C (Baja Prioridad) - Top 10
            </div>
            <?php if(count($materiales_c) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Material</th>
                            <th>Stock</th>
                            <th>Precio Unit.</th>
                            <th>Valor Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($materiales_c as $mat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mat['codigo']); ?></strong></td>
                                <td>
                                    <a href="ver.php?id=<?php echo $mat['id_material']; ?>" style="text-decoration: none; color: #1f2937;">
                                        <strong><?php echo htmlspecialchars($mat['nombre']); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo $mat['stock_actual']; ?></td>
                                <td>$<?php echo number_format($mat['precio_unitario'] ?? 0, 2); ?></td>
                                <td><strong>$<?php echo number_format($mat['valor_stock'] ?? 0, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align: center; color: #6b7280; padding: 20px; font-size: 13px;">
                    Mostrando solo los 10 más valiosos de Clase C. <a href="index.php?clasificacion=C">Ver todos →</a>
                </p>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">No hay materiales en Clase C</p>
            <?php endif; ?>
            
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>