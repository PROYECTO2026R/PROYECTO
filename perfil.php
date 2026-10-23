<?php
session_start();
require_once 'conexion/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('America/Bogota');

$id_usuario = $_SESSION['id_usuario'];
$mensaje = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_ok']);
$error = '';
$tab_activa = 'actividad';

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// ---------------------------------------------------------------
// Procesar formularios
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1. Datos personales
    if ($accion == 'actualizar_datos') {
        $tab_activa = 'editar';
        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $correo   = trim($_POST['correo'] ?? '');

        if ($nombre === '' || $apellido === '' || $correo === '') {
            $error = "Todos los campos de datos personales son obligatorios.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "Escribe un correo electrónico válido.";
        } else {
            try {
                $stmt = $conexion->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, correo = :correo WHERE id_usuario = :id");
                $stmt->execute([':nombre' => $nombre, ':apellido' => $apellido, ':correo' => $correo, ':id' => $id_usuario]);
                $_SESSION['nombre'] = $nombre . ' ' . $apellido;
                $_SESSION['correo'] = $correo;
                $_SESSION['flash_ok'] = "Perfil actualizado correctamente.";
                header("Location: perfil.php");
                exit();
            } catch (PDOException $e) {
                error_log('Perfil (datos): ' . $e->getMessage());
                $error = "No se pudo actualizar (es posible que el correo ya esté registrado por otro usuario).";
            }
        }
    }

    // 2. Cambiar contraseña
    if ($accion == 'cambiar_password') {
        $tab_activa = 'seguridad';
        $pass_actual    = $_POST['pass_actual'] ?? '';
        $pass_nueva     = $_POST['pass_nueva'] ?? '';
        $pass_confirmar = $_POST['pass_confirmar'] ?? '';

        if ($pass_actual === '' || $pass_nueva === '' || $pass_confirmar === '') {
            $error = "Por favor, completa todos los campos de contraseña.";
        } elseif (strlen($pass_nueva) < 8) {
            $error = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif ($pass_nueva !== $pass_confirmar) {
            $error = "Las nuevas contraseñas no coinciden.";
        } else {
            try {
                $stmt = $conexion->prepare("SELECT password FROM usuarios WHERE id_usuario = :id");
                $stmt->execute([':id' => $id_usuario]);
                $usr = $stmt->fetch();

                if ($usr && password_verify($pass_actual, $usr['password'])) {
                    $update = $conexion->prepare("UPDATE usuarios SET password = :pass WHERE id_usuario = :id");
                    $update->execute([':pass' => password_hash($pass_nueva, PASSWORD_DEFAULT), ':id' => $id_usuario]);
                    $_SESSION['flash_ok'] = "Contraseña actualizada exitosamente.";
                    header("Location: perfil.php");
                    exit();
                } else {
                    $error = "La contraseña actual es incorrecta.";
                }
            } catch (PDOException $e) {
                error_log('Perfil (password): ' . $e->getMessage());
                $error = "Error técnico al cambiar la contraseña.";
            }
        }
    }

    // 3. Foto de perfil
    if ($accion == 'actualizar_foto') {
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['foto'];
            $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

            // Tipo real del archivo (no el que declara el navegador)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($archivo['tmp_name']);

            if (!isset($tipos[$mime])) {
                $error = "Formato de imagen no permitido. Solo se acepta JPG, PNG o WEBP.";
            } elseif ($archivo['size'] > 3 * 1024 * 1024) {
                $error = "La imagen supera el máximo de 3 MB.";
            } else {
                $carpeta = 'uploads/';
                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0755, true);
                }
                $ruta = $carpeta . 'perfil_' . $id_usuario . '_' . time() . '.' . $tipos[$mime];

                if (move_uploaded_file($archivo['tmp_name'], $ruta)) {
                    try {
                        // Borrar la foto anterior para no acumular archivos
                        $ant = $conexion->prepare("SELECT foto FROM usuarios WHERE id_usuario = :id");
                        $ant->execute([':id' => $id_usuario]);
                        $foto_ant = $ant->fetchColumn();

                        $stmt = $conexion->prepare("UPDATE usuarios SET foto = :foto WHERE id_usuario = :id");
                        $stmt->execute([':foto' => $ruta, ':id' => $id_usuario]);

                        if ($foto_ant && strpos($foto_ant, 'uploads/perfil_') === 0 && is_file($foto_ant)) {
                            @unlink($foto_ant);
                        }
                        $_SESSION['flash_ok'] = "Foto de perfil actualizada.";
                        header("Location: perfil.php");
                        exit();
                    } catch (PDOException $e) {
                        error_log('Perfil (foto): ' . $e->getMessage());
                        $error = "Error al guardar la foto en la base de datos.";
                    }
                } else {
                    $error = "No se pudo guardar el archivo en el servidor.";
                }
            }
        } else {
            $error = "Por favor, selecciona una imagen válida.";
        }
    }
}

