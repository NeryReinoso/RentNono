<?php
// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("database/registro.php");
include("database/inicio_sesion.php");

$error = isset($_GET['error']) ? $_GET['error'] : "";
$success = isset($_GET['success']) ? $_GET['success'] : "";
$error_mensaje = "";
$success_mensaje = "";
$registro_exitoso = isset($_SESSION['registro_exitoso']) ? $_SESSION['registro_exitoso'] : false;

// Manejo de errores
if ($error == "1") {
    $error_mensaje = "Correo o contraseña incorrectos";
} elseif ($error == "inactivo") {
    $error_mensaje = "Usuario inhabilitado. Contacte al administrador";
} elseif ($error == "google_auth_failed") {
    $error_mensaje = "Error al autenticar con Google. Intenta nuevamente.";
} elseif ($error == "google_access_denied") {
    $error_mensaje = "Acceso a Google denegado.";
} elseif ($error == "correo_existe") {
    $error_mensaje = "El correo ya está registrado";
} elseif ($error == "dni_existe") {
    $error_mensaje = "El DNI ya está registrado";
} elseif ($error == "correo_invalido") {
    $error_mensaje = "Formato de correo inválido";
} elseif ($error == "dni_invalido") {
    $error_mensaje = "El DNI debe tener 7 u 8 dígitos";
} elseif ($error == "telefono_invalido") {
    $error_mensaje = "Formato de teléfono inválido";
} elseif ($error == "campos_vacios") {
    $error_mensaje = "Todos los campos son obligatorios";
} elseif ($error == "registro_fallido") {
    $error_mensaje = "Error en el registro. Intenta nuevamente.";
} elseif ($error == "password_no_coincide") {
    $error_mensaje = "Las contraseñas no coinciden";
} elseif ($error == "token_invalido") {
    $error_mensaje = "El enlace de recuperación es inválido o ha expirado";
} elseif ($error == "sesion_requerida") {
    $error_mensaje = "Debes iniciar sesión para acceder a esta página";
}

