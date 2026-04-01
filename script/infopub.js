// infopub.js - Funciones para información de publicaciones
// Este archivo puede estar vacío o contener funciones específicas para publicaciones

console.log('infopub.js cargado');

// Función para mostrar información detallada de una publicación
function mostrarInfoPublicacion(id) {
    console.log('Mostrando información de publicación:', id);
    // Aquí puedes agregar lógica para mostrar modales o redireccionar
}

// Función para compartir publicación
function compartirPublicacion(url, titulo) {
    if (navigator.share) {
        navigator.share({
            title: titulo,
            url: url
        });
    } else {
        // Fallback para navegadores que no soportan Web Share API
        alert('Comparte esta publicación: ' + url);
    }
}