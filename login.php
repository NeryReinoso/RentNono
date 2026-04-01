<?php
include("database/registro.php");
include("database/inicio_sesion.php");

$error = isset($_GET['error']) ? $_GET['error'] : "";
$error_mensaje = "";
$registro_exitoso = isset($_SESSION['registro_exitoso']) ? $_SESSION['registro_exitoso'] : false;

if ($error == "1") {
    $error_mensaje = "Correo o contraseña incorrectos";
} elseif ($error == "inactivo") {
    $error_mensaje = "Usuario inhabilitado. Contacte al administrador";
} elseif ($error == "google_auth_failed") {
    $error_mensaje = "Error al autenticar con Google. Intenta nuevamente.";
} elseif ($error == "google_access_denied") {
    $error_mensaje = "Acceso a Google denegado.";
}
?>
<link rel="stylesheet" href="estilos/login.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- MODAL DE LOGIN -->
<div id="modalFondoLogin" class="modal-fondo">
    <div class="modal-contenido">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-sign-in-alt"></i>
            INICIAR SESIÓN
        </div>

        <?php if ($error_mensaje): ?>
            <div class="error-login">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error_mensaje ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('modalFondoLogin').style.display = 'flex';
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="database/inicio_sesion.php" id="formLogin" class="modal-formulario">
            <div class="input-grupo">
                <label for="usuarioLogin">
                    <i class="fas fa-envelope"></i> Correo electrónico
                </label>
                <input type="email" id="usuarioLogin" name="correo" placeholder="ejemplo@correo.com" required>
            </div>

            <div class="input-grupo">
                <label for="passwordLogin">
                    <i class="fas fa-lock"></i> Contraseña
                </label>
                <div class="password-container">
                    <input type="password" id="passwordLogin" name="password" placeholder="Tu contraseña" required>
                    <button type="button" class="btn-ver-password" onclick="togglePassword('passwordLogin')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="enlaces-extra">
                <a href="#" class="enlace-olvido" onclick="abrirRecuperacion()">
                    <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
                </a>
            </div>

            <button type="submit" name="iniciarSesion" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>

            <!-- BOTÓN DE GOOGLE -->
        <div class="google-login-container">
            <button id="googleLoginBtn" class="btn-google">
                <i class="fab fa-google"></i>
                Iniciar sesión con Google
            </button>
            <div class="divider">
                <span>o</span>
            </div>
        </div>

            <div class="divisor">
                <span>¿No tienes cuenta?</span>
            </div>

            <div class="btn-registro-alt">
                <button type="button" class="btn-registro" onclick="abrirRegistro('propietario')">
                    <i class="fas fa-home"></i> Soy Propietario
                </button>
                <button type="button" class="btn-registro" onclick="abrirRegistro('visitante')">
                    <i class="fas fa-user"></i> Soy Visitante
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE REGISTRO PROPIETARIO -->
<div id="modalFondoRegistroPropietario" class="modal-fondo">
    <div class="modal-contenido">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-user-plus"></i>
            REGISTRO PROPIETARIO
        </div>
        
        <form method="POST" action="database/registro.php" autocomplete="off" id="formRegistroPropietario" class="modal-formulario">
            <div class="input-grupo">
                <label for="nombrePropietario">
                    <i class="fas fa-user"></i> Nombre Completo *
                </label>
                <input type="text" id="nombrePropietario" name="nombre" placeholder="Juan Pérez" required>
            </div>

            <div class="input-grupo">
                <label for="sexoPropietario">
                    <i class="fas fa-venus-mars"></i> Sexo *
                </label>
                <select id="sexoPropietario" name="sexo" required>
                    <option value="" disabled selected>Seleccionar</option>
                    <option value="masculino">Masculino</option>
                    <option value="femenino">Femenino</option>
                </select>
            </div>

            <div class="input-grupo">
                <label for="dniPropietario">
                    <i class="fas fa-id-card"></i> DNI *
                </label>
                <input type="text" id="dniPropietario" name="dni" placeholder="12345678" required maxlength="8" pattern="[0-9]{7,8}" oninput="formatDNI(this)">
            </div>

            <div class="input-grupo">
                <label for="correoPropietario">
                    <i class="fas fa-envelope"></i> Correo Electrónico *
                </label>
                <input type="email" id="correoPropietario" name="correo" placeholder="ejemplo@correo.com" required>
            </div>

            <div class="input-grupo">
                <label for="telefonoPropietario">
                    <i class="fas fa-phone"></i> Teléfono
                </label>
                <input type="tel" id="telefonoPropietario" name="telefono" placeholder="3825 23-3223" oninput="formatTelefono(this)">
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>Te enviaremos una contraseña temporal a tu correo</span>
            </div>

            <div class="form-footer">
                <button type="button" class="btn-volver" onclick="volverAlLogin()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button type="submit" name="registrar_propietario" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Registrarme
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE REGISTRO VISITANTE -->
<div id="modalFondoRegistroVisitante" class="modal-fondo">
    <div class="modal-contenido">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-user-plus"></i>
            REGISTRO VISITANTE
        </div>
        
        <form method="POST" action="database/registro.php" autocomplete="off" id="formRegistroVisitante" class="modal-formulario">
            <div class="input-grupo">
                <label for="nombreVisitante">
                    <i class="fas fa-user"></i> Nombre Completo *
                </label>
                <input type="text" id="nombreVisitante" name="nombre" placeholder="María González" required>
            </div>

            <div class="input-grupo">
                <label for="correoVisitante">
                    <i class="fas fa-envelope"></i> Correo Electrónico *
                </label>
                <input type="email" id="correoVisitante" name="correo" placeholder="ejemplo@correo.com" required>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>Te enviaremos una contraseña temporal a tu correo</span>
            </div>

            <div class="form-footer">
                <button type="button" class="btn-volver" onclick="volverAlLogin()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button type="submit" name="registrar_visitante" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Registrarme
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA RECUPERAR CONTRASEÑA -->
<div id="modalOlvidoPassword" class="modal-fondo">
    <div class="modal-contenido">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-key"></i>
            Recuperar Contraseña
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <span>Ingresa tu correo para recibir un enlace de recuperación</span>
        </div>
        
        <form id="formOlvidoPassword" class="modal-formulario" onsubmit="enviarRecuperacion(event)">
            <div class="input-grupo">
                <input type="email" id="correoRecuperacion" name="correo" placeholder="ejemplo@correo.com" required>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Enviar Enlace
            </button>
        </form>
    </div>
