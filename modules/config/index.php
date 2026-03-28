<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../includes/permisos.php';
verificarPermiso('admin');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Configuración</title>
    <link rel="stylesheet" href="/gesifar/assets/css/style.css">
    <link rel="stylesheet" href="/gesifar/assets/css/modules/config/config.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>⚙️ Configuración del Sistema</h1>
            <p>Administrar parámetros y catálogos del sistema</p>
        </div>
        
        <div class="config-grid">
            <!-- Presentaciones -->
            <div class="config-card">
                <div class="config-icon">📦</div>
                <h3>Presentaciones</h3>
                <p>Gestionar tipos de presentaciones de materiales (cajas, frascos, ampollas)</p>
                <a href="presentaciones/index.php" class="btn btn-primary">Administrar</a>
            </div>
            
            <!-- Formas Farmacéuticas -->
            <div class="config-card">
                <div class="config-icon">💊</div>
                <h3>Formas Farmacéuticas</h3>
                <p>Gestionar formas farmacéuticas (comprimidos, cápsulas, jarabes)</p>
                <a href="formas_farmaceuticas/index.php" class="btn btn-primary">Administrar</a>
            </div>
            
            <!-- Áreas -->
            <div class="config-card">
                <div class="config-icon">🏥</div>
                <h3>Áreas Hospitalarias</h3>
                <p>Gestionar áreas de dispensación y servicios del hospital</p>
                <a href="areas/index.php" class="btn btn-primary">Administrar</a>
            </div>
            
            <!-- Proveedores -->
            <div class="config-card">
                <div class="config-icon">🏢</div>
                <h3>Proveedores</h3>
                <p>Gestionar proveedores de medicamentos e insumos</p>
                <a href="proveedores/index.php" class="btn btn-primary">Administrar</a>
            </div>
        </div>
        
        <div style="margin-top: 40px; text-align: center;">
            <a href="../../dashboard.php" class="btn" style="background: #6c757d; color: white;">← Volver al Dashboard</a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>