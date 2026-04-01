<?php
// Configuración de conexión a la base de datos
$host = 'localhost';
$dbname = 'rentnono'; // Cambia esto por el nombre de tu base de datos
$username = 'root';   // Usuario de MySQL (por defecto root en XAMPP)
$password = '';       // Contraseña de MySQL (vacía por defecto en XAMPP)

try {
    // Crear conexión PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configurar PDO para que lance excepciones en errores
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar para que devuelva arrays asociativos por defecto
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    //echo "✅ Conexión a la base de datos exitosa";
    
} catch(PDOException $e) {
    // Si hay error en la conexión
    die("❌ Error de conexión a la base de datos: " . $e->getMessage());
}

// También puedes configurar la zona horaria si es necesario
date_default_timezone_set('America/Argentina/Buenos_Aires');
?>