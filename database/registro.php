<?php
// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("conexion.php");
include_once("config_correos.php");

/* ============================================
   FUNCIONES AGREGADAS PARA SEGURIDAD Y VALIDACIÓN
   - Verificamos si ya existen antes de declararlas
============================================ */

/**
 * Verifica si un correo ya existe en una tabla específica
 */
if (!function_exists('verificarCorreoExistente')) {
    function verificarCorreoExistente($conn, $correo, $tabla) {
        // Lista blanca de tablas permitidas por seguridad
        $tablas_permitidas = ['usuario_propietario', 'usuario_visitante', 'usuario_admin'];
        if (!in_array($tabla, $tablas_permitidas)) {
            return false;
        }
        
        $sql = "SELECT id FROM $tabla WHERE correo = :correo";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        return $stmt->rowCount() > 0;
    }
}

/**
 * Genera una contraseña segura con caracteres especiales
 */
if (!function_exists('generarContrasenaSegura')) {
    function generarContrasenaSegura($longitud = 10) {
        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+';
        return substr(str_shuffle($caracteres), 0, $longitud);
    }
}

/**
 * Sanitiza inputs para prevenir XSS
 */
if (!function_exists('sanitizarInput')) {
    function sanitizarInput($dato) {
        $dato = trim($dato);
        $dato = stripslashes($dato);
        $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');
        return $dato;
    }
}

/**
 * Verifica si un DNI ya existe en la tabla de propietarios
 */
if (!function_exists('verificarDniExistente')) {
    function verificarDniExistente($conn, $dni) {
        $sql = "SELECT id FROM usuario_propietario WHERE dni = :dni";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':dni' => $dni]);
        return $stmt->rowCount() > 0;
    }
}

/**
 * Registra logs de actividad
 */
if (!function_exists('registrarLogActividad')) {
    function registrarLogActividad($conn, $usuario_id, $usuario_nombre, $rol, $accion) {
        try {
            $sql = "INSERT INTO logs_actividad (usuario_id, usuario_nombre, rol, accion, fecha) 
                    VALUES (:usuario_id, :usuario_nombre, :rol, :accion, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':usuario_nombre' => $usuario_nombre,
                ':rol' => $rol,
                ':accion' => $accion
            ]);
        } catch (Exception $e) {
            error_log("Error al registrar log: " . $e->getMessage());
        }
    }
}

