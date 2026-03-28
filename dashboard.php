<?php
session_start();

// Verificar si está logueado
if(!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

// Obtener estadísticas básicas
$database = new Database();
$db = $database->getConnection();

// Contar solicitudes pendientes
$query = "SELECT COUNT(*) as total FROM solicitud WHERE id_estado = 1";
$stmt = $db->query($query);
$solicitudes_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contar materiales
$query = "SELECT COUNT(*) as total FROM material";
$stmt = $db->query($query);
$total_materiales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Contar materiales próximos a vencer (30 días)
$query = "SELECT COUNT(*) as total FROM material 
          WHERE fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND fecha_vencimiento >= CURDATE()";
$stmt = $db->query($query);
$materiales_por_vencer = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Bienvenido, <?php echo $_SESSION['nombre']; ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <h3><?php echo $solicitudes_pendientes; ?></h3>
                    <p>Solicitudes Pendientes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💊</div>
                <div class="stat-content">
                    <h3><?php echo $total_materiales; ?></h3>
                    <p>Materiales en Stock</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-content">
                    <h3><?php echo $materiales_por_vencer; ?></h3>
                    <p>Próximos a Vencer</p>
                </div>
            </div>
        </div>
        
        <div class="modules-grid">
            <a href="modules/stock/" class="module-card">
                <div class="module-icon">📦</div>
                <h3>Gestión de Stock</h3>
                <p>Administrar materiales, medicamentos e insumos</p>
            </a>
            
            <a href="modules/solicitudes/" class="module-card">
                <div class="module-icon">📝</div>
                <h3>Solicitudes</h3>
                <p>Gestionar solicitudes de material</p>
            </a>
            
            <a href="modules/profesionales/" class="module-card">
                <div class="module-icon">👨‍⚕️</div>
                <h3>Profesionales</h3>
                <p>Administrar profesionales solicitantes</p>
            </a>
            
            <a href="modules/compras/" class="module-card">
                <div class="module-icon">🛒</div>
                <h3>Órdenes de Compra</h3>
                <p>Gestionar compras y proveedores</p>
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>