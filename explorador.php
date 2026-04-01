<?php
include("database/publicaciones.php");
include("database/session.php");
include("login.php"); 

// Verificar si el usuario está logueado como visitante
$es_visitante = isset($_SESSION['rol']) && $_SESSION['rol'] === 'visitante';
$usuario_id = $_SESSION['id'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono | Explorador</title>
    <link rel="stylesheet" href="estilos/estilo.css">
    <link rel="stylesheet" href="estilos/publicaciones.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a2d9a66f09.js" crossorigin="anonymous"></script>
</head>
<body>

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
                    <li><a href="index.php">Inicio</a></li>
                    <li><b href="#" class="btn-primary-small">Explorar Propiedades</b></li>
                    
                    <!-- NOMBRE DE USUARIO O BOTON INICIAR SESION-->
                    <?php if(isset($_SESSION['nombre'])): ?>
                        <li><a href="database/logout.php">Cerrar sesión</a></li>
                    <?php else: ?>
                        <a id="abrirLogin" class="btn-iniciar-sesion">Iniciar sesión</a>
                    <?php endif; ?>
                </ul>
        </nav>
    </div>
</header>

<!-- 🏡 FILTROS -->
<section class="filtros container">
  <h3 class="titulo-filtros">Filtrar propiedades</h3>
  <form id="filtrosForm" class="filtros-form">
    <div class="fila-filtros">
      <div class="campo-filtro">
        <label><i class="fa-solid fa-handshake"></i> Operación</label>
        <select name="operacion" id="operacion">
          <option value="">Todos</option>
          <option value="alquiler">Alquiler</option>
          <option value="venta">Venta</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-house"></i> Tipo de Propiedad</label>
        <select name="tipo" id="tipo">
          <option value="">Todos</option>
          <option value="casa">Casa</option>
          <option value="departamento">Departamento</option>
          <option value="terreno o lote">Terreno o Lote</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-building"></i> Estado</label>
        <select name="estado" id="estado">
          <option value="">Todos</option>
          <option value="usado">Usado</option>
          <option value="a estrenar">A Estrenar</option>
          <option value="en construccion">En Construcción</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-car"></i> Garaje</label>
        <select name="garaje" id="garaje">
          <option value="">Todos</option>
          <option value="1">Sí</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="fila-filtros">
      <div class="campo-filtro">
        <label><i class="fa-solid fa-dollar-sign"></i> Precio Máximo</label>
        <select name="precio_max" id="precio_max">
          <option value="">Todos</option>
          <option value="100000">$100.000</option>
          <option value="200000">$200.000</option>
          <option value="300000">$300.000</option>
          <option value="400000">$400.000</option>
          <option value="500000">$500.000</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-door-open"></i> Ambientes</label>
        <select name="ambientes" id="ambientes">
          <option value="">Todos</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">Más de 5</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-bed"></i> Dormitorios</label>
        <select name="dormitorios" id="dormitorios">
          <option value="">Todos</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">Más de 5</option>
        </select>
      </div>

      <div class="campo-filtro">
        <label><i class="fa-solid fa-bath"></i> Baños</label>
        <select name="sanitarios" id="sanitarios">
          <option value="">Todos</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">Más de 3</option>
        </select>
      </div>
    </div>

    <div class="botones-filtros">
      <button type="button" class="btn-reiniciar" id="reiniciarFiltros">
        <i class="fa-solid fa-rotate-right"></i> Reiniciar filtros
      </button>
    </div>
  </form>
</section>

<!-- 🏠 PUBLICACIONES FILTRADAS -->
<section class="features-section container">
  <div class="features-grid" id="featuresGrid">
    <!-- Se llenará dinámicamente con fetch -->
  </div>
  <p id="noResultsMessage" style="display:none;text-align:center;padding:20px;">
    No existen publicaciones que coincidan con tu búsqueda
  </p>
</section>

<footer class="main-footer">
  <div class="container footer-content">
    <p>&copy; 2025 RentNono. Todos los derechos reservados.</p>
    <ul class="footer-links">
      <li><a href="#">Términos y Condiciones</a></li>
      <li><a href="#">Política de Privacidad</a></li>
    </ul>
  </div>
</footer>

<!-- ⚙️ Script de filtros, búsqueda y reinicio -->
<script>
// Variables globales para el estado de sesión
const estaLogueado = <?php echo isset($_SESSION['id']) ? 'true' : 'false'; ?>;
const esVisitante = <?php echo $es_visitante ? 'true' : 'false'; ?>;

const filtros = ['operacion','tipo','estado','garaje','precio_max','ambientes','dormitorios','sanitarios'];
const featuresGrid = document.getElementById('featuresGrid');
const reiniciarBtn = document.getElementById('reiniciarFiltros');
const noResultsMessage = document.getElementById('noResultsMessage');

// 🎯 Cargar publicaciones filtradas
function cargarPublicaciones() {
    let params = filtros.map(f => {
        const val = document.getElementById(f).value;
        return val ? `${f}=${encodeURIComponent(val)}` : '';
    }).filter(p => p !== '').join('&');

    fetch('database/publicaciones.php?ajax=1&' + params)
        .then(res => res.text())
        .then(html => {
            featuresGrid.innerHTML = html;
            
            // Agregar eventos a los botones de favorito después de cargar
            agregarEventosFavoritos();
            
            // Agregar eventos a los enlaces de las publicaciones
            agregarEventosEnlaces();
            
            // Efecto visual
            featuresGrid.style.opacity = 0;
            setTimeout(() => {
                featuresGrid.style.opacity = 1;
                featuresGrid.style.transition = 'opacity 0.4s ease';
            }, 50);

            if(html.trim() === '' || html.includes('No se encontraron resultados')) {
                noResultsMessage.style.display = 'block';
            } else {
                noResultsMessage.style.display = 'none';
            }
        })
        .catch(err => console.error('Error al cargar publicaciones:', err));
}

// Función para agregar eventos a los botones de favorito
// Función para agregar eventos a los botones de favorito
function agregarEventosFavoritos() {
    document.querySelectorAll('.fav-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const idPublicacion = this.dataset.id;
            
            if (!estaLogueado || !esVisitante) {
                // Abrir ventana de login si no está logueado
                const modalLogin = document.getElementById('modalFondoLogin');
                if (modalLogin) {
                    modalLogin.style.display = 'flex';
                }
                return;
            }
            
            // Determinar la acción basada en el estado ACTUAL
            const esFavoritoActual = this.classList.contains('active');
            
            // CORRECCIÓN: Toggle visual del botón
            this.classList.toggle('active');
            this.classList.add('animating');
            
            // CORRECCIÓN: Cambiar icono correctamente
            const icon = this.querySelector('i');
            if (this.classList.contains('active')) {
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
            } else {
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
            }
            
            // Determinar acción para el servidor
            const accion = esFavoritoActual ? 'eliminar' : 'agregar';
            
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
                        // Incrementar contador
                        if (favCount) {
                            const currentCount = parseInt(favCount.textContent.match(/\d+/)[0] || 0);
                            favCount.innerHTML = `<i class="fas fa-heart"></i> ${currentCount + 1}`;
                        } else {
                            // Crear contador si no existe
                            const newCount = document.createElement('span');
                            newCount.className = 'fav-count';
                            newCount.innerHTML = `<i class="fas fa-heart"></i> 1`;
                            card.prepend(newCount);
                        }
                    } else if (data.accion === 'eliminado') {
                        // Decrementar contador
                        if (favCount) {
                            const currentCount = parseInt(favCount.textContent.match(/\d+/)[0] || 0);
                            if (currentCount - 1 <= 0) {
                                favCount.remove();
                            } else {
                                favCount.innerHTML = `<i class="fas fa-heart"></i> ${currentCount - 1}`;
                            }
                        }
                    }
                }
            })
            .catch(err => {
                console.error('Error:', err);
                // Revertir visualmente si hay error
                this.classList.toggle('active');
                icon.classList.toggle('fa-regular');
                icon.classList.toggle('fa-solid');
            })
            .finally(() => {
                setTimeout(() => {
                    this.classList.remove('animating');
                }, 800);
            });
        });
    });
}

// Función para agregar eventos a los enlaces de las publicaciones
function agregarEventosEnlaces() {
    document.querySelectorAll('.publicacion-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Solo navegar si no se hizo click en el botón de favorito
            if (!e.target.closest('.fav-btn')) {
                window.location.href = this.href;
            }
        });
    });
}

// Cambios en los filtros
filtros.forEach(f => {
    const el = document.getElementById(f);
    if(el) el.addEventListener('change', cargarPublicaciones);
});

// Botón reiniciar
reiniciarBtn.addEventListener('click', () => {
    filtros.forEach(f => document.getElementById(f).value = '');
    cargarPublicaciones();
});

// Carga inicial
document.addEventListener('DOMContentLoaded', cargarPublicaciones);
</script>

<!--HABILITA VENTANAS FLOTANTES DE LOGIN Y REGISTRO-->
<script src="script/login.js"></script>

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

</body>
</html>