// Manejo de mensajes de éxito
if ($success == "password_actualizada") {
    $success_mensaje = "Contraseña actualizada correctamente. Ya puedes iniciar sesión.";
} elseif ($success == "correo_enviado") {
    $success_mensaje = "Se ha enviado un correo con las instrucciones.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentNono - Sistema de Login</title>
    <link rel="stylesheet" href="estilos/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Estilos adicionales para mensajes */
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 12px 20px;
            margin: 15px 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .mensaje-exito i {
            font-size: 18px;
            color: #28a745;
        }
        
        /* Loader para botones */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn-loading i.fa-spinner {
            margin-right: 8px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Tooltips de validación */
        .validation-tooltip {
            position: absolute;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            bottom: 100%;
            left: 0;
            margin-bottom: 5px;
            white-space: nowrap;
            display: none;
        }
        
        .input-grupo {
            position: relative;
        }
        
        .input-grupo input:invalid:focus + .validation-tooltip {
            display: block;
        }
        
        /* Animaciones */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<!-- MODAL DE LOGIN -->
<div id="modalFondoLogin" class="modal-fondo" style="display: <?= ($error_mensaje || $success_mensaje || $registro_exitoso) ? 'flex' : 'none' ?>;">
    <div class="modal-contenido fade-in">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-sign-in-alt"></i>
            INICIAR SESIÓN
        </div>

        <?php if ($error_mensaje): ?>
            <div class="error-login">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_mensaje): ?>
            <div class="mensaje-exito">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="database/inicio_sesion.php" id="formLogin" class="modal-formulario" autocomplete="off">
            <div class="input-grupo">
                <label for="usuarioLogin">
                    <i class="fas fa-envelope"></i> Correo electrónico
                </label>
                <input type="email" id="usuarioLogin" name="correo" 
                       placeholder="ejemplo@correo.com" 
                       value="<?= isset($_COOKIE['recordar_correo']) ? htmlspecialchars($_COOKIE['recordar_correo']) : '' ?>"
                       required>
            </div>

            <div class="input-grupo">
                <label for="passwordLogin">
                    <i class="fas fa-lock"></i> Contraseña
                </label>
                <div class="password-container">
                    <input type="password" id="passwordLogin" name="password" placeholder="Tu contraseña" required>
                    <button type="button" class="btn-ver-password" onclick="togglePassword('passwordLogin', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="recordar-container">
                <label class="checkbox-label">
                    <input type="checkbox" name="recordar" id="recordarCheckbox" 
                           <?= isset($_COOKIE['recordar_correo']) ? 'checked' : '' ?>>
                    <span class="checkmark"></span>
                    Recordar mi correo
                </label>
            </div>

            <div class="enlaces-extra">
                <a href="#" class="enlace-olvido" onclick="abrirRecuperacion(event)">
                    <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
                </a>
            </div>

            <button type="submit" name="iniciarSesion" class="btn-submit" id="btnIniciarSesion">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>

            <!-- BOTÓN DE GOOGLE -->
            <div class="google-login-container">
                <button type="button" id="googleLoginBtn" class="btn-google">
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
    <div class="modal-contenido fade-in">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-user-plus"></i>
            REGISTRO PROPIETARIO
        </div>
        
        <form method="POST" action="database/registro.php" autocomplete="off" id="formRegistroPropietario" class="modal-formulario" onsubmit="return validarFormularioPropietario(event)">
            <div class="input-grupo">
                <label for="nombrePropietario">
                    <i class="fas fa-user"></i> Nombre Completo *
                </label>
                <input type="text" id="nombrePropietario" name="nombre" 
                       placeholder="Juan Pérez" 
                       pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]{3,50}"
                       title="Mínimo 3 caracteres, solo letras y espacios"
                       required>
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
                <input type="text" id="dniPropietario" name="dni" 
                       placeholder="12345678" 
                       required 
                       maxlength="8" 
                       pattern="[0-9]{7,8}" 
                       title="El DNI debe tener 7 u 8 dígitos numéricos"
                       oninput="formatDNI(this)">
                <div class="validation-tooltip">Solo números, 7-8 dígitos</div>
            </div>

            <div class="input-grupo">
                <label for="correoPropietario">
                    <i class="fas fa-envelope"></i> Correo Electrónico *
                </label>
                <input type="email" id="correoPropietario" name="correo" 
                       placeholder="ejemplo@correo.com" 
                       required
                       onblur="validarCorreoDuplicado(this, 'propietario')">
                <div id="correoPropietarioMsg" class="validation-message"></div>
            </div>

            <div class="input-grupo">
                <label for="telefonoPropietario">
                    <i class="fas fa-phone"></i> Teléfono
                </label>
                <input type="tel" id="telefonoPropietario" name="telefono" 
                       placeholder="3825 23-3223" 
                       pattern="[0-9\s\-]{8,15}"
                       title="Formato: 3825 23-3223"
                       oninput="formatTelefono(this)">
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>Te enviaremos una contraseña temporal a tu correo</span>
            </div>

            <div class="terminos-container">
                <label class="checkbox-label">
                    <input type="checkbox" name="terminos" id="terminosPropietario" required>
                    <span class="checkmark"></span>
                    Acepto los <a href="#" onclick="mostrarTerminos(event)">términos y condiciones</a>
                </label>
            </div>

            <div class="form-footer">
                <button type="button" class="btn-volver" onclick="volverAlLogin()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button type="submit" name="registrar_propietario" class="btn-submit" id="btnRegistroPropietario">
                    <i class="fas fa-user-plus"></i> Registrarme
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE REGISTRO VISITANTE -->
<div id="modalFondoRegistroVisitante" class="modal-fondo">
    <div class="modal-contenido fade-in">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-user-plus"></i>
            REGISTRO VISITANTE
        </div>
        
        <form method="POST" action="database/registro.php" autocomplete="off" id="formRegistroVisitante" class="modal-formulario" onsubmit="return validarFormularioVisitante(event)">
            <div class="input-grupo">
                <label for="nombreVisitante">
                    <i class="fas fa-user"></i> Nombre Completo *
                </label>
                <input type="text" id="nombreVisitante" name="nombre" 
                       placeholder="María González" 
                       pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]{3,50}"
                       title="Mínimo 3 caracteres, solo letras y espacios"
                       required>
            </div>

            <div class="input-grupo">
                <label for="correoVisitante">
                    <i class="fas fa-envelope"></i> Correo Electrónico *
                </label>
                <input type="email" id="correoVisitante" name="correo" 
                       placeholder="ejemplo@correo.com" 
                       required
                       onblur="validarCorreoDuplicado(this, 'visitante')">
                <div id="correoVisitanteMsg" class="validation-message"></div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>Te enviaremos una contraseña temporal a tu correo</span>
            </div>

            <div class="terminos-container">
                <label class="checkbox-label">
                    <input type="checkbox" name="terminos" id="terminosVisitante" required>
                    <span class="checkmark"></span>
                    Acepto los <a href="#" onclick="mostrarTerminos(event)">términos y condiciones</a>
                </label>
            </div>

            <div class="form-footer">
                <button type="button" class="btn-volver" onclick="volverAlLogin()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button type="submit" name="registrar_visitante" class="btn-submit" id="btnRegistroVisitante">
                    <i class="fas fa-user-plus"></i> Registrarme
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA RECUPERAR CONTRASEÑA -->
<div id="modalOlvidoPassword" class="modal-fondo">
    <div class="modal-contenido fade-in">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-key"></i>
            Recuperar Contraseña
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <span>Ingresa tu correo para recibir un enlace de recuperación</span>
        </div>
        
        <form id="formOlvidoPassword" class="modal-formulario" onsubmit="return enviarRecuperacion(event)">
            <div class="input-grupo">
                <input type="email" id="correoRecuperacion" name="correo" 
                       placeholder="ejemplo@correo.com" 
                       required
                       oninput="this.classList.remove('error')">
            </div>
            
            <div class="form-footer">
                <button type="button" class="btn-volver" onclick="volverAlLogin()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button type="submit" class="btn-submit" id="btnEnviarRecuperacion">
                    <i class="fas fa-paper-plane"></i> Enviar Enlace
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DE TÉRMINOS Y CONDICIONES -->
<div id="modalTerminos" class="modal-fondo">
    <div class="modal-contenido modal-terminos">
        <span class="cerrar-modal">&times;</span>
        
        <div class="modal-titulo">
            <i class="fas fa-file-contract"></i>
            Términos y Condiciones
        </div>
        
        <div class="terminos-contenido">
            <h3>1. Aceptación de términos</h3>
            <p>Al registrarte en RentNono, aceptas cumplir con estos términos y condiciones.</p>
            
            <h3>2. Uso de la plataforma</h3>
            <p>RentNono es una plataforma que conecta propietarios e inquilinos. No nos hacemos responsables de las transacciones entre usuarios.</p>
            
            <h3>3. Privacidad</h3>
            <p>Tus datos personales serán tratados conforme a nuestra política de privacidad.</p>
            
            <h3>4. Responsabilidades</h3>
            <p>Los usuarios son responsables de la veracidad de la información proporcionada.</p>
        </div>
        
        <button type="button" class="btn-submit" onclick="cerrarTerminos()">Entendido</button>
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
    <?php if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'visitante'): ?>
        window.location.href = 'usuario_visitante/ixusuario.php';
    <?php elseif (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'propietario'): ?>
        window.location.href = 'usuario_propietario/index_propietario.php';
    <?php else: ?>
        window.location.href = 'index.php';
    <?php endif; ?>
}
</script>

