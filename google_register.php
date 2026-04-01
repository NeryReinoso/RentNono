<?php
// google_register.php
session_start();

// Verificar si hay datos de Google en sesión
if (!isset($_SESSION['google_user_data'])) {
    header("Location: index.php");
    exit;
}

$google_data = $_SESSION['google_user_data'];
$email = $google_data['email'];
$name = $google_data['name'];
$google_id = $google_data['google_id'];

// Si el usuario ya eligió un tipo
if (isset($_POST['tipo_cuenta'])) {
    include("database/conexion.php");

    $tipo = $_POST['tipo_cuenta'];

    if ($tipo === 'propietario') {
        // Registrar como propietario
        $sql = "INSERT INTO usuario_propietario (nombre, correo, google_id, estado, password) 
                VALUES (:nombre, :correo, :google_id, 'activo', 'google_auth')";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([
            ':nombre' => $name,
            ':correo' => $email,
            ':google_id' => $google_id
        ])) {
            $usuario_id = $conn->lastInsertId();

            $_SESSION['id'] = $usuario_id;
            $_SESSION['nombre'] = $name;
            $_SESSION['correo'] = $email;
            $_SESSION['rol'] = 'propietario';
            $_SESSION['tipo_usuario'] = 'propietario';
            $_SESSION['google_login'] = true;

            unset($_SESSION['google_user_data']);
            header("Location: usuario_propietario/index_propietario.php");
            exit;
        }

    } elseif ($tipo === 'visitante') {
        // Registrar como visitante
        $sql = "INSERT INTO usuario_visitante (nombre, correo, google_id, estado, password) 
                VALUES (:nombre, :correo, :google_id, 'activo', 'google_auth')";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([
            ':nombre' => $name,
            ':correo' => $email,
            ':google_id' => $google_id
        ])) {
            $usuario_id = $conn->lastInsertId();

            $_SESSION['id'] = $usuario_id;
            $_SESSION['nombre'] = $name;
            $_SESSION['correo'] = $email;
            $_SESSION['rol'] = 'visitante';
            $_SESSION['tipo_usuario'] = 'visitante';
            $_SESSION['google_login'] = true;

            unset($_SESSION['google_user_data']);
            header("Location: usuario_visitante/ixusuario.php");
            exit;
        }
    }

    // Si llegamos aquí, hubo error
    $error = "Error al crear la cuenta. Intenta nuevamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Registro - RentNono</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts: Poppins e Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           VARIABLES GLOBALES (UNIFICADAS DE TODOS LOS CSS)
           ============================================ */
        :root {
            /* Colores principales de RentNono */
            --primary-color: #82b16d;
            --primary-dark: #6a9a58;
            --primary-light: #b3e58a;
            --secondary-color: #4a6fa5;
            --secondary-light: #6a89cc;
            
            /* Tonos verdes */
            --verde-olivo: #5A6D4D;
            --verde-olivo-claro: #7A8F6A;
            --verde-olivo-oscuro: #3E4D34;
            --verde-sage: #9CAF88;
            --verde-sage-claro: #D4E0C0;
            
            /* Colores de fondo y texto */
            --dark-text: #2c3e50;
            --medium-text: #34495e;
            --light-text: #7f8c8d;
            --white-bg: #ffffff;
            --light-gray-bg: #f8f9fa;
            --border-color: #e0e0e0;
            --border-soft: #e2e8f0;
            
            /* Estados */
            --success: #28a745;
            --error: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            
            /* Sombras */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            
            /* Bordes */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            
            /* Espaciados */
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }

        /* ============================================
           RESET Y ESTILOS BASE
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-gray-bg) 0%, #e9ecef 100%);
            min-height: 100vh;
            color: var(--dark-text);
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ============================================
           CONTENEDOR PRINCIPAL
           ============================================ */
        .register-wrapper {
            width: 100%;
            max-width: 560px;
            position: relative;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-card {
            background: var(--white-bg);
            border-radius: var(--radius-lg);
            padding: var(--space-xl) var(--space-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-soft);
            position: relative;
            overflow: hidden;
        }

        /* Decoración superior */
        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        /* ============================================
           BADGE DE GOOGLE
           ============================================ */
        .google-badge {
            display: inline-flex;
            align-items: center;
            background: var(--primary-color);
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            margin-bottom: var(--space-lg);
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.3px;
            box-shadow: var(--shadow-sm);
        }

        .google-badge i {
            margin-right: 8px;
            font-size: 16px;
            color: white;
        }

        /* ============================================
           TIPOGRAFÍA
           ============================================ */
        .register-card h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-text);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .register-card > p {
            color: var(--light-text);
            font-size: 15px;
            margin-bottom: var(--space-xl);
            font-weight: 400;
        }

        /* ============================================
           TARJETA DE USUARIO (COMO EN PROPIETARIO.CSS)
           ============================================ */
        .user-card {
            background: var(--light-gray-bg);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            margin-bottom: var(--space-xl);
            display: flex;
            align-items: center;
            gap: var(--space-lg);
            border: 1px solid var(--border-soft);
            transition: var(--shadow-sm);
        }

        .user-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .user-details {
            flex: 1;
            min-width: 0; /* Para evitar desbordamiento */
        }

        .user-details h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-details p {
            color: var(--light-text);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .user-details p i {
            color: var(--primary-color);
            font-size: 14px;
        }

        /* ============================================
           OPCIONES DE TIPO DE CUENTA
           ============================================ */
        .account-options {
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
            margin-bottom: var(--space-xl);
        }

        .option-card {
            display: flex;
            align-items: flex-start;
            gap: var(--space-lg);
            padding: var(--space-lg);
            background: var(--white-bg);
            border: 2px solid var(--border-soft);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: var(--light-gray-bg);
        }

        .option-card.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(130, 177, 109, 0.05), rgba(130, 177, 109, 0.1));
            box-shadow: var(--shadow-sm);
        }

        .option-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-sm);
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .option-card.selected .option-icon {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .option-content {
            flex: 1;
        }

        .option-content h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 6px;
        }

        .option-content p {
            color: var(--light-text);
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
        }

        input[type="radio"] {
            display: none;
        }

        /* Badge para "recomendado" (opcional) */
        .option-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
        }

        /* ============================================
           BOTÓN CONTINUAR (COMO EN ADMIN.CSS)
           ============================================ */
        .btn-continue {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-continue::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }

        .btn-continue:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-continue:hover {
            background: linear-gradient(135deg, var(--primary-dark), #5a8a48);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(130, 177, 109, 0.3);
        }

        .btn-continue:active {
            transform: translateY(0);
        }

        .btn-continue:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #95a5a6;
            transform: none !important;
            box-shadow: none !important;
        }

        .btn-continue:disabled::before {
            display: none;
        }

        .btn-continue i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .btn-continue:hover i {
            transform: translateX(5px);
        }

        /* ============================================
           MENSAJE DE ERROR (COMO EN LOGIN.CSS)
           ============================================ */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-lg);
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .error-message i {
            font-size: 16px;
            color: #dc2626;
        }

        /* ============================================
           FOOTER CON ENLACES (OPCIONAL)
           ============================================ */
        .register-footer {
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border-soft);
            text-align: center;
            font-size: 13px;
            color: var(--light-text);
        }

        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .register-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* ============================================
           ANIMACIONES ADICIONALES
           ============================================ */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-down {
            animation: slideDown 0.3s ease-out;
        }

        /* ============================================
           RESPONSIVE (COMBINADO DE TODOS LOS CSS)
           ============================================ */
        @media (max-width: 640px) {
            .register-card {
                padding: var(--space-lg);
            }

            .register-card h2 {
                font-size: 24px;
            }

            .user-card {
                flex-direction: column;
                text-align: center;
                gap: var(--space-md);
            }

            .user-details p {
                justify-content: center;
            }

            .option-card {
                padding: var(--space-md);
                gap: var(--space-md);
            }

            .option-icon {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }

            .option-content h4 {
                font-size: 16px;
            }

            .option-content p {
                font-size: 13px;
            }

            .option-badge {
                top: -8px;
                right: 10px;
                padding: 3px 10px;
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .register-card {
                padding: var(--space-md);
            }

            .btn-continue {
                padding: 12px 20px;
                font-size: 15px;
            }
        }

        /* ============================================
           UTILIDADES (OPCIONAL)
           ============================================ */
        .text-center { text-align: center; }
        .mt-2 { margin-top: var(--space-sm); }
        .mt-3 { margin-top: var(--space-md); }
        .mt-4 { margin-top: var(--space-lg); }
        .mb-2 { margin-bottom: var(--space-sm); }
        .mb-3 { margin-bottom: var(--space-md); }
        .mb-4 { margin-bottom: var(--space-lg); }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="register-card">
            <div class="google-badge">
                <i class="fab fa-google"></i>
                Conectado con Google
            </div>

            <h2>Completar Registro</h2>
            <p>Elige el tipo de cuenta que deseas crear</p>

            <?php if (isset($error)): ?>
                <div class="error-message slide-down">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="user-card">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($name); ?></h3>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?></p>
                </div>
            </div>

            <form method="POST" id="registerForm">
                <div class="account-options">
                    <!-- Opción Propietario -->
                    <label class="option-card" for="type_propietario">
                        <input type="radio" id="type_propietario" name="tipo_cuenta" value="propietario">
                        <div class="option-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="option-content">
                            <h4>Propietario</h4>
                            <p>Para usuarios que desean publicar y administrar propiedades en alquiler.</p>
                        </div>
                    </label>

                    <!-- Opción Visitante -->
                    <label class="option-card" for="type_visitante">
                        <input type="radio" id="type_visitante" name="tipo_cuenta" value="visitante">
                        <div class="option-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="option-content">
                            <h4>Visitante</h4>
                            <p>Para usuarios que buscan propiedades para alquilar o comprar.</p>
                        </div>
                    </label>
                </div>

                <button type="submit" class="btn-continue" id="btnContinue" disabled>
                    <span>Continuar</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="register-footer">
                <p>¿Ya tienes una cuenta? <a href="index.php">Iniciar sesión</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const options = document.querySelectorAll('.option-card');
            const btnContinue = document.getElementById('btnContinue');
            const form = document.getElementById('registerForm');

            // Función para mostrar mensajes flotantes (estilo admin)
            function showToast(message, type = 'error') {
                // Eliminar toast existente si hay
                const existingToast = document.querySelector('.custom-toast');
                if (existingToast) existingToast.remove();

                // Crear toast
                const toast = document.createElement('div');
                toast.className = 'custom-toast';
                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'error' ? '#fef2f2' : '#e6f3e6'};
                    border-left: 4px solid ${type === 'error' ? '#dc2626' : '#82b16d'};
                    color: ${type === 'error' ? '#b91c1c' : '#2c3e50'};
                    padding: 16px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    z-index: 1000;
                    animation: slideInRight 0.3s ease-out;
                    max-width: 350px;
                    font-family: 'Inter', sans-serif;
                `;

                const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
                toast.innerHTML = `
                    <i class="fas ${icon}" style="color: ${type === 'error' ? '#dc2626' : '#82b16d'}; font-size: 18px;"></i>
                    <span style="flex: 1; font-size: 14px;">${message}</span>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #666; cursor: pointer; font-size: 16px;">&times;</button>
                `;

                document.body.appendChild(toast);

                // Auto-cerrar después de 4 segundos
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        toast.style.animation = 'slideOutRight 0.3s ease-out';
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 4000);
            }

            // Añadir estilos de animación para el toast
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);

            // Manejar selección de opciones
            options.forEach(option => {
                option.addEventListener('click', function() {
                    // Remover selección anterior
                    options.forEach(opt => {
                        opt.classList.remove('selected');
                    });

                    // Marcar esta como seleccionada
                    this.classList.add('selected');

                    // Seleccionar el radio button interno
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;

                    // Habilitar botón
                    btnContinue.disabled = false;

                    // Pequeña animación en el botón
                    btnContinue.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        btnContinue.style.transform = 'scale(1)';
                    }, 150);
                });

                // Efectos hover
                option.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.backgroundColor = '#f8fafc';
                    }
                });

                option.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.backgroundColor = '';
                    }
                });
            });

            // Validar envío del formulario
            form.addEventListener('submit', function(e) {
                const selected = document.querySelector('input[name="tipo_cuenta"]:checked');
                if (!selected) {
                    e.preventDefault();
                    showToast('Por favor, selecciona un tipo de cuenta', 'error');
                }
            });

            // Si hay un error de PHP, mostrarlo como toast también
            <?php if (isset($error)): ?>
            showToast('<?= addslashes($error) ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>
</html>