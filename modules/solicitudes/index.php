<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
verificarPermiso('solicitudes.ver');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';

// Filtros
$estado = isset($_GET['estado']) ? (int)$_GET['estado'] : 0;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// WHERE
$where = ["1=1"];
$params = [];

if($estado > 0) {
    $where[] = "s.id_estado = :estado";
    $params[':estado'] = $estado;
}

if($busqueda) {
    $where[] = "(s.codigo_solicitud LIKE :busqueda OR s.nombre_profesional LIKE :busqueda OR s.dni_profesional LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

$where_clause = implode(" AND ", $where);

// Obtener solicitudes
$query = "SELECT 
    s.*,
    e.descripcion as estado_desc,
    (SELECT COUNT(*) FROM detalle_solicitud WHERE id_solicitud = s.id_solicitud) as total_items,
    (SELECT COUNT(*) FROM detalle_solicitud WHERE id_solicitud = s.id_solicitud AND aprobado = 1) as items_aprobados
FROM solicitud s
JOIN estado_solicitud e ON s.id_estado = e.id_estado
WHERE $where_clause
ORDER BY 
    CASE 
        WHEN s.id_estado = 1 THEN 1
        ELSE 2
    END,
    s.fecha_solicitud DESC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$query_stats = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN id_estado = 1 THEN 1 END) as pendientes,
    COUNT(CASE WHEN id_estado IN (2,3) THEN 1 END) as aprobadas,
    COUNT(CASE WHEN id_estado IN (5,6) THEN 1 END) as entregadas
FROM solicitud";
$stats = $db->query($query_stats)->fetch(PDO::FETCH_ASSOC);

// Estados para filtro
$estados = $db->query("SELECT * FROM estado_solicitud ORDER BY id_estado")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Gestión de Solicitudes</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📋 Gestión de Solicitudes</h1>
                    <p>Revisar y aprobar solicitudes de materiales</p>
                </div>
            </div>
        </div>
        
        <?php if($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Solicitudes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo $stats['pendientes']; ?></h3>
                    <p>Pendientes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $stats['aprobadas']; ?></h3>
                    <p>Aprobadas</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <h3><?php echo $stats['entregadas']; ?></h3>
                    <p>Entregadas</p>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label>🔍 Buscar</label>
                <input type="text" name="busqueda" placeholder="Código, profesional, DNI..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            
            <div class="filter-group" style="max-width: 200px;">
                <label>Estado</label>
                <select name="estado">
                    <option value="0">Todos</option>
                    <?php foreach($estados as $est): ?>
                        <option value="<?php echo $est['id_estado']; ?>" <?php echo $estado == $est['id_estado'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($est['descripcion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group" style="max-width: 100px;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Filtrar</button>
            </div>
            
            <div class="filter-group" style="max-width: 100px;">
                <label>&nbsp;</label>
                <a href="index.php" class="btn" style="width: 100%; text-align: center; background: #6c757d; color: white;">Limpiar</a>
            </div>
        </form>
        
        <!-- Tabla -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Profesional</th>
                        <th>Servicio</th>
                        <th>Items</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($solicitudes) > 0): ?>
                        <?php foreach($solicitudes as $sol): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sol['codigo_solicitud']); ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($sol['nombre_profesional']); ?></strong>
                                    <br><small style="color: #666;">DNI: <?php echo htmlspecialchars($sol['dni_profesional']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($sol['servicio']); ?></td>
                                <td>
                                    <?php echo $sol['total_items']; ?> item(s)
                                    <?php if($sol['items_aprobados'] > 0): ?>
                                        <br><small style="color: #10b981;">✓ <?php echo $sol['items_aprobados']; ?> aprobados</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $color = '#666';
                                    if($sol['id_estado'] == 1) $color = '#f59e0b'; // Pendiente
                                    if($sol['id_estado'] == 2 || $sol['id_estado'] == 3) $color = '#10b981'; // Aprobada
                                    if($sol['id_estado'] == 4) $color = '#ef4444'; // Rechazada
                                    if($sol['id_estado'] == 5 || $sol['id_estado'] == 6) $color = '#3b82f6'; // Entregada
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                        <?php echo htmlspecialchars($sol['estado_desc']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ver.php?id=<?php echo $sol['id_solicitud']; ?>" class="btn btn-sm btn-info" title="Ver">👁️</a>
                                    
                                    <?php if($sol['id_estado'] == 1): ?>
                                        <a href="revisar.php?id=<?php echo $sol['id_solicitud']; ?>" class="btn btn-sm btn-warning" title="Revisar">📝</a>
                                    <?php endif; ?>
                                    
                                    <?php if($sol['id_estado'] == 2 || $sol['id_estado'] == 3): ?>
                                        <a href="entregar.php?id=<?php echo $sol['id_solicitud']; ?>" class="btn btn-sm btn-success" title="Entregar">📦</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                No se encontraron solicitudes
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>