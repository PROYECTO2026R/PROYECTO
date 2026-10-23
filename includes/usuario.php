<?php
/*
 * includes/usuario.php
 * Datos del usuario en sesión para la cabecera: foto de perfil o iniciales.
 *
 * Uso en cualquier página (después de session_start(), de cargar la conexión
 * y de validar que el usuario inició sesión):
 *
 *     require_once 'includes/usuario.php';
 *     ...
 *     <?= avatar_usuario() ?>      <- dentro de la cabecera
 */

$nombre_usuario = $_SESSION['nombre'] ?? '';

// Foto actualizada desde la base de datos (puede cambiar desde perfil.php)
$foto_perfil = null;
try {
    $stmt_foto = $conexion->prepare("SELECT foto FROM usuarios WHERE id_usuario = :id");
    $stmt_foto->execute([':id' => $_SESSION['id_usuario']]);
    $fila_foto = $stmt_foto->fetch();
    $foto_perfil = $fila_foto['foto'] ?? null;
} catch (PDOException $e) {
    error_log('Foto de perfil: ' . $e->getMessage());
}

// Iniciales por si el usuario no tiene foto (o el archivo ya no existe)
$iniciales_usuario = '';
foreach (array_slice(preg_split('/\s+/', trim($nombre_usuario)), 0, 2) as $parte) {
    $iniciales_usuario .= mb_strtoupper(mb_substr($parte, 0, 1));
}

/**
 * Devuelve el avatar del usuario como enlace a su perfil.
 * Muestra la foto si existe; si no, las iniciales.
 */
function avatar_usuario() {
    global $foto_perfil, $iniciales_usuario;

    $contenido = (!empty($foto_perfil) && file_exists($foto_perfil))
        ? '<img src="' . htmlspecialchars($foto_perfil, ENT_QUOTES, 'UTF-8') . '" alt="">'
        : htmlspecialchars($iniciales_usuario, ENT_QUOTES, 'UTF-8');

    return '<a href="perfil.php" class="avatar" title="Mi perfil" aria-label="Mi perfil">' . $contenido . '</a>';
}