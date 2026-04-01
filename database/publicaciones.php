<?php
include("conexion.php");
include("session.php");

// Si es una petición AJAX para cargar publicaciones filtradas
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $condiciones = [];
    $params = [];
    
    // Filtros desde explorador.php
    if (isset($_GET['operacion']) && $_GET['operacion'] != '') {
        $condiciones[] = "operacion = ?";
        $params[] = $_GET['operacion'];
    }
    
    if (isset($_GET['tipo']) && $_GET['tipo'] != '') {
        $condiciones[] = "tipo = ?";
        $params[] = $_GET['tipo'];
    }
    
    if (isset($_GET['estado']) && $_GET['estado'] != '') {
        $condiciones[] = "estado = ?";
        $params[] = $_GET['estado'];
    }
    
    if (isset($_GET['garaje']) && $_GET['garaje'] != '') {
        $condiciones[] = "garaje = ?";
        $params[] = $_GET['garaje'];
    }
    
    if (isset($_GET['precio_max']) && $_GET['precio_max'] != '') {
        $condiciones[] = "precio <= ?";
        $params[] = $_GET['precio_max'];
    }
    
    if (isset($_GET['precio_min']) && $_GET['precio_min'] != '') {
        $condiciones[] = "precio >= ?";
        $params[] = $_GET['precio_min'];
    }
    
    if (isset($_GET['ambientes']) && $_GET['ambientes'] != '') {
        $condiciones[] = "ambientes = ?";
        $params[] = $_GET['ambientes'];
    }
    
    if (isset($_GET['dormitorios']) && $_GET['dormitorios'] != '') {
        $condiciones[] = "dormitorios = ?";
        $params[] = $_GET['dormitorios'];
    }
    
    if (isset($_GET['sanitarios']) && $_GET['sanitarios'] != '') {
        $condiciones[] = "sanitarios = ?";
        $params[] = $_GET['sanitarios'];
    }
    
    // Construir la consulta
    $sql = "SELECT * FROM propiedades";
    if (!empty($condiciones)) {
        $sql .= " WHERE " . implode(" AND ", $condiciones);
    }
    $sql .= " ORDER BY fecha_publicacion DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar sesión del usuario
    $usuario_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
    $rol = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;
    
    if (count($publicaciones) > 0) {
        foreach ($publicaciones as $pub) {
            // Verificar si esta publicación está en favoritos del usuario
            $esFavorito = false;
            if ($usuario_id && $rol == 'visitante') {
                $checkFav = $conn->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND propiedad_id = ?");
                $checkFav->execute([$usuario_id, $pub['id']]);
                $esFavorito = $checkFav->rowCount() > 0;
            }
            
            // Contador de favoritos para esta publicación
            $countFav = $conn->prepare("SELECT COUNT(*) as total FROM favoritos WHERE propiedad_id = ?");
            $countFav->execute([$pub['id']]);
            $totalFav = $countFav->fetch(PDO::FETCH_ASSOC)['total'];
            
            // HTML con clase fav-btn (igual que en ixusuario)
            echo '<div class="feature-item pub-card">';
            if ($totalFav > 0) {
                echo '<span class="fav-count"><i class="fas fa-heart"></i> ' . $totalFav . '</span>';
            }
            echo '<button class="fav-btn ' . ($esFavorito ? 'active' : '') . '" data-id="' . $pub['id'] . '">';
            echo '<i class="fa-' . ($esFavorito ? 'solid' : 'regular') . ' fa-heart"></i>';
            echo '</button>';
            echo '<a href="/Rentnono/detalle_publicaciones.php?id=' . $pub['id'] . '" class="publicacion-link">';
            echo '<img src="/RentNono/media/publicaciones/' . htmlspecialchars($pub['imagen']) . '" alt="' . htmlspecialchars($pub['titulo']) . '">';
            echo '<h4>' . htmlspecialchars($pub['titulo']) . '</h4>';
            echo '<p>' . htmlspecialchars(substr($pub['descripcion'], 0, 100)) . '...</p>';
            echo '<p><strong>Precio:</strong> $' . number_format($pub['precio'], 2) . '</p>';
            echo '</a>';
            echo '</div>';
        }
    } else {
        echo '<p class="no-results">No se encontraron propiedades con los filtros seleccionados.</p>';
    }
    exit;
}

// Para uso en index.php normal (sin AJAX)
$sql = "SELECT * FROM propiedades ORDER BY fecha_publicacion DESC LIMIT 6";
$stmt = $conn->query($sql);
$publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>