<?php 
    unset($_SESSION['registro_exitoso']);
    unset($_SESSION['password_temporal']);
    unset($_SESSION['tipo_usuario']);
    unset($_SESSION['usuario_nombre']);
endif; 
?>

<script>
// ===========================================
// FUNCIONES GLOBALES MEJORADAS
// ===========================================

// Configuración
const CONFIG = {
    clientId: '24939222054-j2nhbalkqbqk0hivb51kidq5duacpglk.apps.googleusercontent.com',
    redirectUri: encodeURIComponent('http://localhost/RentNono/database/google_callback.php'),
    apiUrl: window.location.origin + '/RentNono'
};

// Función para abrir el login
function abrirLogin() {
    cerrarTodosLosModales();
    const modal = document.getElementById('modalFondoLogin');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Enfocar el primer input
        setTimeout(() => {
            const input = document.getElementById('usuarioLogin');
            if (input) input.focus();
        }, 100);
    }
}

// Función para abrir registro
function abrirRegistro(tipo) {
    cerrarTodosLosModales();
    
    if (tipo === 'propietario') {
        const modal = document.getElementById('modalFondoRegistroPropietario');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => {
                const input = document.getElementById('nombrePropietario');
                if (input) input.focus();
            }, 100);
        }
    } else if (tipo === 'visitante') {
        const modal = document.getElementById('modalFondoRegistroVisitante');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => {
                const input = document.getElementById('nombreVisitante');
                if (input) input.focus();
            }, 100);
        }
    }
}

