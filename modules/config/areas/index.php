<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('config.ver');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';

$query = "SELECT * FROM area ORDER BY activo DESC, descripcion";
$stmt = $db->query($query);
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Áreas</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🏥 Áreas Hospitalarias</h1>
                    <p>Gestionar áreas de dispensación</p>
                </div>
                <div>
                    <a href="agregar.php" class="btn btn-primary">➕ Nueva Área</a>
                </div>
            </div>
        </div>
        
        <?php if($mensaje): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($areas as $area): ?>
                        <tr <?php echo !$area['activo'] ? 'style="background: #fee2e2; text-decoration: line-through;"' : ''; ?>>
                            <td><strong><?php echo htmlspecialchars($area['descripcion']); ?></strong></td>
                            <td>
                                <?php if($area['activo']): ?>
                                    <span style="color: #10b981;">✓ Activo</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">✗ Inactivo (En remodelación)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar.php?id=<?php echo $area['id_area']; ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                <?php if($area['activo']): ?>
                                    <a href="eliminar.php?id=<?php echo $area['id_area']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar esta área?')">🗑️ Desactivar</a>
                                <?php else: ?>
                                    <a href="activar.php?id=<?php echo $area['id_area']; ?>" class="btn btn-sm btn-success">✓ Activar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="../index.php" class="btn" style="background: #6c757d; color: white;">← Volver a Configuración</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>