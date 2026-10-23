<?php
session_start();
require_once 'conexion/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $documento = trim($_POST['documento']);
    $telefono = trim($_POST['telefono']);
    $correo = trim($_POST['correo']);

    if (!empty($nombre) && !empty($apellido) && !empty($documento) && !empty($correo)) {
        try {
            $sql = "INSERT INTO clientes (nombre, apellido, documento, telefono, correo) 
                    VALUES (:nombre, :apellido, :documento, :telefono, :correo)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':documento' => $documento,
                ':telefono' => $telefono,
                ':correo' => $correo
            ]);
            $mensaje = "¡Cliente registrado exitosamente!";
        } catch (PDOException $e) {
            $error = "Error al registrar el cliente (es posible que el documento o correo ya existan): " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa los campos obligatorios.";
    }
}

try {
    $stmt = $conexion->query("SELECT * FROM clientes ORDER BY id_cliente DESC");
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
    $error = "Error al cargar los clientes.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Artesanías Manuales</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background-color: #f9f9f9; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .nav-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .nav-header h2 { color: #2c2c2c; }
        .btn-volver { background: #666; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn-volver:hover { background: #444; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #444; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .btn-primary { background-color: #b85d34; color: white; padding: 12px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .btn-primary:hover { background-color: #9a4c28; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background-color: #f8f5f0; color: #333; font-weight: 600; }
        .alert-success { background-color: #def7ec; color: #03543f; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #bcf0da; }
        .alert-error { background-color: #fde8e8; color: #9b1c1c; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f8b4b4; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="nav-header">
            <h2>Gestión de Clientes</h2>
            <a href="dashboard.php" class="btn-volver">← Volver al Panel</a>
        </div>

        <?php if (!empty($mensaje)): ?><div class="alert-success"><?php echo $mensaje; ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

        <form action="clientes.php" method="POST" style="background: #faf8f5; padding: 20px; border-radius: 8px; border: 1px solid #eae5dc;">
            <h3 style="margin-bottom: 15px; color: #b85d34; font-size: 18px;">Registrar Nuevo Cliente</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej: Ana María">
                </div>
                <div class="form-group">
                    <label for="apellido">Apellido *</label>
                    <input type="text" id="apellido" name="apellido" required placeholder="Ej: Pérez Gómez">
                </div>
                <div class="form-group">
                    <label for="documento">Documento *</label>
                    <input type="text" id="documento" name="documento" required placeholder="Cédula o NIT">
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" placeholder="3101234***">
                </div>
                <div class="form-group">
                    <label for="correo">Correo Electrónico *</label>
                    <input type="email" id="correo" name="correo" required placeholder="correo@ejemplo.com">
                </div>
            </div>
            <button type="submit" class="btn-primary">Guardar Cliente</button>
        </form>

        <h3 style="margin-top: 40px; margin-bottom: 15px; color: #2c2c2c;">Listado de Clientes</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Documento</th>
                    <th>Nombre y Apellido</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($clientes) > 0): ?>
                    <?php foreach ($clientes as $cli): ?>
                        <tr>
                            <td><?php echo $cli['id_cliente']; ?></td>
                            <td><?php echo htmlspecialchars($cli['documento']); ?></td>
                            <td><strong><?php echo htmlspecialchars($cli['nombre'] . ' ' . $cli['apellido']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cli['telefono']); ?></td>
                            <td><?php echo htmlspecialchars($cli['correo']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; color: #777;">No hay clientes registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>