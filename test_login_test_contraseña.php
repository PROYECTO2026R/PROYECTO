<?php
require_once 'conexion/conexion.php';

$correo = 'admin@artesanias.com';
$password_prueba = 'Admin123*';

$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = :correo");
$stmt->execute([':correo' => $correo]);
$usuario = $stmt->fetch();

if ($usuario) {
    echo "Usuario encontrado en la BD.<br>";
    echo "Hash en la BD: " . $usuario['password'] . "<br>";
    
    if (password_verify($password_prueba, $usuario['password'])) {
        echo "<h2 style='color:green;'>¡ÉXITO! La contraseña coincide perfectamente con el hash.</h2>";
    } else {
        echo "<h2 style='color:red;'>FALLÓ: El hash de la base de datos NO coincide con la contraseña 'Admin123*'.</h2>";
        
        // Generemos un nuevo hash correcto en vivo para que veas cómo debería quedar
        $nuevo_hash = password_hash($password_prueba, PASSWORD_DEFAULT);
        echo "Copia y pega este comando en tu MySQL Workbench para arreglarlo:<br>";
        echo "<code>UPDATE usuarios SET password = '" . $nuevo_hash . "' WHERE correo = '" . $correo . "';</code>";
    }
} else {
    echo "<h2 style='color:red;'>El usuario con correo $correo no existe en la base de datos.</h2>";
}
?>