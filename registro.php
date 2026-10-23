<?php
session_start();
require_once 'conexion/conexion.php';

// Si ya hay sesión, ir directo al panel
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$nombre = '';
$apellido = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($nombre === '' || $apellido === '' || $correo === '' || $password === '' || $confirmar === '') {
        $error = "Completa todos los campos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Escribe un correo electrónico válido.";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            // ¿El correo ya existe?
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE correo = :correo");
            $stmt->execute([':correo' => $correo]);

            if ($stmt->fetch()) {
                $error = "Ya existe una cuenta con ese correo.";
            } else {
                // Rol por defecto: uno no administrador si existe; si no, el de mayor id
                $rol = $conexion->query(
                    "SELECT id_rol FROM roles
                     WHERE LOWER(nombre) IN ('usuario','cliente','vendedor','empleado')
                     ORDER BY id_rol LIMIT 1"
                )->fetch();
                if (!$rol) {
                    $rol = $conexion->query("SELECT id_rol FROM roles ORDER BY id_rol DESC LIMIT 1")->fetch();
                }

                if (!$rol) {
                    $error = "No hay roles configurados en el sistema. Contacta al administrador.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $conexion->prepare(
                        "INSERT INTO usuarios (nombre, apellido, correo, password, id_rol)
                         VALUES (:nombre, :apellido, :correo, :password, :id_rol)"
                    );
                    $ins->execute([
                        ':nombre'   => $nombre,
                        ':apellido' => $apellido,
                        ':correo'   => $correo,
                        ':password' => $hash,
                        ':id_rol'   => $rol['id_rol'],
                    ]);

                    header("Location: login.php?registrado=1");
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log('Registro: ' . $e->getMessage());
            $error = "No pudimos crear tu cuenta por un problema del sistema. Inténtalo de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta - Artesanías Manuales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --anil: #1d2a4a; --anil-suave: #2c3c63; --ocre: #d9a23b; --ocre-bg: #fbf0d9;
            --verde: #2c7a68; --rojo: #b5402f; --rojo-bg: #f8e1dc; --papel: #eef0f4;
            --superficie: #ffffff; --tinta: #161b2b; --tinta-2: #565d72; --linea: #cfd5e1;
            --fuente-titulo: "Bricolage Grotesque", "Segoe UI", system-ui, sans-serif;
            --fuente-texto: "Public Sans", "Segoe UI", system-ui, sans-serif;
        }
        .acceso, .acceso *, .acceso *::before, .acceso *::after { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: var(--papel); }
        .acceso {
            min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
            font-family: var(--fuente-texto); color: var(--tinta); line-height: 1.5;
        }
        .acceso h1, .acceso h2, .acceso p { margin: 0; }
        .acceso svg { width: 20px; height: 20px; flex: none; }
        .acceso :focus-visible { outline: 3px solid var(--ocre); outline-offset: 3px; border-radius: 8px; }

        .marca-lado {
            position: relative; overflow: hidden; isolation: isolate; background: var(--anil); color: #fff;
            padding: 40px 48px 56px; display: flex; flex-direction: column; justify-content: space-between; gap: 48px;
        }
        .marca-lado::before {
            content: ""; position: absolute; inset: 0; z-index: -1;
            background:
                linear-gradient(135deg, var(--anil-suave) 25%, transparent 25%) -14px 0 / 28px 28px,
                linear-gradient(225deg, var(--anil-suave) 25%, transparent 25%) -14px 0 / 28px 28px,
                linear-gradient(315deg, var(--anil-suave) 25%, transparent 25%) 0 0 / 28px 28px,
                linear-gradient(45deg,  var(--anil-suave) 25%, transparent 25%) 0 0 / 28px 28px;
            background-color: var(--anil);
            -webkit-mask-image: linear-gradient(200deg, #000 0%, transparent 65%);
                    mask-image: linear-gradient(200deg, #000 0%, transparent 65%);
        }
        .marca-lado::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 8px; z-index: -1;
            background: repeating-linear-gradient(90deg, var(--ocre) 0 28px, var(--rojo) 28px 56px, var(--verde) 56px 84px);
        }
        .marca { display: flex; align-items: center; gap: 10px; font-family: var(--fuente-titulo); font-weight: 700; font-size: 18px; }
        .marca-logo { width: 36px; height: 36px; border-radius: 10px; background: var(--ocre); color: var(--anil); display: grid; place-items: center; }
        .marca-logo svg { width: 19px; height: 19px; }
        .marca-texto h1 { font-family: var(--fuente-titulo); font-weight: 700; letter-spacing: -.02em; font-size: clamp(30px, 3.8vw, 46px); line-height: 1.08; max-width: 14ch; }
        .marca-texto p { margin-top: 14px; color: #d3daec; max-width: 40ch; }

        .form-lado { display: grid; place-items: center; padding: 40px 24px; }
        .tarjeta { width: 100%; max-width: 420px; }
        .tarjeta h2 { font-family: var(--fuente-titulo); font-size: 30px; font-weight: 700; letter-spacing: -.02em; }
        .tarjeta .sub { color: var(--tinta-2); margin-top: 6px; }

        .alerta {
            display: flex; gap: 10px; align-items: flex-start; margin-top: 22px; padding: 12px 14px; border-radius: 10px;
            background: var(--rojo-bg); color: #7d2a1e; border: 1px solid #ecb8ae; font-size: 14px;
        }
        .alerta svg { margin-top: 1px; color: var(--rojo); }

        form { margin-top: 26px; display: grid; gap: 16px; }
        .fila-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .campo label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 7px; }
        .campo-caja { position: relative; }
        .campo input {
            width: 100%; padding: 13px 14px; font: inherit; font-size: 15px; color: var(--tinta);
            background: var(--superficie); border: 1.5px solid var(--linea); border-radius: 11px;
            transition: border-color .2s, box-shadow .2s;
        }
        .campo input::placeholder { color: #9aa1b4; }
        .campo input:hover { border-color: #aeb6c9; }
        .campo input:focus { outline: none; border-color: var(--anil); box-shadow: 0 0 0 4px rgba(29, 42, 74, .12); }
        .campo input.con-boton { padding-right: 88px; }
        .ayuda { font-size: 12.5px; color: var(--tinta-2); margin-top: 6px; }

        .ver {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            border: 0; background: transparent; cursor: pointer; font: inherit; font-size: 13px; font-weight: 600;
            color: var(--anil); padding: 7px 10px; border-radius: 8px;
        }
        .ver:hover { background: var(--papel); }

        .btn-ingresar {
            margin-top: 4px; width: 100%; padding: 14px; border: 0; cursor: pointer;
            background: var(--anil); color: #fff; font: inherit; font-size: 16px; font-weight: 600;
            border-radius: 11px; transition: background .2s, transform .2s;
        }
        .btn-ingresar:hover { background: var(--anil-suave); }
        .btn-ingresar:active { transform: translateY(1px); }

        .pie { margin-top: 24px; font-size: 14px; color: var(--tinta-2); text-align: center; }
        .pie a { color: var(--anil); font-weight: 600; text-decoration: none; }
        .pie a:hover { text-decoration: underline; }

        @media (max-width: 820px) {
            .acceso { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
            .marca-lado { padding: 24px 24px 32px; gap: 22px; }
            .marca-texto h1 { font-size: 28px; max-width: none; }
            .marca-texto p { display: none; }
            .form-lado { place-items: start center; padding-top: 32px; }
        }
        @media (max-width: 480px) { .fila-2 { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { .acceso * { transition: none !important; } }
    </style>
</head>
<body>
<div class="acceso">

    <aside class="marca-lado">
        <div class="marca">
            <span class="marca-logo">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
            </span>
            Artesanías Manuales
        </div>
        <div class="marca-texto">
            <h1>Crea tu cuenta</h1>
            <p>Únete para gestionar productos, clientes, cotizaciones y pedidos desde un solo lugar.</p>
        </div>
    </aside>

    <main class="form-lado">
        <div class="tarjeta">
            <h2>Registrarse</h2>
            <p class="sub">Completa tus datos para empezar.</p>

            <?php if (!empty($error)): ?>
                <div class="alerta" role="alert">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="registro.php" method="POST" novalidate>
                <div class="fila-2">
                    <div class="campo">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required autocomplete="given-name"
                               placeholder="Ana" value="<?= htmlspecialchars($nombre) ?>" autofocus>
                    </div>
                    <div class="campo">
                        <label for="apellido">Apellido</label>
                        <input type="text" id="apellido" name="apellido" required autocomplete="family-name"
                               placeholder="Pérez" value="<?= htmlspecialchars($apellido) ?>">
                    </div>
                </div>

                <div class="campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" required autocomplete="email"
                           placeholder="ejemplo@gmail.com" value="<?= htmlspecialchars($correo) ?>">
                </div>

                <div class="campo">
                    <label for="password">Contraseña</label>
                    <div class="campo-caja">
                        <input type="password" id="password" name="password" required minlength="8"
                               autocomplete="new-password" class="con-boton" placeholder="Mínimo 8 caracteres">
                        <button type="button" class="ver" id="ver" aria-pressed="false" aria-controls="password">Mostrar</button>
                    </div>
                </div>

                <div class="campo">
                    <label for="confirmar">Confirmar contraseña</label>
                    <input type="password" id="confirmar" name="confirmar" required minlength="8"
                           autocomplete="new-password" placeholder="Repite tu contraseña">
                    <p class="ayuda" id="ayuda-confirmar" aria-live="polite"></p>
                </div>

                <button type="submit" class="btn-ingresar">Crear cuenta</button>
            </form>

            <p class="pie">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
        </div>
    </main>

</div>

<script>
    (function () {
        var btn = document.getElementById('ver');
        var pass = document.getElementById('password');
        var conf = document.getElementById('confirmar');
        var ayuda = document.getElementById('ayuda-confirmar');

        btn.addEventListener('click', function () {
            var visible = pass.type === 'text';
            pass.type = visible ? 'password' : 'text';
            conf.type = visible ? 'password' : 'text';
            btn.textContent = visible ? 'Mostrar' : 'Ocultar';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
        });

        function revisar() {
            if (!conf.value) { ayuda.textContent = ''; return; }
            var ok = pass.value === conf.value;
            ayuda.textContent = ok ? 'Las contraseñas coinciden.' : 'Las contraseñas no coinciden.';
            ayuda.style.color = ok ? '#2c7a68' : '#b5402f';
        }
        pass.addEventListener('input', revisar);
        conf.addEventListener('input', revisar);
    })();
</script>
</body>
</html>
