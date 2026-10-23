<?php
session_start();
require_once 'conexion/conexion.php';

// Si ya hay sesión, ir directo al panel
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$exito = isset($_GET['registrado']) ? "¡Cuenta creada! Ya puedes iniciar sesión." : '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? ''; // sin trim: igual que al registrarse

    if (!empty($correo) && !empty($password)) {
        try {
            // Consulta preparada para buscar al usuario por su correo junto con su rol
            $sql = "SELECT u.*, r.nombre AS rol_nombre FROM usuarios u 
                    INNER JOIN roles r ON u.id_rol = r.id_rol 
                    WHERE u.correo = :correo";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':correo' => $correo]);
            $usuario = $stmt->fetch();

            // Verificamos si el usuario existe y si la contraseña coincide de forma segura con el hash
            if ($usuario && password_verify($password, $usuario['password'])) {
                // Autenticación exitosa - regeneramos ID de sesión por seguridad
                session_regenerate_id(true);
                
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                $_SESSION['correo'] = $usuario['correo'];
                $_SESSION['rol'] = $usuario['rol_nombre'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Correo o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            // No mostramos detalles técnicos al usuario; se registra en el log del servidor
            error_log('Login: ' . $e->getMessage());
            $error = "No pudimos iniciar sesión por un problema del sistema. Inténtalo de nuevo en unos minutos.";
        }
    } else {
        $error = "Completa el correo y la contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Artesanías Manuales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --anil:        #1d2a4a;
            --anil-suave:  #2c3c63;
            --ocre:        #d9a23b;
            --ocre-bg:     #fbf0d9;
            --verde:       #2c7a68;
            --rojo:        #b5402f;
            --rojo-bg:     #f8e1dc;
            --papel:       #eef0f4;
            --superficie:  #ffffff;
            --tinta:       #161b2b;
            --tinta-2:     #565d72;
            --linea:       #cfd5e1;
            --fuente-titulo: "Bricolage Grotesque", "Segoe UI", system-ui, sans-serif;
            --fuente-texto:  "Public Sans", "Segoe UI", system-ui, sans-serif;
        }

        .acceso, .acceso *, .acceso *::before, .acceso *::after { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: var(--papel); }

        .acceso {
            min-height: 100vh;
            display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
            font-family: var(--fuente-texto); color: var(--tinta); line-height: 1.5;
        }
        .acceso h1, .acceso h2, .acceso p { margin: 0; }
        .acceso svg { width: 20px; height: 20px; flex: none; }
        .acceso :focus-visible { outline: 3px solid var(--ocre); outline-offset: 3px; border-radius: 8px; }

        /* ---------- Lado de marca (tejido en zigzag, igual que el panel) ---------- */
        .marca-lado {
            position: relative; overflow: hidden; isolation: isolate;
            background: var(--anil); color: #fff;
            padding: 40px 48px 56px;
            display: flex; flex-direction: column; justify-content: space-between; gap: 48px;
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
        .marca { display: flex; align-items: center; gap: 10px; font-family: var(--fuente-titulo); font-weight: 700; font-size: 18px; letter-spacing: -.01em; }
        .marca-logo { width: 36px; height: 36px; border-radius: 10px; background: var(--ocre); color: var(--anil); display: grid; place-items: center; }
        .marca-logo svg { width: 19px; height: 19px; }

        .marca-texto h1 {
            font-family: var(--fuente-titulo); font-weight: 700; letter-spacing: -.02em;
            font-size: clamp(30px, 3.8vw, 46px); line-height: 1.08; max-width: 14ch;
        }
        .marca-texto p { margin-top: 14px; color: #d3daec; max-width: 40ch; }

        /* ---------- Formulario ---------- */
        .form-lado { display: grid; place-items: center; padding: 40px 24px; }
        .tarjeta { width: 100%; max-width: 400px; }
        .tarjeta h2 { font-family: var(--fuente-titulo); font-size: 30px; font-weight: 700; letter-spacing: -.02em; }
        .tarjeta .sub { color: var(--tinta-2); margin-top: 6px; }

        .alerta {
            display: flex; gap: 10px; align-items: flex-start;
            margin-top: 22px; padding: 12px 14px; border-radius: 10px;
            background: var(--rojo-bg); color: #7d2a1e; border: 1px solid #ecb8ae; font-size: 14px;
        }
        .alerta svg { margin-top: 1px; color: var(--rojo); }
        .alerta-ok { background: #dff0ea; color: #114337; border-color: #b2d8cd; }
        .alerta-ok svg { color: var(--verde); }

        form { margin-top: 26px; display: grid; gap: 18px; }
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

        .pie { margin-top: 28px; font-size: 14px; color: var(--tinta-2); text-align: center; }
        .pie a { color: var(--anil); font-weight: 600; text-decoration: none; }
        .pie a:hover { text-decoration: underline; }
        .pie small { display: block; margin-top: 10px; font-size: 13px; }

        /* ---------- Responsive ---------- */
        @media (max-width: 820px) {
            .acceso { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
            .marca-lado { padding: 24px 24px 32px; gap: 22px; }
            .marca-texto h1 { font-size: 28px; max-width: none; }
            .marca-texto p { display: none; }
            .form-lado { place-items: start center; padding-top: 32px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .acceso * { transition: none !important; }
        }
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
            <h1>Panel de administración</h1>
            <p>Gestiona tus productos, clientes, cotizaciones y pedidos desde un solo lugar.</p>
        </div>
    </aside>

    <main class="form-lado">
        <div class="tarjeta">
            <h2>Iniciar sesión</h2>
            <p class="sub">Ingresa con tu correo para continuar.</p>

            <?php if (!empty($exito)): ?>
                <div class="alerta alerta-ok" role="status">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16 9.5"/></svg>
                    <span><?= htmlspecialchars($exito) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alerta" role="alert">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="campo">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" required autocomplete="email"
                           placeholder="ejemplo@gmail.com" value="<?= htmlspecialchars($correo) ?>"
                           <?= empty($correo) ? 'autofocus' : '' ?>>
                </div>

                <div class="campo">
                    <label for="password">Contraseña</label>
                    <div class="campo-caja">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               class="con-boton" placeholder="Tu contraseña" <?= !empty($correo) ? 'autofocus' : '' ?>>
                        <button type="button" class="ver" id="ver" aria-pressed="false" aria-controls="password">Mostrar</button>
                    </div>
                </div>

                <button type="submit" class="btn-ingresar">Ingresar</button>
            </form>

            <p class="pie">¿No tienes cuenta? <a href="registro.php">Regístrate</a>
                <small>Si olvidaste tu acceso, pídele al administrador que lo restablezca.</small>
            </p>
        </div>
    </main>

</div>

<script>
    (function () {
        var btn = document.getElementById('ver');
        var input = document.getElementById('password');
        btn.addEventListener('click', function () {
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.textContent = visible ? 'Mostrar' : 'Ocultar';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
        });
    })();
</script>
</body>
</html>