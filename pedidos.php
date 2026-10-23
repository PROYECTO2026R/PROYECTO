<?php
session_start();
require_once 'conexion/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

// Registrar nuevo pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cotizacion = trim($_POST['id_cotizacion']);
    $costo_total = trim($_POST['costo_total']);
    $estado = trim($_POST['estado']);
    $id_usuario = $_SESSION['id_usuario'];

    if (!empty($id_cotizacion) && !empty($costo_total) && !empty($estado)) {
        try {
            // Consultar el id_cliente asociado a la cotización seleccionada
            $stmt_c = $conexion->prepare("SELECT id_cliente FROM cotizaciones WHERE id_cotizacion = :id_cotizacion");
            $stmt_c->execute([':id_cotizacion' => $id_cotizacion]);
            $cot = $stmt_c->fetch();

            if ($cot) {
                $id_cliente = $cot['id_cliente'];

                $sql = "INSERT INTO pedidos (id_usuario, id_cotizacion, fecha, estado, costo_total, id_cliente) 
                        VALUES (:id_usuario, :id_cotizacion, NOW(), :estado, :costo_total, :id_cliente)";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ':id_usuario' => $id_usuario,
                    ':id_cotizacion' => $id_cotizacion,
                    ':estado' => $estado,
                    ':costo_total' => $costo_total,
                    ':id_cliente' => $id_cliente
                ]);

                $mensaje = "¡Pedido registrado exitosamente!";
            } else {
                $error = "La cotización seleccionada no existe.";
            }
        } catch (PDOException $e) {
            $error = "Error al registrar el pedido: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos requeridos.";
    }
}

// Cargar cotizaciones y listado de pedidos
try {
    $cotizaciones = $conexion->query("SELECT c.id_cotizacion, cl.nombre, cl.apellido 
                                     FROM cotizaciones c 
                                     INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente 
                                     ORDER BY c.id_cotizacion DESC")->fetchAll();

    $sql_ped = "SELECT p.*, cl.nombre AS cliente_nombre, cl.apellido AS cliente_apellido, u.nombre AS usuario_nombre 
                FROM pedidos p
                INNER JOIN clientes cl ON p.id_cliente = cl.id_cliente
                INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                ORDER BY p.id_pedido DESC";
    $pedidos = $conexion->query($sql_ped)->fetchAll();
} catch (PDOException $e) {
    $cotizaciones = [];
    $pedidos = [];
    $error = "Error al cargar la información.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Artesanías Manuales</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background-color: #f9f9f9; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .nav-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .nav-header h2 { color: #2c2c2c; }
        .btn-volver { background: #666; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn-volver:hover { background: #444; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #444; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
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
            <h2>🛒 Gestión de Pedidos</h2>
            <a href="dashboard.php" class="btn-volver">← Volver al Panel</a>
        </div>

        <?php if (!empty($mensaje)): ?><div class="alert-success"><?php echo $mensaje; ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

        <form action="pedidos.php" method="POST" style="background: #faf8f5; padding: 20px; border-radius: 8px; border: 1px solid #eae5dc;">
            <h3 style="margin-bottom: 15px; color: #b85d34; font-size: 18px;">Generar Nuevo Pedido</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="id_cotizacion">Cotización Base *</label>
                    <select id="id_cotizacion" name="id_cotizacion" required>
                        <option value="">-- Seleccione Cotización --</option>
                        <?php foreach ($cotizaciones as $cot): ?>
                            <option value="<?php echo $cot['id_cotizacion']; ?>">
                                Cotización #<?php echo $cot['id_cotizacion']; ?> - <?php echo htmlspecialchars($cot['nombre'] . ' ' . $cot['apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="costo_total">Costo Total ($) *</label>
                    <input type="number" step="0.01" id="costo_total" name="costo_total" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="estado">Estado del Pedido *</label>
                    <select id="estado" name="estado" required>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Enviado">Enviado</option>
                        <option value="Entregado">Entregado</option>
                        <option value="Cancelado">Cancelado</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-primary">Confirmar Pedido</button>
        </form>

        <h3 style="margin-top: 40px; margin-bottom: 15px; color: #2c2c2c;">Historial de Pedidos</h3>
        <table>
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Cotización</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Costo Total</th>
                    <th>Estado</th>
                    <th>Registrado Por</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pedidos) > 0): ?>
                    <?php foreach ($pedidos as $ped): ?>
                        <tr>
                            <td>#<?php echo $ped['id_pedido']; ?></td>
                            <td>Cotización #<?php echo $ped['id_cotizacion']; ?></td>
                            <td><strong><?php echo htmlspecialchars($ped['cliente_nombre'] . ' ' . $ped['cliente_apellido']); ?></strong></td>
                            <td><?php echo $ped['fecha']; ?></td>
                            <td>$<?php echo number_format($ped['costo_total'], 2); ?></td>
                            <td><?php echo htmlspecialchars($ped['estado']); ?></td>
                            <td><?php echo htmlspecialchars($ped['usuario_nombre']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; color: #777;">No hay pedidos registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>