// Función para abrir recuperación
function abrirRecuperacion(event) {
    if (event) event.preventDefault();
    cerrarTodosLosModales();
    const modal = document.getElementById('modalOlvidoPassword');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => {
            const input = document.getElementById('correoRecuperacion');
            if (input) input.focus();
        }, 100);
    }
}

// Función para volver al login
function volverAlLogin() {
    cerrarTodosLosModales();
    abrirLogin();
}

// Función para cerrar todos los modales
function cerrarTodosLosModales() {
    document.querySelectorAll('.modal-fondo').forEach(modal => {
        modal.style.display = 'none';
    });
    document.body.style.overflow = 'auto';
}

// Función para cerrar modal específico
function cerrarModal(elemento) {
    const modal = elemento.closest('.modal-fondo');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Función mejorada para mostrar/ocultar contraseña
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
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

// Formatear teléfono mejorado
function formatTelefono(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 0) {
        if (value.length <= 2) {
            input.value = value;
        } else if (value.length <= 4) {
            input.value = value.slice(0,2) + ' ' + value.slice(2);
        } else if (value.length <= 6) {
            input.value = value.slice(0,2) + ' ' + value.slice(2,4) + ' ' + value.slice(4);
        } else if (value.length <= 10) {
            input.value = value.slice(0,2) + ' ' + value.slice(2,4) + ' ' + value.slice(4,6) + '-' + value.slice(6);
        } else {
            input.value = value.slice(0,2) + ' ' + value.slice(2,4) + ' ' + value.slice(4,6) + '-' + value.slice(6,10);
        }
    }
}