// ---------------------------------------------------------------
// Datos del usuario y estadísticas
// ---------------------------------------------------------------
$usuario = [];
$rol = $_SESSION['rol'] ?? 'Usuario';
try {
    $stmt = $conexion->prepare("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u LEFT JOIN roles r ON u.id_rol = r.id_rol WHERE u.id_usuario = :id");
    $stmt->execute([':id' => $id_usuario]);
    $usuario = $stmt->fetch() ?: [];
    $rol = $usuario['rol_nombre'] ?? $rol;
} catch (PDOException $e) {
    error_log('Perfil (usuario): ' . $e->getMessage());
}

function contar($conexion, $tabla, $id) {
    try {
        $s = $conexion->prepare("SELECT COUNT(*) FROM $tabla WHERE id_usuario = :id");
        $s->execute([':id' => $id]);
        return (int) $s->fetchColumn();
    } catch (PDOException $e) {
        error_log("Perfil (contar $tabla): " . $e->getMessage());
        return 0;
    }
}
$n_cot = contar($conexion, 'cotizaciones', $id_usuario);
$n_ped = contar($conexion, 'pedidos', $id_usuario);
$n_rec = contar($conexion, 'recepciones', $id_usuario);

// Actividad reciente (cotizaciones y pedidos del usuario)
$recientes = [];
try {
    $s = $conexion->prepare("SELECT c.id_cotizacion AS id, c.estado, c.fecha_emision AS fecha, cl.nombre, cl.apellido
                             FROM cotizaciones c INNER JOIN clientes cl ON c.id_cliente = cl.id_cliente
                             WHERE c.id_usuario = :id ORDER BY c.id_cotizacion DESC LIMIT 6");
    $s->execute([':id' => $id_usuario]);
    foreach ($s->fetchAll() as $r) { $r['tipo'] = 'Cotización'; $recientes[] = $r; }

    $s = $conexion->prepare("SELECT p.id_pedido AS id, p.estado, p.fecha, p.costo_total, cl.nombre, cl.apellido
                             FROM pedidos p INNER JOIN clientes cl ON p.id_cliente = cl.id_cliente
                             WHERE p.id_usuario = :id ORDER BY p.id_pedido DESC LIMIT 6");
    $s->execute([':id' => $id_usuario]);
    foreach ($s->fetchAll() as $r) { $r['tipo'] = 'Pedido'; $recientes[] = $r; }

    usort($recientes, function ($a, $b) { return strcmp((string) $b['fecha'], (string) $a['fecha']); });
    $recientes = array_slice($recientes, 0, 9);
} catch (PDOException $e) {
    error_log('Perfil (recientes): ' . $e->getMessage());
}

// Datos de presentación
$nombre_completo = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
if ($nombre_completo === '') { $nombre_completo = $_SESSION['nombre'] ?? 'Usuario'; }
$correo_actual = $usuario['correo'] ?? ($_SESSION['correo'] ?? '');
$handle = '@' . preg_replace('/[^a-z0-9._]/i', '', strtolower(strstr($correo_actual, '@', true) ?: 'usuario'));

$iniciales = '';
foreach (array_slice(preg_split('/\s+/', $nombre_completo), 0, 2) as $p) {
    $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
}
$foto = (!empty($usuario['foto']) && is_file($usuario['foto'])) ? $usuario['foto'] : null;

function color_estado($estado) {
    $e = mb_strtolower((string) $estado);
    if (in_array($e, ['aprobada', 'entregado', 'enviado'])) return 'verde';
    if (in_array($e, ['rechazada', 'cancelado'])) return 'rojo';
    return 'ocre';
}
function fecha_corta($f) {
    $t = strtotime((string) $f);
    if (!$t) return '';
    $m = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return date('j', $t) . ' ' . $m[date('n', $t) - 1] . ' ' . date('Y', $t);
}

if ($error !== '' && ($_POST['accion'] ?? '') === 'actualizar_foto') { $tab_activa = 'actividad'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($nombre_completo) ?> (<?= h($handle) ?>) - Artesanías Manuales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --anil: #1d2a4a; --anil-suave: #2c3c63; --ocre: #d9a23b; --ocre-bg: #fbf0d9;
            --verde: #2c7a68; --verde-bg: #dff0ea; --rojo: #b5402f; --rojo-bg: #f8e1dc;
            --papel: #eef0f4; --superficie: #ffffff; --tinta: #161b2b; --tinta-2: #565d72; --linea: #dde1ea;
            --fuente-titulo: "Bricolage Grotesque", "Segoe UI", system-ui, sans-serif;
            --fuente-texto: "Public Sans", "Segoe UI", system-ui, sans-serif;
        }
        .pf, .pf *, .pf *::before, .pf *::after { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: var(--papel); }
        .pf { min-height: 100vh; font-family: var(--fuente-texto); color: var(--tinta); line-height: 1.5; padding-bottom: 60px; }
        .pf h1, .pf h2, .pf h3, .pf p { margin: 0; }
        .pf a { color: inherit; text-decoration: none; }
        .pf svg { width: 20px; height: 20px; flex: none; }
        .pf :focus-visible { outline: 3px solid var(--ocre); outline-offset: 3px; border-radius: 8px; }

        .pf-wrap { max-width: 760px; margin: 0 auto; padding: 0 16px; }

        /* Barra superior */
        .pf-top { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; }
        .pf-volver { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; color: var(--anil); background: var(--superficie); border: 1px solid var(--linea); padding: 8px 14px; border-radius: 999px; transition: background .2s; }
        .pf-volver:hover { background: #fff; box-shadow: 0 4px 12px -6px rgba(22,27,43,.35); }
        .pf-top-titulo { font-family: var(--fuente-titulo); font-weight: 700; font-size: 16px; }

        /* Tarjeta de cabecera */
        .pf-card { background: var(--superficie); border: 1px solid var(--linea); border-radius: 22px; overflow: hidden; }
        .pf-portada { position: relative; height: 130px; background: var(--anil); isolation: isolate; }
        .pf-portada::before {
            content: ""; position: absolute; inset: 0; z-index: -1;
            background:
                linear-gradient(135deg, var(--anil-suave) 25%, transparent 25%) -14px 0 / 28px 28px,
                linear-gradient(225deg, var(--anil-suave) 25%, transparent 25%) -14px 0 / 28px 28px,
                linear-gradient(315deg, var(--anil-suave) 25%, transparent 25%) 0 0 / 28px 28px,
                linear-gradient(45deg,  var(--anil-suave) 25%, transparent 25%) 0 0 / 28px 28px;
            background-color: var(--anil);
        }
        .pf-portada::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 6px;
            background: repeating-linear-gradient(90deg, var(--ocre) 0 28px, var(--rojo) 28px 56px, var(--verde) 56px 84px);
        }

        .pf-cabecera { padding: 0 24px 22px; text-align: center; }
        .pf-avatar-box { position: relative; width: 112px; height: 112px; margin: -56px auto 0; }
        .pf-avatar {
            width: 112px; height: 112px; border-radius: 50%; overflow: hidden; display: grid; place-items: center;
            background: var(--ocre-bg); color: var(--anil); font-family: var(--fuente-titulo); font-weight: 700; font-size: 38px;
            border: 4px solid var(--superficie); box-shadow: 0 6px 20px -8px rgba(22,27,43,.45);
        }
        .pf-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pf-cam {
            position: absolute; right: 2px; bottom: 2px; width: 34px; height: 34px; border-radius: 50%; cursor: pointer;
            display: grid; place-items: center; background: var(--anil); color: #fff; border: 3px solid var(--superficie);
            transition: background .2s, transform .2s;
        }
        .pf-cam:hover { background: var(--ocre); color: var(--anil); transform: scale(1.06); }
        .pf-cam svg { width: 16px; height: 16px; }
        .pf-cam input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; font-size: 0; }

        .pf-nombre { margin-top: 12px; font-family: var(--fuente-titulo); font-size: 24px; font-weight: 700; letter-spacing: -.01em; }
        .pf-handle { color: var(--tinta-2); font-size: 14px; margin-top: 2px; }
        .pf-rol { display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 999px; background: var(--ocre-bg); color: #7a5208; font-size: 12.5px; font-weight: 600; }

        /* Estadísticas */
        .pf-stats { display: flex; justify-content: center; gap: 8px; margin-top: 18px; }
        .pf-stat { min-width: 96px; padding: 6px 14px; }
        .pf-stat b { display: block; font-family: var(--fuente-titulo); font-size: 22px; font-weight: 700; line-height: 1.2; }
        .pf-stat span { font-size: 13px; color: var(--tinta-2); }
        .pf-stat + .pf-stat { border-left: 1px solid var(--linea); }

        /* Botones */
        .pf-acciones { display: flex; justify-content: center; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .pf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 26px; border-radius: 10px; font: inherit; font-weight: 600; font-size: 14.5px; cursor: pointer; border: 0; transition: background .2s, transform .2s; }
        .pf-btn:active { transform: translateY(1px); }
        .pf-btn-primario { background: var(--anil); color: #fff; }
        .pf-btn-primario:hover { background: var(--anil-suave); }
        .pf-btn-suave { background: var(--papel); color: var(--tinta); border: 1px solid var(--linea); }
        .pf-btn-suave:hover { background: #e4e7ee; }
        .pf-btn-verde { background: var(--verde); color: #fff; }
        .pf-btn-verde:hover { background: #236354; }

        /* Avisos */
        .pf-aviso { display: flex; gap: 10px; align-items: flex-start; margin-top: 16px; padding: 12px 14px; border-radius: 12px; font-size: 14px; }
        .pf-aviso svg { margin-top: 1px; }
        .pf-aviso-ok { background: var(--verde-bg); color: #114337; border: 1px solid #b2d8cd; }
        .pf-aviso-ok svg { color: var(--verde); }
        .pf-aviso-error { background: var(--rojo-bg); color: #7d2a1e; border: 1px solid #ecb8ae; }
        .pf-aviso-error svg { color: var(--rojo); }

        /* Pestañas estilo TikTok */
        .pf-tabs { display: flex; margin-top: 18px; background: var(--superficie); border: 1px solid var(--linea); border-radius: 16px 16px 0 0; border-bottom: 0; }
        .pf-tab {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 8px;
            background: transparent; border: 0; cursor: pointer; font: inherit; font-weight: 600; font-size: 14px; color: var(--tinta-2);
            border-bottom: 3px solid transparent; transition: color .2s, border-color .2s;
        }
        .pf-tab:hover { color: var(--tinta); }
        .pf-tab[aria-selected="true"] { color: var(--tinta); border-bottom-color: var(--tinta); }
        .pf-panel { background: var(--superficie); border: 1px solid var(--linea); border-top: 1px solid var(--linea); border-radius: 0 0 16px 16px; padding: 22px; }
        .pf-panel[hidden] { display: none; }

        /* Cuadrícula de actividad */
        .pf-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .pf-item {
            --t: #9a6a0c; --tb: var(--ocre-bg);
            position: relative; aspect-ratio: 3 / 4; border-radius: 12px; padding: 12px; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            background: var(--tb); border: 1px solid rgba(22,27,43,.06); transition: transform .2s, box-shadow .2s;
        }
        .pf-item:hover { transform: translateY(-2px); box-shadow: 0 10px 22px -14px rgba(22,27,43,.5); }
        .pf-item.verde { --t: var(--verde); --tb: var(--verde-bg); }
        .pf-item.rojo  { --t: var(--rojo);  --tb: var(--rojo-bg); }
        .pf-item-tipo { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--t); }
        .pf-item-num { font-family: var(--fuente-titulo); font-size: 30px; font-weight: 700; line-height: 1; color: var(--tinta); }
        .pf-item-cli { font-size: 13px; font-weight: 600; line-height: 1.25; margin-top: 6px; }
        .pf-item-pie { display: flex; justify-content: space-between; align-items: center; gap: 6px; font-size: 11.5px; color: var(--tinta-2); }
        .pf-item-estado { background: var(--t); color: #fff; padding: 2px 8px; border-radius: 999px; font-weight: 600; font-size: 11px; }
        .pf-vacio { text-align: center; padding: 36px 10px; color: var(--tinta-2); }
        .pf-vacio strong { display: block; color: var(--tinta); font-size: 16px; margin-bottom: 4px; }

        /* Formularios */
        .pf-panel h2 { font-family: var(--fuente-titulo); font-size: 19px; }
        .pf-nota { font-size: 14px; color: var(--tinta-2); margin: 4px 0 18px; }
        .pf-form { display: grid; gap: 14px; }
        .pf-fila { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .pf-campo label { display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px; }
        .pf-campo input { width: 100%; padding: 12px 14px; font: inherit; font-size: 14.5px; background: var(--papel); border: 1.5px solid var(--linea); border-radius: 11px; transition: border-color .2s, box-shadow .2s, background .2s; }
        .pf-campo input:focus { outline: none; border-color: var(--anil); background: #fff; box-shadow: 0 0 0 4px rgba(29,42,74,.1); }
        .pf-form .pf-btn { justify-self: start; }

        @media (max-width: 560px) {
            .pf-fila { grid-template-columns: 1fr; }
            .pf-stat { min-width: 0; padding: 6px 10px; flex: 1; }
            .pf-tab span { display: none; }
            .pf-cabecera { padding: 0 16px 20px; }
            .pf-panel { padding: 16px; }
        }
        @media (prefers-reduced-motion: reduce) { .pf * { transition: none !important; } }
    </style>
</head>
<body>
<div class="pf">
<div class="pf-wrap">

    <header class="pf-top">
        <a href="dashboard.php" class="pf-volver">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Panel
        </a>
        <span class="pf-top-titulo"><?= h($handle) ?></span>
        <span style="width: 78px" aria-hidden="true"></span>
    </header>

    <section class="pf-card">
        <div class="pf-portada"></div>
        <div class="pf-cabecera">

            <!-- La foto se envía sola al elegir un archivo -->
            <form action="perfil.php" method="POST" enctype="multipart/form-data" id="form-foto">
                <input type="hidden" name="accion" value="actualizar_foto">
                <div class="pf-avatar-box">
                    <div class="pf-avatar">
                        <?php if ($foto): ?>
                            <img src="<?= h($foto) ?>" alt="Foto de perfil de <?= h($nombre_completo) ?>">
                        <?php else: ?>
                            <?= h($iniciales) ?>
                        <?php endif; ?>
                    </div>
                    <label class="pf-cam" title="Cambiar foto">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
                        <input type="file" name="foto" id="input-foto" accept="image/jpeg,image/png,image/webp" aria-label="Cambiar foto de perfil">
                    </label>
                </div>
            </form>

            <h1 class="pf-nombre"><?= h($nombre_completo) ?></h1>
            <p class="pf-handle"><?= h($handle) ?> · <?= h($correo_actual) ?></p>
            <span class="pf-rol"><?= h($rol) ?></span>

            <div class="pf-stats" aria-label="Resumen de actividad">
                <div class="pf-stat"><b><?= $n_cot ?></b><span>Cotizaciones</span></div>
                <div class="pf-stat"><b><?= $n_ped ?></b><span>Pedidos</span></div>
                <div class="pf-stat"><b><?= $n_rec ?></b><span>Recepciones</span></div>
            </div>

            <div class="pf-acciones">
                <button type="button" class="pf-btn pf-btn-primario" data-ir="editar">Editar perfil</button>
                <a href="logout.php" class="pf-btn pf-btn-suave">Cerrar sesión</a>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div class="pf-aviso pf-aviso-ok" role="status">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16 9.5"/></svg>
                    <span><?= h($mensaje) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="pf-aviso pf-aviso-error" role="alert">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                    <span><?= h($error) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pestañas -->
    <div class="pf-tabs" role="tablist">
        <button class="pf-tab" role="tab" id="t-actividad" aria-controls="p-actividad" data-tab="actividad" aria-selected="<?= $tab_activa === 'actividad' ? 'true' : 'false' ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>Actividad</span>
        </button>
        <button class="pf-tab" role="tab" id="t-editar" aria-controls="p-editar" data-tab="editar" aria-selected="<?= $tab_activa === 'editar' ? 'true' : 'false' ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
            <span>Editar perfil</span>
        </button>
        <button class="pf-tab" role="tab" id="t-seguridad" aria-controls="p-seguridad" data-tab="seguridad" aria-selected="<?= $tab_activa === 'seguridad' ? 'true' : 'false' ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <span>Seguridad</span>
        </button>
    </div>

    <!-- Actividad -->
    <section class="pf-panel" id="p-actividad" role="tabpanel" aria-labelledby="t-actividad" <?= $tab_activa !== 'actividad' ? 'hidden' : '' ?>>
        <?php if (count($recientes) > 0): ?>
            <div class="pf-grid">
                <?php foreach ($recientes as $r): ?>
                    <article class="pf-item <?= color_estado($r['estado']) ?>">
                        <div>
                            <span class="pf-item-tipo"><?= h($r['tipo']) ?></span>
                            <div class="pf-item-num" style="margin-top:6px">#<?= h($r['id']) ?></div>
                            <div class="pf-item-cli"><?= h($r['nombre'] . ' ' . $r['apellido']) ?></div>
                        </div>
                        <div class="pf-item-pie">
                            <span><?= h(fecha_corta($r['fecha'])) ?></span>
                            <span class="pf-item-estado"><?= h($r['estado']) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pf-vacio">
                <strong>Aún no tienes actividad</strong>
                Crea tu primera cotización y aparecerá aquí.
                <div style="margin-top:14px"><a href="cotizaciones.php" class="pf-btn pf-btn-primario">Nueva cotización</a></div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Editar perfil -->
    <section class="pf-panel" id="p-editar" role="tabpanel" aria-labelledby="t-editar" <?= $tab_activa !== 'editar' ? 'hidden' : '' ?>>
        <h2>Editar perfil</h2>
        <p class="pf-nota">Actualiza tu nombre y tu correo. Para cambiar la foto, toca el icono de cámara sobre tu avatar.</p>
        <form action="perfil.php" method="POST" class="pf-form">
            <input type="hidden" name="accion" value="actualizar_datos">
            <div class="pf-fila">
                <div class="pf-campo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="<?= h($usuario['nombre'] ?? '') ?>" required>
                </div>
                <div class="pf-campo">
                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" value="<?= h($usuario['apellido'] ?? '') ?>" required>
                </div>
            </div>
            <div class="pf-campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" value="<?= h($usuario['correo'] ?? '') ?>" required>
            </div>
            <button type="submit" class="pf-btn pf-btn-primario">Guardar cambios</button>
        </form>
    </section>

    <!-- Seguridad -->
    <section class="pf-panel" id="p-seguridad" role="tabpanel" aria-labelledby="t-seguridad" <?= $tab_activa !== 'seguridad' ? 'hidden' : '' ?>>
        <h2>Contraseña</h2>
        <p class="pf-nota">Ingresa tu contraseña actual y elige una nueva de al menos 8 caracteres.</p>
        <form action="perfil.php" method="POST" class="pf-form">
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="pf-campo">
                <label for="pass_actual">Contraseña actual</label>
                <input type="password" id="pass_actual" name="pass_actual" required autocomplete="current-password">
            </div>
            <div class="pf-fila">
                <div class="pf-campo">
                    <label for="pass_nueva">Nueva contraseña</label>
                    <input type="password" id="pass_nueva" name="pass_nueva" required minlength="8" autocomplete="new-password">
                </div>
                <div class="pf-campo">
                    <label for="pass_confirmar">Confirmar nueva contraseña</label>
                    <input type="password" id="pass_confirmar" name="pass_confirmar" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="pf-btn pf-btn-verde">Actualizar contraseña</button>
        </form>
    </section>

</div>
</div>

<script>
    (function () {
        var tabs = [].slice.call(document.querySelectorAll('.pf-tab'));
        var paneles = [].slice.call(document.querySelectorAll('.pf-panel'));

        function mostrar(nombre) {
            tabs.forEach(function (t) { t.setAttribute('aria-selected', t.dataset.tab === nombre ? 'true' : 'false'); });
            paneles.forEach(function (p) { p.hidden = p.id !== 'p-' + nombre; });
        }
        tabs.forEach(function (t) { t.addEventListener('click', function () { mostrar(t.dataset.tab); }); });
        [].forEach.call(document.querySelectorAll('[data-ir]'), function (b) {
            b.addEventListener('click', function () {
                mostrar(b.dataset.ir);
                document.querySelector('.pf-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Enviar la foto automáticamente al elegirla
        var input = document.getElementById('input-foto');
        input.addEventListener('change', function () {
            if (input.files && input.files.length) { document.getElementById('form-foto').submit(); }
        });
    })();
</script>
</body>
</html>
