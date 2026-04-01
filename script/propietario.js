// ============================================
// SISTEMA DE PANEL DE PROPIETARIO - RENTNONO
// ============================================

document.addEventListener("DOMContentLoaded", function() {
    console.log("🚀 Iniciando sistema de RentNono...");
    
    // Inicializar todos los componentes
    inicializarNavegacion();
    inicializarInterfaz();
    inicializarSubidaImagenes();
    inicializarBuscadorLaRioja();
    inicializarMapaLaRioja();
    inicializarEventos();
    inicializarFiltros();
    
    console.log("✅ Sistema inicializado correctamente");
});

// ============================================
// 1. NAVEGACIÓN Y UI
// ============================================

function inicializarNavegacion() {
    console.log("🔧 Inicializando navegación...");
    
    // Botón de menú responsive
    const botonMenu = document.getElementById('botonMenu');
    const barraLateral = document.querySelector('.barra-lateral');
    const contenidoPrincipal = document.querySelector('.contenido-principal');
    
    if (botonMenu) {
        botonMenu.addEventListener('click', function() {
            barraLateral.classList.toggle('colapsada');
            contenidoPrincipal.classList.toggle('barra-colapsada');
        });
    }
    
    // Navegación por secciones
    const enlacesNav = document.querySelectorAll('.enlace-navegacion');
    enlacesNav.forEach(enlace => {
        enlace.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                const seccion = href.substring(1);
                mostrarSeccion(seccion);
            }
        });
    });
    
    // Tarjetas interactivas
    const tarjetasInteractivas = document.querySelectorAll('.tarjeta-interactiva');
    tarjetasInteractivas.forEach(tarjeta => {
        tarjeta.addEventListener('click', function() {
            const seccion = this.getAttribute('data-abrir') || 'formulario';
            mostrarSeccion(seccion);
        });
    });
}

function mostrarSeccion(seccionId) {
    console.log(`📌 Mostrando sección: ${seccionId}`);
    
    const secciones = {
        'inicio': document.getElementById('sec-inicio'),
        'formulario': document.getElementById('sec-formulario'),
        'propiedades': document.getElementById('sec-propiedades'),
        'comentarios': document.getElementById('sec-comentarios'),
        'notificaciones': document.getElementById('sec-notificaciones')
    };
    
    const enlaces = {
        'inicio': document.getElementById('nav-inicio'),
        'formulario': document.getElementById('nav-formulario'),
        'propiedades': document.getElementById('nav-propiedades'),
        'comentarios': document.getElementById('nav-comentarios'),
        'notificaciones': document.getElementById('nav-notificaciones')
    };
    
    // Ocultar todas las secciones y remover estado activo
    Object.values(secciones).forEach(sec => {
        if (sec) {
            sec.classList.remove('activa');
            sec.classList.add('oculto');
        }
    });
    
    Object.values(enlaces).forEach(enlace => {
        if (enlace) {
            enlace.parentElement.classList.remove('activo');
        }
    });
    
    // Mostrar sección seleccionada
    if (secciones[seccionId]) {
        secciones[seccionId].classList.remove('oculto');
        secciones[seccionId].classList.add('activa');
        
        // Activar enlace en barra lateral
        if (enlaces[seccionId]) {
            enlaces[seccionId].parentElement.classList.add('activo');
        }
        
        // Actualizar título de página
        const tituloPagina = document.getElementById('tituloPagina');
        if (tituloPagina) {
            const titulos = {
                'inicio': 'Panel de Control',
                'formulario': 'Agregar Propiedad',
                'propiedades': 'Mis Propiedades',
                'comentarios': 'Comentarios',
                'notificaciones': 'Notificaciones'
            };
            tituloPagina.textContent = titulos[seccionId] || 'RentNono';
        }
        
        // Scroll al inicio de la sección
        window.scrollTo({ top: 0, behavior: 'smooth' });
                // Inicializar filtros si estamos en propiedades
        if (seccionId === 'propiedades') {
            setTimeout(() => {
                filtrarPropiedadesTarjetas();
            }, 100);
        }
    }
}

// ============================================
// 2. INTERFAZ Y UTILIDADES
// ============================================

function inicializarInterfaz() {
    console.log("🎨 Inicializando interfaz...");
    
    // Toggle de precio no publicado
    const checkboxPrecio = document.getElementById('no-decirlo');
    const inputPrecio = document.getElementById('precio');
    
    if (checkboxPrecio && inputPrecio) {
        checkboxPrecio.addEventListener('change', function() {
            inputPrecio.disabled = this.checked;
            inputPrecio.placeholder = this.checked ? 'No publicado' : '120000';
            if (this.checked) {
                inputPrecio.value = '';
            }
        });
        
        // Estado inicial
        inputPrecio.disabled = checkboxPrecio.checked;
        inputPrecio.placeholder = checkboxPrecio.checked ? 'No publicado' : '120000';
    }
    
    // Tooltips
    const elementosTooltip = document.querySelectorAll('[data-tooltip]');
    elementosTooltip.forEach(elemento => {
        elemento.addEventListener('mouseenter', function() {
            const tooltip = this.getAttribute('data-tooltip');
            if (tooltip) {
                mostrarTooltip(this, tooltip);
            }
        });
        
        elemento.addEventListener('mouseleave', function() {
            ocultarTooltip();
        });
    });
}

function mostrarTooltip(elemento, texto) {
    // Eliminar tooltip anterior si existe
    ocultarTooltip();
    
    // Crear tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip-custom';
    tooltip.textContent = texto;
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 10000;
        white-space: nowrap;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    // Posicionar tooltip
    const rect = elemento.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    tooltip.style.top = (rect.top - tooltipRect.height - 8) + 'px';
    tooltip.style.left = (rect.left + (rect.width - tooltipRect.width) / 2) + 'px';
}

function ocultarTooltip() {
    const tooltip = document.querySelector('.tooltip-custom');
    if (tooltip) {
        tooltip.remove();
    }
}

// ============================================
// 3. SUBIDA DE IMÁGENES MEJORADA - VERSIÓN CORREGIDA
// ============================================

