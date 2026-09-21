<?php
// Credenciales por defecto de XAMPP
$servidor = "localhost";
$usuario = "root";
$contrasena = ""; // XAMPP por defecto no tiene contraseña
$base_datos = "urban_style_db";

// Crear la conexión
$conexion = new mysqli($servidor, $usuario, $contrasena, $base_datos);

// Comprobar la conexión
if ($conexion->connect_error) {
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}
// Si todo va bien, no imprimimos nada para que no arruine el diseño
?>