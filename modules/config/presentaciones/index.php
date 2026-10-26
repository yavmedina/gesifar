<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('admin');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';

$query = "SELECT * FROM presentaciones ORDER BY tipo, descripcion";
$stmt = $db->query($query);
$presentaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Presentaciones</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/components.css">
    <link rel="stylesheet" href="/gesifar/assets/css/utilities.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div class="flex-between">
                <div>
                    <h1>📦 Presentaciones</h1>
                    <p>Gestionar tipos de presentaciones</p>
                </div>
                <div>
                    <a href="agregar.php" class="btn btn-primary">➕ Nueva Presentación</a>
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
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($presentaciones as $pres): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pres['descripcion']); ?></strong></td>
                            <td>
                                <?php
                                $tipo_color = [
                                    'medicamento' => 'text-primary',  // azul
                                    //'medicamento' => '#ef4444',
                                    'insumo' => 'text-success',       // verde
                                    'ambos' => 'text-muted'         // gris
                                    //'ambos' => '#3b82f6'
                                ];
                                
                                ?>
                                <span class="<?php echo $tipo_color[$pres['tipo']]; ?> fw-500">
                                    <?php echo ucfirst($pres['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($pres['activo']): ?>
                                    <span class="text-success">✓ Activo</span>
                                <?php else: ?>
                                    <span class="text-danger">✗ Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar.php?id=<?php echo $pres['id_presentacion']; ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                <?php if($pres['activo']): ?>
                                    <a href="eliminar.php?id=<?php echo $pres['id_presentacion']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar esta presentación?')">🗑️ Desactivar</a>
                                <?php else: ?>
                                    <a href="activar.php?id=<?php echo $pres['id_presentacion']; ?>" class="btn btn-sm btn-success">✓ Activar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-20">
            <a href="../index.php" class="btn btn-secondary">← Volver a Configuración</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>