function inicializarSubidaImagenes() {
    console.log("🖼️ Inicializando subida de imágenes...");
    
    const areaSubida = document.getElementById('areaSubidaArchivos');
    const inputImagenes = document.getElementById('imagenes');
    const gridImagenes = document.getElementById('gridImagenes');
    const contadorSeleccionadas = document.getElementById('contadorSeleccionadas');
    
    if (!areaSubida || !inputImagenes) {
        console.error("❌ Elementos de subida no encontrados");
        return;
    }
    
    console.log("✅ Elementos encontrados");
    
    // VARIABLE PARA CONTROLAR CLICKS MÚLTIPLES
    let clickEnProgreso = false;
    let dragOverTimeout = null;
    
    // ====================
    // 1. CLICK EN EL ÁREA
    // ====================
    areaSubida.addEventListener('click', function(e) {
        console.log("🖱️ Click en área de subida");
        
        // Evitar clicks múltiples
        if (clickEnProgreso) {
            console.log("⏳ Click bloqueado (ya en progreso)");
            return;
        }
        
        clickEnProgreso = true;
        
        // Solo abrir selector si el click NO fue en el input
        if (e.target !== inputImagenes && !inputImagenes.contains(e.target)) {
            console.log("📂 Abriendo selector de archivos...");
            inputImagenes.click();
        }
        
        // Resetear después de 500ms
        setTimeout(() => {
            clickEnProgreso = false;
        }, 500);
    });
    
    // ====================
    // 2. DRAG AND DROP
    // ====================
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        areaSubida.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    areaSubida.addEventListener('dragenter', function(e) {
        console.log("📤 Drag enter");
        clearTimeout(dragOverTimeout);
        this.classList.add('drag-over');
    });
    
    areaSubida.addEventListener('dragover', function(e) {
        // Mantener el estado drag-over
        this.classList.add('drag-over');
    });
    
    areaSubida.addEventListener('dragleave', function(e) {
        console.log("📤 Drag leave");
        // Usar timeout para evitar parpadeo
        dragOverTimeout = setTimeout(() => {
            if (!this.contains(e.relatedTarget)) {
                this.classList.remove('drag-over');
            }
        }, 100);
    });
    
    areaSubida.addEventListener('drop', function(e) {
        console.log("📤 Drop de archivos");
        clearTimeout(dragOverTimeout);
        this.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        console.log(`📄 ${files.length} archivo(s) soltado(s)`);
        
        if (files.length > 0) {
            // Combinar archivos existentes con nuevos
            const archivosActuales = inputImagenes.files;
            const totalArchivos = archivosActuales.length + files.length;
            
            if (totalArchivos > 5) {
                mostrarToast('Máximo 5 imágenes permitidas', 'error');
                return;
            }
            
            // Crear DataTransfer para combinar archivos
            const dt = new DataTransfer();
            
            // Agregar archivos existentes
            for (let i = 0; i < archivosActuales.length; i++) {
                dt.items.add(archivosActuales[i]);
            }
            
            // Agregar archivos nuevos
            for (let i = 0; i < files.length; i++) {
                dt.items.add(files[i]);
            }
            
            // Actualizar input
            inputImagenes.files = dt.files;
            
            // Procesar TODAS las imágenes
            procesarImagenes(inputImagenes.files);
        }
    });
    
    // ====================
    // 3. CAMBIO EN INPUT
    // ====================
    inputImagenes.addEventListener('change', function(e) {
        console.log("🔄 Cambio en input de imágenes");
        
        const files = this.files;
        console.log(`📄 ${files.length} archivo(s) seleccionado(s)`);
        
        if (files.length > 5) {
            mostrarToast('Máximo 5 imágenes permitidas. Se mantendrán las primeras 5.', 'warning');
            
            // Mantener solo las primeras 5
            const dt = new DataTransfer();
            for (let i = 0; i < 5; i++) {
                dt.items.add(files[i]);
            }
            this.files = dt.files;
        }
        
        procesarImagenes(this.files);
    });
    
    // ====================
    // 4. FUNCIÓN DE PROCESAMIENTO
    // ====================
    window.procesarImagenes = function(files) {
        console.log("🔄 Procesando imágenes:", files?.length || 0);
        
        if (!files || files.length === 0) {
            // Limpiar grid si no hay imágenes
            if (gridImagenes) {
                gridImagenes.innerHTML = '';
            }
            
            // Actualizar contador
            if (contadorSeleccionadas) {
                contadorSeleccionadas.textContent = '0';
            }
            
            return;
        }
        
        // Limpiar grid anterior
        if (gridImagenes) {
            gridImagenes.innerHTML = '';
        }
        
        // Array para trackear imágenes procesadas
        const imagenesProcesadas = [];
        
        // Procesar cada archivo
        Array.from(files).forEach((file, index) => {
            console.log(`🖼️ Procesando imagen ${index + 1}:`, file.name, file.type, `${(file.size / 1024).toFixed(2)}KB`);
            
            // Validar tipo
            if (!file.type.startsWith('image/')) {
                mostrarToast(`"${file.name}" no es una imagen válida. Se omitirá.`, 'warning');
                return;
            }
            
            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                mostrarToast(`"${file.name}" es demasiado grande (máximo 5MB). Se omitirá.`, 'warning');
                return;
            }
            
            // Crear preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const imagenId = `imagen-${Date.now()}-${index}`;
                
                const imagenPreview = document.createElement('div');
                imagenPreview.className = 'imagen-preview';
                imagenPreview.id = imagenId;
                imagenPreview.dataset.index = index;
                imagenPreview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}" loading="lazy">
                    <div class="overlay-imagen">
                        <button type="button" class="btn-imagen" onclick="rotarImagen('${imagenId}')" title="Rotar 90°">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                        <button type="button" class="btn-imagen" onclick="eliminarImagen(${index})" title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <span class="nombre-imagen" title="${file.name}">${file.name.length > 15 ? file.name.substring(0, 12) + '...' : file.name}</span>
                    </div>
                `;
                
                if (gridImagenes) {
                    gridImagenes.appendChild(imagenPreview);
                }
                
                // Guardar referencia
                imagenesProcesadas.push({ id: imagenId, file: file });
            };
            
            reader.onerror = function() {
                console.error(`❌ Error leyendo imagen ${file.name}`);
                mostrarToast(`Error al leer "${file.name}"`, 'error');
            };
            
            reader.readAsDataURL(file);
        });
        
        // Actualizar contador
        if (contadorSeleccionadas) {
            contadorSeleccionadas.textContent = files.length;
        }
        
        // Mostrar mensaje
        if (files.length > 0) {
            setTimeout(() => {
                mostrarToast(`${files.length} imagen(es) cargada(s) correctamente`, 'success');
            }, 500);
        }
        
        console.log("✅ Procesamiento de imágenes completado");
    };
    
    // ====================
    // 5. BOTONES DE ACCIÓN
    // ====================
    
    window.rotarImagen = function(imagenId) {
        console.log("🔄 Rotando imagen:", imagenId);
        
        const imagenPreview = document.getElementById(imagenId);
        if (!imagenPreview) return;
        
        const img = imagenPreview.querySelector('img');
        if (!img) return;
        
        let rotacion = parseInt(img.style.transform.replace('rotate(', '').replace('deg)', '')) || 0;
        rotacion = (rotacion + 90) % 360;
        
        img.style.transform = `rotate(${rotacion}deg)`;
        img.style.transition = 'transform 0.3s ease';
        
        // Actualizar título
        const overlay = imagenPreview.querySelector('.overlay-imagen');
        const botonRotar = overlay?.querySelector('button:first-child');
        if (botonRotar) {
            botonRotar.title = `Rotar ${(rotacion + 90) % 360}°`;
        }
    };
    
    window.eliminarImagen = function(index) {
        console.log("🗑️ Eliminando imagen índice:", index);
        
        const inputImagenes = document.getElementById('imagenes');
        const gridImagenes = document.getElementById('gridImagenes');
        const contadorSeleccionadas = document.getElementById('contadorSeleccionadas');
        
        if (!inputImagenes || !gridImagenes) return;
        
        // Crear nuevo FileList sin la imagen eliminada
        const dt = new DataTransfer();
        const archivos = inputImagenes.files;
        
        // Verificar índice válido
        if (index < 0 || index >= archivos.length) {
            console.error("Índice de imagen inválido:", index);
            return;
        }
        
        // Agregar todos los archivos excepto el eliminado
        for (let i = 0; i < archivos.length; i++) {
            if (i !== index) {
                dt.items.add(archivos[i]);
            }
        }
        
        // Actualizar input
        inputImagenes.files = dt.files;
        
        // Reprocesar todas las imágenes
        procesarImagenes(inputImagenes.files);
        
        // Mostrar mensaje
        setTimeout(() => {
            mostrarToast('Imagen eliminada', 'info');
        }, 300);
    };
    
    // ====================
    // 6. INICIALIZACIÓN
    // ====================
    
    // Previsualización inicial si hay imágenes
    if (inputImagenes.files.length > 0) {
        console.log("🖼️ Cargando imágenes existentes:", inputImagenes.files.length);
        setTimeout(() => {
            procesarImagenes(inputImagenes.files);
        }, 500);
    }

        // ====================
    // 7. DRAG AND DROP PARA REORDENAR
    // ====================
    
    if (gridImagenes) {
        // Hacer imágenes arrastrables
        gridImagenes.addEventListener('dragstart', function(e) {
            if (e.target.closest('.imagen-preview')) {
                const imagen = e.target.closest('.imagen-preview');
                e.dataTransfer.setData('text/plain', imagen.dataset.index);
                imagen.classList.add('dragging');
            }
        });
        
        gridImagenes.addEventListener('dragover', function(e) {
            e.preventDefault();
            const dragging = this.querySelector('.dragging');
            const afterElement = getDragAfterElement(this, e.clientY);
            
            if (afterElement == null) {
                this.appendChild(dragging);
            } else {
                this.insertBefore(dragging, afterElement);
            }
        });
        
        gridImagenes.addEventListener('dragend', function(e) {
            const dragging = this.querySelector('.dragging');
            if (dragging) {
                dragging.classList.remove('dragging');
                
                // Reordenar archivos según nueva posición
                reordenarArchivos();
            }
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.imagen-preview:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        function reordenarArchivos() {
            const inputImagenes = document.getElementById('imagenes');
            const imagenes = gridImagenes.querySelectorAll('.imagen-preview');
            
            const dt = new DataTransfer();
            imagenes.forEach(imagen => {
                const index = parseInt(imagen.dataset.index);
                if (!isNaN(index) && inputImagenes.files[index]) {
                    dt.items.add(inputImagenes.files[index]);
                }
            });
            
            inputImagenes.files = dt.files;
            procesarImagenes(inputImagenes.files);
            mostrarToast('Imágenes reordenadas', 'info');
        }
    }
    
    console.log("✅ Subida de imágenes inicializada correctamente");
}

function inicializarBuscadorLaRioja() {
    console.log("🔍 Inicializando Google Maps API...");
    
    // Primero cargar Google Maps API
    cargarGoogleMapsAPI();
}

// Variables globales para Google Maps
let mapaGoogle = null;
let marcadorGoogle = null;
let autocompleteGoogle = null;
let tipoMapaActual = 'roadmap';
let geocoderGoogle = null;

// Tu API Key de Google - ¡IMPORTANTE!
const GOOGLE_API_KEY = 'AIzaSyCnHEEX-Hr2PRg8XeHSpWWUCQjdJ_SydPg'; // REEMPLAZA ESTO

function cargarGoogleMapsAPI() {
    // Verificar si ya está cargada
    if (typeof google !== 'undefined' && google.maps) {
        initGoogleMaps();
        return;
    }
    
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_API_KEY}&libraries=places&callback=initGoogleMaps&language=es&region=AR`;
    script.async = true;
    script.defer = true;
    script.onerror = () => {
        mostrarToast('Error cargando Google Maps. Usa buscador alternativo.', 'error');
        mostrarBuscadorAlternativo();
    };
    
    document.head.appendChild(script);
}

window.initGoogleMaps = function() {
    if (typeof google === 'undefined') {
        mostrarToast('Google Maps no disponible', 'error');
        return;
    }
    
    try {
        // Inicializar autocomplete
        const input = document.getElementById('autocomplete');
        if (!input) return;
        
        // Configurar autocomplete para Argentina, enfocado en La Rioja
        const defaultBounds = new google.maps.LatLngBounds(
            new google.maps.LatLng(-30.0, -68.0), // Suroeste
            new google.maps.LatLng(-28.0, -66.0)  // Noreste
        );
        
        const opciones = {
            bounds: defaultBounds,
            types: ['address', 'geocode', 'establishment'],
            componentRestrictions: { country: 'ar' },
            fields: ['address_components', 'geometry', 'formatted_address', 'name']
        };
        
        autocompleteGoogle = new google.maps.places.Autocomplete(input, opciones);
        
        // Evento cuando se selecciona un lugar
        autocompleteGoogle.addListener('place_changed', function() {
            mostrarLoaderAutocomplete(true);
            const lugar = autocompleteGoogle.getPlace();
            
            if (!lugar.geometry) {
                mostrarToast('Ubicación no encontrada. Selecciona de la lista.', 'error');
                mostrarLoaderAutocomplete(false);
                return;
            }
            
            procesarLugarSeleccionado(lugar);
        });
        
        // Evento de teclado
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const infoUbicacion = document.getElementById('infoUbicacion');
                if (infoUbicacion) infoUbicacion.style.display = 'none';
            }
        });
        
        // Inicializar geocoder
        geocoderGoogle = new google.maps.Geocoder();
        
        // Inicializar mapa
        initMapGoogle();
        
        console.log('✅ Google Maps inicializado correctamente');
        
    } catch (error) {
        console.error('Error inicializando Google Maps:', error);
        mostrarToast('Error al inicializar el mapa', 'error');
    }
};

