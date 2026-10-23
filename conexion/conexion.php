<?php
/**
 * Archivo de conexión a la base de datos utilizando PDO
 * Proyecto ADSO - Vendedor de Artesanías Manuales
 */

$host = 'localhost';
$dbname = 'artesanias_proyecto';
$username = 'root';
$password = ''; // Por defecto en XAMPP la contraseña de root viene vacía

try {
    // Creamos la conexión PDO con codificación UTF-8
    $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configuramos el modo de error de PDO para que lance excepciones en caso de fallos
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configuración opcional para manejo por defecto de los resultados como arreglos asociativos
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Nota: Descomenta la siguiente línea solo si quieres verificar visualmente que conectó con éxito
    //echo "¡Conexión exitosa a la base de datos!";

} catch (PDOException $e) {
    // Si hay un error en la conexión, detenemos la ejecución y mostramos un mensaje controlado
    // (En producción no se recomienda mostrar el error técnico exacto por seguridad)
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>