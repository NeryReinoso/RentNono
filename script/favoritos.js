// script/favoritos.js - Funcionalidad reutilizable
class GestorFavoritos {
    constructor() {
        this.estaLogueado = false;
        this.esVisitante = false;
        this.init();
    }
    
    init() {
        // Verificar estado de sesión
        this.estaLogueado = typeof usuarioLogueado !== 'undefined' ? usuarioLogueado : false;
        this.esVisitante = typeof esVisitante !== 'undefined' ? esVisitante : false;
        
        this.agregarEventos();
    }
    
    agregarEventos() {
        document.addEventListener('click', (e) => {
            const btnFav = e.target.closest('.btn-fav, .fav-btn');
            if (btnFav) {
                this.toggleFavorito(btnFav);
            }
            
            // Evitar que los enlaces de publicaciones se activen al hacer click en el botón favorito
            const linkPub = e.target.closest('.publicacion-link');
            if (linkPub && e.target.closest('.btn-fav, .fav-btn')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }
    
    toggleFavorito(btn) {
        const idPublicacion = btn.dataset.id;
        
        // CORRECCIÓN: Verificación correcta del rol de visitante
        if (!this.estaLogueado || this.esVisitante !== true) {
            this.abrirLogin();
            return;
        }
        
        // Animación y toggle visual
        btn.classList.toggle('active');
        btn.classList.add('animating');
        
        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-regular');
            icon.classList.toggle('fa-solid');
        }
        
        // Llamada AJAX
        fetch('../database/favoritos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `accion=toggle&id_publicacion=${idPublicacion}`
        })
        .then(res => res.json())
        .then(data => this.procesarRespuesta(data, btn))
        .catch(err => this.revertirCambios(btn, btn.querySelector('i')))
        .finally(() => {
            setTimeout(() => btn.classList.remove('animating'), 800);
        });
    }
    
    procesarRespuesta(data, btn) {
        if (data.error) {
            console.error('Error:', data.error);
            this.revertirCambios(btn, btn.querySelector('i'));
        } else if (data.success) {
            // Actualizar contador si existe
            const card = btn.closest('.pub-card, .feature-item');
            if (card) {
                const favCount = card.querySelector('.fav-count');
                if (data.accion === 'agregado') {
                    if (favCount) {
                        const currentCount = parseInt(favCount.textContent.match(/\d+/)[0]) || 0;
                        favCount.innerHTML = `<i class="fas fa-heart"></i> ${currentCount + 1}`;
                    } else {
                        const newCount = document.createElement('span');
                        newCount.className = 'fav-count';
                        newCount.innerHTML = `<i class="fas fa-heart"></i> 1`;
                        card.prepend(newCount);
                    }
                } else if (data.accion === 'eliminado' && favCount) {
                    const currentCount = parseInt(favCount.textContent.match(/\d+/)[0]) || 0;
                    if (currentCount - 1 <= 0) {
                        favCount.remove();
                    } else {
                        favCount.innerHTML = `<i class="fas fa-heart"></i> ${currentCount - 1}`;
                    }
                }
            }
        }
    }
    
    revertirCambios(btn, icon) {
        btn.classList.toggle('active');
        if (icon) {
            icon.classList.toggle('fa-regular');
            icon.classList.toggle('fa-solid');
        }
    }
    
    abrirLogin() {
        const modalLogin = document.getElementById('modalFondoLogin');
        if (modalLogin) {
            modalLogin.style.display = 'flex';
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Declarar variables globales para que GestorFavoritos las pueda usar
    window.usuarioLogueado = typeof usuarioLogueado !== 'undefined' ? usuarioLogueado : false;
    window.esVisitante = typeof esVisitante !== 'undefined' ? esVisitante : false;
    
    window.gestorFavoritos = new GestorFavoritos();
});