function initMapGoogle() {
    const centroInicial = { lat: -29.1619, lng: -67.4974 }; // Chilecito
    
    const mapaElement = document.getElementById('map');
    if (!mapaElement) return;
    
    mapaGoogle = new google.maps.Map(mapaElement, {
        center: centroInicial,
        zoom: 12,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: true,
        zoomControl: false,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });
    
    // Crear marcador inicial
    marcadorGoogle = new google.maps.Marker({
        map: mapaGoogle,
        position: centroInicial,
        draggable: true,
        animation: google.maps.Animation.DROP,
        title: 'Arrástrame para ajustar la ubicación',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10,
            fillColor: '#4285F4',
            fillOpacity: 1,
            strokeColor: '#FFF',
            strokeWeight: 2
        }
    });
    
    // Evento cuando se arrastra el marcador
    marcadorGoogle.addListener('dragend', function() {
        const pos = marcadorGoogle.getPosition();
        actualizarUbicacionDesdeMarcador(pos.lat(), pos.lng());
    });
    
    // Evento click en el mapa
    mapaGoogle.addListener('click', function(event) {
        colocarMarcadorEnGoogle(event.latLng);
    });
}

function procesarLugarSeleccionado(lugar) {
    try {
        const direccion = lugar.formatted_address;
        const lat = lugar.geometry.location.lat();
        const lng = lugar.geometry.location.lng();
        
        let ciudad = '';
        let provincia = '';
        let pais = '';
        let codigoPostal = '';
        
        // Extraer componentes de dirección
        lugar.address_components.forEach(component => {
            const tipos = component.types;
            
            if (tipos.includes('locality')) {
                ciudad = component.long_name;
            } else if (tipos.includes('administrative_area_level_1')) {
                provincia = component.long_name;
            } else if (tipos.includes('country')) {
                pais = component.long_name;
            } else if (tipos.includes('postal_code')) {
                codigoPostal = component.long_name;
            }
        });
        
        // Verificar que sea La Rioja
        if (provincia !== 'La Rioja') {
            mostrarToast('Por favor, selecciona una ubicación en La Rioja, Argentina', 'warning');
            mostrarLoaderAutocomplete(false);
            return;
        }
        
        // Actualizar campos ocultos
        actualizarCamposFormulario({
            direccion,
            latitud: lat,
            longitud: lng,
            ciudad,
            provincia,
            pais,
            codigo_postal: codigoPostal
        });
        
        // Actualizar display
        document.getElementById('textoDireccion').textContent = direccion;
        document.getElementById('textoCiudad').textContent = ciudad || '-';
        document.getElementById('textoProvincia').textContent = provincia || '-';
        document.getElementById('textoPais').textContent = pais || '-';
        document.getElementById('displayLatitud').textContent = lat.toFixed(6);
        document.getElementById('displayLongitud').textContent = lng.toFixed(6);
        
        // Mostrar secciones
        document.getElementById('infoUbicacion').style.display = 'block';
        document.getElementById('seccionMapa').style.display = 'block';
        document.getElementById('mensajeBusqueda').style.display = 'none';
        
        // Mover mapa y marcador
        const nuevaPosicion = new google.maps.LatLng(lat, lng);
        mapaGoogle.setCenter(nuevaPosicion);
        mapaGoogle.setZoom(16);
        marcadorGoogle.setPosition(nuevaPosicion);
        
        // Actualizar información del marcador
        marcadorGoogle.setTitle(direccion);
        
        mostrarToast('Ubicación seleccionada correctamente', 'success');
        mostrarLoaderAutocomplete(false);
        
    } catch (error) {
        console.error('Error procesando ubicación:', error);
        mostrarToast('Error al procesar la ubicación', 'error');
        mostrarLoaderAutocomplete(false);
    }
}

function actualizarCamposFormulario(datos) {
    Object.keys(datos).forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.value = datos[id];
        }
    });
}

function colocarMarcadorEnGoogle(latLng) {
    marcadorGoogle.setPosition(latLng);
    actualizarUbicacionDesdeMarcador(latLng.lat(), latLng.lng());
    
    // Obtener dirección desde coordenadas
    if (geocoderGoogle) {
        geocoderGoogle.geocode({ location: latLng }, function(results, status) {
            if (status === 'OK' && results[0]) {
                procesarLugarSeleccionado(results[0]);
            } else {
                // Si no encuentra dirección, usar coordenadas
                const direccion = `Ubicación (${latLng.lat().toFixed(6)}, ${latLng.lng().toFixed(6)})`;
                actualizarCamposFormulario({
                    direccion,
                    latitud: latLng.lat(),
                    longitud: latLng.lng(),
                    ciudad: '',
                    provincia: 'La Rioja',
                    pais: 'Argentina'
                });
                
                mostrarToast('Ubicación establecida. Puedes arrastrar el marcador.', 'info');
            }
        });
    }
}

function actualizarUbicacionDesdeMarcador(lat, lng) {
    const campoLat = document.getElementById('latitud');
    const campoLon = document.getElementById('longitud');
    const displayLat = document.getElementById('displayLatitud');
    const displayLon = document.getElementById('displayLongitud');
    
    if (campoLat) campoLat.value = lat;
    if (campoLon) campoLon.value = lng;
    if (displayLat) displayLat.textContent = lat.toFixed(6);
    if (displayLon) displayLon.textContent = lng.toFixed(6);
}

function mostrarLoaderAutocomplete(mostrar) {
    const loader = document.getElementById('loaderAutocomplete');
    if (loader) {
        loader.style.display = mostrar ? 'block' : 'none';
    }
}

// Funciones de controles del mapa
window.zoomInGoogle = function() {
    if (mapaGoogle) {
        mapaGoogle.setZoom(mapaGoogle.getZoom() + 1);
    }
};

window.zoomOutGoogle = function() {
    if (mapaGoogle) {
        mapaGoogle.setZoom(mapaGoogle.getZoom() - 1);
    }
};

window.centrarMarcadorGoogle = function() {
    if (mapaGoogle && marcadorGoogle) {
        mapaGoogle.panTo(marcadorGoogle.getPosition());
    }
};

window.marcadorArrastrableGoogle = function() {
    if (marcadorGoogle) {
        const arrastrable = marcadorGoogle.getDraggable();
        marcadorGoogle.setDraggable(!arrastrable);
        mostrarToast(
            arrastrable ? 'Marcador fijado' : 'Marcador listo para arrastrar',
            'info'
        );
    }
};

window.alternarTipoMapaGoogle = function() {
    if (mapaGoogle) {
        const tipos = ['roadmap', 'satellite', 'hybrid', 'terrain'];
        const indiceActual = tipos.indexOf(mapaGoogle.getMapTypeId());
        const nuevoTipo = tipos[(indiceActual + 1) % tipos.length];
        
        mapaGoogle.setMapTypeId(nuevoTipo);
        mostrarToast('Vista cambiada: ' + nuevoTipo, 'info');
    }
};

