<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';

if(empty($dni)) {
    header("Location: index.php");
    exit();
}

// Obtener datos del profesional
$query = "SELECT ps.*, p.descripcion AS profesion_nombre
          FROM profesional_solicitante ps
          JOIN profesion p ON ps.id_profesion = p.id_profesion
          WHERE ps.dni_profesional_solicitante = :dni";
$stmt = $db->prepare($query);
$stmt->bindParam(':dni', $dni);
$stmt->execute();
$profesional = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$profesional) {
    header("Location: index.php?error=Profesional no encontrado");
    exit();
}

// Obtener últimas solicitudes (si las hay)
$query_sol = "SELECT 
    s.id_solicitud,
    s.numero_solicitud,
    s.fecha,
    s.tipo_solicitud,
    a.descripcion AS area_nombre,
    e.descripcion AS estado_nombre,
    (SELECT COUNT(*) FROM detalle_solicitud WHERE id_solicitud = s.id_solicitud) as items
FROM solicitud s
JOIN area a ON s.id_area = a.id_area
JOIN estado e ON s.id_estado = e.id_estado
WHERE s.id_profesional_solicitante = :dni
ORDER BY s.fecha DESC, s.hora DESC
LIMIT 10";

$stmt_sol = $db->prepare($query_sol);
$stmt_sol->bindParam(':dni', $dni);
$stmt_sol->execute();
$solicitudes = $stmt_sol->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$query_stats = "SELECT 
    COUNT(*) as total_solicitudes,
    COUNT(CASE WHEN id_estado = 3 THEN 1 END) as entregadas,
    COUNT(CASE WHEN id_estado = 1 THEN 1 END) as pendientes
FROM solicitud 
WHERE id_profesional_solicitante = :dni";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->bindParam(':dni', $dni);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Detalle Profesional</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/profesionales/profesionales.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>👨‍⚕️ Detalle del Profesional</h1>
                    <p>Información completa y historial</p>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background: #6c757d; color: white;">← Volver</a>
                </div>
            </div>
        </div>
        
        <!-- Datos del profesional -->
        <div class="detail-card">
            <h2><?php echo htmlspecialchars($profesional['apellido'] . ', ' . $profesional['nombre']); ?></h2>
            
            <div class="detail-grid">
                <div class="detail-row">
                    <span class="detail-label">DNI:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($profesional['dni_profesional_solicitante']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Profesión:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($profesional['profesion_nombre']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Matrícula:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($profesional['matricula']) ?: 'No registrada'; ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value">
                        <?php if($profesional['activo']): ?>
                            <span style="color: #10b981; font-weight: bold;">✓ Activo</span>
                        <?php else: ?>
                            <span style="color: #ef4444; font-weight: bold;">✗ Inactivo</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_solicitudes']; ?></h3>
                    <p>Solicitudes Totales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $stats['entregadas']; ?></h3>
                    <p>Entregadas</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo $stats['pendientes']; ?></h3>
                    <p>Pendientes</p>
                </div>
            </div>
        </div>
        
        <!-- Historial de solicitudes -->
        <div class="detail-card">
            <h3>📊 Últimas Solicitudes</h3>
            
            <?php if(count($solicitudes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nº Solicitud</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Área</th>
                            <th>Items</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($solicitudes as $sol): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sol['numero_solicitud']); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($sol['fecha'])); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $sol['tipo_solicitud'])); ?></td>
                                <td><?php echo htmlspecialchars($sol['area_nombre']); ?></td>
                                <td><?php echo $sol['items']; ?></td>
                                <td>
                                    <span class="badge-<?php echo strtolower($sol['estado_nombre']); ?>">
                                        <?php echo htmlspecialchars($sol['estado_nombre']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">
                    Este profesional no tiene solicitudes registradas aún
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Acciones -->
        <div class="actions-bar">
            <a href="editar.php?dni=<?php echo urlencode($profesional['dni_profesional_solicitante']); ?>" class="btn btn-warning">✏️ Editar Profesional</a>
            <a href="eliminar.php?dni=<?php echo urlencode($profesional['dni_profesional_solicitante']); ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro?')">🗑️ Eliminar</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
