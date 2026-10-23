<?php
session_start();
require_once 'conexion/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('America/Bogota');

$id_usuario = $_SESSION['id_usuario'];
$nombre     = $_SESSION['nombre'];

// Consultar los datos actualizados del usuario (incluyendo la ruta de la foto)
try {
    $stmt = $conexion->prepare("SELECT foto FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $usr_db = $stmt->fetch();
    $foto_perfil = $usr_db['foto'] ?? null;
} catch (PDOException $e) {
    $foto_perfil = null;
}

// Iniciales para el avatar en caso de no tener foto
$partes = preg_split('/\s+/', trim($nombre));
$iniciales = '';
foreach (array_slice($partes, 0, 2) as $p) {
    $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
}

// Saludo según la hora
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

// Fecha en español
$dias  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fecha = $dias[date('w')] . ' ' . date('j') . ' de ' . $meses[date('n') - 1];

// Iconos SVG (trazo, 24x24)
$icons = [
    'productos'     => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    'clientes'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.4c2.2.7 3.5 2.6 3.5 5.6"/>',
    'cotizaciones'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    'detalle'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
    'pedidos'       => '<path d="M6 6h15l-1.7 8.5a2 2 0 0 1-2 1.5H8.4a2 2 0 0 1-2-1.6L4 3H2"/><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/>',
    'recepciones'   => '<path d="M3 13h5l1.5 3h5l1.5-3h5"/><path d="M5.5 5h13l2.5 8v6a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19v-6l2.5-8Z"/>',
    'mas'           => '<path d="M12 5v14M5 12h14"/>',
];
function icono($svg) {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $svg . '</svg>';
}

// Secciones del panel
$grupos = [
    [
        'titulo' => 'Catálogo',
        'texto'  => 'Lo que vendes y a quién.',
        'tono'   => 'verde',
        'items'  => [
            ['productos.php', 'Productos', 'Gestionar catálogo de artesanías', 'productos'],
            ['clientes.php',  'Clientes',  'Administrar compradores',          'clientes'],
        ],
    ],
    [
        'titulo' => 'Cotizar',
        'texto'  => 'Presupuestos para tus compradores.',
        'tono'   => 'ocre',
        'items'  => [
            ['cotizaciones.php',       'Cotizaciones',       'Crear y ver presupuestos', 'cotizaciones'],
            ['detalle_cotizacion.php', 'Detalle cotización', 'Añadir ítems y productos', 'detalle'],
        ],
    ],
    [
        'titulo' => 'Pedidos y entregas',
        'texto'  => 'Del pedido a la mercancía recibida.',
        'tono'   => 'rojo',
        'items'  => [
            ['pedidos.php',     'Pedidos',     'Gestión de ventas',      'pedidos'],
            ['recepciones.php', 'Recepciones', 'Registro de recepción',  'recepciones'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal - Artesanías Manuales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --anil:        #1d2a4a;   /* azul añil, base */
            --anil-suave:  #2c3c63;
            --ocre:        #d9a23b;
            --ocre-bg:     #fbf0d9;
            --verde:       #2c7a68;
            --verde-bg:    #dff0ea;
            --rojo:        #b5402f;
            --rojo-bg:     #f8e1dc;
            --papel:       #eef0f4;
            --superficie:  #ffffff;
            --tinta:       #161b2b;
            --tinta-2:     #565d72;
            --linea:       #dde1ea;
            --fuente-titulo: "Bricolage Grotesque", "Segoe UI", system-ui, sans-serif;
            --fuente-texto:  "Public Sans", "Segoe UI", system-ui, sans-serif;
        }

        .app *, .app *::before, .app *::after { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: var(--papel); }

        .app {
            min-height: 100vh;
            font-family: var(--fuente-texto);
            color: var(--tinta);
            line-height: 1.5;
        }
        .app h1, .app h2, .app h3, .app p { margin: 0; }
        .app a { color: inherit; text-decoration: none; }
        .app svg { width: 22px; height: 22px; flex: none; }

        .app :focus-visible { outline: 3px solid var(--ocre); outline-offset: 3px; border-radius: 8px; }

        /* ---------- Barra superior ---------- */
        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; max-width: 1120px; margin: 0 auto; padding: 18px 24px;
        }
        .marca { display: flex; align-items: center; gap: 10px; font-family: var(--fuente-titulo); font-weight: 700; font-size: 17px; letter-spacing: -.01em; }
        .marca-logo {
            width: 34px; height: 34px; border-radius: 9px; background: var(--anil); color: var(--ocre);
            display: grid; place-items: center;
        }
        .marca-logo svg { width: 18px; height: 18px; }

        /* ---------- Enlace directo al perfil ---------- */
        .usuario-link {
            display: flex; align-items: center; gap: 12px;
            background: transparent; border: none; cursor: pointer; padding: 4px 8px;
            border-radius: 12px; text-decoration: none; color: inherit;
            transition: opacity .2s;
        }
        .usuario-link:hover { opacity: 0.8; }
        
        .avatar {
            width: 38px; height: 38px; border-radius: 50%; background: var(--ocre-bg); color: var(--anil);
            display: grid; place-items: center; font-weight: 600; font-size: 14px; border: 1px solid #ecd9ab;
            overflow: hidden; /* Asegura que la foto redonda no desborde */
        }
        .avatar img {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .usuario-nombre { font-size: 14px; font-weight: 600; line-height: 1.2; text-align: right; }
        .usuario-rol { font-size: 12px; color: var(--tinta-2); text-align: right; }

        /* ---------- Contenido ---------- */
        .contenido { max-width: 1120px; margin: 0 auto; padding: 8px 24px 56px; }

        /* Portada */
        .portada {
            position: relative; overflow: hidden; isolation: isolate;
            background: var(--anil); color: #fff; border-radius: 20px;
            padding: 40px 40px 44px;
            display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 24px;
        }
        .portada::before {
            content: ""; position: absolute; inset: 0 0 0 auto; width: 46%; z-index: -1;
            background:
                linear-gradient(135deg, var(--anil-suave) 25%, transparent 25%) -14px 0 / 28px 28px,
                linear-gradient(225deg, var(--anil-suave) 25%, transparent 25%) -14px 0 / 28px 28px,
                linear-gradient(315deg, var(--anil-suave) 25%, transparent 25%) 0 0 / 28px 28px,
                linear-gradient(45deg,  var(--anil-suave) 25%, transparent 25%) 0 0 / 28px 28px;
            background-color: var(--anil);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 70%);
                    mask-image: linear-gradient(90deg, transparent, #000 70%);
        }
        .portada::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 8px; z-index: -1;
            background: repeating-linear-gradient(90deg, var(--ocre) 0 28px, var(--rojo) 28px 56px, var(--verde) 56px 84px);
        }
        .portada-fecha { font-size: 14px; color: #b9c3dd; margin-bottom: 6px; }
        .portada h1 {
            font-family: var(--fuente-titulo); font-weight: 700; letter-spacing: -.02em;
            font-size: clamp(28px, 4.4vw, 44px); line-height: 1.08;
        }
        .portada p { margin-top: 10px; color: #d3daec; max-width: 46ch; }

        .btn-principal {
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--ocre); color: var(--anil); font-weight: 600; font-size: 15px;
            padding: 13px 20px; border-radius: 12px;
            transition: background .2s, transform .2s;
        }
        .btn-principal svg { width: 18px; height: 18px; }
        .btn-principal:hover { background: #e8b24c; transform: translateY(-1px); }

        /* ---------- Grupos ---------- */
        .grupos {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px;
        }
        .grupo {
            background: var(--superficie); border: 1px solid var(--linea); border-radius: 18px; padding: 22px;
            display: flex; flex-direction: column; gap: 14px;
        }
        .grupo-cab h2 { font-family: var(--fuente-titulo); font-size: 20px; font-weight: 700; letter-spacing: -.01em; }
        .grupo-cab p { font-size: 14px; color: var(--tinta-2); margin-top: 2px; }

        .item {
            --tono: var(--verde); --tono-bg: var(--verde-bg);
            display: flex; align-items: center; gap: 14px;
            padding: 14px; border-radius: 12px; border: 1px solid var(--linea); background: #fafbfd;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }
        .tono-ocre .item { --tono: #9a6a0c; --tono-bg: var(--ocre-bg); }
        .tono-rojo .item { --tono: var(--rojo); --tono-bg: var(--rojo-bg); }

        .item-icono {
            width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center;
            background: var(--tono-bg); color: var(--tono);
            transition: background .2s, color .2s;
        }
        .item-titulo { font-weight: 600; font-size: 15.5px; line-height: 1.25; }
        .item-texto { font-size: 13px; color: var(--tinta-2); margin-top: 2px; }

        .item:hover { border-color: var(--tono); background: #fff; box-shadow: 0 6px 18px -10px rgba(22, 27, 43, .35); }
        .item:hover .item-icono { background: var(--tono); color: #fff; }

        /* ---------- Responsive ---------- */
        @media (max-width: 960px) {
            .grupos { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .topbar { padding: 14px 16px; }
            .contenido { padding: 4px 16px 40px; }
            .usuario-datos { display: none; }
            .portada { padding: 28px 22px 34px; border-radius: 16px; }
            .portada::before { width: 70%; }
            .grupos { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            .app * { transition: none !important; }
        }
    </style>
</head>
<body>
<div class="app">

    <header class="topbar">
        <div class="marca">
            <span class="marca-logo"><?= icono($icons['productos']) ?></span>
            Artesanías Manuales
        </div>
        
        <!-- Enlace directo al perfil -->
        <a href="perfil.php" class="usuario-link" title="Ir a configuración del perfil">
            <div class="usuario-datos">
                <div class="usuario-nombre"><?= htmlspecialchars($nombre) ?></div>
                <div class="usuario-rol">Administrador</div>
            </div>
            <div class="avatar" aria-hidden="true">
                <?php if (!empty($foto_perfil) && file_exists($foto_perfil)): ?>
                    <img src="<?= htmlspecialchars($foto_perfil) ?>" alt="Foto de perfil">
                <?php else: ?>
                    <?= htmlspecialchars($iniciales) ?>
                <?php endif; ?>
            </div>
        </a>
    </header>

    <main class="contenido">
        <section class="portada">
            <div>
                <div class="portada-fecha"><?= ucfirst($fecha) ?></div>
                <h1><?= $saludo ?>, <?= htmlspecialchars($nombre) ?></h1>
                <p>Panel de control y administración para tu tienda de artesanías.</p>
            </div>
            <a href="cotizaciones.php" class="btn-principal"><?= icono($icons['mas']) ?>Nueva cotización</a>
        </section>

        <div class="grupos">
            <?php foreach ($grupos as $g): ?>
                <section class="grupo tono-<?= $g['tono'] ?>">
                    <div class="grupo-cab">
                        <h2><?= $g['titulo'] ?></h2>
                        <p><?= $g['texto'] ?></p>
                    </div>
                    <?php foreach ($g['items'] as $it): ?>
                        <a href="<?= $it[0] ?>" class="item">
                            <span class="item-icono"><?= icono($icons[$it[3]]) ?></span>
                            <span>
                                <span class="item-titulo" style="display:block"><?= $it[1] ?></span>
                                <span class="item-texto" style="display:block"><?= $it[2] ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

</div>

</body>
</html>