window.centrarEnMiUbicacion = function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                colocarMarcadorEnGoogle(new google.maps.LatLng(pos.lat, pos.lng));
                mostrarToast('Centrado en tu ubicación actual', 'success');
            },
            function() {
                mostrarToast('No se pudo obtener tu ubicación', 'error');
            }
        );
    } else {
        mostrarToast('Tu navegador no soporta geolocalización', 'error');
    }
};

window.limpiarUbicacionGoogle = function() {
    const input = document.getElementById('autocomplete');
    if (input) input.value = '';
    
    const campos = ['direccion', 'latitud', 'longitud', 'ciudad', 'provincia', 'pais', 'codigo_postal'];
    campos.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) campo.value = '';
    });
    
    document.getElementById('infoUbicacion').style.display = 'none';
    document.getElementById('seccionMapa').style.display = 'none';
    document.getElementById('mensajeBusqueda').style.display = 'block';
    
    if (marcadorGoogle) {
        marcadorGoogle.setMap(null);
        marcadorGoogle = null;
    }
    
    mostrarToast('Ubicación limpiada', 'info');
};

function mostrarBuscadorAlternativo() {
    // Solo si falla Google Maps
    mostrarToast('Usando buscador local', 'info');
}

function mostrarSugerenciasLocales(termino) {
    const listaSugerencias = document.getElementById('lista-sugerencias');
    if (!listaSugerencias) return;
    
    // Filtrar ubicaciones
    const sugerencias = ubicacionesLaRioja.filter(ubicacion => {
        const nombreLower = ubicacion.nombre.toLowerCase();
        const terminoLower = termino.toLowerCase();
        
        // Priorizar coincidencias exactas o que empiecen con el término
        if (nombreLower.startsWith(terminoLower)) {
            return true;
        }
        
        // También incluir coincidencias parciales
        return nombreLower.includes(terminoLower);
    }).slice(0, 15); // Limitar a 15 resultados
    
    if (sugerencias.length === 0) {
        listaSugerencias.innerHTML = `
            <div class="sugerencia-vacia">
                <i class="fa-solid fa-map-marker-alt"></i>
                <p>No se encontraron resultados para "${termino}"</p>
                <small>Intenta con otra búsqueda</small>
            </div>
        `;
        listaSugerencias.style.display = 'block';
        sugerenciasActuales = [];
        return;
    }
    
    // Ordenar: destacados primero, luego por relevancia
    sugerencias.sort((a, b) => {
        if (a.destacado && !b.destacado) return -1;
        if (!a.destacado && b.destacado) return 1;
        
        const aStartsWith = a.nombre.toLowerCase().startsWith(termino.toLowerCase());
        const bStartsWith = b.nombre.toLowerCase().startsWith(termino.toLowerCase());
        
        if (aStartsWith && !bStartsWith) return -1;
        if (!aStartsWith && bStartsWith) return 1;
        
        return a.nombre.localeCompare(b.nombre);
    });
    
    // Generar HTML de sugerencias
    let html = '';
    sugerencias.forEach((ubicacion, index) => {
        const icono = obtenerIconoTipo(ubicacion.tipo);
        const color = obtenerColorTipo(ubicacion.tipo);
        const destacado = ubicacion.destacado ? 'data-destacado="true"' : '';
        
        html += `
            <div class="sugerencia-item" 
                 data-index="${index}"
                 data-lat="${ubicacion.lat}"
                 data-lon="${ubicacion.lon}"
                 data-nombre="${ubicacion.nombre}"
                 ${destacado}
                 onclick="seleccionarUbicacionLocal(this)">
                <div class="icono-sugerencia" style="background: ${color}20; color: ${color};">
                    <i class="fa-solid ${icono}"></i>
                </div>
                <div class="info-sugerencia">
                    <div class="nombre-lugar">
                        ${ubicacion.nombre}
                        ${ubicacion.destacado ? '<span class="badge-ubicacion badge-nono">NONOGASTA</span>' : 
                          ubicacion.nombre.includes('Chilecito') ? '<span class="badge-ubicacion badge-chilecito">CHILECITO</span>' : ''}
                    </div>
                    <div class="detalle-lugar">
                        <span class="tipo-lugar">${ubicacion.tipo}</span>
                        <small><i class="fa-solid fa-location-dot"></i> La Rioja, Argentina</small>
                    </div>
                </div>
            </div>
        `;
    });
    
    listaSugerencias.innerHTML = html;
    listaSugerencias.style.display = 'block';
    sugerenciasActuales = sugerencias;
}

function navegarSugerencias(direccion) {
    const sugerencias = document.querySelectorAll('.sugerencia-item');
    if (sugerencias.length === 0) return;
    
    let indexActivo = -1;
    for (let i = 0; i < sugerencias.length; i++) {
        if (sugerencias[i].classList.contains('activa')) {
            indexActivo = i;
            break;
        }
    }
    
    // Remover activo actual
    sugerencias.forEach(s => s.classList.remove('activa'));
    
    // Calcular nuevo índice
    let nuevoIndex;
    if (direccion === 'ArrowDown') {
        nuevoIndex = indexActivo === -1 ? 0 : (indexActivo + 1) % sugerencias.length;
    } else {
        nuevoIndex = indexActivo === -1 ? sugerencias.length - 1 : 
                    (indexActivo - 1 + sugerencias.length) % sugerencias.length;
    }
    
    // Activar nueva sugerencia
    sugerencias[nuevoIndex].classList.add('activa');
    sugerencias[nuevoIndex].scrollIntoView({ block: 'nearest' });
}

function seleccionarSugerenciaActiva() {
    const sugerenciaActiva = document.querySelector('.sugerencia-item.activa');
    if (sugerenciaActiva) {
        seleccionarUbicacionLocal(sugerenciaActiva);
    }
}

function obtenerIconoTipo(tipo) {
    const iconos = {
        'pueblo': 'fa-house-chimney',
        'ciudad': 'fa-city',
        'barrio': 'fa-map-pin',
        'centro': 'fa-map-marker-alt',
        'localidad': 'fa-location-dot',
        'calle': 'fa-road',
        'ruta': 'fa-route',
        'institucion': 'fa-building',
        'hospital': 'fa-hospital',
        'terminal': 'fa-bus',
        'plaza': 'fa-tree',
        'quebrada': 'fa-mountain',
        'cerro': 'fa-mountain',
        'rio': 'fa-water'
    };
    return iconos[tipo] || 'fa-map-marker-alt';
}

function obtenerColorTipo(tipo) {
    const colores = {
        'pueblo': '#ff9800',
        'ciudad': '#2196f3',
        'barrio': '#9c27b0',
        'centro': '#4caf50',
        'localidad': '#ff5722',
        'calle': '#795548',
        'ruta': '#f44336',
        'institucion': '#3f51b5',
        'hospital': '#e91e63',
        'terminal': '#ff9800',
        'plaza': '#2e7d32',
        'quebrada': '#795548',
        'cerro': '#607d8b',
        'rio': '#2196f3'
    };
    return colores[tipo] || '#82b16d';
}

