<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';

// Solo admin y farmaceutico_jefe pueden gestionar usuarios
verificarPermiso('usuarios.gestionar');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener usuarios
$query = "SELECT * FROM usuarios ORDER BY activo DESC, nombre ASC";
$stmt = $db->query($query);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_usuarios = count($usuarios);
$usuarios_activos = count(array_filter($usuarios, function($u) { return $u['activo'] == 1; }));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Usuarios del Sistema</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/usuarios/usuarios.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>👥 Usuarios del Sistema</h1>
                    <p>Gestión de usuarios y roles</p>
                </div>
                <div>
                    <a href="agregar.php" class="btn btn-primary">➕ Agregar Usuario</a>
                </div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $total_usuarios; ?></h3>
                    <p>Total Usuarios</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $usuarios_activos; ?></h3>
                    <p>Usuarios Activos</p>
                </div>
            </div>
        </div>
        
        <!-- Tabla de usuarios -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $usuario): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($usuario['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']) ?: '-'; ?></td>
                            <td>
                                <span class="badge-rol badge-<?php echo $usuario['rol']; ?>">
                                    <?php echo nombreRol($usuario['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($usuario['activo']): ?>
                                    <span class="estado-activo">✓ Activo</span>
                                <?php else: ?>
                                    <span class="estado-inactivo">✗ Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($usuario['ultimo_acceso']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                                <?php else: ?>
                                    <small style="color: #999;">Nunca</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="editar.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-warning" title="Editar">✏️</a>
                                    <a href="cambiar_password.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-info" title="Cambiar contraseña">🔑</a>
                                    <?php if($usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                                        <a href="eliminar.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Seguro que desea eliminar este usuario?')">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
