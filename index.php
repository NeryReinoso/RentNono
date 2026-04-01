<?php
ob_start();
include("database/session.php");
include("database/publicaciones.php");
include("login.php");

if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'visitante') {
        header("Location: usuario_visitante/ixusuario.php");
        exit;
    } elseif ($_SESSION['rol'] === 'propietario') {
        header("Location: usuario_propietario/index_propietario.php");
        exit;
    }
}

// Verificar si el usuario está logueado como visitante
$es_visitante = isset($_SESSION['rol']) && $_SESSION['rol'] === 'visitante';
$usuario_id = $_SESSION['id'] ?? null;

// Para la sección de publicaciones más visitadas, necesitamos verificar favoritos
include("database/conexion.php");
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Inicio</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link rel="stylesheet" href="estilos/publicaciones.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a2d9a66f09.js" crossorigin="anonymous"></script>
</head>
<body>

    <!-- BARRA DE NAVEGACION PRINCIPAL -->
    <header class="main-header">
        <div class="container header-content">
            <h1 class="site-logo">
                <?php if(isset($_SESSION['nombre'])): ?>
                    <a href="index.php">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
                <?php else: ?>
                    <a href="index.php">RentNono</a>
                <?php endif; ?>
            </h1>

            <nav class="main-nav">
                <ul>
                    <li><b href="#" class="btn-primary-small" href="index.php">Inicio</b></li>
                    <li><a href="explorador.php">Explorar Propiedades</a></li>
                    
                    <!-- NOMBRE DE USUARIO O BOTON INICIAR SESION-->
                    <?php if(isset($_SESSION['nombre'])): ?>
                        <li><a href="database/logout.php">Cerrar sesión</a></li>
                    <?php else: ?>
                        <a href="#" id="abrirLogin" class="btn-iniciar-sesion" onclick="abrirLogin(); return false;">
        Iniciar sesión
    </a>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main>
        <!--SECCION DE PRESENTACION-->
        <section class="hero-section">
            <div class="hero-text-content">
                <h2>Encontrá tu hogar en Nonogasta</h2>
                <p>Una plataforma simple e intuitiva para que alquiles y des en alquiler tus objetos y propiedades de 
                    forma segura y eficiente.</p>              
        
        <!-- 🔍 BUSCADOR POR PRECIO -->
        <section class="buscador-precio container" style="margin-top:30px;">
            <h3>Filtrar por precio</h3>

            <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                <div>
                    <label>Precio mínimo</label>
                    <input type="number" id="precio_min" placeholder="Ej: 100000" style="padding:8px;">
                </div>

                <div>
                    <label>Precio máximo</label>
                    <input type="number" id="precio_max" placeholder="Ej: 300000" style="padding:8px;">
                </div>

                <button id="btnFiltrar" style="padding:10px 20px; cursor:pointer; background:#2d6cdf; border:none; color:white; border-radius:5px;">
                    Aplicar filtros
                </button>

                <button id="btnReset" style="padding:10px 20px; cursor:pointer; background:#777; border:none; color:white; border-radius:5px;">
                    Reiniciar
                </button>
            </div>
        </section>

        <section class="features-section container" style="margin-top:20px;">
            <h3>Publicaciones</h3>
            <div class="features-grid" id="gridIndex"></div>
            <p id="mensajeVacio" style="display:none; text-align:center; padding:20px;">
                No existen publicaciones en ese rango de precio.
            </p>
        </section>
        </section>

        
    </main>
    
    <!--PIE DE PAGINA-->
    <footer class="main-footer">
        <div class="container footer-content">
            <p>&copy; 2025 Rentnono. Todos los derechos reservados.</p>
            <ul class="footer-links">
                <li><a href="#">Términos y Condiciones</a></li>
                <li><a href="#">Política de Privacidad</a></li>
            </ul>
        </div>
    </footer>
    
    <!--HABILITA VENTANAS FLOTANTES DE LOGIN Y REGISTRO-->
    <script src="script/login.js"></script>
    <script src="script/infopub.js"></script>

    <!--HABILITA VENTANA FLOTANTE DE MENSAJE DE USUARIO CREADO-->
    <script>
        window.addEventListener("DOMContentLoaded", function() {
            const mensajeExito = document.getElementById("mensajeExito");

            <?php if (isset($_GET['registro']) && $_GET['registro'] === "ok"): ?>
                mensajeExito.style.display = "flex";

                // Ocultar después de 3 segundos
                setTimeout(() => {
                    mensajeExito.style.display = "none";
                }, 3000);
            <?php endif; ?>
        });
    </script>

    <script>
        // Variables para control de sesión
        const estaLogueado = <?php echo isset($_SESSION['id']) ? 'true' : 'false'; ?>;
        const esVisitante = <?php echo $es_visitante ? 'true' : 'false'; ?>;
        
        // 🌟 Contenedores para filtros
        const gridIndex = document.getElementById("gridIndex");
        const mensajeVacio = document.getElementById("mensajeVacio");

        // Inputs
        const precioMin = document.getElementById("precio_min");
        const precioMax = document.getElementById("precio_max");

        const btnFiltrar = document.getElementById("btnFiltrar");
        const btnReset = document.getElementById("btnReset");

        // 🔄 Función para agregar eventos a favoritos
        function agregarEventosFavoritos() {
            document.querySelectorAll('.fav-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const idPublicacion = this.dataset.id;
                    
                    if (!estaLogueado || !esVisitante) {
                        // Abrir ventana de login si no está logueado
                        document.getElementById('modalFondoLogin').style.display = 'flex';
                        return;
                    }
                    
                    // Toggle visual del botón
                    this.classList.toggle('active');
                    this.classList.add('animating');
                    
                    // Cambiar icono
                    const icon = this.querySelector('i');
                    if (this.classList.contains('active')) {
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid');
                    } else {
                        icon.classList.remove('fa-solid');
                        icon.classList.add('fa-regular');
                    }
                    
                    // Enviar petición al servidor
                    fetch('database/favoritos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `accion=toggle&id_publicacion=${idPublicacion}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error:', data.error);
                            // Revertir visualmente si hay error
                            this.classList.toggle('active');
                            icon.classList.toggle('fa-regular');
                            icon.classList.toggle('fa-solid');
                        } else {
                            // Actualizar contador de favoritos
                            const card = this.closest('.pub-card');
                            const favCount = card.querySelector('.fav-count');
                            
                            if (data.accion === 'agregado') {
                                if (favCount) {
                                    const currentCount = parseInt(favCount.textContent.match(/\d+/)[0]);
                                    favCount.innerHTML = `<i class="fas fa-heart"></i> ${currentCount + 1}`;
                                } else {
                                    // Crear contador si no existe
                                    const newCount = document.createElement('span');
                                    newCount.className = 'fav-count';
                                    newCount.innerHTML = `<i class="fas fa-heart"></i> 1`;
                                    card.prepend(newCount);
                                }
                            } else {
                                if (favCount) {
                                    const currentCount = parseInt(favCount.textContent.match(/\d+/)[0]);
                                    if (currentCount - 1 <= 0) {
                                        favCount.remove();
                                    } else {
                                        favCount.innerHTML = `<i class="fas fa-heart"></i> ${currentCount - 1}`;
                                    }
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Error:', err))
                    .finally(() => {
                        setTimeout(() => {
                            this.classList.remove('animating');
                        }, 800);
                    });
                });
            });
            
            // Agregar eventos a los enlaces de las publicaciones
            document.querySelectorAll('.publicacion-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!e.target.closest('.fav-btn')) {
                        window.location.href = this.href;
                    }
                });
            });
        }

        // 🔄 Función para cargar publicaciones vía AJAX (filtros)
        function cargarPublicacionesFiltradas() {
            let params = [];
            if (precioMin.value) params.push("precio_min=" + encodeURIComponent(precioMin.value));
            if (precioMax.value) params.push("precio_max=" + encodeURIComponent(precioMax.value));

            let url = "database/publicaciones.php?ajax=1&" + params.join("&");

            fetch(url)
                .then(res => res.text())
                .then(html => {
                    gridIndex.innerHTML = html;
                    
                    // Agregar eventos a los botones de favorito después de cargar
                    agregarEventosFavoritos();
                    
                    // Efecto visual
                    gridIndex.style.opacity = 0;
                    setTimeout(() => {
                        gridIndex.style.opacity = 1;
                        gridIndex.style.transition = 'opacity 0.4s ease';
                    }, 50);

                    if (html.trim() === "" || html.includes("No existen")) {
                        mensajeVacio.style.display = "block";
                    } else {
                        mensajeVacio.style.display = "none";
                    }
                })
                .catch(err => console.error('Error:', err));
        }

        // ▶️ Botón "Aplicar filtros"
        if (btnFiltrar) {
            btnFiltrar.addEventListener("click", cargarPublicacionesFiltradas);
        }

        // 🔄 Botón "Reiniciar"
        if (btnReset) {
            btnReset.addEventListener("click", () => {
                if (precioMin) precioMin.value = "";
                if (precioMax) precioMax.value = "";
                cargarPublicacionesFiltradas();
            });
        }

        // ▶️ Agregar eventos a los favoritos de la sección estática
        document.addEventListener("DOMContentLoaded", function() {
            agregarEventosFavoritos();
            
            // Cargar publicaciones filtradas al iniciar
            if (gridIndex) {
                cargarPublicacionesFiltradas();
            }
        });
    </script>
<script>
// Mostrar mensaje de éxito al registrarse
window.addEventListener("DOMContentLoaded", function() {
    const mensajeExito = document.getElementById("mensajeExito");
    
    <?php if (isset($_GET['registro']) && $_GET['registro'] === "ok"): ?>
        const tipo = "<?php echo $_GET['tipo'] ?? 'usuario'; ?>";
        const mensaje = tipo === 'propietario' 
            ? '¡Registro como propietario exitoso! Revisa tu correo para crear tu contraseña.'
            : '¡Registro como visitante exitoso! Revisa tu correo para crear tu contraseña.';
        
        mensajeExito.innerHTML = `
            <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
            ${mensaje}
        `;
        mensajeExito.style.display = "flex";
        
        setTimeout(() => {
            mensajeExito.style.display = "none";
        }, 5000);
    <?php endif; ?>
    
    // Manejar el enlace "¿Olvidaste tu contraseña?"
    const enlaceOlvido = document.querySelector('.enlace-olvido');
    if (enlaceOlvido) {
        enlaceOlvido.addEventListener('click', function(e) {
            e.preventDefault();
            mostrarMensajeRecuperacion();
        });
    }
});

</script>
</body>
</html>