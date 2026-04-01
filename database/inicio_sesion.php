<?php
include("conexion.php");
include("registro.php"); // ← ESTO YA INCLUYE LAS FUNCIONES

// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ❌ ELIMINAR ESTA FUNCIÓN (ya está en registro.php)
// ============================================
// function registrarLogActividad($conn, $usuario_id, $usuario_nombre, $rol, $accion) {
//     ... código ...
// }

// ============================================
// FUNCIÓN PARA VERIFICAR CONTRASEÑA (MEJORADA)
// ============================================
function verificarPassword($password_ingresada, $password_bd) {
    // Intentar con MD5 (tu sistema actual)
    $password_md5 = md5($password_ingresada);
    
    // 1. Verificar MD5 completo (32 caracteres)
    if ($password_md5 === $password_bd) {
        return true;
    }
    
    // 2. Si es texto plano (por si acaso)
    if ($password_ingresada === $password_bd) {
        return true;
    }
    
    // 3. Si es hash truncado (30 caracteres - casos especiales)
    if (strlen($password_bd) === 30 && substr($password_md5, 0, 30) === $password_bd) {
        return true;
    }
    
    // 4. Verificar con password_verify (para admins y usuarios nuevos)
    if (password_verify($password_ingresada, $password_bd)) {
        return true;
    }
    
    return false;
}

// ============================================
// FUNCIÓN PARA ACTUALIZAR CONTRASEÑA A FORMATO MODERNO (AGREGADA)
// ============================================
function actualizarPasswordSiEsNecesario($conn, $tabla, $id, $password_ingresada, $password_actual) {
    // Si la contraseña actual no es hash de password_hash y verificó con MD5
    if (strlen($password_actual) === 32 && md5($password_ingresada) === $password_actual) {
        // Actualizar a password_hash para mejor seguridad
        $nuevo_hash = password_hash($password_ingresada, PASSWORD_DEFAULT);
        $updateSql = "UPDATE $tabla SET password = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$nuevo_hash, $id]);
        error_log("Contraseña actualizada a hash moderno para usuario ID: $id en tabla: $tabla");
    }
}

// ============================================
// FUNCIÓN PARA VERIFICAR INTENTOS FALLIDOS (AGREGADA)
// ============================================
function verificarIntentosFallidos($conn, $correo) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $fecha_limite = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        
        // Crear tabla de intentos si no existe
        $createTable = "CREATE TABLE IF NOT EXISTS intentos_login (
            id INT AUTO_INCREMENT PRIMARY KEY,
            correo VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            intentos INT DEFAULT 1,
            ultimo_intento DATETIME NOT NULL,
            bloqueado_hasta DATETIME NULL,
            INDEX idx_correo_ip (correo, ip_address)
        )";
        $conn->exec($createTable);
        
        // Verificar si está bloqueado
        $checkSql = "SELECT * FROM intentos_login 
                     WHERE correo = ? AND ip_address = ? 
                     AND bloqueado_hasta > NOW() 
                     ORDER BY ultimo_intento DESC LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$correo, $ip]);
        
        if ($checkStmt->rowCount() > 0) {
            $bloqueo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $segundos_restantes = strtotime($bloqueo['bloqueado_hasta']) - time();
            return [
                'bloqueado' => true,
                'segundos_restantes' => $segundos_restantes,
                'mensaje' => "Demasiados intentos fallidos. Intenta nuevamente en " . ceil($segundos_restantes / 60) . " minutos."
            ];
        }
        
        return ['bloqueado' => false];
    } catch (Exception $e) {
        error_log("Error al verificar intentos: " . $e->getMessage());
        return ['bloqueado' => false];
    }
}

// ============================================
// FUNCIÓN PARA REGISTRAR INTENTO FALLIDO (AGREGADA)
// ============================================
function registrarIntentoFallido($conn, $correo) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Verificar intentos existentes
        $checkSql = "SELECT * FROM intentos_login 
                     WHERE correo = ? AND ip_address = ? 
                     AND ultimo_intento > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$correo, $ip]);
        
        if ($checkStmt->rowCount() > 0) {
            $registro = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $nuevos_intentos = $registro['intentos'] + 1;
            
            if ($nuevos_intentos >= 5) {
                // Bloquear por 30 minutos
                $bloqueo_hasta = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $updateSql = "UPDATE intentos_login 
                              SET intentos = ?, bloqueado_hasta = ?, ultimo_intento = NOW() 
                              WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([$nuevos_intentos, $bloqueo_hasta, $registro['id']]);
            } else {
                $updateSql = "UPDATE intentos_login 
                              SET intentos = ?, ultimo_intento = NOW() 
                              WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([$nuevos_intentos, $registro['id']]);
            }
        } else {
            // Nuevo registro de intento
            $insertSql = "INSERT INTO intentos_login (correo, ip_address, intentos, ultimo_intento) 
                          VALUES (?, ?, 1, NOW())";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->execute([$correo, $ip]);
        }
    } catch (Exception $e) {
        error_log("Error al registrar intento fallido: " . $e->getMessage());
    }
}

