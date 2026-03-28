<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Filtros
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$profesion = isset($_GET['profesion']) ? (int)$_GET['profesion'] : 0;

// Paginación
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

// Construir WHERE
$where = ["ps.activo = 1"];
$params = [];

if($busqueda) {
    $where[] = "(ps.dni_profesional_solicitante LIKE :busqueda OR ps.nombre LIKE :busqueda OR ps.apellido LIKE :busqueda OR ps.matricula LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

if($profesion > 0) {
    $where[] = "ps.id_profesion = :profesion";
    $params[':profesion'] = $profesion;
}

$where_clause = implode(" AND ", $where);

// Contar total
$query_count = "SELECT COUNT(*) as total 
                FROM profesional_solicitante ps 
                WHERE $where_clause";
$stmt_count = $db->prepare($query_count);
foreach($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener profesionales
$query = "SELECT 
    ps.*,
    p.descripcion AS profesion_nombre
FROM profesional_solicitante ps
JOIN profesion p ON ps.id_profesion = p.id_profesion
WHERE $where_clause
ORDER BY ps.apellido ASC, ps.nombre ASC
LIMIT :offset, :limit";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener profesiones para el filtro
$query_prof = "SELECT * FROM profesion ORDER BY descripcion";
$profesiones = $db->query($query_prof)->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas rápidas
$query_stats = "SELECT COUNT(*) as total FROM profesional_solicitante WHERE activo = 1";
$total_activos = $db->query($query_stats)->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Profesionales Solicitantes</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/profesionales/profesionales.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>👨‍⚕️ Profesionales Solicitantes</h1>
                    <p>Gestión de profesionales autorizados para solicitar materiales</p>
                </div>
                <div>
                    <a href="agregar.php" class="btn btn-primary">➕ Agregar Profesional</a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $total_activos; ?></h3>
                    <p>Profesionales Activos</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <h3><?php echo count($profesiones); ?></h3>
                    <p>Profesiones Registradas</p>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="GET" action="" class="filters-bar">
            <div class="filter-group">
                <label>🔍 Buscar</label>
                <input type="text" name="busqueda" placeholder="DNI, nombre, apellido o matrícula..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            
            <div class="filter-group" style="max-width: 250px;">
                <label>Profesión</label>
                <select name="profesion">
                    <option value="">Todas las profesiones</option>
                    <?php foreach($profesiones as $prof): ?>
                        <option value="<?php echo $prof['id_profesion']; ?>" <?php echo $profesion == $prof['id_profesion'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prof['descripcion']); ?>
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
        
        <!-- Tabla de profesionales -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Apellido y Nombre</th>
                        <th>Profesión</th>
                        <th>Matrícula</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($profesionales) > 0): ?>
                        <?php foreach($profesionales as $prof): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($prof['dni_profesional_solicitante']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prof['apellido'] . ', ' . $prof['nombre']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($prof['profesion_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prof['matricula']) ?: '-'; ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="ver.php?dni=<?php echo urlencode($prof['dni_profesional_solicitante']); ?>" class="btn btn-sm btn-info" title="Ver detalle">👁️</a>
                                        <a href="editar.php?dni=<?php echo urlencode($prof['dni_profesional_solicitante']); ?>" class="btn btn-sm btn-warning" title="Editar">✏️</a>
                                        <a href="eliminar.php?dni=<?php echo urlencode($prof['dni_profesional_solicitante']); ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Seguro que desea eliminar este profesional?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                                No se encontraron profesionales con los filtros aplicados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if($total_paginas > 1): ?>
            <div class="pagination">
                <?php if($pagina > 1): ?>
                    <a href="?pagina=<?php echo $pagina-1; ?>&busqueda=<?php echo urlencode($busqueda); ?>&profesion=<?php echo $profesion; ?>">« Anterior</a>
                <?php endif; ?>
                
                <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                    <?php if($i == $pagina): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?php echo $i; ?>&busqueda=<?php echo urlencode($busqueda); ?>&profesion=<?php echo $profesion; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($pagina < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina+1; ?>&busqueda=<?php echo urlencode($busqueda); ?>&profesion=<?php echo $profesion; ?>">Siguiente »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
