<?php
session_start();
require_once 'conexion/conexion.php';

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/usuario.php'; // foto de perfil / iniciales para la cabecera

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// Mensaje de éxito guardado antes de redirigir (evita reenviar el formulario al recargar)
$mensaje = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_ok']);
$error = '';

// Valores del formulario (se conservan si hay un error)
$old = [
    'nombre' => '', 'material' => '', 'precio' => '',
    'disponibilidad' => '1', 'estado' => 'Disponible', 'descripcion' => '',
];

// Procesar el formulario cuando se envía para registrar un producto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($old as $campo => $valor) {
        $old[$campo] = trim($_POST[$campo] ?? '');
    }
    extract($old); // $nombre, $material, $precio, $disponibilidad, $estado, $descripcion

    if (!empty($nombre) && !empty($precio) && !empty($estado)) {
        if (!is_numeric($precio) || $precio < 0 || !is_numeric($disponibilidad) || $disponibilidad < 0) {
            $error = "Revisa el precio y el stock: deben ser números mayores o iguales a 0.";
        } else {
            try {
                // Consulta preparada para insertar el producto respetando exactamente los campos del modelo
                $sql = "INSERT INTO productos (nombre, descripcion, material, precio, disponibilidad, estado) 
                        VALUES (:nombre, :descripcion, :material, :precio, :disponibilidad, :estado)";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':descripcion' => $descripcion,
                    ':material' => $material,
                    ':precio' => $precio,
                    ':disponibilidad' => $disponibilidad,
                    ':estado' => $estado
                ]);

                $_SESSION['flash_ok'] = "Producto registrado correctamente.";
                header("Location: productos.php");
                exit();
            } catch (PDOException $e) {
                error_log('Productos (insert): ' . $e->getMessage());
                $error = "No se pudo registrar el producto. Inténtalo de nuevo.";
            }
        }
    } else {
        $error = "Completa los campos obligatorios (*).";
    }
}

// Obtener la lista de productos existentes para mostrarlos en la tabla
try {
    $stmt_prod = $conexion->query("SELECT * FROM productos ORDER BY id_producto DESC");
    $productos = $stmt_prod->fetchAll();
} catch (PDOException $e) {
    error_log('Productos (select): ' . $e->getMessage());
    $productos = [];
    $error = "No se pudieron cargar los productos.";
}

// Conteo por estado para los filtros
$conteos = array_count_values(array_column($productos, 'estado'));

function clase_estado($estado) {
    switch ($estado) {
        case 'Disponible':    return 'etq-verde';
        case 'Agotado':       return 'etq-rojo';
        case 'En Producción': return 'etq-ocre';
        default:              return 'etq-gris';
    }
}
function formato_precio($n) {
    $decimales = ((float) $n == floor((float) $n)) ? 0 : 2;
    return '$ ' . number_format((float) $n, $decimales, ',', '.');
}