function seleccionarUbicacionLocal(elemento) {
    const lat = parseFloat(elemento.getAttribute('data-lat'));
    const lon = parseFloat(elemento.getAttribute('data-lon'));
    const nombre = elemento.getAttribute('data-nombre');
    
    // Actualizar campo de búsqueda
    const inputBusqueda = document.getElementById('buscar-direccion');
    if (inputBusqueda) {
        inputBusqueda.value = nombre;
    }
    
    // Ocultar sugerencias
    const listaSugerencias = document.getElementById('lista-sugerencias');
    if (listaSugerencias) {
        listaSugerencias.style.display = 'none';
    }

function actualizarUbicacionDesdeClick(lat, lng) {
    // Determinar el nombre basado en la ubicación
    let nombre = 'Ubicación personalizada';
    
    // Verificar distancia a puntos importantes
    const distanciaNonogasta = calcularDistancia(lat, lng, -29.2833, -67.5000);
    const distanciaChilecito = calcularDistancia(lat, lng, -29.1619, -67.4974);
    
    if (distanciaNonogasta < 2) { // Menos de 2km
        nombre = 'Nonogasta, La Rioja';
    } else if (distanciaChilecito < 5) { // Menos de 5km
        nombre = 'Chilecito, La Rioja';
    } else if (distanciaNonogasta < 10) {
        nombre = 'Cerca de Nonogasta, La Rioja';
    } else if (distanciaChilecito < 15) {
        nombre = 'Cerca de Chilecito, La Rioja';
    } else {
        nombre = `Ubicación (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
    }
    
    actualizarUbicacion(lat, lng, nombre);
}

function calcularDistancia(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radio de la Tierra en km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function actualizarUbicacion(lat, lng, nombre) {
    console.log("📝 Actualizando ubicación:", nombre);
    
    // Campos ocultos del formulario
    const campos = {
        'direccion': nombre,
        'latitud': lat,
        'longitud': lng,
        'ciudad': extraerCiudad(nombre),
        'provincia': 'La Rioja'
    };
    
    Object.keys(campos).forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.value = campos[id];
        }
    });
    
    // Campos visibles en el mapa
    const textoUbicacion = document.getElementById('texto-ubicacion-mapa');
    const textoLat = document.getElementById('texto-latitud');
    const textoLon = document.getElementById('texto-longitud');
    
    if (textoUbicacion) textoUbicacion.textContent = nombre;
    if (textoLat) textoLat.textContent = lat.toFixed(6);
    if (textoLon) textoLon.textContent = lng.toFixed(6);
    
    // Validar que la ubicación esté completa
    validarUbicacion();
}

function extraerCiudad(direccion) {
    if (direccion.includes('Nonogasta')) return 'Nonogasta';
    if (direccion.includes('Chilecito')) return 'Chilecito';
    if (direccion.includes('Anguinán')) return 'Anguinán';
    if (direccion.includes('Los Sarmientos')) return 'Los Sarmientos';
    if (direccion.includes('La Puntilla')) return 'La Puntilla';
    return 'La Rioja';
}

function validarUbicacion() {
    const direccion = document.getElementById('direccion');
    const btnEnviar = document.getElementById('btnEnviarFormulario');
    
    if (direccion && direccion.value && btnEnviar) {
        btnEnviar.disabled = false;
        return true;
    }
    return false;
}


// ============================================
// 5. FILTROS Y TARJETAS (MODIFICADO)
// ============================================

function inicializarFiltros() {
    // Inicializar filtro para tarjetas
    const filtroEstadoTarjetas = document.getElementById('filtroEstadoTarjetas');
    if (filtroEstadoTarjetas) {
        filtroEstadoTarjetas.addEventListener('change', filtrarPropiedadesTarjetas);
    }
    
    // Inicializar buscador para tarjetas
    const buscadorPropiedades = document.getElementById('buscadorPropiedades');
    if (buscadorPropiedades) {
        buscadorPropiedades.addEventListener('input', filtrarPropiedadesTarjetas);
    }
}

function filtrarPropiedadesTarjetas() {
    const busqueda = document.getElementById('buscadorPropiedades')?.value.toLowerCase() || '';
    const filtroEstado = document.getElementById('filtroEstadoTarjetas')?.value || 'todas';
    const tarjetas = document.querySelectorAll('.tarjeta-propiedad');
    let contador = 0;
    
    console.log(`🔍 Filtrando propiedades: busqueda="${busqueda}", estado="${filtroEstado}"`);
    
    tarjetas.forEach(tarjeta => {
        const titulo = tarjeta.getAttribute('data-titulo') || '';
        const descripcion = tarjeta.getAttribute('data-descripcion') || '';
        const direccion = tarjeta.getAttribute('data-direccion') || '';
        const estado = tarjeta.getAttribute('data-estado') || '';
        
        // Aplicar filtros
        const coincideBusqueda = !busqueda || 
            titulo.includes(busqueda) || 
            descripcion.includes(busqueda) || 
            direccion.includes(busqueda);
        
        const coincideEstado = filtroEstado === 'todas' || estado === filtroEstado;
        
        if (coincideBusqueda && coincideEstado) {
            tarjeta.style.display = 'block';
            contador++;
            
            // Animación de entrada
            tarjeta.style.animation = 'fadeInUp 0.5s ease forwards';
        } else {
            tarjeta.style.display = 'none';
        }
    });
    
    // Actualizar contador
    const contadorElement = document.getElementById('contadorPropiedadesMostradas');
    if (contadorElement) {
        contadorElement.textContent = contador;
    }
    
    // Mostrar mensaje si no hay resultados
    const contenedor = document.getElementById('contenedorTarjetas');
    let mensajeNoResultados = contenedor?.querySelector('.mensaje-no-resultados');
    
    if (contador === 0 && tarjetas.length > 0) {
        if (!mensajeNoResultados) {
            mensajeNoResultados = document.createElement('div');
            mensajeNoResultados.className = 'estado-vacio-propiedades mensaje-no-resultados';
            mensajeNoResultados.innerHTML = `
                <div class="icono-estado-vacio">
                    <i class="fa-solid fa-search"></i>
                </div>
                <h3>No se encontraron propiedades</h3>
                <p class="mensaje-estado-vacio">
                    No hay propiedades que coincidan con tu búsqueda. 
                    Intenta con otros términos o cambia el filtro.
                </p>
                <div class="acciones-estado-vacio">
                    <button class="boton-secundario grande" onclick="limpiarBusqueda()">
                        <i class="fa-solid fa-times"></i> Limpiar búsqueda
                    </button>
                </div>
            `;
            contenedor.appendChild(mensajeNoResultados);
        }
        mensajeNoResultados.style.display = 'block';
    } else if (mensajeNoResultados) {
        mensajeNoResultados.style.display = 'none';
    }
    
    console.log(`✅ Mostrando ${contador} de ${tarjetas.length} propiedades`);
}

window.limpiarBusqueda = function() {
    const buscador = document.getElementById('buscadorPropiedades');
    const filtro = document.getElementById('filtroEstadoTarjetas');
    
    if (buscador) buscador.value = '';
    if (filtro) filtro.value = 'todas';
    
    filtrarPropiedadesTarjetas();
    mostrarToast('Búsqueda limpiada', 'info');
};

// ============================================
// 8. FORMULARIO Y ENVÍO
// ============================================

function inicializarEventos() {
    const formulario = document.getElementById('formulario-propiedad');
    if (formulario) {
        formulario.addEventListener('submit', enviarFormulario);
    }
    
    // Botón para marcar todas las notificaciones como leídas
    const btnMarcarTodas = document.getElementById('btnMarcarTodasLeidas');
    if (btnMarcarTodas) {
        btnMarcarTodas.addEventListener('click', marcarTodasLeidas);
    }
}

async function enviarFormulario(e) {
    e.preventDefault();
    
    const formulario = e.target;
    const btnEnviar = document.getElementById('btnEnviarFormulario');
    
    if (!formulario || !btnEnviar) return;
    
    // Validaciones
    if (!validarFormulario()) {
        return false;
    }
    
    // Confirmación
    if (!confirm('¿Estás seguro de enviar la solicitud de propiedad?')) {
        return false;
    }
    
    // Mostrar loading
    const originalText = btnEnviar.innerHTML;
    btnEnviar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';
    btnEnviar.disabled = true;
    formulario.classList.add('formulario-loading');
    
    try {
        const formData = new FormData(formulario);
        
        // Enviar al servidor
        const response = await fetch('../database/guardar_propiedades.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            mostrarToast('✅ ' + resultado.message, 'success');
            
            // Resetear formulario
            formulario.reset();
            editarUbicacion();
            
            // Resetear imágenes
            const gridImagenes = document.getElementById('gridImagenes');
            if (gridImagenes) gridImagenes.innerHTML = '';
            
            const contadorSeleccionadas = document.getElementById('contadorSeleccionadas');
            if (contadorSeleccionadas) contadorSeleccionadas.textContent = '0';
            
            // Volver al inicio
            setTimeout(() => {
                mostrarSeccion('inicio');
            }, 1500);
            
        } else {
            mostrarToast('❌ Error: ' + (resultado.error || 'Error desconocido'), 'error');
        }
        
    } catch (error) {
        console.error('Error:', error);
        mostrarToast('❌ Error de conexión con el servidor', 'error');
        
    } finally {
        btnEnviar.innerHTML = originalText;
        btnEnviar.disabled = false;
        formulario.classList.remove('formulario-loading');
    }
}

function validarFormulario() {
    let errores = [];
    
    // Validar título
    const titulo = document.getElementById('titulo');
    if (!titulo.value.trim()) {
        errores.push('El título es obligatorio');
        titulo.classList.add('error');
    } else {
        titulo.classList.remove('error');
    }
    
    // Validar descripción
    const descripcion = document.getElementById('descripcion');
    if (!descripcion.value.trim()) {
        errores.push('La descripción es obligatoria');
        descripcion.classList.add('error');
    } else {
        descripcion.classList.remove('error');
    }
    
    // Validar ubicación
    const direccion = document.getElementById('direccion');
    if (!direccion.value) {
        errores.push('Debes seleccionar una ubicación');
    }
    
    // Validar imágenes
    const inputImagenes = document.getElementById('imagenes');
    if (!inputImagenes || inputImagenes.files.length === 0) {
        errores.push('Debes subir al menos una imagen');
    }
    
    // Mostrar errores si los hay
    if (errores.length > 0) {
        mostrarToast(errores.join('<br>'), 'error');
        return false;
    }
    
    return true;
}

// ============================================
// 9. NOTIFICACIONES TOAST
// ============================================

function mostrarToast(mensaje, tipo = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    // Crear toast
    const toast = document.createElement('div');
    toast.className = 'toast-content';
    
    // Icono según tipo
    let icono = 'fa-info-circle';
    if (tipo === 'success') icono = 'fa-check-circle';
    if (tipo === 'error') icono = 'fa-exclamation-circle';
    if (tipo === 'warning') icono = 'fa-exclamation-triangle';
    
    toast.innerHTML = `
        <div class="icono-toast" style="color: ${tipo === 'success' ? '#28a745' : tipo === 'error' ? '#dc3545' : '#ffc107'}">
            <i class="fa-solid ${icono}"></i>
        </div>
        <div class="texto-toast">${mensaje}</div>
        <button class="cerrar-toast" onclick="this.parentElement.remove()">
            <i class="fa-solid fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// ============================================
// 10. FUNCIONES AUXILIARES
// ============================================

function mostrarErrorMapa(mensaje) {
    const contenedorMapa = document.getElementById('mapa-propiedad');
    if (contenedorMapa) {
        contenedorMapa.innerHTML = `
            <div style="padding: 30px; text-align: center; background: #ffeaea; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 12px;">
                <div style="color: #d32f2f; font-size: 48px; margin-bottom: 20px;">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                </div>
                <h4 style="color: #b71c1c; margin-bottom: 10px;">Error al cargar el mapa</h4>
                <p style="color: #d32f2f; margin-bottom: 20px; max-width: 300px;">${mensaje || 'Error desconocido'}</p>
                <button onclick="inicializarMapaLaRioja()" 
                        style="background: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-redo"></i> Reintentar
                </button>
            </div>
        `;
    }
}

// Funciones para probar el sistema
window.probarNonogasta = function() {
    mostrarSeccion('formulario');
    setTimeout(() => {
        const input = document.getElementById('buscar-direccion');
        if (input) {
            input.value = 'Nonogasta';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }, 500);
};

window.probarChilecito = function() {
    mostrarSeccion('formulario');
    setTimeout(() => {
        const input = document.getElementById('buscar-direccion');
        if (input) {
            input.value = 'Chilecito';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }, 500);
};

// ============================================
// INICIALIZACIÓN FINAL
// ============================================

// Agregar estilos dinámicos si no existen
if (!document.getElementById('estilos-dinamicos')) {
    const estilos = document.createElement('style');
    estilos.id = 'estilos-dinamicos';
    estilos.textContent = `
        .error {
            border-color: #dc3545 !important;
            background-color: #fff5f5 !important;
        }
        
        .marcador-principal {
            animation: pulse 2s infinite;
        }
        
        .icono-toast {
            font-size: 20px;
        }
        
        .texto-toast {
            flex: 1;
            font-size: 14px;
        }
        
        .cerrar-toast {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }
        
        .cerrar-toast:hover {
            background: rgba(0,0,0,0.1);
        }
        
        .ver-mapa {
            color: #3498db;
            cursor: pointer;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        
        .ver-mapa:hover {
            text-decoration: underline;
        }
        
        .modal-mapa {
            max-width: 800px;
            width: 90%;
        }
        
        .info-mapa-modal {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        #mapa-modal {
            margin-bottom: 15px;
        }
    `;
    document.head.appendChild(estilos);
}

// ============================================
// 9. FUNCIONES PARA TARJETAS DE PROPIEDADES
// ============================================

window.verDetallesPropiedadTarjeta = function(id) {
    console.log(`🔍 Ver detalles de propiedad #${id}`);
    
    const modal = document.getElementById('modalDetallesPropiedad');
    const contenido = document.getElementById('detallesPropiedadContent');
    
    if (!modal || !contenido) {
        mostrarToast('Modal no encontrado', 'error');
        return;
    }
    
    // Mostrar loader
    contenido.innerHTML = `
        <div class="loader-detalles" style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #82b16d; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>Cargando detalles de la propiedad...</p>
        </div>
    `;
    
    modal.style.display = 'block';
    
    // Simulación de datos (reemplazar con llamada AJAX real)
    setTimeout(() => {
        contenido.innerHTML = `
            <div class="detalles-completos">
                <div class="cabecera-detalles">
                    <h4>Detalles completos de la propiedad</h4>
                    <div class="badge-estado-tarjeta estado-pendiente" style="display: inline-flex; margin-left: 15px;">
                        <i class="fa-solid fa-clock"></i>
                        <span>Pendiente</span>
                    </div>
                </div>
                
                <div class="grid-detalles">
                    <div class="seccion-detalles">
                        <h5><i class="fa-solid fa-info-circle"></i> Información básica</h5>
                        <div class="info-grid">
                            <div class="info-item">
                                <strong>ID:</strong>
                                <span>#${id}</span>
                            </div>
                            <div class="info-item">
                                <strong>Fecha de solicitud:</strong>
                                <span>15/12/2024</span>
                            </div>
                            <div class="info-item">
                                <strong>Ambientes:</strong>
                                <span>3</span>
                            </div>
                            <div class="info-item">
                                <strong>Baños:</strong>
                                <span>1</span>
                            </div>
                            <div class="info-item">
                                <strong>Superficie:</strong>
                                <span>80 m²</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seccion-detalles">
                        <h5><i class="fa-solid fa-dollar-sign"></i> Información financiera</h5>
                        <div class="info-grid">
                            <div class="info-item">
                                <strong>Precio mensual:</strong>
                                <span>$120,000</span>
                            </div>
                            <div class="info-item">
                                <strong>Estado del precio:</strong>
                                <span>Publicado</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seccion-detalles ancho-completo">
                        <h5><i class="fa-solid fa-map-marker-alt"></i> Ubicación</h5>
                        <p><strong>Dirección:</strong> Calle Ejemplo 123, Chilecito, La Rioja</p>
                        <p><strong>Coordenadas:</strong> -29.161900, -67.497400</p>
                        <button class="boton-mapa" onclick="verMapaPropiedadTarjeta(${id}, -29.1619, -67.4974, 'Propiedad #${id}')" style="margin-top: 10px;">
                            <i class="fa-solid fa-map"></i> Ver en mapa
                        </button>
                    </div>
                    
                    <div class="seccion-detalles ancho-completo">
                        <h5><i class="fa-solid fa-align-left"></i> Descripción completa</h5>
                        <div class="descripcion-completa">
                            <p>Esta es la descripción completa de la propiedad. Aquí debería aparecer toda la información detallada que el propietario ingresó al crear la propiedad.</p>
                            <p>Incluye detalles sobre características especiales, servicios, estado de conservación, y cualquier otra información relevante.</p>
                        </div>
                    </div>
                    
                    <div class="seccion-detalles ancho-completo">
                        <h5><i class="fa-solid fa-images"></i> Imágenes</h5>
                        <div class="galeria-imagenes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-top: 10px;">
                            <div class="imagen-galeria" style="background-image: url('https://images.unsplash.com/photo-1518780664697-55e3ad937233'); background-size: cover; height: 150px; border-radius: 8px;"></div>
                            <div class="imagen-galeria" style="background-image: url('https://images.unsplash.com/photo-1560448204-e02f11c3d0e2'); background-size: cover; height: 150px; border-radius: 8px;"></div>
                            <div class="imagen-galeria" style="background-image: url('https://images.unsplash.com/photo-1570129477492-45c003edd2be'); background-size: cover; height: 150px; border-radius: 8px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="acciones-detalles" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px;">
                    <button class="boton-ver" onclick="verMapaPropiedadTarjeta(${id}, -29.1619, -67.4974, 'Propiedad #${id}')">
                        <i class="fa-solid fa-map"></i> Ver en mapa
                    </button>
                    <button class="boton-secundario" onclick="cerrarModalDetalles()">
                        <i class="fa-solid fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        `;
    }, 1000);
};

window.editarPropiedadTarjeta = function(id) {
    console.log(`✏️ Editando propiedad #${id}`);
    
    // Aquí deberías redirigir a un formulario de edición
    // o cargar el formulario existente con los datos
    mostrarToast(`Editando propiedad #${id}`, 'info');
    
    // Ejemplo de implementación:
    // 1. Obtener datos de la propiedad por AJAX
    // 2. Cargarlos en el formulario existente
    // 3. Cambiar a la sección de formulario
    // 4. Mostrar botón "Actualizar" en lugar de "Enviar"
    
    setTimeout(() => {
        mostrarToast('Funcionalidad de edición en desarrollo. Implementar llamada AJAX.', 'warning');
    }, 500);
};

window.verMapaPropiedadTarjeta = function(id, lat, lng, titulo) {
    console.log(`🗺️ Mostrando mapa de propiedad #${id}`);
    
    const modal = document.getElementById('modalMapaPropiedad');
    const tituloElement = document.getElementById('titulo-mapa-modal');
    const direccionElement = document.getElementById('direccion-mapa-modal');
    
    if (!modal || !tituloElement || !direccionElement) {
        mostrarToast('Modal de mapa no encontrado', 'error');
        return;
    }
    
    modal.style.display = 'block';
    tituloElement.textContent = titulo || `Propiedad #${id}`;
    direccionElement.textContent = `Coordenadas: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    
    // Usar el mapa Leaflet ya existente (no Google Maps para ver)
    if (!window.mapaModal) {
        window.mapaModal = L.map('mapa-modal').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(window.mapaModal);
    } else {
        window.mapaModal.setView([lat, lng], 15);
    }
    
    // Limpiar marcadores anteriores
    window.mapaModal.eachLayer(function(layer) {
        if (layer instanceof L.Marker) {
            window.mapaModal.removeLayer(layer);
        }
    });
    
    // Añadir nuevo marcador
    L.marker([lat, lng])
        .addTo(window.mapaModal)
        .bindPopup(`<b>${titulo || 'Propiedad'}</b><br>ID: ${id}`)
        .openPopup();
};

window.cerrarModalDetalles = function() {
    const modal = document.getElementById('modalDetallesPropiedad');
    if (modal) {
        modal.style.display = 'none';
    }
};

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modalDetallesPropiedad');
    const modalMapa = document.getElementById('modalMapaPropiedad');
    
    if (modal && event.target == modal) {
        cerrarModalDetalles();
    }
    
    if (modalMapa && event.target == modalMapa) {
        cerrarModalMapa();
    }
});


// ============================================
// FUNCIONES PARA COMENTARIOS - VERSIÓN CORREGIDA
// ============================================

// Inicializar comentarios cuando se muestre la sección
function inicializarComentarios() {
    console.log("💬 Inicializando filtros de comentarios...");
    
    // Filtros rápidos - CORREGIDO
    const filtros = document.querySelectorAll('.filtro-rapido');
    if (filtros.length > 0) {
        console.log(`✅ Encontrados ${filtros.length} botones de filtro`);
        
        filtros.forEach(btn => {
            // Remover eventos anteriores para evitar duplicados
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            // Agregar nuevo evento
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log(`🎯 Filtro clickeado: ${this.dataset.filtro}`);
                
                // Remover activo de todos
                document.querySelectorAll('.filtro-rapido').forEach(b => {
                    b.classList.remove('activo');
                });
                
                // Activar el clickeado
                this.classList.add('activo');
                
                // Aplicar filtro
                filtrarComentarios(this.dataset.filtro);
            });
        });
    } else {
        console.warn("⚠️ No se encontraron botones de filtro");
    }
    
    // Buscador
    const buscador = document.getElementById('buscadorComentarios');
    if (buscador) {
        buscador.addEventListener('input', function() {
            console.log(`🔍 Buscando: ${this.value}`);
            filtrarComentariosPorBusqueda(this.value.toLowerCase());
        });
    }
    
    // Aplicar filtro inicial
    filtrarComentarios('todos');
}

// Filtrar comentarios por tipo - CORREGIDO
function filtrarComentarios(filtro) {
    console.log(`🔧 Aplicando filtro: ${filtro}`);
    
    const tarjetas = document.querySelectorAll('.tarjeta-comentario');
    let contadorMostradas = 0;
    
    tarjetas.forEach(tarjeta => {
        const rating = parseInt(tarjeta.dataset.rating) || 0;
        const favorito = tarjeta.dataset.favorito === 'si';
        const esNoLeido = tarjeta.classList.contains('no-leido');
        
        let mostrar = true;
        
        switch(filtro) {
            case 'no-leidos':
                mostrar = esNoLeido;
                break;
            case '5-estrellas':
                mostrar = rating === 5;
                break;
            case 'favoritos':
                mostrar = favorito;
                break;
            case 'todos':
            default:
                mostrar = true;
        }
        
        if (mostrar) {
            tarjeta.style.display = 'block';
            tarjeta.style.animation = 'fadeInUp 0.3s ease forwards';
            contadorMostradas++;
        } else {
            tarjeta.style.display = 'none';
        }
    });
    
    console.log(`✅ Mostrando ${contadorMostradas} de ${tarjetas.length} comentarios`);
    
    // Mostrar mensaje si no hay resultados
    mostrarMensajeSinResultados(contadorMostradas, filtro);
}

// Mostrar mensaje cuando no hay resultados
function mostrarMensajeSinResultados(contador, filtro) {
    const contenedor = document.getElementById('contenedorComentarios');
    let mensaje = contenedor.querySelector('.mensaje-sin-resultados');
    
    if (contador === 0) {
        if (!mensaje) {
            mensaje = document.createElement('div');
            mensaje.className = 'estado-vacio-comentarios mensaje-sin-resultados';
            
            let texto = '';
            switch(filtro) {
                case 'no-leidos':
                    texto = 'No tenés comentarios sin leer';
                    break;
                case '5-estrellas':
                    texto = 'No tenés comentarios con 5 estrellas';
                    break;
                case 'favoritos':
                    texto = 'No tenés comentarios con favoritos';
                    break;
                default:
                    texto = 'No hay comentarios que coincidan';
            }
            
            mensaje.innerHTML = `
                <div class="icono-estado-vacio">
                    <i class="fa-solid fa-search"></i>
                </div>
                <h3>${texto}</h3>
                <p class="mensaje-estado-vacio">
                    Intenta con otro filtro o cambia los términos de búsqueda.
                </p>
                <div class="acciones-estado-vacio">
                    <button class="boton-secundario" onclick="document.querySelector('.filtro-rapido[data-filtro=\"todos\"]').click()">
                        <i class="fa-solid fa-rotate-left"></i> Ver todos
                    </button>
                </div>
            `;
            
            contenedor.appendChild(mensaje);
        }
        mensaje.style.display = 'block';
    } else if (mensaje) {
        mensaje.style.display = 'none';
    }
}

// Filtrar comentarios por búsqueda - CORREGIDO
function filtrarComentariosPorBusqueda(termino) {
    console.log(`🔍 Filtrando por búsqueda: "${termino}"`);
    
    const tarjetas = document.querySelectorAll('.tarjeta-comentario');
    let contadorCoincidencias = 0;
    
    if (!termino) {
        // Mostrar todas y aplicar filtro actual
        const filtroActual = document.querySelector('.filtro-rapido.activo');
        const filtro = filtroActual ? filtroActual.dataset.filtro : 'todos';
        filtrarComentarios(filtro);
        return;
    }
    
    tarjetas.forEach(tarjeta => {
        const usuario = tarjeta.dataset.usuario || '';
        const comentario = tarjeta.dataset.comentario || '';
        const propiedad = tarjeta.dataset.propiedad || '';
        
        const coincide = usuario.includes(termino) || 
                        comentario.includes(termino) || 
                        propiedad.includes(termino);
        
        if (coincide) {
            tarjeta.style.display = 'block';
            contadorCoincidencias++;
            
            // Resaltar término buscado
            resaltarTextoEnComentario(tarjeta, termino);
        } else {
            tarjeta.style.display = 'none';
        }
    });
    
    console.log(`✅ ${contadorCoincidencias} comentarios coinciden con la búsqueda`);
}

// Resaltar texto en comentario (opcional, para mejor UX)
function resaltarTextoEnComentario(tarjeta, termino) {
    const textoComentario = tarjeta.querySelector('.texto-comentario');
    if (textoComentario) {
        const textoOriginal = textoComentario.textContent;
        const regex = new RegExp(`(${termino})`, 'gi');
        const textoResaltado = textoOriginal.replace(regex, '<mark class="resaltado-busqueda">$1</mark>');
        textoComentario.innerHTML = `"${textoResaltado}"`;
    }
}

// También modifica la función mostrarSeccion para inicializar comentarios:
function mostrarSeccion(seccionId) {
    // ... tu código existente ...
    
    // INICIALIZAR COMENTARIOS SI ESTAMOS EN ESA SECCIÓN
    if (seccionId === 'comentarios') {
        setTimeout(() => {
            console.log("📱 Sección comentarios activa - inicializando...");
            inicializarComentarios();
        }, 300); // Aumentado a 300ms para asegurar que el DOM esté listo
    }
}

// ============================================
// FUNCIONES PARA NOTIFICACIONES - CON TU SISTEMA
// ============================================

// Marcar notificación como leída
function marcarNotificacionLeida(id, boton) {
    console.log(`Marcando notificación ${id} como leída...`);
    
    // Enviar solicitud al servidor usando TU archivo PHP
    fetch('../database/marcar_notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'marcar_leida',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar UI
            const tarjeta = boton.closest('.tarjeta-notificacion-historico');
            if (tarjeta) {
                tarjeta.classList.remove('no-leida');
                tarjeta.classList.add('leida');
                tarjeta.setAttribute('data-leida', '1');
                
                // Quitar indicador de no leída
                const indicador = tarjeta.querySelector('.indicador-no-leida');
                if (indicador) {
                    indicador.remove();
                }
                
                // Cambiar botón a "marcar como no leída"
                const nuevoBoton = document.createElement('button');
                nuevoBoton.className = 'btn-marcar-no-leida';
                nuevoBoton.title = 'Marcar como no leída';
                nuevoBoton.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
                nuevoBoton.onclick = function() {
                    marcarNotificacionNoLeida(id, this);
                };
                
                boton.parentNode.replaceChild(nuevoBoton, boton);
                
                // Actualizar contadores
                actualizarContadoresNotificaciones();
                
                // Actualizar badge en barra lateral
                const noLeidas = document.querySelectorAll('.tarjeta-notificacion-historico.no-leida').length;
                actualizarBadgeNotificaciones(noLeidas);
                
                // Mostrar toast de confirmación
                mostrarToast('✅ Notificación marcada como leída', 'success');
            }
        } else {
            mostrarToast('❌ Error: ' + (data.error || 'No se pudo marcar como leída'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarToast('❌ Error de conexión', 'error');
    });
}

// Marcar notificación como NO leída
function marcarNotificacionNoLeida(id, boton) {
    console.log(`Marcando notificación ${id} como NO leída...`);
    
    // NOTA: Tu PHP actual no tiene esta función, podemos implementarla de dos formas:
    
    // Opción 1: Crear una nueva función en PHP (mejor)
    // Opción 2: Hacerlo directamente con SQL aquí (alternativa rápida)
    
    // Por ahora, mostramos mensaje informativo
    mostrarToast('ℹ️ Para marcar como no leída, actualiza el sistema', 'info');
    
    // Si quieres implementarlo rápido, puedes usar esto (pero necesitarías modificar tu PHP):
    /*
    fetch('../database/marcar_notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'marcar_no_leida',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar UI (similar a marcarLeida pero al revés)
        }
    });
    */
}

// Marcar TODAS las notificaciones como leídas
function marcarTodasLeidas() {
    console.log('Marcando TODAS las notificaciones como leídas...');
    
    if (!confirm('¿Estás seguro de marcar todas las notificaciones como leídas?')) {
        return;
    }
    
    // Mostrar indicador de carga
    const btn = document.querySelector('.btn-accion-notif');
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';
    btn.disabled = true;
    
    // Enviar solicitud al servidor usando TU archivo PHP
    fetch('../database/marcar_notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'marcar_todas_leidas'
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
        
        if (data.success) {
            // Actualizar TODAS las notificaciones en la UI
            const notificaciones = document.querySelectorAll('.tarjeta-notificacion-historico');
            
            notificaciones.forEach(notif => {
                // Actualizar clases
                notif.classList.remove('no-leida');
                notif.classList.add('leida');
                notif.setAttribute('data-leida', '1');
                
                // Quitar indicadores de no leída
                const indicador = notif.querySelector('.indicador-no-leida');
                if (indicador) {
                    indicador.remove();
                }
                
                // Actualizar botones
                const contenedorAcciones = notif.querySelector('.acciones-notificacion-historico');
                const nuevoBoton = document.createElement('button');
                nuevoBoton.className = 'btn-marcar-no-leida';
                nuevoBoton.title = 'Marcar como no leída';
                nuevoBoton.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
                const id = notif.getAttribute('data-id');
                nuevoBoton.onclick = function() {
                    marcarNotificacionNoLeida(id, this);
                };
                
                // Reemplazar el botón actual
                contenedorAcciones.innerHTML = '';
                contenedorAcciones.appendChild(nuevoBoton);
            });
            
            // Actualizar contadores
            actualizarContadoresNotificaciones();
            
            // Actualizar badge en barra lateral a 0
            actualizarBadgeNotificaciones(0);
            
            // Mostrar toast de confirmación
            mostrarToast('✅ ' + data.message, 'success');
        } else {
            mostrarToast('❌ Error: ' + (data.error || 'No se pudieron marcar todas'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
        mostrarToast('❌ Error de conexión', 'error');
    });
}

// Función para actualizar badge en tiempo real
function actualizarBadgeNotificaciones(cantidad) {
    // Quitar completamente el badge (como pediste)
    const badge = document.querySelector('#nav-notificaciones .badge.nuevo');
    if (badge) {
        badge.remove(); // Elimina completamente el badge
    }
    
    // También actualizar el contador en el icono de la cabecera
    const contadorCabecera = document.querySelector('.contador-notificacion');
    if (contadorCabecera) {
        if (cantidad > 0) {
            contadorCabecera.textContent = cantidad;
            contadorCabecera.style.display = 'flex';
        } else {
            contadorCabecera.style.display = 'none';
        }
    }
}

// Ver propiedad desde notificación
function verPropiedadDesdeNotificacion(idPropiedad) {
    console.log(`Abriendo propiedad ${idPropiedad} desde notificación...`);
    
    // Buscar la propiedad en la sección de propiedades
    mostrarSeccion('propiedades');
    
    // Esperar a que se cargue la sección
    setTimeout(() => {
        // Buscar todas las tarjetas de propiedades
        const tarjetas = document.querySelectorAll('.tarjeta-propiedad');
        let propiedadEncontrada = null;
        
        tarjetas.forEach(tarjeta => {
            // Buscar botones que tengan el id de la propiedad
            const botones = tarjeta.querySelectorAll('button');
            botones.forEach(boton => {
                const onclick = boton.getAttribute('onclick') || '';
                if (onclick.includes(idPropiedad.toString())) {
                    propiedadEncontrada = tarjeta;
                }
            });
        });
        
        if (propiedadEncontrada) {
            // Simular clic en el botón "Ver"
            const botonVer = propiedadEncontrada.querySelector('.boton-ver');
            if (botonVer) {
                botonVer.click();
                mostrarToast('✅ Propiedad encontrada', 'success');
            } else {
                mostrarToast('⚠️ No se pudo abrir la propiedad', 'warning');
            }
        } else {
            mostrarToast('⚠️ Propiedad no encontrada en la lista', 'warning');
            
            // Alternativa: Mostrar mensaje con opción de búsqueda
            const buscarBtn = document.createElement('button');
            buscarBtn.className = 'boton-principal';
            buscarBtn.innerHTML = '<i class="fa-solid fa-search"></i> Buscar propiedad';
            buscarBtn.onclick = function() {
                // Poner el ID en el buscador de propiedades
                const buscador = document.getElementById('buscadorPropiedades');
                if (buscador) {
                    buscador.value = `ID: ${idPropiedad}`;
                    filtrarPropiedadesTarjetas();
                }
            };
            
            // Mostrar en un toast especial
            mostrarToastPersonalizado(
                'No se encontró la propiedad en la lista actual',
                'warning',
                buscarBtn
            );
        }
    }, 500); // Pequeño delay para asegurar que la sección se cargó
}

// Función para mostrar toast personalizado
function mostrarToastPersonalizado(mensaje, tipo, elementoExtra) {
    const toastContainer = document.getElementById('toastContainer');
    
    const toast = document.createElement('div');
    toast.className = `toast-content toast-${tipo}`;
    
    const contenido = document.createElement('div');
    contenido.style.display = 'flex';
    contenido.style.flexDirection = 'column';
    contenido.style.gap = '10px';
    
    const texto = document.createElement('div');
    texto.style.display = 'flex';
    texto.style.alignItems = 'center';
    texto.style.gap = '10px';
    texto.innerHTML = `
        <i class="fa-solid ${tipo === 'success' ? 'fa-check-circle' : tipo === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${mensaje}</span>
    `;
    
    contenido.appendChild(texto);
    if (elementoExtra) {
        contenido.appendChild(elementoExtra);
    }
    
    toast.appendChild(contenido);
    toastContainer.appendChild(toast);
    
    // Remover después de 5 segundos
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Inicializar sistema de notificaciones (llamar desde DOMContentLoaded)
function inicializarSistemaNotificaciones() {
    console.log("🔔 Inicializando sistema de notificaciones...");
    
    // 1. Inicializar buscador
    inicializarBuscadorNotificaciones();
    
    // 2. Inicializar filtros
    inicializarFiltrosNotificaciones();
    
    // 3. Aplicar filtros iniciales
    aplicarFiltrosNotificaciones();
    
    // 4. Eliminar badge de la barra lateral (como pediste)
    eliminarBadgeBarraLateral();
    
    console.log("✅ Sistema de notificaciones inicializado");
}

// Función para eliminar badge de barra lateral
function eliminarBadgeBarraLateral() {
    const badges = document.querySelectorAll('#nav-notificaciones .badge.nuevo');
    badges.forEach(badge => badge.remove());
}

// Agregar al final de tu archivo propietario.js
document.addEventListener('DOMContentLoaded', function() {
    // ... tu código existente ...
    
    // Inicializar sistema de notificaciones
    if (document.getElementById('sec-notificaciones')) {
        inicializarSistemaNotificaciones();
    }
});

// Verificación final
setTimeout(() => {
    console.log("=== ✅ SISTEMA RENTNONO LISTO ===");
    console.log("🎯 Enfoque en: Nonogasta y Chilecito, La Rioja");
    console.log("🗺️  Sistema de mapas: Listo");
    console.log("🖼️  Subida de imágenes: Listo");
    console.log("🔍  Buscador de ubicaciones: Listo");
    console.log("📊 " + ubicacionesLaRioja.length + " ubicaciones disponibles");
    console.log("💡 Usa probarNonogasta() o probarChilecito() para probar");
    console.log("=================================");
}, 1000);}