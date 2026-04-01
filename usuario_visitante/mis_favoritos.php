<?php
include("../database/session.php");
include("../database/conexion.php");

// Verificar que el usuario sea visitante
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'visitante') {
    header("Location: ../index.php");
    exit;
}

// Obtener favoritos del usuario
$stmt = $conn->prepare("
    SELECT p.*, f.fecha_agregado 
    FROM favoritos f 
    JOIN propiedades p ON f.propiedad_id = p.id 
    WHERE f.usuario_id = ? 
    ORDER BY f.fecha_agregado DESC
");
$stmt->execute([$_SESSION['id']]);
$favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Favoritos | RentNono</title>
    <link rel="stylesheet" href="../estilos/estilo.css">
    <link rel="stylesheet" href="../estilos/publicaciones.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://kit.fontawesome.com/a2d9a66f09.js" crossorigin="anonymous"></script>
    <style>
        /* Estilos para pantalla completa */
        body {
            background: #f8f9fa;
        }
        
        .favoritos-section {
            padding: 30px 0;
            max-width: 100%;
            width: 100%;
            margin: 0;
            background: #f8f9fa;
        }
        
        .favoritos-section h2 {
            text-align: center;
            margin-bottom: 40px;
            color: #2c3e50;
            font-size: 2.2rem;
        }
        
        .favoritos-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .favoritos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            justify-content: center;
        }
        
        /* Estilo de las cards igual que en index */
        .fav-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            height: 100%;
        }
        
        .fav-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .fav-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        
        .fav-content {
            padding: 20px;
        }
        
        .fav-content h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.3rem;
            min-height: 60px;
        }
        
        .fav-content p {
            color: #666;
            margin: 8px 0;
            line-height: 1.5;
        }
        
        .fav-content .price {
            color: #82b16d;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 15px 0;
        }
        
        .fav-content .date {
            font-size: 0.9rem;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        /* =========================================== */
        /* BOTÓN DE FAVORITOS - CORREGIDO PARA MOSTRAR CORAZÓN */
        /* =========================================== */
        
        /* Botón de eliminar favorito - USAR MISMO ESTILO QUE .fav-btn */
        .btn-remove-fav {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid #ddd;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .btn-remove-fav:hover {
            transform: scale(1.15);
            border-color: #ff4757;
            background: rgba(255, 255, 255, 1);
        }
        
        .btn-remove-fav.active {
            background: #ff4757 !important;
            border-color: #ff4757 !important;
        }
        
        /* Icono dentro del botón */
        .btn-remove-fav i {
            transition: all 0.3s ease;
            color: #666;
        }
        
        .btn-remove-fav.active i {
            color: white !important;
            font-weight: bold;
        }
        
        .btn-remove-fav:hover i {
            color: #ff4757;
        }
        
        /* Hacer que .btn-remove-fav herede estilos de .fav-btn */
        .btn-remove-fav, .fav-btn, .btn-fav {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid #ddd;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .btn-remove-fav:hover, .fav-btn:hover, .btn-fav:hover {
            transform: scale(1.15);
            border-color: #ff4757;
            background: rgba(255, 255, 255, 1);
        }
        
        .btn-remove-fav.active, .fav-btn.active, .btn-fav.active {
            background: #ff4757 !important;
            border-color: #ff4757 !important;
        }
        
        .btn-remove-fav i, .fav-btn i, .btn-fav i {
            transition: all 0.3s ease;
            color: #666;
        }
        
        .btn-remove-fav.active i, .fav-btn.active i, .btn-fav.active i {
            color: white !important;
            font-weight: bold;
        }
        
        .btn-remove-fav:hover i, .fav-btn:hover i, .btn-fav:hover i {
            color: #ff4757;
        }
        
        /* Contador de favoritos */
        .fav-count {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 71, 87, 0.9);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            z-index: 9;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .fav-count i {
            font-size: 10px;
            color: white !important;
        }
        
        /* Animación para ambos */
        @keyframes heartBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.3); }
            28% { transform: scale(1); }
            42% { transform: scale(1.3); }
            70% { transform: scale(1); }
        }
        
        .btn-remove-fav.animating, .fav-btn.animating, .btn-fav.animating {
            animation: heartBeat 0.8s ease;
        }
        /* =========================================== */
        
        /* Cuando no hay favoritos */
        .favorito-vacio {
            text-align: center;
            padding: 80px 20px;
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .favorito-vacio i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 25px;
        }
        
        .favorito-vacio h3 {
            color: #444;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .favorito-vacio p {
            color: #777;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        






















        .btn-primary {
            background: #b3e58a;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #82b16d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .favoritos-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
                padding: 0 10px;
            }
            
            .favoritos-section h2 {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }
            
            .btn-remove-fav, .fav-btn, .btn-fav {
                width: 36px;
                height: 36px;
                padding: 8px;
                font-size: 18px;
            }
            
            .fav-count {
                top: 8px;
                left: 8px;
                padding: 3px 8px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="container header-content">
        <h1 class="site-logo">
            <a href="ixusuario.php">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
        </h1>

        <nav class="main-nav">
            <ul>
                <li><a href="ixusuario.php">Inicio</a></li>
                <li><a href="erusuario.php">Explorar Propiedades</a></li>
                <li><b class="btn-primary-small">Mis Favoritos</b></li>
                <li><a href="../database/logout.php">Cerrar sesión</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="favoritos-section">
    <div class="favoritos-container">
        <h2>Mis Propiedades Favoritas</h2>
        
        <?php if (count($favoritos) > 0): ?>
            <div class="favoritos-grid">
                <?php foreach ($favoritos as $fav): 
                    // Obtener contador de favoritos para esta publicación
                    $countFav = $conn->prepare("SELECT COUNT(*) as total FROM favoritos WHERE propiedad_id = ?");
                    $countFav->execute([$fav['id']]);
                    $totalFav = $countFav->fetch(PDO::FETCH_ASSOC)['total'];
                ?>
                    <div class="fav-card">
                        <?php if ($totalFav > 0): ?>
                            <span class="fav-count"><i class="fas fa-heart"></i> <?= $totalFav ?></span>
                        <?php endif; ?>
                        
                        <button class="btn-remove-fav active" data-id="<?= $fav['id'] ?>">
                            <i class="fa-solid fa-heart"></i>
                        </button>
                        
                        <a href="../detalle_publicaciones.php?id=<?= $fav['id'] ?>" class="publicacion-link">
                            <img src="../media/publicaciones/<?= htmlspecialchars($fav['imagen']) ?>" alt="<?= htmlspecialchars($fav['titulo']) ?>">
                            <div class="fav-content">
                                <h4><?= htmlspecialchars($fav['titulo']) ?></h4>
                                <p><?= htmlspecialchars(substr($fav['descripcion'], 0, 120)) ?>...</p>
                                <p class="price"><strong>Precio:</strong> $<?= number_format($fav['precio'], 2) ?></p>
                                <p><strong>Ubicación:</strong> <?= htmlspecialchars($fav['ubicacion']) ?></p>
                                <p><strong>Tipo:</strong> <?= htmlspecialchars($fav['tipo']) ?> - <?= htmlspecialchars($fav['operacion']) ?></p>
                                <p class="date">Agregado el: <?= date('d/m/Y', strtotime($fav['fecha_agregado'])) ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="favorito-vacio">
                <i class="fa-regular fa-heart"></i>
                <h3>No tienes propiedades favoritas</h3>
                <p>Explora nuestras propiedades y haz clic en el corazón para guardarlas aquí.</p>
                <a href="erusuario.php" class="btn-primary">Explorar Propiedades</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="main-footer">
    <div class="container footer-content">
        <p>&copy; 2025 RentNono. Todos los derechos reservados.</p>
    </div>
</footer>

<script>
// Variables globales para el gestor de favoritos
const usuarioLogueado = true; // Ya estamos logueados en esta página
const esVisitante = true; // Esta página solo es accesible para visitantes

// Script SIMPLIFICADO para manejar favoritos en esta página
document.addEventListener('DOMContentLoaded', function() {
    console.log('💖 Mis Favoritos - Inicializando...');
    
    // Agregar eventos a todos los botones de eliminar favorito
    document.querySelectorAll('.btn-remove-fav').forEach(btn => {
        // Clonar el botón para limpiar eventos previos
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        // Agregar evento de click
        newBtn.addEventListener('click', function(e) {
            console.log('❤️ Click en botón eliminar favorito:', this.dataset.id);
            e.preventDefault();
            e.stopPropagation();
            
            const idPublicacion = this.dataset.id;
            const card = this.closest('.fav-card');
            
            // Confirmación
            if (!confirm('¿Eliminar esta propiedad de tus favoritos?')) {
                return;
            }
            
            // Animación visual
            this.classList.add('animating');
            this.style.transform = 'scale(0.8)';
            
            // Enviar petición al servidor
            fetch('../database/favoritos.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `accion=toggle&id_publicacion=${idPublicacion}`
            })
            .then(res => res.json())
            .then(data => {
                console.log('Respuesta:', data);
                
                if (data.success && data.accion === 'eliminado') {
                    // Animación de eliminación
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(100px)';
                    card.style.transition = 'all 0.4s ease';
                    
                    setTimeout(() => {
                        card.remove();
                        console.log('✅ Propiedad eliminada de favoritos');
                        
                        // Si no quedan favoritos, recargar la página
                        if (document.querySelectorAll('.fav-card').length === 0) {
                            console.log('🔄 No hay más favoritos, recargando...');
                            setTimeout(() => location.reload(), 500);
                        }
                    }, 400);
                } else {
                    alert('Error: ' + (data.error || 'No se pudo eliminar'));
                    this.style.transform = '';
                    this.classList.remove('animating');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error de conexión');
                this.style.transform = '';
                this.classList.remove('animating');
            });
        });
    });
    
    // Hacer cards clickeables (excepto botón eliminar)
    document.querySelectorAll('.publicacion-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Si el click es en el botón de eliminar, no seguir el enlace
            if (e.target.closest('.btn-remove-fav')) {
                e.preventDefault();
                return;
            }
            
            // Si el click es en el icono de corazón, no seguir el enlace
            if (e.target.classList.contains('fa-heart')) {
                e.preventDefault();
                return;
            }
            
            // Para otros clicks, navegar normalmente
            window.location.href = this.href;
        });
    });
    
    // También prevenir clicks en toda la tarjeta
    document.querySelectorAll('.fav-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Si el click es en el botón de eliminar, no hacer nada más
            if (e.target.closest('.btn-remove-fav')) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            
            // Si el click es en el icono de corazón, no hacer nada más
            if (e.target.classList.contains('fa-heart')) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            
            // Para otros clicks, seguir el enlace
            const link = this.querySelector('.publicacion-link');
            if (link && !e.target.closest('.btn-remove-fav')) {
                window.location.href = link.href;
            }
        });
    });
    
    console.log('✅ Eventos configurados en Mis Favoritos');
});
</script>

</body>
</html>