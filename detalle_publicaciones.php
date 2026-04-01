<?php
require_once "database/session.php";
require_once "database/conexion.php";
include "login.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<h2 style="text-align:center; margin-top:50px;">ID de publicación no válido.</h2>');
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM propiedades WHERE id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$pub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pub) {
    die('<h2 style="text-align:center; margin-top:50px;">Publicación no encontrada.</h2>');
}

$stmtVisita = $conn->prepare("UPDATE propiedades SET visitas = visitas + 1 WHERE id = ?");
$stmtVisita->execute([$id]);

// Obtener múltiples imágenes
$stmtImagenes = $conn->prepare("SELECT imagen FROM propiedades_imagenes WHERE propiedad_id = ? ORDER BY orden ASC");
$stmtImagenes->execute([$id]);
$imagenes = $stmtImagenes->fetchAll(PDO::FETCH_COLUMN);

// Si hay imagen principal en la tabla propiedades y no hay imágenes en propiedades_imagenes
if (empty($imagenes) && !empty($pub['imagen'])) {
    $imagenes = ["media/publicaciones/" . $pub['imagen']];
}

// Si no hay imágenes, crear imágenes por defecto (grises)
if (empty($imagenes)) {
    $imagenes = [];
    for ($i = 0; $i < 5; $i++) {
        $imagenes[] = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23e0e0e0'/%3E%3Ctext x='50%25' y='50%25' font-family='Arial' font-size='18' fill='%23999' text-anchor='middle' dy='.3em'%3EImagen no disponible%3C/text%3E%3C/svg%3E";
    }
}

// Lista de avatares aleatorios para usuarios sin foto
$avatares = [
    'https://api.dicebear.com/7.x/avataaars/svg?seed=1',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=2',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=3',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=4',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=5',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=6',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=7',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=8',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=9',
    'https://api.dicebear.com/7.x/avataaars/svg?seed=10',
];

$resenas = [];

