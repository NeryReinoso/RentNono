<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["id_usuario"])) {
    echo "NO_LOGIN";
    exit;
}

$id_usuario = $_SESSION["id_usuario"];
$id_publicacion = $_POST["id_publicacion"];

// Evitar duplicados
$sql = $conn->prepare("INSERT IGNORE INTO favoritos (id_usuario, id_publicacion) VALUES (?, ?)");
$sql->execute([$id_usuario, $id_publicacion]);

echo "OK";
?>
