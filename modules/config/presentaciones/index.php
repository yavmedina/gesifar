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
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
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
                                    'medicamento' => '#3b82f6',  // azul
                                    //'medicamento' => '#ef4444',
                                    'insumo' => '#10b981',       // verde
                                    'ambos' => '#6b7280'         // gris
                                    //'ambos' => '#3b82f6'
                                ];
                                
                                ?>
                                <span style="color: <?php echo $tipo_color[$pres['tipo']]; ?>; font-weight: 500;">
                                    <?php echo ucfirst($pres['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($pres['activo']): ?>
                                    <span style="color: #10b981;">✓ Activo</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">✗ Inactivo</span>
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
        
        <div style="margin-top: 20px;">
            <a href="../index.php" class="btn" style="background: #6c757d; color: white;">← Volver a Configuración</a>
        </div>
    </div>
    
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>