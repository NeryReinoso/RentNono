<?php
session_start();
include("database/conexion.php");

// Verificar si el usuario está logueado
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $mensaje = "Todos los campos son obligatorios";
        $tipo_mensaje = "error";
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje = "Las contraseñas nuevas no coinciden";
        $tipo_mensaje = "error";
    } elseif (strlen($password_nueva) < 6) {
        $mensaje = "La nueva contraseña debe tener al menos 6 caracteres";
        $tipo_mensaje = "error";
    } else {
        // Determinar tabla
        $tabla = ($_SESSION['rol'] === 'propietario') ? 'usuario_propietario' : 'usuario_visitante';
        
        // Obtener usuario con su contraseña actual
        $sql = "SELECT id, password FROM $tabla WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $_SESSION['id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            $mensaje = "Usuario no encontrado";
            $tipo_mensaje = "error";
        } else {
            // VERIFICACIÓN CORREGIDA: Probar múltiples formas
            $password_actual_md5 = md5($password_actual);
            $password_actual_raw = $password_actual; // texto plano
            
            $password_valida = false;
            
            // 1. Verificar si coincide con MD5
            if ($password_actual_md5 === $usuario['password']) {
                $password_valida = true;
            }
            // 2. Verificar si coincide con texto plano (para usuarios viejos)
            elseif ($password_actual_raw === $usuario['password']) {
                $password_valida = true;
            }
            // 3. Verificar si el hash en BD está truncado (30 chars)
            elseif (strlen($usuario['password']) === 30) {
                // Intentar con los primeros 30 chars del MD5
                if (substr($password_actual_md5, 0, 30) === $usuario['password']) {
                    $password_valida = true;
                }
            }
            
            if ($password_valida) {
                // Actualizar contraseña CON MD5 COMPLETO (32 chars)
                $nueva_password_hash = md5($password_nueva);
                
                $sql_update = "UPDATE $tabla SET password = :password WHERE id = :id";
                $stmt_update = $conn->prepare($sql_update);
                
                if ($stmt_update->execute([
                    ':password' => $nueva_password_hash,
                    ':id' => $_SESSION['id']
                ])) {
                    $mensaje = "¡Contraseña cambiada exitosamente!";
                    $tipo_mensaje = "success";
                    
                    // Registrar en logs si existe la tabla
                    try {
                        $log_sql = "INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion) 
                                   VALUES (:id, :nombre, :rol, :accion)";
                        $log_stmt = $conn->prepare($log_sql);
                        $log_stmt->execute([
                            ':id' => $_SESSION['id'],
                            ':nombre' => $_SESSION['nombre'],
                            ':rol' => $_SESSION['rol'],
                            ':accion' => 'Cambió su contraseña'
                        ]);
                    } catch (Exception $e) {
                        // Ignorar si la tabla no existe
                    }
                } else {
                    $mensaje = "Error al cambiar la contraseña";
                    $tipo_mensaje = "error";
                }
            } else {
                $mensaje = "La contraseña actual es incorrecta";
                $tipo_mensaje = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - RentNono</title>
    <link rel="stylesheet" href="../estilos/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .password-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        
        .password-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-header i {
            font-size: 40px;
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        .password-header h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .password-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        .mensaje {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .mensaje.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .mensaje.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="password-page">
        <div class="password-container">
            <div class="password-header">
                <i class="fas fa-lock"></i>
                <h2>Cambiar Contraseña</h2>
                <p>Actualiza tu contraseña de seguridad</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?= $tipo_mensaje ?>">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="modal-formulario">
                <div class="input-grupo">
                    <label for="password_actual">
                        <i class="fas fa-key"></i> Contraseña Actual
                    </label>
                    <div class="password-container">
                        <input type="password" id="password_actual" name="password_actual" 
                               placeholder="Ingresa tu contraseña actual" required>
                        <button type="button" class="btn-ver-password" onclick="togglePassword('password_actual')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="input-grupo">
                    <label for="password_nueva">
                        <i class="fas fa-lock"></i> Nueva Contraseña
                    </label>
                    <div class="password-container">
                        <input type="password" id="password_nueva" name="password_nueva" 
                               placeholder="Mínimo 6 caracteres" required minlength="6">
                        <button type="button" class="btn-ver-password" onclick="togglePassword('password_nueva')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="input-grupo">
                    <label for="password_confirmar">
                        <i class="fas fa-lock"></i> Confirmar Nueva Contraseña
                    </label>
                    <div class="password-container">
                        <input type="password" id="password_confirmar" name="password_confirmar" 
                               placeholder="Repite la nueva contraseña" required minlength="6">
                        <button type="button" class="btn-ver-password" onclick="togglePassword('password_confirmar')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" style="width: 100%;">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
                
                <a href="javascript:history.back()" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver atrás
                </a>
            </form>
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