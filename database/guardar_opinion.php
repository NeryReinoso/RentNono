<?php
session_start();
require_once "conexion.php";

// Verificar que lleguen todos los datos necesarios
if (!isset($_POST["rating"], $_POST["comentario"], $_POST["propiedad_id"])) {
    die("Faltan datos para guardar la reseña");
}

$rating = intval($_POST["rating"]);
$comentario = trim($_POST["comentario"]);
$propiedad_id = intval($_POST["propiedad_id"]);
$usuario_id = $_SESSION["id"] ?? null;

if (!$usuario_id) {
    die("Debes iniciar sesión para dejar una reseña.");
}

if (empty($rating) || empty($comentario)) {
    header("Location: ../detalle_publicaciones.php?id=" . $propiedad_id . "&resena=error&motivo=campos_vacios");
    exit();
}

try {
    // Cambiamos 'pendiente' por 'aprobado' para que se muestre inmediatamente
    $stmt = $conn->prepare("
        INSERT INTO opiniones (propiedad_id, usuario_id, rating, comentario, estado, fecha)
        VALUES (:pid, :uid, :rating, :comentario, 'aprobado', NOW())
    ");

    $stmt->bindParam(":pid", $propiedad_id);
    $stmt->bindParam(":uid", $usuario_id);
    $stmt->bindParam(":rating", $rating);
    $stmt->bindParam(":comentario", $comentario);

    if ($stmt->execute()) {
        // Redirigir con mensaje de éxito
        header("Location: ../detalle_publicaciones.php?id=" . $propiedad_id . "&resena=ok");
        exit();
    } else {
        header("Location: ../detalle_publicaciones.php?id=" . $propiedad_id . "&resena=error");
        exit();
    }
} catch (PDOException $e) {
    // Si hay error, redirigir con mensaje de error
    header("Location: ../detalle_publicaciones.php?id=" . $propiedad_id . "&resena=error");
    exit();
}
?>