</div>

<!-- MENSAJE DE BIENVENIDA -->
<?php if (isset($_SESSION['registro_exitoso']) && $_SESSION['registro_exitoso']): ?>
<div id="mensajeBienvenida" class="mensaje-flotante" style="display: flex;">
    <div style="text-align: center;">
        <i class="fas fa-check-circle" style="font-size: 40px; color: #4CAF50; margin-bottom: 10px;"></i>
        <h3 style="margin: 0 0 10px 0;">¡Registro Exitoso!</h3>
        <p style="margin: 0 0 5px 0;">Hola <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong></p>
        <p style="margin: 0 0 15px 0; font-size: 14px;">Tu contraseña temporal ha sido enviada a tu correo</p>
        <button onclick="cerrarMensaje()" style="padding: 8px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Continuar
        </button>
    </div>
</div>

<script>
function cerrarMensaje() {
    document.getElementById('mensajeBienvenida').style.display = 'none';
    <?php if ($_SESSION['tipo_usuario'] == 'visitante'): ?>
        window.location.href = 'usuario_visitante/ixusuario.php';
    <?php elseif ($_SESSION['tipo_usuario'] == 'propietario'): ?>
        window.location.href = 'usuario_propietario/index_propietario.php';
    <?php endif; ?>
}
</script>

<?php 
    unset($_SESSION['registro_exitoso']);
    unset($_SESSION['password_temporal']);
    unset($_SESSION['tipo_usuario']);
endif; 
?>

<script>
// ===========================================
// FUNCIONES GLOBALES
// ===========================================

