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

$query = "SELECT * FROM proveedor ORDER BY activo DESC, razon_social";
$stmt = $db->query($query);
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Proveedores</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>🏢 Proveedores</h1>
                    <p>Gestionar proveedores del sistema</p>
                </div>
                <div>
                    <a href="agregar.php" class="btn btn-primary">➕ Nuevo Proveedor</a>
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
                        <th>Razón Social</th>
                        <th>CUIT</th>
                        <th>Dirección</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($proveedores as $prov): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($prov['razon_social']); ?></strong></td>
                            <td><?php echo htmlspecialchars($prov['cuit']); ?></td>
                            <td>
                                <?php if($prov['direccion']): ?>
                                    <?php echo htmlspecialchars($prov['direccion']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($prov['telefono']): ?>
                                    📞 <?php echo htmlspecialchars($prov['telefono']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($prov['email']): ?>
                                    <?php echo htmlspecialchars($prov['email']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($prov['activo']): ?>
                                    <span style="color: #10b981;">✓ Activo</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">✗ Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar.php?id=<?php echo $prov['id_proveedor']; ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                <?php if($prov['activo']): ?>
                                    <a href="eliminar.php?id=<?php echo $prov['id_proveedor']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar este proveedor?')">🗑️ Desactivar</a>
                                <?php else: ?>
                                    <a href="activar.php?id=<?php echo $prov['id_proveedor']; ?>" class="btn btn-sm btn-success">✓ Activar</a>
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