/* ================================
   REGISTRO DE USUARIO PROPIETARIO
================================ */
if (isset($_POST['registrar_propietario'])) {
    // Sanitizar inputs
    $nombre = sanitizarInput($_POST['nombre']);
    $dni = sanitizarInput($_POST['dni']);
    $correo = sanitizarInput($_POST['correo']);
    $sexo = $_POST['sexo'];
    $telefono = sanitizarInput($_POST['telefono'] ?? '');
    
    // Validar campos obligatorios
    if (empty($nombre) || empty($dni) || empty($correo) || empty($sexo)) {
        $_SESSION['error_registro'] = "Todos los campos son obligatorios";
        header("Location: ../index.php?error=campos_vacios");
        exit;
    }
    
    // Validar formato de correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_registro'] = "Formato de correo inválido";
        header("Location: ../index.php?error=correo_invalido");
        exit;
    }
    
    // Validar formato de DNI (solo números)
    if (!preg_match('/^[0-9]{7,8}$/', $dni)) {
        $_SESSION['error_registro'] = "El DNI debe tener 7 u 8 dígitos numéricos";
        header("Location: ../index.php?error=dni_invalido");
        exit;
    }
    
    // Validar teléfono si se proporciona
    if (!empty($telefono) && !preg_match('/^[0-9\-\+\s]{8,15}$/', $telefono)) {
        $_SESSION['error_registro'] = "Formato de teléfono inválido";
        header("Location: ../index.php?error=telefono_invalido");
        exit;
    }
    
    // Verificar si el correo ya existe en cualquiera de las tablas
    if (verificarCorreoExistente($conn, $correo, 'usuario_propietario') || 
        verificarCorreoExistente($conn, $correo, 'usuario_visitante') ||
        verificarCorreoExistente($conn, $correo, 'usuario_admin')) {
        $_SESSION['error_registro'] = "El correo ya está registrado";
        header("Location: ../index.php?error=correo_existe");
        exit;
    }
    
    // Verificar si el DNI ya existe
    if (verificarDniExistente($conn, $dni)) {
        $_SESSION['error_registro'] = "El DNI ya está registrado";
        header("Location: ../index.php?error=dni_existe");
        exit;
    }
    
    // Generar contraseña temporal segura
    $password_temporal = generarContrasenaSegura(12);
    $password_hash = md5($password_temporal);

    error_log("=== REGISTRO NUEVO PROPIETARIO ===");
    error_log("Fecha: " . date('Y-m-d H:i:s'));
    error_log("Nombre: " . $nombre);
    error_log("Correo: " . $correo);
    error_log("DNI: " . $dni);
    error_log("Contraseña temporal: " . $password_temporal);
    error_log("===================================");
    
    try {
        // Insertar en la base de datos
        $sql = "INSERT INTO usuario_propietario (nombre, dni, correo, sexo, telefono, password, estado) 
                VALUES (:nombre, :dni, :correo, :sexo, :telefono, :password, 'activo')";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([
            ':nombre' => $nombre,
            ':dni' => $dni,
            ':correo' => $correo,
            ':sexo' => $sexo,
            ':telefono' => $telefono,
            ':password' => $password_hash
        ])) {
            $usuario_id = $conn->lastInsertId();
            
            // Enviar correo de bienvenida
            $correo_enviado = enviarCorreoBienvenida($correo, $nombre, $password_temporal, 'propietario');
            
            if (!$correo_enviado) {
                error_log("⚠️ No se pudo enviar el correo de bienvenida a: " . $correo);
            }
            
            // Registrar en logs
            registrarLogActividad($conn, $usuario_id, $nombre, 'propietario', 'Registro exitoso');
            
            // Iniciar sesión automáticamente
            $_SESSION['id'] = $usuario_id;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['correo'] = $correo;
            $_SESSION['rol'] = 'propietario';
            $_SESSION['tipo_usuario'] = 'propietario';
            $_SESSION['password_temporal'] = $password_temporal;
            $_SESSION['registro_exitoso'] = true;
            $_SESSION['usuario_nombre'] = $nombre;
            
            // Redireccionar con mensaje de éxito
            $_SESSION['mensaje_exito'] = "Registro exitoso. Se ha enviado un correo con tu contraseña temporal.";
            header("Location: ../usuario_propietario/index_propietario.php");
            exit;
        } else {
            throw new Exception("Error al ejecutar la consulta");
        }
    } catch (Exception $e) {
        error_log("Error SQL al registrar propietario: " . $e->getMessage());
        $_SESSION['error_registro'] = "Error al registrar en la base de datos";
        header("Location: ../index.php?error=registro_fallido");
        exit;
    }
}

