<?php
/**
 * GESIFAR - Sistema de Permisos
 * Funciones para verificar permisos de usuario según su rol
 */

/**
 * Verifica si el usuario actual tiene un permiso específico
 * @param string $permiso Nombre del permiso (ej: 'stock.ver', 'usuarios.gestionar')
 * @return bool True si tiene el permiso, False si no
 */
function tienePermiso($permiso) {
    // Verificar que hay sesión activa
    if(!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
        return false;
    }
    
    // Admin siempre tiene todos los permisos, ver si podemos ocultar a Javier-Laura 
    // para agregar un admin, editar la tabla desde phpmyadmin con el id-rol admin
    if($_SESSION['rol'] == 'admin') {
        return true;
    }
    
    // Consultar en la base de datos
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as tiene 
              FROM rol_permisos rp
              JOIN permisos p ON rp.id_permiso = p.id_permiso
              WHERE rp.rol = :rol AND p.nombre = :permiso";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':rol', $_SESSION['rol']);
    $stmt->bindParam(':permiso', $permiso);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado['tiene'] > 0;
}

/**
 * Verifica permiso y redirige si no lo tiene
 * Usar al inicio de páginas que requieren permisos específicos
 * @param string $permiso Nombre del permiso requerido
 */
function verificarPermiso($permiso) {
    if(!tienePermiso($permiso)) {
        header("Location: /gesifar/dashboard.php?error=" . urlencode("No tiene permisos para esta acción"));
        exit();
    }
}

/**
 * Obtiene todos los permisos del usuario actual
 * Útil para debugging o mostrar en pantalla
 * @return array Lista de permisos
 */
function obtenerMisPermisos() {
    if(!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
        return [];
    }
    
    // Admin tiene todos
    if($_SESSION['rol'] == 'admin') {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT nombre FROM permisos ORDER BY modulo, nombre";
        $stmt = $db->query($query);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Consultar permisos del rol
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT p.nombre 
              FROM rol_permisos rp
              JOIN permisos p ON rp.id_permiso = p.id_permiso
              WHERE rp.rol = :rol
              ORDER BY p.modulo, p.nombre";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':rol', $_SESSION['rol']);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Verifica si el usuario tiene al menos uno de varios permisos
 * @param array $permisos Array de nombres de permisos
 * @return bool True si tiene al menos uno
 */
function tieneAlgunPermiso($permisos) {
    foreach($permisos as $permiso) {
        if(tienePermiso($permiso)) {
            return true;
        }
    }
    return false;
}

/**
 * Verifica si el usuario tiene TODOS los permisos especificados
 * @param array $permisos Array de nombres de permisos
 * @return bool True si tiene todos
 */
function tieneTodosLosPermisos($permisos) {
    foreach($permisos as $permiso) {
        if(!tienePermiso($permiso)) {
            return false;
        }
    }
    return true;
}

/**
 * Obtiene el nombre legible del rol
 * @param string $rol Código del rol
 * @return string Nombre formateado
 */
function nombreRol($rol) {
    $roles = [
        'admin' => 'Administrador',
        'farmaceutico_jefe' => 'Farmacéutico Jefe',
        'farmaceutico' => 'Farmacéutico',
        'auxiliar_farmacia' => 'Auxiliar de Farmacia',
        'responsable_stock' => 'Responsable de Stock'
    ];
    
    return isset($roles[$rol]) ? $roles[$rol] : ucfirst(str_replace('_', ' ', $rol));
}
?>
