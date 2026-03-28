<?php
session_start();

if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../../includes/permisos.php';
verificarPermiso('config.editar');

require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: index.php");
    exit();
}

try {
    $query = "UPDATE forma_farmaceutica SET activo = 0 WHERE id_forma_farmaceutica = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    header("Location: index.php?msg=Forma farmacéutica desactivada correctamente");
} catch(PDOException $e) {
    header("Location: index.php?msg=Error al desactivar");
}
exit();
?>