// Obtener las reseñas
$stmtRes = $conn->prepare("
    SELECT * FROM opiniones 
    WHERE propiedad_id = :id
    ORDER BY fecha DESC
");
$stmtRes->bindParam(':id', $id);
$stmtRes->execute();
$opiniones = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// Para cada reseña, buscar nombre y foto del usuario
foreach ($opiniones as $opinion) {
    $usuario_id = $opinion['usuario_id'];
    $nombre_usuario = "Usuario";
    $foto_usuario = $avatares[array_rand($avatares)]; // Avatar aleatorio por defecto
    
    // Buscar en usuario_visitante
    $stmt = $conn->prepare("SELECT nombre, foto FROM usuario_visitante WHERE id = ?");
    $stmt->execute([$usuario_id]);
    if ($row = $stmt->fetch()) {
        $nombre_usuario = $row['nombre'];
        if (!empty($row['foto'])) {
            $foto_usuario = $row['foto']; // Si tiene foto, usar esa
        }
    } else {
        // Buscar en usuario_propietario
        $stmt = $conn->prepare("SELECT nombre, foto FROM usuario_propietario WHERE id = ?");
        $stmt->execute([$usuario_id]);
        if ($row = $stmt->fetch()) {
            $nombre_usuario = $row['nombre'];
            if (!empty($row['foto'])) {
                $foto_usuario = $row['foto']; // Si tiene foto, usar esa
            }
        }
    }
    
    $opinion['usuario_nombre'] = $nombre_usuario;
    $opinion['usuario_foto'] = $foto_usuario;
    $resenas[] = $opinion;
}

// Obtener información del propietario
$stmtProp = $conn->prepare("SELECT up.* FROM usuario_propietario up 
                            INNER JOIN propiedades p ON p.id_usuario = up.id 
                            WHERE p.id = ?");
$stmtProp->execute([$id]);
$propietario = $stmtProp->fetch(PDO::FETCH_ASSOC);

// Promedio de calificaciones
$promedio = 0;
if (count($resenas) > 0) {
    $suma = array_sum(array_column($resenas, 'rating'));
    $promedio = round($suma / count($resenas), 1);
}

// Verificar si el usuario ya opinó
$yaOpino = false;
if (isset($_SESSION['id'])) {
    $checkOpinion = $conn->prepare("SELECT id FROM opiniones WHERE propiedad_id = ? AND usuario_id = ?");
    $checkOpinion->execute([$id, $_SESSION['id']]);
    $yaOpino = $checkOpinion->rowCount() > 0;
}

// Mensajes de feedback
$mensajeExito = isset($_GET['resena']) && $_GET['resena'] == 'ok';
$mensajeError = isset($_GET['resena']) && $_GET['resena'] == 'error';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pub['titulo']) ?> | RentNono</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #fff;
            color: #222;
        }
        
        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* HEADER SIMPLE */
        .simple-header {
            background-color: #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-volver-simple {
            background: none;
            border: none;
            color: #4CAF50;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .btn-volver-simple:hover {
            background-color: #f0f8f0;
        }

        .simple-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            color: #333;
            font-weight: 500;
        }

        .btn-cerrar-sesion {
            color: #ff4444;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .btn-cerrar-sesion:hover {
            background-color: #fff0f0;
        }

        .btn-iniciar-sesion-simple {
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
            font-size: 16px;
        }

        .btn-iniciar-sesion-simple:hover {
            background-color: #45a049;
        }

        /* GALERÍA - 1 GRANDE + 4 PEQUEÑAS */
        .gallery-container {
            margin-bottom: 32px;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(2, 200px);
            gap: 8px;
            height: 408px;
            border-radius: 12px;
            overflow: hidden;
        }

        .gallery-item {
            position: relative;
            cursor: pointer;
            overflow: hidden;
            background-color: #f5f5f5;
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .gallery-item:hover img {
            transform: scale(1.05);
        }

        .gallery-item.main-image {
            grid-column: span 2;
            grid-row: span 2;
            height: 408px;
        }

        .gallery-item.small-image {
            height: 200px;
        }

        .view-more {
            position: absolute;
            bottom: 16px;
            right: 16px;
            background: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 2;
        }

        .view-more:hover {
            background: #f7f7f7;
        }

        /* CONTENIDO PRINCIPAL */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 40px;
            margin-bottom: 48px;
        }

        .property-info h1 {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .property-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            color: #717171;
            font-size: 14px;
        }

        .rating-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #222;
            font-weight: 600;
        }

        .rating-badge i {
            color: #FFD700; /* CAMBIADO A AMARILLO */
        }

        /* SECCIÓN DEL ANFITRIÓN - ESTILO AIRBNB */
        .host-section {
            padding: 32px 0;
            border-top: 1px solid #ebebeb;
            border-bottom: 1px solid #ebebeb;
            margin-bottom: 24px;
        }

        .host-section h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #222;
        }

        .host-stats {
            display: flex;
            gap: 40px;
            margin-bottom: 24px;
        }

        .stat-item {
            text-align: left;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #222;
            display: block;
        }

        .stat-label {
            font-size: 14px;
            color: #717171;
        }

        .host-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .host-avatar-large {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
        }

        .host-name-title h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .superhost-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background-color: #f7f7f7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            color: #222;
        }

        .superhost-badge-large i {
            color: #4CAF50;
        }

        .host-description {
            margin-bottom: 20px;
            line-height: 1.5;
            color: #222;
            font-size: 15px;
        }

        .host-response {
            margin-bottom: 20px;
        }

        .response-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .response-item i {
            font-size: 20px;
            color: #4CAF50;
            width: 24px;
        }

        .response-item strong {
            font-size: 15px;
            font-weight: 600;
        }

        .response-item span {
            font-size: 14px;
            color: #717171;
        }

        .host-bio {
            margin-bottom: 20px;
            padding: 12px 0;
            border-top: 1px solid #ebebeb;
            border-bottom: 1px solid #ebebeb;
        }

        .host-bio p {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #222;
        }

        .host-bio i {
            color: #4CAF50;
            width: 24px;
        }

        .btn-contactar {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            margin-bottom: 16px;
        }

        .btn-contactar:hover {
            background-color: #45a049;
        }

        .payment-warning {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background-color: #f7f7f7;
            border-radius: 8px;
            font-size: 14px;
            color: #717171;
            line-height: 1.4;
        }

        .payment-warning i {
            font-size: 20px;
            color: #4CAF50;
        }

        /* SERVICIOS DESTACADOS */
        .amenities {
            padding: 24px 0;
            border-bottom: 1px solid #ebebeb;
        }

        .amenities h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #4CAF50;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .amenity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #222;
            font-size: 16px;
        }

        .amenity-item i {
            width: 24px;
            color: #4CAF50;
        }

        .show-all-amenities {
            margin-top: 24px;
            background: none;
            border: 1px solid #4CAF50;
            color: #4CAF50;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .show-all-amenities:hover {
            background: #f0f8f0;
        }

        .description {
            padding: 24px 0;
            border-bottom: 1px solid #ebebeb;
            line-height: 1.6;
            color: #222;
        }

        /* TARJETA DEL PROPIETARIO */
        .owner-card {
            background: white;
            border: 1px solid #ebebeb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            position: sticky;
            top: 24px;
        }

        .owner-card-header {
            margin-bottom: 20px;
        }

        .owner-card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #222;
        }

        .owner-card-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ebebeb;
        }

        .owner-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .owner-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .owner-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            color: #4CAF50;
        }

        .owner-badge i {
            font-size: 14px;
        }

        .owner-contact-info {
            margin-bottom: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-item i {
            width: 24px;
            font-size: 18px;
            color: #4CAF50;
        }

        .contact-item strong {
            display: block;
            font-size: 14px;
            color: #222;
            margin-bottom: 2px;
        }

        .contact-item span {
            font-size: 14px;
            color: #717171;
        }

        .owner-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .btn-llamar, .btn-email {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-llamar {
            background-color: #4CAF50;
            color: white;
        }

        .btn-llamar:hover {
            background-color: #45a049;
        }

        .btn-email {
            background-color: #f0f8f0;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }

        .btn-email:hover {
            background-color: #e0f0e0;
        }

        .btn-llamar.disabled, .btn-email.disabled {
            opacity: 0.5;
            pointer-events: none;
            background-color: #ccc;
            border-color: #ccc;
            color: #666;
        }

        .owner-note {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background-color: #f7f7f7;
            border-radius: 8px;
            font-size: 12px;
            color: #717171;
        }

        .owner-note i {
            color: #4CAF50;
        }

        /* MAPA */
        .map-section {
            padding: 48px 0;
            border-top: 1px solid #ebebeb;
        }

        .map-section h3 {
            font-size: 22px;
            margin-bottom: 24px;
            color: #4CAF50;
        }

        .map-section iframe {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            border: none;
        }

        .map-address {
            margin-top: 16px;
            padding: 12px;
            background-color: #f7f7f7;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #222;
        }

        .map-address i {
            color: #4CAF50;
        }

        /* SECCIÓN DE RESEÑAS */
        .reviews-section {
            padding: 48px 0;
            border-top: 1px solid #ebebeb;
        }

        .reviews-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .reviews-header h2 {
            font-size: 22px;
            font-weight: 600;
            color: #4CAF50;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            overflow-x: auto;
            padding-bottom: 16px;
        }

        .review-card {
            min-width: 300px;
            border: 1px solid #ebebeb;
            border-radius: 12px;
            padding: 24px;
            background: white;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .reviewer-details h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .reviewer-details span {
            font-size: 14px;
            color: #717171;
        }

        .review-rating {
            color: #FFD700; /* CAMBIADO A AMARILLO */
            margin-bottom: 12px;
        }

        .review-text {
            color: #222;
            line-height: 1.5;
            font-size: 15px;
        }

        /* FORMULARIO DE RESEÑA */
        .review-form-section {
            margin-bottom: 48px;
            background: #f9f9f9;
            padding: 32px;
            border-radius: 12px;
            border: 1px solid #e0e0e0;
        }

        .review-form-section h3 {
            font-size: 20px;
            margin-bottom: 24px;
            color: #4CAF50;
        }

        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 4px;
            margin-bottom: 16px;
        }

        .rating input {
            display: none;
        }

        .rating label {
            font-size: 32px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }

        .rating label:hover,
        .rating label:hover ~ label,
        .rating input:checked ~ label {
            color: #FFD700; /* CAMBIADO A AMARILLO */
        }

        .review-form-section textarea {
            width: 100%;
            padding: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 16px;
            resize: vertical;
            margin: 16px 0;
        }

        .review-form-section textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .btn-submit {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #45a049;
        }

        .btn-login-resena {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-login-resena:hover {
            background: #45a049;
        }

        .ya-opino {
            background: #f0f8f0;
            padding: 16px;
            border-radius: 8px;
            color: #4CAF50;
            text-align: center;
            border: 1px solid #4CAF50;
        }

        /* MODAL DE SERVICIOS */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .close-modal:hover {
            color: #4CAF50;
        }

        .modal h2 {
            color: #4CAF50;
            margin-bottom: 25px;
            font-size: 24px;
        }

        .services-category {
            margin-bottom: 25px;
        }

        .services-category h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 5px;
        }

        .services-list {
            list-style: none;
        }

        .services-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .services-list li i {
            width: 24px;
            color: #4CAF50;
        }

        .services-list li.not-included i {
            color: #999;
        }

        .services-list li.not-included {
            color: #999;
        }

        /* MENSAJES FLOTANTES */
        .mensaje-flotante {
            position: fixed;
            top: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            animation: slideIn 0.3s;
        }

        .mensaje-exito {
            background: #4CAF50;
        }

        .mensaje-error {
            background: #f44336;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .gallery-grid {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .gallery-item.main-image {
                grid-column: span 1;
                grid-row: span 1;
                height: 300px;
            }
            
            .gallery-item.small-image {
                height: 200px;
            }
            
            .reviews-grid {
                grid-template-columns: 1fr;
            }
            
            .amenities-grid {
                grid-template-columns: 1fr;
            }
            
            .host-stats {
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Mensajes flotantes -->
    <?php if ($mensajeExito): ?>
        <div class="mensaje-flotante mensaje-exito">
            <i class="fas fa-check-circle"></i> ¡Reseña guardada con éxito!
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.mensaje-flotante')?.remove();
            }, 3000);
        </script>
    <?php endif; ?>
    
    <?php if ($mensajeError): ?>
        <div class="mensaje-flotante mensaje-error">
            <i class="fas fa-exclamation-circle"></i> Error al guardar la reseña
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.mensaje-flotante')?.remove();
            }, 3000);
        </script>
    <?php endif; ?>

    <!-- HEADER SIMPLE -->
    <header class="simple-header">
        <div class="container header-content">
            <button onclick="history.back()" class="btn-volver-simple">
                <i class="fas fa-arrow-left"></i> Volver
            </button>
            
            <nav class="simple-nav">
                <?php if(isset($_SESSION['nombre'])): ?>
                    <span class="user-name"><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['nombre']) ?></span>
                    <a href="database/logout.php" class="btn-cerrar-sesion">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </a>
                <?php else: ?>
                    <button id="abrirLogin" class="btn-iniciar-sesion-simple">
                        <i class="fas fa-sign-in-alt"></i> Iniciar sesión
                    </button>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <!-- GALERÍA - 1 GRANDE + 4 PEQUEÑAS -->
        <div class="gallery-container">
            <div class="gallery-grid">
                <?php 
                // Asegurar que tenemos al menos 5 imágenes
                $imagenes_galeria = $imagenes;
                while (count($imagenes_galeria) < 5) {
                    $imagenes_galeria[] = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23e0e0e0'/%3E%3Ctext x='50%25' y='50%25' font-family='Arial' font-size='18' fill='%23999' text-anchor='middle' dy='.3em'%3EImagen no disponible%3C/text%3E%3C/svg%3E";
                }
                ?>
                
                <!-- Imagen grande (primera) -->
                <div class="gallery-item main-image">
                    <img src="<?= htmlspecialchars($imagenes_galeria[0]) ?>" alt="<?= htmlspecialchars($pub['titulo']) ?>">
                </div>
                
                <!-- 4 imágenes pequeñas -->
                <?php for ($i = 1; $i < 5; $i++): ?>
                    <div class="gallery-item small-image">
                        <img src="<?= htmlspecialchars($imagenes_galeria[$i]) ?>" alt="<?= htmlspecialchars($pub['titulo']) ?> - Imagen <?= $i+1 ?>">
                        <?php if ($i === 4 && count($imagenes) > 5): ?>
                            <button class="view-more" onclick="abrirTodasFotos()">
                                <i class="fas fa-th"></i> +<?= count($imagenes) - 5 ?> más
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="main-content">
            <!-- COLUMNA IZQUIERDA -->
            <div class="property-info">
                <h1><?= htmlspecialchars($pub['titulo']) ?></h1>
                
                <div class="property-meta">
                    <span class="rating-badge">
                        <i class="fas fa-star" style="color: #FFD700;"></i> <?= $promedio ?> · <?= count($resenas) ?> evaluaciones
                    </span>
                    <span>·</span>
                    <span><?= ucfirst(htmlspecialchars($pub['tipo'])) ?></span>
                    <span>·</span>
                    <span><?= htmlspecialchars($pub['ubicacion'] ?? 'Ubicación no especificada') ?></span>
                </div>

                <!-- SECCIÓN DEL ANFITRIÓN - ESTILO AIRBNB -->
                <?php if ($propietario): ?>
                <div class="host-section">
                    <h2>Conocé al anfitrión</h2>
                    
                    <div class="host-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?= rand(150, 300) ?></span>
                            <span class="stat-label">Evaluaciones</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($promedio ?: 4.9, 2) ?>★</span>
                            <span class="stat-label">Calificación</span>
                        </div>
                    </div>
                    
                    <div class="host-profile">
                        <img src="<?= !empty($propietario['foto']) ? $propietario['foto'] : 'media/default-avatar.png' ?>" 
                             alt="<?= htmlspecialchars($propietario['nombre']) ?>" 
                             class="host-avatar-large">
                        <div class="host-name-title">
                            <h3><?= htmlspecialchars($propietario['nombre']) ?></h3>
                            <span class="superhost-badge-large">
                                <i class="fas fa-medal"></i> Superanfitrión
                            </span>
                        </div>
                    </div>
                    
                    <div class="host-description">
                        <p><strong><?= htmlspecialchars(explode(' ', $propietario['nombre'])[0]) ?> es Superanfitrión</strong><br>
                        Los Superanfitriones son anfitriones con experiencia y calificaciones excelentes, que se esfuerzan para que los huéspedes disfruten una estadía maravillosa.</p>
                    </div>
                    
                    <div class="host-response">
                        <div class="response-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Índice de respuesta: 100%</strong><br>
                                <span>Responde en menos de una hora</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="host-bio">
                        <p><i class="fas fa-birthday-cake"></i> Nació en la década de los 80</p>
                    </div>
                    
                    <div class="host-contact">
                        <button class="btn-contactar" onclick="contactarAnfitrion(<?= $propietario['id'] ?>)">
                            <i class="fas fa-envelope"></i> Contactar
                        </button>
                    </div>
                    
                    <div class="payment-warning">
                        <i class="fas fa-shield-alt"></i>
                        Para proteger tus pagos, no transfieras dinero ni te comuniques fuera de la plataforma.
                    </div>
                </div>
                <?php endif; ?>

                <!-- SERVICIOS DESTACADOS -->
                <div class="amenities">
                    <h3>Lo que ofrece este lugar</h3>
                    <div class="amenities-grid">
                        <div class="amenity-item">
                            <i class="fas fa-mountain"></i>
                            <span>Vista a las montañas</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-wine-bottle"></i>
                            <span>Vista al viñedo</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-parking"></i>
                            <span>Estacionamiento gratis</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-swimming-pool"></i>
                            <span>Pileta compartida</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-dog"></i>
                            <span>Se permiten mascotas</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-tree"></i>
                            <span>Patio privado</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-wind"></i>
                            <span>Aire acondicionado</span>
                        </div>
                        <div class="amenity-item">
                            <i class="fas fa-wifi"></i>
                            <span>WiFi</span>
                        </div>
                    </div>
                    <button class="show-all-amenities" onclick="abrirModalServicios()">
                        <i class="fas fa-list"></i> Mostrar todos los servicios
                    </button>
                </div>

                <!-- DESCRIPCIÓN -->
                <div class="description">
                    <p><?= nl2br(htmlspecialchars($pub['descripcion'])) ?></p>
                </div>

                <!-- MAPA -->
                <div class="map-section">
                    <h3>Ubicación</h3>
                    <?php 
                    $direccion = urlencode($pub['direccion'] ?: $pub['ubicacion']);
                    ?>
                    <iframe
                        src="https://www.openstreetmap.org/export/embed.html?bbox=-67.5,-29.2,-67.4,-29.1&layer=mapnik&marker=<?= $pub['latitud'] ?? '-29.163' ?>,<?= $pub['longitud'] ?? '-67.498' ?>"
                        allowfullscreen
                        loading="lazy">
                    </iframe>
                    <p class="map-address">
                        <i class="fas fa-map-pin"></i> <?= htmlspecialchars($pub['direccion'] ?: $pub['ubicacion']) ?>
                    </p>
                </div>

                <!-- SECCIÓN DE RESEÑAS -->
                <div class="reviews-section">
                    <div class="reviews-header">
                        <i class="fas fa-star" style="color: #FFD700;"></i>
                        <h2><?= $promedio ?> · <?= count($resenas) ?> evaluaciones</h2>
                    </div>

                    <?php if (count($resenas) > 0): ?>
                        <div class="reviews-grid">
                            <?php foreach ($resenas as $resena): ?>
                                <div class="review-card">
                                    <div class="reviewer-info">
                                        <img src="<?= htmlspecialchars($resena['usuario_foto']) ?>" 
                                             alt="<?= htmlspecialchars($resena['usuario_nombre']) ?>" 
                                             class="reviewer-avatar">
                                        <div class="reviewer-details">
                                            <h4><?= htmlspecialchars($resena['usuario_nombre']) ?></h4>
                                            <span><?= date('F Y', strtotime($resena['fecha'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa<?= $i <= $resena['rating'] ? 's' : 'r' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="review-text"><?= nl2br(htmlspecialchars($resena['comentario'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; color: #999; padding: 40px;">
                            <i class="fas fa-star" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3; color: #FFD700;"></i>
                            <p>No hay reseñas aún. ¡Sé el primero en opinar!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- COLUMNA DERECHA - TARJETA DEL PROPIETARIO -->
            <div class="owner-card">
                <div class="owner-card-header">
                    <h3><i class="fas fa-user-circle"></i> Información de contacto</h3>
                </div>
                
                <?php if ($propietario): ?>
                <div class="owner-card-profile">
                    <img src="<?= !empty($propietario['foto']) ? $propietario['foto'] : 'media/default-avatar.png' ?>" 
                         alt="<?= htmlspecialchars($propietario['nombre']) ?>" 
                         class="owner-avatar">
                    <div class="owner-info">
                        <h4><?= htmlspecialchars($propietario['nombre']) ?></h4>
                        <span class="owner-badge"><i class="fas fa-check-circle"></i> Propietario verificado</span>
                    </div>
                </div>
                
                <div class="owner-contact-info">
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <strong>Teléfono</strong>
                            <span><?= !empty($propietario['telefono']) ? $propietario['telefono'] : 'No disponible' ?></span>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>Email</strong>
                            <span><?= !empty($propietario['correo']) ? $propietario['correo'] : 'No disponible' ?></span>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <strong>Ubicación</strong>
                            <span>Nonogasta, La Rioja</span>
                        </div>
                    </div>
                </div>
                
                <div class="owner-actions">
                    <a href="tel:<?= $propietario['telefono'] ?? '' ?>" class="btn-llamar <?= empty($propietario['telefono']) ? 'disabled' : '' ?>">
                        <i class="fas fa-phone-alt"></i> Llamar
                    </a>
                    <a href="mailto:<?= $propietario['correo'] ?? '' ?>" class="btn-email <?= empty($propietario['correo']) ? 'disabled' : '' ?>">
                        <i class="fas fa-envelope"></i> Enviar email
                    </a>
                </div>
                
                <div class="owner-note">
                    <i class="fas fa-info-circle"></i>
                    Para comunicarte, utiliza los datos de contacto proporcionados por el propietario.
                </div>
                
                <?php else: ?>
                <div style="text-align: center; padding: 20px; color: #999;">
                    <i class="fas fa-user-slash" style="font-size: 40px; margin-bottom: 10px;"></i>
                    <p>Información del propietario no disponible</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- FORMULARIO PARA NUEVA RESEÑA -->
        <div class="review-form-section">
            <h3><i class="fas fa-star" style="color: #FFD700;"></i> Deja tu opinión</h3>
            
            <?php if (isset($_SESSION['id']) && $yaOpino): ?>
                <div class="ya-opino">
                    <i class="fas fa-check-circle"></i>
                    Ya has opinado sobre esta propiedad. ¡Gracias por tu contribución!
                </div>
            <?php else: ?>
                <form method="POST" action="database/guardar_opinion.php" id="formResena">
                    <input type="hidden" name="propiedad_id" value="<?= $id ?>">
                    
                    <div class="rating">
                        <input type="radio" name="rating" id="star5" value="5" required>
                        <label for="star5"></label>
                        <input type="radio" name="rating" id="star4" value="4">
                        <label for="star4"></label>
                        <input type="radio" name="rating" id="star3" value="3">
                        <label for="star3"></label>
                        <input type="radio" name="rating" id="star2" value="2">
                        <label for="star2"></label>
                        <input type="radio" name="rating" id="star1" value="1">
                        <label for="star1"></label>
                    </div>
                    
                    <textarea name="comentario" placeholder="¿Qué te pareció esta propiedad?" required rows="4"></textarea>
                    
                    <?php if (isset($_SESSION['id'])): ?>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Enviar Reseña
                        </button>
                    <?php else: ?>
                        <div style="margin-bottom: 16px; color: #717171;">
                            <i class="fas fa-info-circle"></i> Inicia sesión para compartir tu opinión
                        </div>
                        <button type="button" onclick="abrirLoginYRecordar(<?= $id ?>)" class="btn-login-resena">
                            <i class="fas fa-sign-in-alt"></i> Iniciar sesión para opinar
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>

        <div class="fecha-publicacion" style="text-align: center; color: #717171; font-size: 14px; padding: 24px 0;">
            <i class="far fa-calendar-alt"></i> Publicado el: <?= date('d/m/Y', strtotime($pub['fecha_publicacion'])) ?> · 
            <i class="fas fa-eye"></i> <?= number_format($pub['visitas'] ?? 0) ?> visitas
        </div>
    </main>

    <!-- MODAL DE SERVICIOS -->
    <div id="modalServicios" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModalServicios()">&times;</span>
            <h2><i class="fas fa-concierge-bell"></i> Todos los servicios</h2>
            
            <div class="services-category">
                <h3>Muebles de exterior</h3>
                <ul class="services-list">
                    <li><i class="fas fa-umbrella-beach"></i> Zona para comer al aire libre</li>
                    <li><i class="fas fa-fire"></i> Parrilla</li>
                </ul>
            </div>
            
            <div class="services-category">
                <h3>Estacionamiento e instalaciones</h3>
                <ul class="services-list">
                    <li><i class="fas fa-parking"></i> Estacionamiento gratis en la propiedad</li>
                    <li><i class="fas fa-swimming-pool"></i> Pileta cubierta compartida: disponible todo el año, disponible las 24 horas, en la terraza</li>
                </ul>
            </div>
            
            <div class="services-category">
                <h3>Servicios</h3>
                <ul class="services-list">
                    <li><i class="fas fa-dog"></i> Se permiten mascotas <br><small>Los animales de asistencia siempre están permitidos</small></li>
                    <li><i class="fas fa-user-check"></i> El anfitrión te va a recibir</li>
                </ul>
            </div>
            
            <div class="services-category">
                <h3>No incluidos</h3>
                <ul class="services-list">
                    <li class="not-included"><i class="fas fa-video"></i> Cámaras de seguridad exteriores en la propiedad</li>
                </ul>
            </div>
            
            <div class="services-category">
                <h3>Comodidades</h3>
                <ul class="services-list">
                    <li><i class="fas fa-wifi"></i> WiFi</li>
                    <li><i class="fas fa-soap"></i> Lavavajillas</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    // Función para abrir login y recordar intención de reseña
    window.abrirLoginYRecordar = function(idPropiedad) {
        sessionStorage.setItem('intencionResena', 'true');
        sessionStorage.setItem('propiedadId', idPropiedad);
        
        const loginBtn = document.querySelector('#abrirLogin');
        if (loginBtn) {
            loginBtn.click();
        }
    };

    // Verificar si venimos de un login exitoso
    document.addEventListener('DOMContentLoaded', function() {
        const intentoResena = sessionStorage.getItem('intencionResena');
        const propiedadId = sessionStorage.getItem('propiedadId');
        
        <?php if (isset($_SESSION['id'])): ?>
            if (intentoResena === 'true' && propiedadId == <?= $id ?>) {
                sessionStorage.removeItem('intencionResena');
                sessionStorage.removeItem('propiedadId');
                
                const formulario = document.getElementById('formResena');
                if (formulario) {
                    formulario.scrollIntoView({ behavior: 'smooth' });
                }
            }
        <?php endif; ?>
    });

    // Función para ver todas las fotos
    function abrirTodasFotos() {
        alert('Función para ver todas las fotos - Implementar galería completa');
    }

    // Función para contactar al anfitrión
    function contactarAnfitrion(id) {
        const email = '<?= $propietario['correo'] ?? '' ?>';
        if (email) {
            window.location.href = 'mailto:' + email;
        } else {
            alert('Información de contacto no disponible');
        }
    }

    // Funciones para el modal de servicios
    function abrirModalServicios() {
        document.getElementById('modalServicios').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function cerrarModalServicios() {
        document.getElementById('modalServicios').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        var modal = document.getElementById('modalServicios');
        if (event.target == modal) {
            cerrarModalServicios();
        }
    }
    </script>
    <script src="script/login.js"></script>
</body>
</html>