/* ================================
   REGISTRO DE USUARIO VISITANTE
================================ */
if (isset($_POST['registrar_visitante'])) {
    // Sanitizar inputs
    $nombre = sanitizarInput($_POST['nombre']);
    $correo = sanitizarInput($_POST['correo']);
    
    // Validar campos obligatorios
    if (empty($nombre) || empty($correo)) {
        $_SESSION['error_registro'] = "Todos los campos son obligatorios";
        header("Location: ../index.php?error=campos_vacios");
        exit;
    }
    
    // Validar formato de correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_registro'] = "Formato de correo inválido";
        header("Location: ../index.php?error=correo_invalido");
        exit;
    }
    
    // Verificar si el correo ya existe en cualquiera de las tablas
    if (verificarCorreoExistente($conn, $correo, 'usuario_propietario') || 
        verificarCorreoExistente($conn, $correo, 'usuario_visitante') ||
        verificarCorreoExistente($conn, $correo, 'usuario_admin')) {
        $_SESSION['error_registro'] = "El correo ya está registrado";
        header("Location: ../index.php?error=correo_existe");
        exit;
    }
    
    // Generar contraseña temporal segura
    $password_temporal = generarContrasenaSegura(12);
    $password_hash = md5($password_temporal);
    
    error_log("=== REGISTRO NUEVO VISITANTE ===");
    error_log("Fecha: " . date('Y-m-d H:i:s'));
    error_log("Nombre: " . $nombre);
    error_log("Correo: " . $correo);
    error_log("Contraseña temporal: " . $password_temporal);
    error_log("=================================");
    
    try {
        // Insertar en la base de datos
        $sql = "INSERT INTO usuario_visitante (nombre, correo, password, estado) 
                VALUES (:nombre, :correo, :password, 'activo')";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':password' => $password_hash
        ])) {
            $usuario_id = $conn->lastInsertId();
            
            // Enviar correo de bienvenida
            $correo_enviado = enviarCorreoBienvenida($correo, $nombre, $password_temporal, 'visitante');
            
            if (!$correo_enviado) {
                error_log("⚠️ No se pudo enviar el correo de bienvenida a: " . $correo);
            }
            
            // Registrar en logs
            registrarLogActividad($conn, $usuario_id, $nombre, 'visitante', 'Registro exitoso');
            
            // Iniciar sesión automáticamente
            $_SESSION['id'] = $usuario_id;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['correo'] = $correo;
            $_SESSION['rol'] = 'visitante';
            $_SESSION['tipo_usuario'] = 'visitante';
            $_SESSION['password_temporal'] = $password_temporal;
            $_SESSION['registro_exitoso'] = true;
            $_SESSION['usuario_nombre'] = $nombre;
            
            // Redireccionar con mensaje de éxito
            $_SESSION['mensaje_exito'] = "Registro exitoso. Se ha enviado un correo con tu contraseña temporal.";
            header("Location: ../usuario_visitante/ixusuario.php");
            exit;
        } else {
            throw new Exception("Error al ejecutar la consulta");
        }
    } catch (Exception $e) {
        error_log("Error SQL al registrar visitante: " . $e->getMessage());
        $_SESSION['error_registro'] = "Error al registrar en la base de datos";
        header("Location: ../index.php?error=registro_fallido");
        exit;
    }
}