// Validar correo duplicado via AJAX
function validarCorreoDuplicado(input, tipo) {
    const correo = input.value;
    const msgDiv = document.getElementById(`correo${tipo.charAt(0).toUpperCase() + tipo.slice(1)}Msg`);
    
    if (!correo || !correo.includes('@')) return;
    
    fetch(`${CONFIG.apiUrl}/database/verificar_correo.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `correo=${encodeURIComponent(correo)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            input.classList.add('error');
            msgDiv.innerHTML = '<i class="fas fa-times-circle"></i> Este correo ya está registrado';
            msgDiv.className = 'validation-message error';
        } else {
            input.classList.remove('error');
            msgDiv.innerHTML = '<i class="fas fa-check-circle"></i> Correo disponible';
            msgDiv.className = 'validation-message success';
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Validar formulario propietario
function validarFormularioPropietario(event) {
    event.preventDefault();
    
    const form = event.target;
    const btn = document.getElementById('btnRegistroPropietario');
    const nombre = document.getElementById('nombrePropietario').value.trim();
    const dni = document.getElementById('dniPropietario').value.trim();
    const correo = document.getElementById('correoPropietario').value.trim();
    const sexo = document.getElementById('sexoPropietario').value;
    const terminos = document.getElementById('terminosPropietario').checked;
    
    // Validaciones
    if (nombre.length < 3) {
        mostrarMensaje('El nombre debe tener al menos 3 caracteres', 'error');
        return false;
    }
    
    if (!/^[0-9]{7,8}$/.test(dni)) {
        mostrarMensaje('El DNI debe tener 7 u 8 dígitos', 'error');
        return false;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        mostrarMensaje('Ingresa un correo válido', 'error');
        return false;
    }
    
    if (!sexo) {
        mostrarMensaje('Selecciona tu sexo', 'error');
        return false;
    }
    
    if (!terminos) {
        mostrarMensaje('Debes aceptar los términos y condiciones', 'error');
        return false;
    }
    
    // Mostrar loading
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    btn.disabled = true;
    btn.classList.add('btn-loading');
    
    // Enviar formulario
    form.submit();
    return true;
}

// Validar formulario visitante
function validarFormularioVisitante(event) {
    event.preventDefault();
    
    const form = event.target;
    const btn = document.getElementById('btnRegistroVisitante');
    const nombre = document.getElementById('nombreVisitante').value.trim();
    const correo = document.getElementById('correoVisitante').value.trim();
    const terminos = document.getElementById('terminosVisitante').checked;
    
    // Validaciones
    if (nombre.length < 3) {
        mostrarMensaje('El nombre debe tener al menos 3 caracteres', 'error');
        return false;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        mostrarMensaje('Ingresa un correo válido', 'error');
        return false;
    }
    
    if (!terminos) {
        mostrarMensaje('Debes aceptar los términos y condiciones', 'error');
        return false;
    }
    
    // Mostrar loading
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    btn.disabled = true;
    btn.classList.add('btn-loading');
    
    // Enviar formulario
    form.submit();
    return true;
}

// Función mejorada para enviar recuperación
function enviarRecuperacion(event) {
    event.preventDefault();
    
    const correo = document.getElementById('correoRecuperacion').value.trim();
    const btn = document.getElementById('btnEnviarRecuperacion');
    
    // Validar correo
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(correo)) {
        mostrarMensaje('❌ Por favor, ingresa un correo válido', 'error');
        document.getElementById('correoRecuperacion').classList.add('error');
        return false;
    }
    
    // Mostrar carga
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    btn.disabled = true;
    btn.classList.add('btn-loading');
    
    // Enviar petición AJAX
    fetch(`${CONFIG.apiUrl}/database/registro.php`, {
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
            
            // Limpiar y cerrar modal después de 3 segundos
            setTimeout(() => {
                document.getElementById('modalOlvidoPassword').style.display = 'none';
                document.getElementById('correoRecuperacion').value = '';
                abrirLogin();
            }, 3000);
        } else {
            mostrarMensaje(data.message, 'error');
            document.getElementById('correoRecuperacion').classList.add('error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('❌ Error de conexión. Intenta nuevamente.', 'error');
    })
    .finally(() => {
        // Restaurar botón
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
        btn.classList.remove('btn-loading');
    });
    
    return false;
}

// Función mejorada para mostrar mensajes
function mostrarMensaje(mensaje, tipo) {
    let mensajeDiv = document.getElementById('mensajeTemporal');
    
    if (!mensajeDiv) {
        mensajeDiv = document.createElement('div');
        mensajeDiv.id = 'mensajeTemporal';
        document.body.appendChild(mensajeDiv);
    }
    
    // Estilos según tipo
    const colores = {
        success: { bg: '#4CAF50', icon: 'fa-check-circle' },
        error: { bg: '#f44336', icon: 'fa-exclamation-circle' },
        info: { bg: '#2196F3', icon: 'fa-info-circle' },
        warning: { bg: '#ff9800', icon: 'fa-exclamation-triangle' }
    };
    
    const estilo = colores[tipo] || colores.info;
    
    mensajeDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 8px;
        background: ${estilo.bg};
        color: white;
        font-weight: 500;
        z-index: 10000;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 400px;
    `;
    
    mensajeDiv.innerHTML = `
        <i class="fas ${estilo.icon}" style="font-size: 18px;"></i>
        <span style="flex: 1;">${mensaje}</span>
        <i class="fas fa-times" style="cursor: pointer; opacity: 0.8;" onclick="this.parentElement.remove()"></i>
    `;
    
    // Eliminar después de 5 segundos
    setTimeout(() => {
        if (mensajeDiv.parentNode) {
            mensajeDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (mensajeDiv.parentNode) mensajeDiv.remove();
            }, 300);
        }
    }, 5000);
}

// Función para mostrar términos y condiciones
function mostrarTerminos(event) {
    if (event) event.preventDefault();
    const modal = document.getElementById('modalTerminos');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Función para cerrar términos
function cerrarTerminos() {
    document.getElementById('modalTerminos').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Función para manejar login con Google
function iniciarGoogleLogin() {
    const googleAuthUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
        `client_id=${CONFIG.clientId}&` +
        `redirect_uri=${CONFIG.redirectUri}&` +
        `response_type=code&` +
        `scope=email%20profile&` +
        `access_type=offline&` +
        `prompt=consent`;
    
    window.location.href = googleAuthUrl;
}

// ===========================================
// INICIALIZACIÓN AL CARGAR LA PÁGINA
// ===========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Login.js cargado correctamente');
    
    // 1. Configurar botones de cerrar
    document.querySelectorAll('.cerrar-modal').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            cerrarModal(this);
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
    
    // 3. Prevenir cierre al hacer click dentro del modal
    document.querySelectorAll('.modal-contenido').forEach(content => {
        content.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // 4. Botón "Iniciar sesión" en la barra de navegación
    const abrirLoginBtn = document.getElementById('abrirLogin');
    if (abrirLoginBtn) {
        abrirLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            abrirLogin();
        });
    }
    
    // 5. Configurar botón de Google
    const googleLoginBtn = document.getElementById('googleLoginBtn');
    if (googleLoginBtn) {
        googleLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            iniciarGoogleLogin();
        });
    }
    
    // 6. Manejar tecla ESC para cerrar modales
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarTodosLosModales();
        }
    });
    
    // 7. Auto-enfocar en login si hay error
    if (document.getElementById('modalFondoLogin').style.display === 'flex') {
        setTimeout(() => {
            const input = document.getElementById('usuarioLogin');
            if (input) input.focus();
        }, 100);
    }
});

// ===========================================
// ESTILOS ADICIONALES
// ===========================================
if (!document.querySelector('#animacionMensajes')) {
    const style = document.createElement('style');
    style.id = 'animacionMensajes';
    style.textContent = `
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
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
        
        /* Checkbox personalizado */
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* Mensajes de validación */
        .validation-message {
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .validation-message.success {
            color: #4CAF50;
        }
        
        .validation-message.error {
            color: #f44336;
        }
        
        input.error {
            border-color: #f44336 !important;
        }
        
        /* Términos y condiciones */
        .modal-terminos {
            max-width: 600px;
            max-height: 80vh;
        }
        
        .terminos-contenido {
            padding: 20px 30px;
            max-height: 400px;
            overflow-y: auto;
            text-align: left;
        }
        
        .terminos-contenido h3 {
            color: #333;
            margin: 20px 0 10px;
            font-size: 16px;
        }
        
        .terminos-contenido h3:first-child {
            margin-top: 0;
        }
        
        .terminos-contenido p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .terminos-container {
            margin: 15px 30px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        /* Recordar contraseña */
        .recordar-container {
            margin: 10px 30px;
            display: flex;
            align-items: center;
        }
        
        /* Form footer */
        .form-footer {
            display: flex;
            gap: 10px;
            padding: 20px 30px;
            border-top: 1px solid #eee;
        }
        
        .form-footer .btn-volver,
        .form-footer .btn-submit {
            flex: 1;
        }
    `;
    document.head.appendChild(style);
}

// Exponer funciones globalmente
window.abrirLogin = abrirLogin;
window.abrirRegistro = abrirRegistro;
window.abrirRecuperacion = abrirRecuperacion;
window.volverAlLogin = volverAlLogin;
window.togglePassword = togglePassword;
window.formatDNI = formatDNI;
window.formatTelefono = formatTelefono;
window.enviarRecuperacion = enviarRecuperacion;
window.mostrarTerminos = mostrarTerminos;
window.cerrarTerminos = cerrarTerminos;
</script>
</body>
</html>