// Función para abrir el login
function abrirLogin() {
    document.getElementById('modalFondoLogin').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Función para abrir registro
function abrirRegistro(tipo) {
    if (tipo === 'propietario') {
        document.getElementById('modalFondoLogin').style.display = 'none';
        document.getElementById('modalFondoRegistroPropietario').style.display = 'flex';
    } else if (tipo === 'visitante') {
        document.getElementById('modalFondoLogin').style.display = 'none';
        document.getElementById('modalFondoRegistroVisitante').style.display = 'flex';
    }
}

// Función para abrir recuperación
function abrirRecuperacion() {
    document.getElementById('modalFondoLogin').style.display = 'none';
    document.getElementById('modalOlvidoPassword').style.display = 'flex';
}

// Función para volver al login
function volverAlLogin() {
    document.querySelectorAll('.modal-fondo').forEach(modal => {
        modal.style.display = 'none';
    });
    document.getElementById('modalFondoLogin').style.display = 'flex';
}

// Función para cerrar modales
function cerrarModal() {
    event.target.closest('.modal-fondo').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Función para mostrar/ocultar contraseña
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = event.target.tagName === 'I' ? event.target : event.target.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Formatear DNI
function formatDNI(input) {
    input.value = input.value.replace(/\D/g, '').slice(0, 8);
}

// Formatear teléfono
function formatTelefono(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 0) {
        if (value.length <= 4) {
            input.value = value;
        } else if (value.length <= 6) {
            input.value = value.slice(0,4) + ' ' + value.slice(4);
        } else if (value.length <= 10) {
            input.value = value.slice(0,4) + ' ' + value.slice(4,6) + '-' + value.slice(6);
        } else {
            input.value = value.slice(0,4) + ' ' + value.slice(4,6) + '-' + value.slice(6,10);
        }
    }
}

// Función para enviar recuperación
function enviarRecuperacion(event) {
    event.preventDefault();
    const correo = document.getElementById('correoRecuperacion').value;
    const btnSubmit = event.target.querySelector('button[type="submit"]');
    
    // Validar correo
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(correo)) {
        mostrarMensaje('❌ Por favor, ingresa un correo válido', 'error');
        return;
    }
    
    // Mostrar carga
    const textoOriginal = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    btnSubmit.disabled = true;
    
    // Enviar petición AJAX
    fetch('database/registro.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'recuperar_password=1&correo=' + encodeURIComponent(correo)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje(data.message, 'success');
            
            // Cerrar modal después de 3 segundos
            setTimeout(() => {
                document.getElementById('modalOlvidoPassword').style.display = 'none';
                document.getElementById('modalFondoLogin').style.display = 'flex';
                document.getElementById('correoRecuperacion').value = '';
            }, 3000);
        } else {
            mostrarMensaje(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('❌ Error de conexión. Intenta nuevamente.', 'error');
    })
    .finally(() => {
        // Restaurar botón
        btnSubmit.innerHTML = textoOriginal;
        btnSubmit.disabled = false;
    });
}

// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo) {
    let mensajeDiv = document.getElementById('mensajeTemporal');
    
    if (!mensajeDiv) {
        mensajeDiv = document.createElement('div');
        mensajeDiv.id = 'mensajeTemporal';
        mensajeDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
        `;
        document.body.appendChild(mensajeDiv);
    }
    
    // Estilos según tipo
    if (tipo === 'success') {
        mensajeDiv.style.background = '#4CAF50';
    } else {
        mensajeDiv.style.background = '#f44336';
    }
    
    mensajeDiv.innerHTML = `
        <i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span style="margin-left: 10px;">${mensaje}</span>
    `;
    
    // Eliminar después de 5 segundos
    setTimeout(() => {
        if (mensajeDiv.parentNode) {
            mensajeDiv.parentNode.removeChild(mensajeDiv);
        }
    }, 5000);
}

// ===========================================
// INICIALIZACIÓN AL CARGAR LA PÁGINA
// ===========================================
document.addEventListener('DOMContentLoaded', function() {
    // 1. Configurar botones de cerrar
    document.querySelectorAll('.cerrar-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal-fondo').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });
    
    // 2. Cerrar al hacer click fuera del modal
    document.querySelectorAll('.modal-fondo').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    // 3. Botón "Iniciar sesión" en la barra de navegación
    const abrirLoginBtn = document.getElementById('abrirLogin');
    if (abrirLoginBtn) {
        abrirLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            abrirLogin();
        });
    }
    
    // 4. Configurar botón de Google
    const googleLoginBtn = document.getElementById('googleLoginBtn');
    if (googleLoginBtn) {
        googleLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // URL de autorización de Google OAuth
            const clientId = '24939222054-j2nhbalkqbqk0hivb51kidq5duacpglk.apps.googleusercontent.com';
            const redirectUri = encodeURIComponent('http://localhost/RentNono/database/google_callback.php');
            const scope = encodeURIComponent('email profile');
            const responseType = 'code';
            
            // Construir URL de Google OAuth
            const googleAuthUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
                `client_id=${clientId}&` +
                `redirect_uri=${redirectUri}&` +
                `response_type=${responseType}&` +
                `scope=${scope}&` +
                `access_type=offline&` +
                `prompt=consent`;
            
            // Redirigir a Google
            window.location.href = googleAuthUrl;
        });
    }
});

// Función global para abrir login desde cualquier lugar
window.abrirLogin = abrirLogin;

// Añadir CSS para animación
if (!document.querySelector('#animacionMensajes')) {
    const style = document.createElement('style');
    style.id = 'animacionMensajes';
    style.textContent = `
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        /* Estilos para botón de Google */
        .google-login-container {
            padding: 20px 30px 0 30px;
            text-align: center;
        }
        
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
</script>