// ============================================
// FUNCIÓN PARA LIMPIAR INTENTOS EXITOSOS (AGREGADA)
// ============================================
function limpiarIntentosExitosos($conn, $correo) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $deleteSql = "DELETE FROM intentos_login WHERE correo = ? AND ip_address = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->execute([$correo, $ip]);
    } catch (Exception $e) {
        error_log("Error al limpiar intentos: " . $e->getMessage());
    }
}

// ============================================
// PROCESAMIENTO PRINCIPAL DE LOGIN
// ============================================
if (isset($_POST['iniciarSesion'])) {
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $recordar = isset($_POST['recordar']) ? true : false;
    
    // ============================================
    // VALIDACIONES INICIALES
    // ============================================
    if (empty($correo) || empty($password)) {
        header("Location: ../index.php?error=campos_vacios");
        exit;
    }
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../index.php?error=correo_invalido");
        exit;
    }
    
    // ============================================
    // VERIFICAR INTENTOS FALLIDOS
    // ============================================
    $intentos = verificarIntentosFallidos($conn, $correo);
    if ($intentos['bloqueado']) {
        $_SESSION['error_login'] = $intentos['mensaje'];
        header("Location: ../index.php?error=demasiados_intentos");
        exit;
    }
    
    // ============================================
    // 1. INTENTAR INICIAR SESIÓN COMO ADMIN
    // ============================================
    $sql_admin = "SELECT * FROM usuario_admin WHERE correo = :correo";
    $stmt_admin = $conn->prepare($sql_admin);
    $stmt_admin->execute([':correo' => $correo]);
    
    if ($stmt_admin->rowCount() > 0) {
        $usuario = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        
        // Verificar la contraseña con password_verify
        if (password_verify($password, $usuario['password_hash'])) {
            // Verificar si el usuario está activo
            if (isset($usuario['estado']) && $usuario['estado'] == 0) {
                registrarIntentoFallido($conn, $correo);
                header("Location: ../index.php?error=inactivo");
                exit;
            }
            
            // Limpiar intentos fallidos
            limpiarIntentosExitosos($conn, $correo);
            
            // Guardar datos en sesión para el admin
            $_SESSION['admin_id'] = $usuario['id'];
            $_SESSION['admin_nombre'] = $usuario['nombre'];
            $_SESSION['admin_correo'] = $usuario['correo'];
            $_SESSION['admin_role'] = $usuario['role'] ?? 'admin';
            $_SESSION['rol'] = 'admin';
            $_SESSION['es_superadmin'] = ($usuario['role'] === 'superadmin');
            $_SESSION['login_time'] = time();
            
            // Configurar cookie de "recordar" si se solicitó
            if ($recordar) {
                setcookie('recordar_correo', $correo, time() + (86400 * 30), "/"); // 30 días
            } else {
                setcookie('recordar_correo', '', time() - 3600, "/");
            }
            
            // Registrar en logs (USANDO LA FUNCIÓN DE registro.php)
            registrarLogActividad($conn, $usuario['id'], $usuario['nombre'], 'admin', 'Inicio de sesión en panel admin');
            
            // Redirigir al panel de admin
            header("Location: ../admin/indexadmin.php");
            exit;
        }
    }
    
    // ============================================
    // 2. SI NO ES ADMIN, INTENTAR COMO PROPIETARIO
    // ============================================
    $sql_propietario = "SELECT * FROM usuario_propietario WHERE correo = :correo";
    $stmt_prop = $conn->prepare($sql_propietario);
    $stmt_prop->execute([':correo' => $correo]);
    
    if ($stmt_prop->rowCount() > 0) {
        $usuario = $stmt_prop->fetch(PDO::FETCH_ASSOC);
        
        // Usar la función de verificación
        if (verificarPassword($password, $usuario['password'])) {
            if (isset($usuario['estado']) && $usuario['estado'] == 0) {
                registrarIntentoFallido($conn, $correo);
                header("Location: ../index.php?error=inactivo");
                exit;
            }
            
            // Limpiar intentos fallidos
            limpiarIntentosExitosos($conn, $correo);
            
            // Actualizar contraseña si es necesario (MD5 a password_hash)
            actualizarPasswordSiEsNecesario($conn, 'usuario_propietario', $usuario['id'], $password, $usuario['password']);
            
            $_SESSION['id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['rol'] = 'propietario';
            $_SESSION['tipo_usuario'] = 'propietario';
            $_SESSION['login_time'] = time();
            
            // Configurar cookie de "recordar" si se solicitó
            if ($recordar) {
                setcookie('recordar_correo', $correo, time() + (86400 * 30), "/"); // 30 días
            } else {
                setcookie('recordar_correo', '', time() - 3600, "/");
            }
            
            // Registrar en logs
            registrarLogActividad($conn, $usuario['id'], $usuario['nombre'], 'propietario', 'Inicio de sesión');
            
            // Verificar si es primera vez (contraseña temporal)
            if (isset($_SESSION['password_temporal'])) {
                header("Location: ../usuario_propietario/cambiar_password.php?primera_vez=1");
            } else {
                header("Location: ../usuario_propietario/index_propietario.php");
            }
            exit;
        }
    }
    
    // ============================================
    // 3. SI NO ES PROPIETARIO, INTENTAR COMO VISITANTE
    // ============================================
    $sql_visitante = "SELECT * FROM usuario_visitante WHERE correo = :correo";
    $stmt_vis = $conn->prepare($sql_visitante);
    $stmt_vis->execute([':correo' => $correo]);
    
    if ($stmt_vis->rowCount() > 0) {
        $usuario = $stmt_vis->fetch(PDO::FETCH_ASSOC);
        
        // Usar la función de verificación
        if (verificarPassword($password, $usuario['password'])) {
            if (isset($usuario['estado']) && $usuario['estado'] == 0) {
                registrarIntentoFallido($conn, $correo);
                header("Location: ../index.php?error=inactivo");
                exit;
            }
            
            // Limpiar intentos fallidos
            limpiarIntentosExitosos($conn, $correo);
            
            // Actualizar contraseña si es necesario (MD5 a password_hash)
            actualizarPasswordSiEsNecesario($conn, 'usuario_visitante', $usuario['id'], $password, $usuario['password']);
            
            $_SESSION['id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['rol'] = 'visitante';
            $_SESSION['tipo_usuario'] = 'visitante';
            $_SESSION['login_time'] = time();
            
            // Configurar cookie de "recordar" si se solicitó
            if ($recordar) {
                setcookie('recordar_correo', $correo, time() + (86400 * 30), "/"); // 30 días
            } else {
                setcookie('recordar_correo', '', time() - 3600, "/");
            }
            
            // Registrar en logs
            registrarLogActividad($conn, $usuario['id'], $usuario['nombre'], 'visitante', 'Inicio de sesión');
            
            // Verificar si es primera vez (contraseña temporal)
            if (isset($_SESSION['password_temporal'])) {
                header("Location: ../usuario_visitante/cambiar_password.php?primera_vez=1");
            } else {
                header("Location: ../usuario_visitante/ixusuario.php");
            }
            exit;
        }
    }
    
    // ============================================
    // 4. SI LLEGA AQUÍ, LAS CREDENCIALES SON INCORRECTAS
    // ============================================
    registrarIntentoFallido($conn, $correo);
    
    // Obtener número de intentos restantes
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $intentosRestantes = 5;
    $checkSql = "SELECT intentos FROM intentos_login WHERE correo = ? AND ip_address = ? AND bloqueado_hasta IS NULL";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$correo, $ip]);
    if ($checkStmt->rowCount() > 0) {
        $registro = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $intentosRestantes = 5 - $registro['intentos'];
    }
    
    $_SESSION['intentos_restantes'] = $intentosRestantes;
    header("Location: ../index.php?error=1&intentos=" . $intentosRestantes);
    exit;
}

