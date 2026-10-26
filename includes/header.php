<?php
// Cargar sistema de permisos
require_once __DIR__ . '/permisos.php';
?>

<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <h2>🏥 GESIFAR</h2>
        </div>
        
        <nav class="main-nav">
            <div>
                <a href="/gesifar/dashboard.php">Dashboard</a>
                
                <?php if(tienePermiso('stock.ver')): ?>
                    <a href="/gesifar/modules/stock/">Stock</a>
                <?php endif; ?>
                    
                <?php if(tienePermiso('solicitudes.ver')): ?>
                    <a href="/gesifar/modules/solicitudes/">Solicitudes</a>
                <?php endif; ?>
                        
                <?php if(tienePermiso('profesionales.gestionar')): ?>
                    <a href="/gesifar/modules/profesionales/">Profesionales</a>
                <?php endif; ?>
                            
                <?php if(tienePermiso('reposiciones.ver')): ?>
                    <a href="/gesifar/modules/reposiciones/">Reposiciones</a>
                <?php endif; ?>
                                
                <?php if(tienePermiso('reportes.basicos')): ?>
                    <a href="/gesifar/modules/reportes/">Reportes</a>
                <?php endif; ?>
            </div>
            <div>
                <?php if(tienePermiso('usuarios.gestionar')): ?>
                    <a href="/gesifar/modules/usuarios/">👥 Usuarios</a>
                <?php endif; ?>
                    
                <?php if(tienePermiso('config.ver')): ?>
                    <a href="/gesifar/modules/config/">⚙️ Configuración</a>
                <?php endif; ?>
            </div>
        </nav>
        
        <div class="user-menu">
            <div class="user-info-text">
                <strong>👤 <?php echo $_SESSION['nombre']; ?></strong>
                <small>
                    <?php echo nombreRol($_SESSION['rol']); ?>
                </small>
            </div>
            <a href="/gesifar/logout.php" class="btn btn-logout nowrap">Cerrar Sesión</a>
        </div>
    </div>
</header>