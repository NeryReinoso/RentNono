<?php
session_start();
include("conexion.php");

header('Content-Type: application/json');

if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'visitante') {
    echo json_encode(['error' => 'Debe estar logueado como visitante']);
    exit;
}

$usuario_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id_publicacion'])) {
        echo json_encode(['error' => 'ID de publicación requerido']);
        exit;
    }
    
    $publicacion_id = intval($_POST['id_publicacion']);
    
    // Verificar si ya está en favoritos
    $check = $conn->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND propiedad_id = ?");
    $check->execute([$usuario_id, $publicacion_id]);
    
    if ($check->rowCount() > 0) {
        // Eliminar de favoritos
        $delete = $conn->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND propiedad_id = ?");
        $delete->execute([$usuario_id, $publicacion_id]);
        echo json_encode(['accion' => 'eliminado', 'success' => true]);
    } else {
        // Agregar a favoritos
        $insert = $conn->prepare("INSERT INTO favoritos (usuario_id, propiedad_id, fecha_agregado) VALUES (?, ?, NOW())");
        $insert->execute([$usuario_id, $publicacion_id]);
        echo json_encode(['accion' => 'agregado', 'success' => true]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Obtener favoritos del usuario
    $query = $conn->prepare("
        SELECT p.*, f.fecha_agregado 
        FROM favoritos f 
        JOIN propiedades p ON f.propiedad_id = p.id 
        WHERE f.usuario_id = ? 
        ORDER BY f.fecha_agregado DESC
    ");
    $query->execute([$usuario_id]);
    $favoritos = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($favoritos);
}
?>