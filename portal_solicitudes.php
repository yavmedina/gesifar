<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$mensaje = '';
$paso = 1; // 1=validar, 2=formulario, 3=confirmación
$profesional = null;

// Validar DNI y Matrícula
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'validar') {
    $dni = trim($_POST['dni']);
    $matricula = trim($_POST['matricula']);
    
    if(empty($dni) || empty($matricula)) {
        $error = "Complete DNI y Matrícula";
    } else {
        // Buscar profesional
        $query = "SELECT p.*, pr.descripcion as profesion_nombre
                  FROM profesional p
                  JOIN profesion pr ON p.id_profesion = pr.id_profesion
                  WHERE p.dni = :dni AND p.matricula = :matricula AND p.activo = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':dni', $dni);
        $stmt->bindParam(':matricula', $matricula);
        $stmt->execute();
        $profesional = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($profesional) {
            $_SESSION['solicitud_profesional'] = $profesional;
            $paso = 2;
        } else {
            $error = "DNI o Matrícula incorrectos, o profesional inactivo";
        }
    }
}

// Enviar solicitud
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'enviar') {
    $profesional = $_SESSION['solicitud_profesional'];
    $observaciones = trim($_POST['observaciones']);
    
    // Recolectar items (hasta 5)
    $items = [];
    for($i = 1; $i <= 5; $i++) {
        $nombre = trim($_POST["item_$i"] ?? '');
        $cantidad = (int)($_POST["cantidad_$i"] ?? 0);
        
        if(!empty($nombre) && $cantidad > 0) {
            $items[] = [
                'numero' => $i,
                'nombre' => $nombre,
                'cantidad' => $cantidad
            ];
        }
    }
    
    if(count($items) == 0) {
        $error = "Debe solicitar al menos 1 item";
        $paso = 2;
    } else {
        try {
            $db->beginTransaction();
            
            // Generar código
            $query_last = "SELECT codigo_solicitud FROM solicitud ORDER BY id_solicitud DESC LIMIT 1";
            $stmt_last = $db->query($query_last);
            $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
            
            if($last) {
                $num = (int)substr($last['codigo_solicitud'], 7) + 1;
                $codigo = 'SOLPED-' . str_pad($num, 6, '0', STR_PAD_LEFT);
            } else {
                $codigo = 'SOLPED-000001';
            }
            
            // Insertar solicitud
            $query = "INSERT INTO solicitud (
                codigo_solicitud, fecha_solicitud, dni_profesional, id_profesional,
                nombre_profesional, servicio, id_estado, observaciones_profesional
            ) VALUES (
                :codigo, NOW(), :dni, :id_prof, :nombre, :servicio, 1, :obs
            )";
            
            $stmt = $db->prepare($query);
            $nombre_completo = $profesional['apellido'] . ', ' . $profesional['nombre'];
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':dni', $profesional['dni']);
            $stmt->bindParam(':id_prof', $profesional['id_profesional']);
            $stmt->bindParam(':nombre', $nombre_completo);
            $stmt->bindParam(':servicio', $profesional['servicio']);
            $stmt->bindParam(':obs', $observaciones);
            $stmt->execute();
            
            $id_solicitud = $db->lastInsertId();
            
            // Insertar items
            foreach($items as $item) {
                // Buscar si existe el material
                $query_mat = "SELECT id_material FROM material WHERE nombre LIKE :nombre AND activo = 1 LIMIT 1";
                $stmt_mat = $db->prepare($query_mat);
                $nombre_buscar = "%{$item['nombre']}%";
                $stmt_mat->bindParam(':nombre', $nombre_buscar);
                $stmt_mat->execute();
                $material = $stmt_mat->fetch(PDO::FETCH_ASSOC);
                
                $id_material = $material ? $material['id_material'] : null;
                
                $query_det = "INSERT INTO detalle_solicitud (
                    id_solicitud, numero_item, nombre_solicitado, id_material, cantidad_solicitada
                ) VALUES (
                    :sol, :num, :nombre, :mat, :cant
                )";
                
                $stmt_det = $db->prepare($query_det);
                $stmt_det->bindParam(':sol', $id_solicitud);
                $stmt_det->bindParam(':num', $item['numero']);
                $stmt_det->bindParam(':nombre', $item['nombre']);
                $stmt_det->bindParam(':mat', $id_material);
                $stmt_det->bindParam(':cant', $item['cantidad']);
                $stmt_det->execute();
            }
            
            $db->commit();
            
            $_SESSION['solicitud_codigo'] = $codigo;
            $paso = 3;
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "Error al enviar solicitud: " . $e->getMessage();
            $paso = 2;
        }
    }
}