// ============================================
// CIERRE DE SESIÓN (AGREGADO)
// ============================================
if (isset($_GET['logout'])) {
    // Registrar cierre de sesión
    if (isset($_SESSION['nombre']) && isset($_SESSION['rol'])) {
        registrarLogActividad(
            $conn, 
            $_SESSION['id'] ?? null,
            $_SESSION['nombre'], 
            $_SESSION['rol'], 
            'Cierre de sesión'
        );
    }
    
    // Destruir la sesión
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    header("Location: ../index.php?success=logout");
    exit;
}

// ============================================
// VERIFICACIÓN DE SESIÓN (AGREGADA)
// ============================================
function verificarSesionActiva() {
    if (isset($_SESSION['login_time'])) {
        // Cerrar sesión después de 8 horas de inactividad
        $tiempo_maximo = 8 * 60 * 60; // 8 horas en segundos
        if (time() - $_SESSION['login_time'] > $tiempo_maximo) {
            return false;
        }
    }
    return isset($_SESSION['id']) || isset($_SESSION['admin_id']);
}

// ============================================
// OBTENER DATOS DEL USUARIO ACTUAL (AGREGADA)
// ============================================
function obtenerUsuarioActual($conn) {
    if (isset($_SESSION['admin_id'])) {
        $sql = "SELECT id, nombre, correo, role as rol FROM usuario_admin WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['admin_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            $usuario['tipo'] = 'admin';
            return $usuario;
        }
    } elseif (isset($_SESSION['id'])) {
        $tabla = $_SESSION['rol'] === 'propietario' ? 'usuario_propietario' : 'usuario_visitante';
        $sql = "SELECT id, nombre, correo, '$tabla' as tipo FROM $tabla WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario) {
            $usuario['tipo'] = $_SESSION['rol'];
            return $usuario;
        }
    }
    return null;
}
?>