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

$query = "SELECT * FROM forma_farmaceutica ORDER BY activo DESC, descripcion";
$stmt = $db->query($query);
$formas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Formas Farmacéuticas</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/stock/stock.css">
</head>
<body>
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>💊 Formas Farmacéuticas</h1>
                    <p>Gestionar formas farmacéuticas del sistema</p>
                </div>
                <div>
                    <a href="agregar.php" class="btn btn-primary">➕ Nueva Forma</a>
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
                    <?php foreach($formas as $forma): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($forma['descripcion']); ?></strong></td>
                            <td>
                                <?php if($forma['activo']): ?>
                                    <span style="color: #10b981;">✓ Activo</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">✗ Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar.php?id=<?php echo $forma['id_forma_farmaceutica']; ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                <?php if($forma['activo']): ?>
                                    <a href="eliminar.php?id=<?php echo $forma['id_forma_farmaceutica']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Desactivar esta forma farmacéutica?')">🗑️ Desactivar</a>
                                <?php else: ?>
                                    <a href="activar.php?id=<?php echo $forma['id_forma_farmaceutica']; ?>" class="btn btn-sm btn-success">✓ Activar</a>
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