// Recuperar profesional de sesión si existe
if(isset($_SESSION['solicitud_profesional']) && $paso == 1) {
    $profesional = $_SESSION['solicitud_profesional'];
    $paso = 2;
}

// Obtener materiales disponibles para datalist
$materiales = $db->query("SELECT DISTINCT nombre FROM material WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GESIFAR - Solicitud de Materiales</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .portal-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .portal-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
        }
        .info-profesional {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .item-row {
            display: grid;
            grid-template-columns: 1fr 150px;
            gap: 10px;
            margin-bottom: 15px;
        }
        .success-message {
            text-align: center;
            padding: 40px;
        }
        .success-message h2 {
            color: #10b981;
            font-size: 48px;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <?php if($paso == 1): ?>
            <!-- PASO 1: Validación -->
            <div class="portal-header">
                <h1>📋 Solicitud de Materiales</h1>
                <p>Portal Público - GESIFAR</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="accion" value="validar">
                
                <div class="form-group">
                    <label>DNI <span class="required">*</span></label>
                    <input type="text" name="dni" required placeholder="Ej: 12345678" autofocus>
                </div>
                
                <div class="form-group">
                    <label>Matrícula Profesional <span class="required">*</span></label>
                    <input type="text" name="matricula" required placeholder="Ej: MN 123456">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Validar y Continuar</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php">← Volver al Login</a>
            </div>
            
        <?php elseif($paso == 2): ?>
            <!-- PASO 2: Formulario de solicitud -->
            <div class="portal-header">
                <h1>📋 Nueva Solicitud</h1>
            </div>
            
            <div class="info-profesional">
                <strong>👨‍⚕️ <?php echo htmlspecialchars($profesional['apellido'] . ', ' . $profesional['nombre']); ?></strong><br>
                <small><?php echo htmlspecialchars($profesional['profesion_nombre']); ?> | <?php echo htmlspecialchars($profesional['servicio']); ?></small>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="accion" value="enviar">
                
                <h3>Materiales Solicitados (máximo 5)</h3>
                <p style="color: #666; margin-bottom: 20px;">Puede escribir el nombre o seleccionar de la lista</p>
                
                <datalist id="materiales">
                    <?php foreach($materiales as $mat): ?>
                        <option value="<?php echo htmlspecialchars($mat['nombre']); ?>">
                    <?php endforeach; ?>
                </datalist>
                
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <div class="item-row">
                        <div class="form-group">
                            <label>Item <?php echo $i; ?></label>
                            <input type="text" name="item_<?php echo $i; ?>" list="materiales" placeholder="Escriba o seleccione el material">
                        </div>
                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" name="cantidad_<?php echo $i; ?>" min="1" placeholder="0">
                        </div>
                    </div>
                <?php endfor; ?>
                
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" rows="3" placeholder="Información adicional (opcional)"></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="?cancelar=1" class="btn" style="background: #6c757d; color: white;">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
                </div>
            </form>
            
        <?php elseif($paso == 3): ?>
            <!-- PASO 3: Confirmación -->
            <div class="success-message">
                <h2>✅</h2>
                <h1>Solicitud Enviada</h1>
                <p style="font-size: 24px; color: #3b82f6; margin: 20px 0;">
                    <strong><?php echo $_SESSION['solicitud_codigo']; ?></strong>
                </p>
                <p>Su solicitud ha sido registrada correctamente y será procesada por farmacia.</p>
                <p style="margin-top: 30px;">
                    <a href="?nueva=1" class="btn btn-primary">Nueva Solicitud</a>
                    <a href="login.php" class="btn" style="background: #6c757d; color: white;">Volver al Login</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Limpiar sesión si cancela o termina
if(isset($_GET['cancelar']) || isset($_GET['nueva'])) {
    unset($_SESSION['solicitud_profesional']);
    unset($_SESSION['solicitud_codigo']);
    header("Location: portal_solicitudes.php");
    exit();
}
?>