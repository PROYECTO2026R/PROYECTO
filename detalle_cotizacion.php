<?php
session_start();
require_once 'conexion/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

// Procesar inclusión de detalle en la cotización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cotizacion = trim($_POST['id_cotizacion']);
    $id_producto = trim($_POST['id_producto']);
    $cantidad = trim($_POST['cantidad']);

    if (!empty($id_cotizacion) && !empty($id_producto) && !empty($cantidad) && $cantidad > 0) {
        try {
            // Consultar el precio actual del producto seleccionado
            $stmt_p = $conexion->prepare("SELECT precio FROM productos WHERE id_producto = :id_producto");
            $stmt_p->execute([':id_producto' => $id_producto]);
            $prod = $stmt_p->fetch();

            if ($prod) {
                $precio = $prod['precio'];

                // Insertar el detalle respetando las llaves foráneas del modelo
                $sql = "INSERT INTO detalle_cotizacion (id_cotizacion, id_producto, cantidad, precio, productos_id_producto) 
                        VALUES (:id_cotizacion, :id_producto, :cantidad, :precio, :id_producto_ref)";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ':id_cotizacion' => $id_cotizacion,
                    ':id_producto' => $id_producto,
                    ':cantidad' => $cantidad,
                    ':precio' => $precio,
                    ':id_producto_ref' => $id_producto
                ]);

                $mensaje = "¡Producto agregado a la cotización con éxito!";
            } else {
                $error = "El producto seleccionado no existe.";
            }
        } catch (PDOException $e) {
            $error = "Error al agregar el detalle: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos requeridos con valores válidos.";
    }
}

// Cargar opciones para los selects y la tabla de detalles
try {
    $cotizaciones = $conexion->query("SELECT c.id_cotizacion, cl.nombre, cl.apellido 
                                     FROM cotizaciones c 
                                     INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente 
                                     ORDER BY c.id_cotizacion DESC")->fetchAll();

    $productos = $conexion->query("SELECT id_producto, nombre, precio, disponibilidad FROM productos WHERE disponibilidad > 0")->fetchAll();

    $sql_det = "SELECT dc.*, p.nombre AS producto_nombre, c.id_cotizacion, cl.nombre AS cliente_nombre, cl.apellido AS cliente_apellido
                FROM detalle_cotizacion dc
                INNER JOIN productos p ON dc.id_producto = p.id_producto
                INNER JOIN cotizaciones c ON dc.id_cotizacion = c.id_cotizacion
                INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
                ORDER BY dc.id_detalle_cotizacion DESC";
    $detalles = $conexion->query($sql_det)->fetchAll();
} catch (PDOException $e) {
    $cotizaciones = [];
    $productos = [];
    $detalles = [];
    $error = "Error al cargar datos de la base de datos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Cotización - Artesanías Manuales</title>
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
            <h2>📝 Detalle de Cotización</h2>
            <a href="dashboard.php" class="btn-volver">← Volver al Panel</a>
        </div>

        <?php if (!empty($mensaje)): ?><div class="alert-success"><?php echo $mensaje; ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

        <form action="detalle_cotizacion.php" method="POST" style="background: #faf8f5; padding: 20px; border-radius: 8px; border: 1px solid #eae5dc;">
            <h3 style="margin-bottom: 15px; color: #b85d34; font-size: 18px;">Agregar Ítem a Cotización</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="id_cotizacion">Cotización N° *</label>
                    <select id="id_cotizacion" name="id_cotizacion" required>
                        <option value="">-- Seleccione una Cotización --</option>
                        <?php foreach ($cotizaciones as $cot): ?>
                            <option value="<?php echo $cot['id_cotizacion']; ?>">
                                Cotización #<?php echo $cot['id_cotizacion']; ?> - <?php echo htmlspecialchars($cot['nombre'] . ' ' . $cot['apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_producto">Producto / Artesanía *</label>
                    <select id="id_producto" name="id_producto" required>
                        <option value="">-- Seleccione un Producto --</option>
                        <?php foreach ($productos as $prod): ?>
                            <option value="<?php echo $prod['id_producto']; ?>">
                                <?php echo htmlspecialchars($prod['nombre']); ?> ($<?php echo number_format($prod['precio'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cantidad">Cantidad *</label>
                    <input type="number" id="cantidad" name="cantidad" min="1" value="1" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">Añadir a Cotización</button>
        </form>

        <h3 style="margin-top: 40px; margin-bottom: 15px; color: #2c2c2c;">Ítems de Cotizaciones Registrados</h3>
        <table>
            <thead>
                <tr>
                    <th>ID Detalle</th>
                    <th>Cotización N°</th>
                    <th>Cliente</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($detalles) > 0): ?>
                    <?php foreach ($detalles as $det): ?>
                        <tr>
                            <td><?php echo $det['id_detalle_cotizacion']; ?></td>
                            <td>#<?php echo $det['id_cotizacion']; ?></td>
                            <td><?php echo htmlspecialchars($det['cliente_nombre'] . ' ' . $det['cliente_apellido']); ?></td>
                            <td><strong><?php echo htmlspecialchars($det['producto_nombre']); ?></strong></td>
                            <td><?php echo $det['cantidad']; ?></td>
                            <td>$<?php echo number_format($det['precio'], 2); ?></td>
                            <td><strong>$<?php echo number_format($det['precio'] * $det['cantidad'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; color: #777;">No hay ítems registrados en las cotizaciones.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>