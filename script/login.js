// LOGICA DE VENTANAS FLOTANTES MEJORADA

const abrirLogin = document.getElementById('abrirLogin');
const cerrarLogin = document.getElementById('cerrarLogin');
const modalFondoLogin = document.getElementById('modalFondoLogin');

const abrirRegistroPropietario = document.getElementById('abrirRegistroPropietario');
const cerrarRegistroPropietario = document.getElementById('cerrarRegistroPropietario');
const modalFondoRegistroPropietario = document.getElementById('modalFondoRegistroPropietario');

const abrirRegistroVisitante = document.getElementById('abrirRegistroVisitante');
const cerrarRegistroVisitante = document.getElementById('cerrarRegistroVisitante');
const modalFondoRegistroVisitante = document.getElementById('modalFondoRegistroVisitante');

// Botones de volver
const btnVolverPropietario = document.getElementById('btnVolverPropietario');
const btnVolverVisitante = document.getElementById('btnVolverVisitante');

// Botones para ver contraseña
const btnsVerPassword = document.querySelectorAll('.btn-ver-password');

// Abrir y cerrar Login
if (abrirLogin) {
    abrirLogin.onclick = () => {
        modalFondoLogin.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
}

if (cerrarLogin) {
    cerrarLogin.onclick = () => {
        modalFondoLogin.style.display = 'none';
        document.body.style.overflow = 'auto';
    };
}

// Abrir Registro Propietario desde Login
if (abrirRegistroPropietario) {
    abrirRegistroPropietario.onclick = () => {
        modalFondoLogin.style.display = 'none';
        modalFondoRegistroPropietario.style.display = 'flex';
    };
}

if (cerrarRegistroPropietario) {
    cerrarRegistroPropietario.onclick = () => {
        modalFondoRegistroPropietario.style.display = 'none';
        document.body.style.overflow = 'auto';
    };
}

// Abrir Registro Visitante desde Login
if (abrirRegistroVisitante) {
    abrirRegistroVisitante.onclick = () => {
        modalFondoLogin.style.display = 'none';
        modalFondoRegistroVisitante.style.display = 'flex';
    };
}

if (cerrarRegistroVisitante) {
    cerrarRegistroVisitante.onclick = () => {
        modalFondoRegistroVisitante.style.display = 'none';
        document.body.style.overflow = 'auto';
    };
}

// Botones de volver
if (btnVolverPropietario) {
    btnVolverPropietario.onclick = () => {
        modalFondoRegistroPropietario.style.display = 'none';
        modalFondoLogin.style.display = 'flex';
    };
}

if (btnVolverVisitante) {
    btnVolverVisitante.onclick = () => {
        modalFondoRegistroVisitante.style.display = 'none';
        modalFondoLogin.style.display = 'flex';
    };
}

// Función para mostrar/ocultar contraseña
if (btnsVerPassword) {
    btnsVerPassword.forEach(btn => {
        btn.addEventListener('click', function() {
            const inputId = this.getAttribute('data-target');
            const input = document.getElementById(inputId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

// Cerrar modales al hacer click fuera
window.addEventListener('click', (e) => {
    if(e.target === modalFondoLogin) {
        modalFondoLogin.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    if(e.target === modalFondoRegistroPropietario) {
        modalFondoRegistroPropietario.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    if(e.target === modalFondoRegistroVisitante) {
        modalFondoRegistroVisitante.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Función para mostrar mensaje de "Olvidé mi contraseña"
function mostrarOlvidoPassword() {
    const mensaje = document.createElement('div');
    mensaje.className = 'mensaje-flotante';
    mensaje.innerHTML = `
        <i class="fas fa-envelope" style="margin-right: 10px;"></i>
        Se ha enviado un enlace de recuperación a tu correo electrónico.
        <br><small>Por favor, revisa tu bandeja de entrada.</small>
    `;
    document.body.appendChild(mensaje);
    
    mensaje.style.display = 'block';
    
    setTimeout(() => {
        mensaje.style.display = 'none';
        mensaje.remove();
    }, 5000);
}

// Agregar evento al enlace "¿Olvidaste tu contraseña?"
document.addEventListener('DOMContentLoaded', function() {
    const enlaceOlvido = document.querySelector('.enlace-olvido');
    if (enlaceOlvido) {
        enlaceOlvido.addEventListener('click', function(e) {
            e.preventDefault();
            mostrarOlvidoPassword();
        });
    }
});


// LOGICA PARA LOGIN CON GOOGLE

// Inicializar botón de Google
function initGoogleLogin() {
    const googleBtn = document.getElementById('googleLoginBtn');
    
    if (googleBtn) {
        googleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Redirigir a la página de autenticación de Google
            window.location.href = 'database/google_auth_init.php';
        });
    }
}

// Llamar a la función cuando se cargue el DOM
document.addEventListener('DOMContentLoaded', function() {
    initGoogleLogin();
});

// Añadir estilos para el botón de Google si no existen
if (!document.querySelector('#googleBtnStyles')) {
    const style = document.createElement('style');
    style.id = 'googleBtnStyles';
    style.textContent = `
        .btn-google {
            width: 100%;
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-bottom: 20px;
        }
        
        .btn-google:hover {
            background: #f8f9fa;
            border-color: #ccc;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-google i {
            color: #DB4437;
            margin-right: 10px;
            font-size: 18px;
        }
        
        .google-login-container {
            padding: 20px 30px 0 30px;
            text-align: center;
        }
        
        .google-login-container .divider {
            margin: 20px 0;
            position: relative;
            text-align: center;
        }
        
        .google-login-container .divider span {
            background: white;
            padding: 0 10px;
            color: #666;
            font-size: 14px;
        }
        
        .google-login-container .divider:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #eee;
            z-index: -1;
        }
    `;
    document.head.appendChild(style);
}