// Iconos SVG
$icons = [
    'caja'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    'volver'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    'buscar'  => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    'guardar' => '<path d="M12 5v14M5 12h14"/>',
    'check'   => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16 9.5"/>',
    'alerta'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/>',
];
function icono($svg) {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $svg . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Artesanías Manuales</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="pagina">

    <header class="cabecera">
        <div class="cabecera-in">
            <div class="cabecera-marca">
                <span class="cabecera-logo"><?= icono($icons['caja']) ?></span>
                Artesanías Manuales
            </div>
            <div class="cabecera-der">
                <a href="dashboard.php" class="enlace-volver"><?= icono($icons['volver']) ?><span>Volver al panel</span></a>
                <?= avatar_usuario() ?>
            </div>
        </div>
    </header>

    <main class="contenedor">

        <div class="titulo-pagina">
            <span class="titulo-icono"><?= icono($icons['caja']) ?></span>
            <div>
                <h1>Productos</h1>
                <p>Registra y consulta el catálogo de artesanías.</p>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="aviso aviso-ok" role="status"><?= icono($icons['check']) ?><span><?= h($mensaje) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="aviso aviso-error" role="alert"><?= icono($icons['alerta']) ?><span><?= h($error) ?></span></div>
        <?php endif; ?>

        <div class="dos-columnas">

            <!-- Formulario para registrar producto -->
            <aside class="columna-fija">
                <section class="caja">
                    <h2 class="caja-titulo">Nueva artesanía</h2>
                    <p class="caja-nota">Los campos con * son obligatorios.</p>

                    <form action="productos.php" method="POST" class="formulario">
                        <div class="entrada">
                            <label for="nombre">Nombre de la artesanía *</label>
                            <input type="text" id="nombre" name="nombre" required
                                   placeholder="Ej: Mochila Wayuu Tradicional" value="<?= h($old['nombre']) ?>">
                        </div>

                        <div class="entrada">
                            <label for="material">Material principal *</label>
                            <input type="text" id="material" name="material" required
                                   placeholder="Ej: Lana de oveja, Totumo" value="<?= h($old['material']) ?>">
                        </div>

                        <div class="fila-2">
                            <div class="entrada">
                                <label for="precio">Precio ($) *</label>
                                <input type="number" step="0.01" min="0" id="precio" name="precio" required
                                       placeholder="0" value="<?= h($old['precio']) ?>">
                            </div>
                            <div class="entrada">
                                <label for="disponibilidad">Stock *</label>
                                <input type="number" min="0" id="disponibilidad" name="disponibilidad" required
                                       value="<?= h($old['disponibilidad']) ?>">
                            </div>
                        </div>

                        <div class="entrada">
                            <label for="estado">Estado *</label>
                            <select id="estado" name="estado" required>
                                <?php foreach (['Disponible', 'Agotado', 'En Producción'] as $op): ?>
                                    <option value="<?= h($op) ?>" <?= $old['estado'] === $op ? 'selected' : '' ?>><?= h($op) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="entrada">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion"
                                      placeholder="Elaboración, dimensiones, técnica manual utilizada..."><?= h($old['descripcion']) ?></textarea>
                        </div>

                        <button type="submit" class="boton boton-primario boton-block"><?= icono($icons['guardar']) ?>Guardar producto</button>
                    </form>
                </section>
            </aside>

            <!-- Catálogo -->
            <section class="caja caja-sin-padding" aria-labelledby="titulo-catalogo">
                <div class="caja-barra">
                    <div>
                        <h2 class="caja-titulo" id="titulo-catalogo">Catálogo actual</h2>
                        <p class="cuenta" id="cuenta" aria-live="polite">
                            <?= count($productos) ?> <?= count($productos) === 1 ? 'producto registrado' : 'productos registrados' ?>
                        </p>
                    </div>

                    <?php if (count($productos) > 0): ?>
                        <div class="buscador">
                            <?= icono($icons['buscar']) ?>
                            <input type="search" id="buscar" placeholder="Buscar por nombre, material o descripción" aria-label="Buscar productos">
                        </div>

                        <div class="filtros" role="group" aria-label="Filtrar por estado">
                            <button type="button" class="chip" data-filtro="todos" aria-pressed="true">
                                Todos <span class="chip-n"><?= count($productos) ?></span>
                            </button>
                            <button type="button" class="chip" data-filtro="Disponible" aria-pressed="false">
                                <span class="punto punto-verde"></span>Disponible <span class="chip-n"><?= $conteos['Disponible'] ?? 0 ?></span>
                            </button>
                            <button type="button" class="chip" data-filtro="En Producción" aria-pressed="false">
                                <span class="punto punto-ocre"></span>En producción <span class="chip-n"><?= $conteos['En Producción'] ?? 0 ?></span>
                            </button>
                            <button type="button" class="chip" data-filtro="Agotado" aria-pressed="false">
                                <span class="punto punto-rojo"></span>Agotado <span class="chip-n"><?= $conteos['Agotado'] ?? 0 ?></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($productos) > 0): ?>
                    <div class="tabla-scroll">
                        <table class="tabla" id="tabla-productos">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Material</th>
                                    <th class="num">Precio</th>
                                    <th class="num">Stock</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $prod): ?>
                                    <?php
                                        $desc = trim((string) ($prod['descripcion'] ?? ''));
                                        $texto = mb_strtolower($prod['nombre'] . ' ' . $prod['material'] . ' ' . $desc);
                                    ?>
                                    <tr data-estado="<?= h($prod['estado']) ?>" data-texto="<?= h($texto) ?>">
                                        <td class="id"><?= h($prod['id_producto']) ?></td>
                                        <td class="celda-nombre">
                                            <strong><?= h($prod['nombre']) ?></strong>
                                            <?php if ($desc !== ''): ?>
                                                <span class="celda-desc"><?= h(mb_strimwidth($desc, 0, 90, '…')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($prod['material']) ?></td>
                                        <td class="num"><?= formato_precio($prod['precio']) ?></td>
                                        <td class="num"><?= h($prod['disponibilidad']) ?></td>
                                        <td><span class="etq <?= clase_estado($prod['estado']) ?>"><?= h($prod['estado']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="vacio" id="sin-resultados" hidden>Ningún producto coincide con la búsqueda o el filtro.</p>
                <?php else: ?>
                    <p class="vacio">Aún no hay productos. Registra la primera artesanía con el formulario.</p>
                <?php endif; ?>
            </section>

        </div>
    </main>

</div>

<script>
    (function () {
        var tabla = document.getElementById('tabla-productos');
        if (!tabla) return;

        var filas = [].slice.call(tabla.querySelectorAll('tbody tr'));
        var chips = [].slice.call(document.querySelectorAll('.chip'));
        var buscar = document.getElementById('buscar');
        var cuenta = document.getElementById('cuenta');
        var vacio = document.getElementById('sin-resultados');
        var filtro = 'todos';

        function aplicar() {
            var t = buscar.value.trim().toLowerCase();
            var visibles = 0;
            filas.forEach(function (f) {
                var ok = (filtro === 'todos' || f.dataset.estado === filtro) &&
                         (!t || f.dataset.texto.indexOf(t) > -1);
                f.hidden = !ok;
                if (ok) visibles++;
            });
            cuenta.textContent = 'Mostrando ' + visibles + ' de ' + filas.length;
            vacio.hidden = visibles !== 0;
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                filtro = chip.dataset.filtro;
                chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
                aplicar();
            });
        });
        buscar.addEventListener('input', aplicar);
    })();
</script>
</body>
</html>