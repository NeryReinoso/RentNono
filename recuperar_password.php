<?php
session_start();
include("database/conexion.php");

$token = $_GET['token'] ?? '';
$mensaje = '';
$tipo_mensaje = '';
$mostrar_formulario = false;

if (empty($token)) {
    $mensaje = "Token de recuperación no válido";
    $tipo_mensaje = "error";
} else {
    try {
        // Verificar token
        $sql = "SELECT * FROM tokens_recuperacion 
                WHERE token = :token 
                AND usado = 0 
                AND expiracion > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':token' => $token]);
        
        if ($stmt->rowCount() > 0) {
            $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $mostrar_formulario = true;
            
            // Procesar cambio de contraseña
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_password'])) {
                $nueva_password = $_POST['nueva_password'];
                $confirmar_password = $_POST['confirmar_password'];
                
                if (empty($nueva_password) || empty($confirmar_password)) {
                    $mensaje = "Ambos campos son requeridos";
                    $tipo_mensaje = "error";
                } elseif ($nueva_password !== $confirmar_password) {
                    $mensaje = "Las contraseñas no coinciden";
                    $tipo_mensaje = "error";
                } elseif (strlen($nueva_password) < 6) {
                    $mensaje = "La contraseña debe tener al menos 6 caracteres";
                    $tipo_mensaje = "error";
                } else {
                    // Actualizar contraseña
                    $password_hash = md5($nueva_password);
                    $tabla = ($token_data['tipo_usuario'] === 'propietario') 
                           ? 'usuario_propietario' 
                           : 'usuario_visitante';
                    
                    $update_sql = "UPDATE $tabla SET password = :password WHERE id = :id";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->execute([
                        ':password' => $password_hash,
                        ':id' => $token_data['usuario_id']
                    ]);
                    
                    // Marcar token como usado
                    $token_sql = "UPDATE tokens_recuperacion SET usado = 1 WHERE id = :id";
                    $token_stmt = $conn->prepare($token_sql);
                    $token_stmt->execute([':id' => $token_data['id']]);
                    
                    $mensaje = "¡Contraseña cambiada exitosamente! Ahora puedes iniciar sesión.";
                    $tipo_mensaje = "success";
                    $mostrar_formulario = false;
                }
            }
        } else {
            $mensaje = "El enlace de recuperación ha expirado o ya fue usado.";
            $tipo_mensaje = "error";
        }
        
    } catch (PDOException $e) {
        $mensaje = "Error en el sistema. Intente más tarde.";
        $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - RentNono</title>
    <link rel="stylesheet" href="estilos/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .password-recovery {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        
        .recovery-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .recovery-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .recovery-header i {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        .recovery-header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .recovery-header p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }
        
        .mensaje-recovery {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .mensaje-recovery.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .mensaje-recovery.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .login-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="password-recovery">
        <div class="recovery-container">
            <div class="recovery-header">
                <i class="fas fa-lock"></i>
                <h1>Nueva Contraseña</h1>
                <p>Crea una nueva contraseña para tu cuenta</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje-recovery <?= $tipo_mensaje ?>">
                    <i class="fas fa-<?= $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>
            
            <?php if ($mostrar_formulario): ?>
            <form method="POST" class="modal-formulario">
                <div class="input-grupo">
                    <label for="nueva_password">
                        <i class="fas fa-key"></i> Nueva Contraseña *
                    </label>
                    <div class="password-container">
                        <input type="password" id="nueva_password" name="nueva_password" 
                               placeholder="Mínimo 6 caracteres" required minlength="6">
                        <button type="button" class="btn-ver-password" onclick="togglePassword('nueva_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="input-grupo">
                    <label for="confirmar_password">
                        <i class="fas fa-key"></i> Confirmar Nueva Contraseña *
                    </label>
                    <div class="password-container">
                        <input type="password" id="confirmar_password" name="confirmar_password" 
                               placeholder="Repite la nueva contraseña" required minlength="6">
                        <button type="button" class="btn-ver-password" onclick="togglePassword('confirmar_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
            </form>
            <?php endif; ?>
            
            <a href="/RentNono/index.php" class="login-link">
                <i class="fas fa-arrow-left"></i> Volver al inicio
            </a>
        </div>
    </div>
    
    <script>
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
    </script>
</body>
</html>