/* ================================
   RECUPERACIÓN DE CONTRASEÑA
================================ */
if (isset($_POST['recuperar_password'])) {
    $correo = trim($_POST['correo']);
    
    header('Content-Type: application/json');
    
    // Validar formato de correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => '❌ Formato de correo inválido.'
        ]);
        exit;
    }
    
    try {
        // Buscar en ambas tablas
        $sql_propietario = "SELECT id, nombre FROM usuario_propietario WHERE correo = :correo AND estado = 'activo'";
        $stmt_prop = $conn->prepare($sql_propietario);
        $stmt_prop->execute([':correo' => $correo]);
        
        $sql_visitante = "SELECT id, nombre FROM usuario_visitante WHERE correo = :correo AND estado = 'activo'";
        $stmt_vis = $conn->prepare($sql_visitante);
        $stmt_vis->execute([':correo' => $correo]);
        
        $sql_admin = "SELECT id, nombre FROM usuario_admin WHERE correo = :correo AND estado = 1";
        $stmt_admin = $conn->prepare($sql_admin);
        $stmt_admin->execute([':correo' => $correo]);
        
        if ($stmt_prop->rowCount() > 0) {
            $usuario = $stmt_prop->fetch(PDO::FETCH_ASSOC);
            $tipo = 'propietario';
        } elseif ($stmt_vis->rowCount() > 0) {
            $usuario = $stmt_vis->fetch(PDO::FETCH_ASSOC);
            $tipo = 'visitante';
        } elseif ($stmt_admin->rowCount() > 0) {
            $usuario = $stmt_admin->fetch(PDO::FETCH_ASSOC);
            $tipo = 'admin';
        } else {
            echo json_encode([
                'success' => false,
                'message' => '❌ El correo no está registrado en nuestro sistema.'
            ]);
            exit;
        }
        
        // Crear tabla de tokens si no existe
        $create_table = "
        CREATE TABLE IF NOT EXISTS tokens_recuperacion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo_usuario VARCHAR(20) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expiracion DATETIME NOT NULL,
            usado BOOLEAN DEFAULT FALSE,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario_tipo (usuario_id, tipo_usuario),
            INDEX idx_expiracion (expiracion),
            INDEX idx_usado (usado)
        )";
        $conn->exec($create_table);
        
        // Eliminar tokens anteriores del mismo usuario
        $delete_old = "DELETE FROM tokens_recuperacion WHERE usuario_id = :usuario_id AND tipo_usuario = :tipo_usuario";
        $stmt_delete = $conn->prepare($delete_old);
        $stmt_delete->execute([
            ':usuario_id' => $usuario['id'],
            ':tipo_usuario' => $tipo
        ]);
        
        // Generar token
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Guardar token
        $sql_token = "INSERT INTO tokens_recuperacion (usuario_id, tipo_usuario, token, expiracion) 
                      VALUES (:usuario_id, :tipo_usuario, :token, :expiracion)";
        $stmt_token = $conn->prepare($sql_token);
        $stmt_token->execute([
            ':usuario_id' => $usuario['id'],
            ':tipo_usuario' => $tipo,
            ':token' => $token,
            ':expiracion' => $expiracion
        ]);
        
        // Enviar correo
        $enlace = "http://" . $_SERVER['HTTP_HOST'] . "/rentnono/recuperar_password.php?token=" . $token;
        
        if (enviarCorreoRecuperacion($correo, $usuario['nombre'], $enlace)) {
            echo json_encode([
                'success' => true,
                'message' => '✅ Se ha enviado un enlace de recuperación a tu correo.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '❌ Error al enviar el correo. Intenta nuevamente.'
            ]);
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => '❌ Error en el sistema. Intenta más tarde.'
        ]);
        error_log("Error recuperación: " . $e->getMessage());
    }
    exit;
}

/* ================================
   FUNCIONES DE CORREOS
================================ */

if (!function_exists('enviarCorreoRecuperacion')) {
    function enviarCorreoRecuperacion($destinatario, $nombre, $enlace) {
        return enviarCorreoRentNono(
            $destinatario, 
            $nombre, 
            'Recuperación de contraseña - RentNono', 
            plantillaRecuperacion($nombre, $enlace),
            "Hola $nombre,\n\nPara recuperar tu contraseña en RentNono, visita:\n$enlace\n\nEste enlace expira en 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje."
        );
    }
}

if (!function_exists('enviarCorreoBienvenida')) {
    function enviarCorreoBienvenida($correo, $nombre, $password_temporal, $tipo_usuario) {
        $mensajeHTML = plantillaBienvenida($nombre, $password_temporal, $tipo_usuario);
        $mensajeTexto = "¡Hola $nombre!\n\n" .
                        "Tu registro como " . ucfirst($tipo_usuario) . " ha sido exitoso.\n\n" .
                        "Tus credenciales de acceso son:\n" .
                        "📧 Correo: $correo\n" .
                        "🔑 Contraseña temporal: $password_temporal\n\n" .
                        "⚠️ IMPORTANTE: Por seguridad, cambia esta contraseña en tu primera sesión.\n\n" .
                        "🌐 Ingresa aquí: http://" . $_SERVER['HTTP_HOST'] . "/rentnono\n\n" .
                        "¡Gracias por unirte a RentNono!";
        
        return enviarCorreoRentNono(
            $correo, 
            $nombre, 
            '¡Bienvenido a RentNono!', 
            $mensajeHTML, 
            $mensajeTexto
        );
    }
}

// ✅ NOTA: LAS FUNCIONES plantillaRecuperacion() y plantillaBienvenida() 
//    DEBEN ESTAR EN config_correos.php (NO SE DECLARAN AQUÍ)
?>