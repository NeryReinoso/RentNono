<?php
session_start();
require_once __DIR__ . '/../database/conexion.php';
require_once __DIR__ . '/../database/session.php';

/* ====== HEADERS DE SEGURIDAD ====== */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ====== VERIFICAR ADMIN ====== */
if (!isset($_SESSION['admin_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

/* ====== DETERMINAR TIPO DE ADMIN ====== */
$es_superadmin = false;
try {
    $stmt = $conn->prepare("SELECT role FROM usuario_admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $es_superadmin = ($admin_data && $admin_data['role'] === 'superadmin');
} catch (Exception $e) {
    error_log("Error verificando role: " . $e->getMessage());
}

$adminNombre = $_SESSION['admin_nombre'] ?? 'Administrador';

/* ====== FUNCIÓN SANITIZAR ====== */
function sanitizar($dato) {
    if (is_array($dato)) {
        return array_map('sanitizar', $dato);
    }
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

/* ====== TOKEN CSRF ====== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ====== VARIABLES GLOBALES ====== */
$mensajeConfirmacion = '';
$tipoMensaje = '';

/* ====== FUNCIONES AUXILIARES ====== */

function verificarColumnaEstado($conn, $tabla) {
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE '$tabla'")->fetch();
        if (!$checkTable) return false;
        
        $checkColumn = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'estado'")->fetch();
        if (!$checkColumn) {
            $conn->exec("ALTER TABLE `$tabla` ADD COLUMN estado TINYINT(1) DEFAULT 1");
        }
        return true;
    } catch (Exception $e) {
        error_log("Error en verificarColumnaEstado: " . $e->getMessage());
        return false;
    }
}

function obtenerUsuariosPaginados($conn, $tabla, $limit, $offset) {
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE '$tabla'")->fetch();
        if (!$checkTable) return [];
        
        $campos = "id, nombre, correo, '$tabla' AS rol";
        $checkEstado = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'estado'")->fetch();
        
        if ($checkEstado) {
            $campos .= ", COALESCE(estado, 1) as estado";
        } else {
            $campos .= ", 1 as estado";
        }
        
        $sql = "SELECT $campos FROM `$tabla` LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error en obtenerUsuariosPaginados: " . $e->getMessage());
        return [];
    }
}

function obtenerServicios($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS servicios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL UNIQUE,
            icono VARCHAR(50) DEFAULT 'fa-solid fa-star',
            estado TINYINT(1) DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $conn->prepare("SELECT * FROM servicios WHERE estado = 1 ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error en obtenerServicios: " . $e->getMessage());
        return [];
    }
}

// Verificar tablas
$tablasUsuarios = ['usuario_admin', 'usuario_propietario', 'usuario_visitante'];
foreach ($tablasUsuarios as $tabla) {
    verificarColumnaEstado($conn, $tabla);
}

/* ====== PROCESAR PETICIONES POST ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // Validar CSRF para todas las peticiones POST
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $respuesta = ['success' => false, 'error' => 'Token de seguridad inválido. Recargá la página.'];
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode($respuesta);
        } else {
            $_SESSION['mensaje_confirmacion'] = $respuesta['error'];
            $_SESSION['tipo_mensaje'] = 'error';
            header("Location: indexadmin.php");
        }
        exit;
    }
    
    $accion = $_POST['accion'];
    
    // ===== AGREGAR USUARIO =====
    if ($accion === 'agregar_usuario') {
        $respuesta = ['success' => false, 'errors' => []];
        
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $rol = trim($_POST['rol'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validaciones
        if (empty($nombre)) $respuesta['errors'][] = 'El nombre es requerido';
        elseif (strlen($nombre) < 2) $respuesta['errors'][] = 'El nombre debe tener al menos 2 caracteres';
        elseif (strlen($nombre) > 100) $respuesta['errors'][] = 'El nombre no puede exceder 100 caracteres';
        
        if (empty($correo)) $respuesta['errors'][] = 'El correo es requerido';
        elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $respuesta['errors'][] = 'Correo electrónico inválido';
        elseif (strlen($correo) > 255) $respuesta['errors'][] = 'El correo no puede exceder 255 caracteres';
        
        $roles_validos = $es_superadmin ? ['admin', 'propietario', 'visitante'] : ['propietario', 'visitante'];
        if (empty($rol) || !in_array($rol, $roles_validos)) {
            $respuesta['errors'][] = 'Rol de usuario inválido';
        }
        
        if (empty($password)) $respuesta['errors'][] = 'La contraseña es requerida';
        elseif (strlen($password) < 8) $respuesta['errors'][] = 'La contraseña debe tener al menos 8 caracteres';
        elseif ($password !== $confirm_password) $respuesta['errors'][] = 'Las contraseñas no coinciden';
        
        if (empty($respuesta['errors'])) {
            $mapaTablas = [
                'admin' => 'usuario_admin',
                'propietario' => 'usuario_propietario',
                'visitante' => 'usuario_visitante'
            ];
            
            $tabla = $mapaTablas[$rol];
            
            try {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM `$tabla` WHERE correo = :correo");
                $stmtCheck->execute([':correo' => $correo]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $respuesta['errors'][] = 'Este correo ya está registrado';
                }
            } catch (Exception $e) {
                $respuesta['errors'][] = 'Error al verificar el correo';
            }
        }
        
        if (empty($respuesta['errors'])) {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                if ($rol === 'admin') {
                    $role = $es_superadmin ? 'admin' : 'admin';
                    $sqlInsert = "INSERT INTO `$tabla` (nombre, correo, password_hash, role, estado, creado_en) 
                                  VALUES (:nombre, :correo, :password, :role, 1, NOW())";
                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->execute([
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':password' => $password_hash,
                        ':role' => $role
                    ]);
                } else {
                    $columna_password = ($rol === 'propietario') ? 'password' : 'password';
                    $sqlInsert = "INSERT INTO `$tabla` (nombre, correo, $columna_password, estado, fecha_creacion) 
                                  VALUES (:nombre, :correo, :password, 1, NOW())";
                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->execute([
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':password' => $password_hash
                    ]);
                }
                
                $nuevoId = $conn->lastInsertId();
                
                $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                $conn->prepare($logSql)->execute([$adminNombre, "Agregó nuevo usuario $nombre (ID: $nuevoId, Rol: $rol)"]);
                
                $respuesta = [
                    'success' => true, 
                    'message' => 'Usuario agregado exitosamente',
                    'id' => $nuevoId,
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'rol' => $rol
                ];
                
            } catch (Exception $e) {
                $respuesta['errors'][] = 'Error al guardar en la base de datos';
            }
        }
        
        echo json_encode($respuesta);
        exit;
    }
    
    // ===== OBTENER DETALLES DE USUARIO =====
    if ($accion === 'obtener_detalles_usuario') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rol = $_POST['rol'] ?? '';
        
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];
        
        if ($id > 0 && isset($mapaTablas[$rol])) {
            $tabla = $mapaTablas[$rol];
            
            try {
                $sql = "SELECT * FROM `$tabla` WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':id' => $id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    if (!isset($usuario['estado'])) $usuario['estado'] = 1;
                    
                    $estadisticas = [];
                    
                    if ($rol === 'propietario') {
                        $stmtProp = $conn->prepare("SELECT COUNT(*) FROM propiedades WHERE id_propietario = :id");
                        $stmtProp->execute([':id' => $id]);
                        $estadisticas['propiedades'] = $stmtProp->fetchColumn();
                    }
                    
                    if ($rol === 'visitante') {
                        $stmtFav = $conn->prepare("SELECT COUNT(*) FROM favoritos WHERE id_usuario = :id");
                        $stmtFav->execute([':id' => $id]);
                        $estadisticas['favoritos'] = $stmtFav->fetchColumn();
                        
                        $stmtOp = $conn->prepare("SELECT COUNT(*) FROM opiniones WHERE usuario_id = :id");
                        $stmtOp->execute([':id' => $id]);
                        $estadisticas['opiniones'] = $stmtOp->fetchColumn();
                    }
                    
                    $stmtAct = $conn->prepare("SELECT accion, fecha FROM logs_actividad 
                                              WHERE usuario_id = :id ORDER BY fecha DESC LIMIT 3");
                    $stmtAct->execute([':id' => $id]);
                    $estadisticas['actividad_reciente'] = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'usuario' => $usuario,
                        'estadisticas' => $estadisticas
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        }
        exit;
    }
    
    // ===== CAMBIAR ESTADO DE USUARIO =====
    if ($accion === 'cambiar_estado') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rol = $_POST['rol'] ?? '';
        $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;
        
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];
        
        if ($id <= 0 || !isset($mapaTablas[$rol])) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
            exit;
        }
        
        $tabla = $mapaTablas[$rol];
        
        try {
            $stmtCheck = $conn->prepare("SELECT id, nombre FROM `$tabla` WHERE id = :id");
            $stmtCheck->execute([':id' => $id]);
            $usuario = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
                exit;
            }
            
            $sql = "UPDATE `$tabla` SET estado = :estado WHERE id = :id";
            $stmtUpdate = $conn->prepare($sql);
            $stmtUpdate->execute([':estado' => $estado, ':id' => $id]);
            
            $accionTxt = $estado ? 'activó' : 'inhabilitó';
            $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
            $conn->prepare($logSql)->execute([$adminNombre, "$accionTxt usuario '{$usuario['nombre']}' (ID: $id, Rol: $rol)"]);
            
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error interno en la base de datos.']);
        }
        exit;
    }
    
    // ===== ELIMINAR USUARIO =====
    if ($accion === 'eliminar_usuario') {
        // Solo superadmin puede eliminar usuarios
        if (!$es_superadmin) {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para eliminar usuarios.']);
            exit;
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $rol = $_POST['rol'] ?? '';
        
        $mapaTablas = [
            'admin' => 'usuario_admin',
            'propietario' => 'usuario_propietario',
            'visitante' => 'usuario_visitante'
        ];
        
        if ($id > 0 && isset($mapaTablas[$rol])) {
            $tabla = $mapaTablas[$rol];
            
            try {
                $sqlSelect = "SELECT nombre FROM `$tabla` WHERE id = :id";
                $stmtSelect = $conn->prepare($sqlSelect);
                $stmtSelect->execute([':id' => $id]);
                $usuario = $stmtSelect->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    $sql = "DELETE FROM `$tabla` WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $ok = $stmt->execute([':id' => $id]);
                    
                    if ($ok && $stmt->rowCount() > 0) {
                        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                        $conn->prepare($logSql)->execute([$adminNombre, "Eliminó usuario " . $usuario['nombre'] . " (ID: $id, Rol: $rol)"]);
                        
                        echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
                        exit;
                    }
                }
                
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Error en la base de datos.']);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
        exit;
    }
    
    // ===== EDITAR USUARIO =====
    if ($accion === 'editar') {
        // Admin regular no puede editar otros admins
        $rol_editado = $_POST['rol'] ?? '';
        if (!$es_superadmin && $rol_editado === 'admin') {
            $errores[] = "No tienes permisos para editar administradores.";
        } else {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $rol = $rol_editado;
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            
            $errores = [];
            
            if ($id <= 0) $errores[] = "ID inválido.";
            if (empty($nombre)) $errores[] = "El nombre no puede estar vacío.";
            if (strlen($nombre) < 2) $errores[] = "El nombre debe tener al menos 2 caracteres.";
            if (empty($correo)) $errores[] = "El correo no puede estar vacío.";
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "Correo inválido.";
            
            $mapaTablas = [
                'admin' => 'usuario_admin',
                'propietario' => 'usuario_propietario',
                'visitante' => 'usuario_visitante'
            ];
            
            if (!isset($mapaTablas[$rol])) {
                $errores[] = "Rol inválido.";
            }
            
            if (empty($errores)) {
                $tabla = $mapaTablas[$rol];
                
                try {
                    $sqlCheck = "SELECT COUNT(*) FROM `$tabla` WHERE correo = :correo AND id != :id";
                    $stmtCheck = $conn->prepare($sqlCheck);
                    $stmtCheck->execute([':correo' => $correo, ':id' => $id]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $errores[] = "El correo ya está en uso por otro usuario.";
                    }
                } catch (Exception $e) {
                    $errores[] = "Error al verificar correo.";
                }
            }
            
            if (empty($errores)) {
                try {
                    $sql = "UPDATE `$tabla` SET nombre = :nombre, correo = :correo, fecha_actualizacion = NOW() WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $ok = $stmt->execute([
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':id' => $id
                    ]);
                    
                    if ($ok && $stmt->rowCount() > 0) {
                        $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                        $conn->prepare($logSql)->execute([$adminNombre, "Editó usuario ID $id ($rol)"]);
                        
                        $_SESSION['mensaje_confirmacion'] = 'Usuario editado correctamente';
                        $_SESSION['tipo_mensaje'] = 'success';
                        
                        header("Location: indexadmin.php?edit=ok&seccion=usuarios");
                        exit;
                    } else {
                        $errores[] = "No se realizaron cambios o el usuario no existe.";
                    }
                } catch (Exception $e) {
                    $errores[] = "Error al actualizar en la base de datos.";
                }
            }
        }
        
        if (!empty($errores)) {
            $_SESSION['mensaje_confirmacion'] = implode(' ', $errores);
            $_SESSION['tipo_mensaje'] = 'error';
            header("Location: indexadmin.php?error=1&seccion=usuarios");
            exit;
        }
    }
    
    // ===== PAGINACIÓN AJAX =====
    if ($accion === 'cambiar_pagina') {
        $tipo = $_POST['tipo'] ?? '';
        $pagina = (int)($_POST['pagina'] ?? 1);
        if ($pagina < 1) $pagina = 1;
        
        $usuariosPorPagina = 8;
        $offset = ($pagina - 1) * $usuariosPorPagina;
        
        $mapaTablas = [
            'admins' => 'usuario_admin',
            'propietarios' => 'usuario_propietario', 
            'visitantes' => 'usuario_visitante'
        ];
        
        $mapaRoles = [
            'admins' => 'admin',
            'propietarios' => 'propietario',
            'visitantes' => 'visitante'
        ];
        
        if (isset($mapaTablas[$tipo])) {
            $tabla = $mapaTablas[$tipo];
            $rol = $mapaRoles[$tipo];
            
            $usuarios = obtenerUsuariosPaginados($conn, $tabla, $usuariosPorPagina, $offset);
            
            if (!empty($usuarios)) {
                ob_start();
                foreach ($usuarios as $u): ?>
                <tr data-id="<?= $u['id'] ?>" data-rol="<?= $rol ?>">
                    <td>
                        <div class="usuario-info">
                            <i class="fa-solid fa-<?= $rol === 'admin' ? 'user-shield' : ($rol === 'propietario' ? 'house-user' : 'user') ?>"></i>
                            <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="rol-badge rol-<?= $rol ?>"><?= ucfirst($rol) ?></span></td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" class="toggle-estado" 
                                   data-id="<?= (int)$u['id'] ?>" 
                                   data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>"
                                   <?= (int)$u['estado'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                            <span class="estado-texto"><?= (int)$u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                        </label>
                    </td>
                    <td class="acciones-td">
                        <div class="acciones-container">
                            <button class="editarBtn" data-id="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" title="Editar usuario">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ($es_superadmin || $rol !== 'admin'): ?>
                            <button class="eliminarBtn" data-id="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <?php endif; ?>
                            <button class="verDetallesBtn" data-id="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach;
                $html = ob_get_clean();
                echo $html;
            } else {
                echo '<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-users"></i> No hay más usuarios</td></tr>';
            }
        } else {
            echo '<tr><td colspan="5" class="sin-datos-tabla"><i class="fa-solid fa-exclamation-triangle"></i> Tipo de usuario no válido</td></tr>';
        }
        exit;
    }
    
    // ===== AGREGAR SERVICIO =====
    if ($accion === 'agregar_servicio') {
        $nombre_servicio = trim($_POST['nombre_servicio'] ?? '');
        $icono_servicio = trim($_POST['icono_servicio'] ?? 'fa-solid fa-star');
        
        $errores = [];
        
        if (empty($nombre_servicio)) $errores[] = 'El nombre del servicio es requerido';
        if (strlen($nombre_servicio) < 2) $errores[] = 'El nombre debe tener al menos 2 caracteres';
        if (strlen($nombre_servicio) > 50) $errores[] = 'El nombre no puede exceder 50 caracteres';
        
        if (empty($errores)) {
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS servicios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nombre VARCHAR(50) NOT NULL UNIQUE,
                    icono VARCHAR(50) DEFAULT 'fa-solid fa-star',
                    estado TINYINT(1) DEFAULT 1,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $sqlCheck = "SELECT COUNT(*) FROM servicios WHERE nombre = :nombre";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute([':nombre' => $nombre_servicio]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $errores[] = 'Este servicio ya está registrado';
                }
            } catch (Exception $e) {
                $errores[] = 'Error al verificar el servicio';
            }
        }
        
        if (!empty($errores)) {
            echo json_encode(['success' => false, 'errors' => $errores]);
            exit;
        }
        
        try {
            $sqlInsert = "INSERT INTO servicios (nombre, icono, estado) VALUES (:nombre, :icono, 1)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->execute([
                ':nombre' => $nombre_servicio,
                ':icono' => $icono_servicio
            ]);
            
            $nuevoId = $conn->lastInsertId();
            
            $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
            $conn->prepare($logSql)->execute([$adminNombre, "Agregó nuevo servicio: $nombre_servicio"]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Servicio agregado exitosamente',
                'id' => $nuevoId,
                'nombre' => $nombre_servicio,
                'icono' => $icono_servicio
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos']);
        }
        exit;
    }
    
    // ===== SUBIR PROPIEDAD COMO ADMIN =====
    if ($accion === 'subir_propiedad_admin') {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0;
        $no_decirlo = isset($_POST['no_decirlo']) ? 1 : 0;
        $ambientes = isset($_POST['ambientes']) ? (int)$_POST['ambientes'] : 0;
        $banios = isset($_POST['banios']) ? (int)$_POST['banios'] : 0;
        $superficie = isset($_POST['superficie']) ? (int)$_POST['superficie'] : 0;
        $direccion = trim($_POST['direccion'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $provincia = trim($_POST['provincia'] ?? '');
        $servicios = isset($_POST['servicios']) ? $_POST['servicios'] : [];
        
        $errores = [];
        
        if (empty($titulo)) $errores[] = 'El título es requerido';
        if (empty($descripcion)) $errores[] = 'La descripción es requerida';
        if (empty($direccion)) $errores[] = 'La dirección es requerida';
        if ($ambientes <= 0) $errores[] = 'Debe tener al menos 1 ambiente';
        if ($banios <= 0) $errores[] = 'Debe tener al menos 1 baño';
        if ($superficie <= 0) $errores[] = 'La superficie debe ser mayor a 0';
        
        if (empty($errores)) {
            try {
                $conn->beginTransaction();
                
                $sqlPropiedad = "INSERT INTO propiedades (
                    titulo, descripcion, precio, precio_no_publicado, ambientes, sanitarios, 
                    superficie, direccion, ciudad, provincia,
                    id_propietario, estado_publicacion, fecha_solicitud, fecha_revision,
                    id_admin_revisor, servicios, tipo, operacion, estado
                ) VALUES (
                    :titulo, :descripcion, :precio, :precio_no_publicado, :ambientes, :sanitarios,
                    :superficie, :direccion, :ciudad, :provincia,
                    :id_propietario, 'aprobada', NOW(), NOW(),
                    :admin_id, :servicios, 'casa', 'alquiler', 'a estrenar'
                )";
                
                $stmtPropiedad = $conn->prepare($sqlPropiedad);
                $servicios_str = !empty($servicios) ? implode(',', $servicios) : '';
                $id_propietario = null;
                
                $stmtPropiedad->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':precio' => $no_decirlo ? 0 : $precio,
                    ':precio_no_publicado' => $no_decirlo,
                    ':ambientes' => $ambientes,
                    ':sanitarios' => $banios,
                    ':superficie' => $superficie,
                    ':direccion' => $direccion,
                    ':ciudad' => $ciudad,
                    ':provincia' => $provincia,
                    ':id_propietario' => $id_propietario,
                    ':admin_id' => $_SESSION['admin_id'],
                    ':servicios' => $servicios_str
                ]);
                
                $id_propiedad = $conn->lastInsertId();
                
                if (!empty($_FILES['imagenes']) && $_FILES['imagenes']['name'][0] != '') {
                    $year = date('Y');
                    $month = date('m');
                    $upload_dir = "../media/propiedades/$year/$month/";
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $total_imagenes = count($_FILES['imagenes']['name']);
                    
                    for ($i = 0; $i < $total_imagenes; $i++) {
                        if ($_FILES['imagenes']['error'][$i] == UPLOAD_ERR_OK) {
                            $nombre_original = $_FILES['imagenes']['name'][$i];
                            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                            $nuevo_nombre = "propiedad_{$id_propiedad}_" . ($i + 1) . "_" . time() . ".$extension";
                            $ruta_completa = $upload_dir . $nuevo_nombre;
                            
                            if (move_uploaded_file($_FILES['imagenes']['tmp_name'][$i], $ruta_completa)) {
                                $sql_img = "INSERT INTO imagenes_propiedades 
                                            (id_propiedad, nombre_archivo, ruta, es_principal, orden) 
                                            VALUES (:id_propiedad, :nombre_archivo, :ruta, :es_principal, :orden)";
                                
                                $stmt_img = $conn->prepare($sql_img);
                                $stmt_img->execute([
                                    ':id_propiedad' => $id_propiedad,
                                    ':nombre_archivo' => $nuevo_nombre,
                                    ':ruta' => "propiedades/$year/$month/$nuevo_nombre",
                                    ':es_principal' => ($i == 0 ? 1 : 0),
                                    ':orden' => $i
                                ]);
                            }
                        }
                    }
                }
                
                $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
                $conn->prepare($logSql)->execute([$adminNombre, "Subió propiedad: $titulo (ID: $id_propiedad)"]);
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Propiedad subida exitosamente y publicada',
                    'id' => $id_propiedad
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'errors' => $errores]);
        }
        exit;
    }
    
    // ===== OBTENER ESTADÍSTICAS DASHBOARD =====
    if ($accion === 'obtener_estadisticas_dashboard') {
        try {
            $periodo = $_POST['periodo'] ?? 'semana';
            $fecha_desde = $_POST['fecha_desde'] ?? null;
            $fecha_hasta = $_POST['fecha_hasta'] ?? null;
            
            $hoy = date('Y-m-d');
            $fecha_inicio = '';
            $fecha_fin = $hoy;
            
            switch($periodo) {
                case 'hoy': $fecha_inicio = $hoy; break;
                case 'semana': $fecha_inicio = date('Y-m-d', strtotime('-7 days')); break;
                case 'mes': $fecha_inicio = date('Y-m-d', strtotime('-30 days')); break;
                case 'trimestre': $fecha_inicio = date('Y-m-d', strtotime('-90 days')); break;
                case 'anio': $fecha_inicio = date('Y-m-d', strtotime('-365 days')); break;
                case 'personalizado':
                    if ($fecha_desde && $fecha_hasta) {
                        $fecha_inicio = $fecha_desde;
                        $fecha_fin = $fecha_hasta;
                    } else {
                        $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
                    }
                    break;
                default: $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
            }
            
            $estadisticas = [];
            
            // Usuarios totales
            $totalAdmins = $conn->query("SELECT COUNT(*) FROM usuario_admin")->fetchColumn();
            $totalPropietarios = $conn->query("SELECT COUNT(*) FROM usuario_propietario")->fetchColumn();
            $totalVisitantes = $conn->query("SELECT COUNT(*) FROM usuario_visitante")->fetchColumn();
            $totalUsuarios = $totalAdmins + $totalPropietarios + $totalVisitantes;
            
            $usuariosMesPasado = $conn->query("
                SELECT COUNT(*) FROM (
                    SELECT id FROM usuario_admin WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 1 MONTH)
                    UNION ALL
                    SELECT id FROM usuario_propietario WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 1 MONTH)
                    UNION ALL
                    SELECT id FROM usuario_visitante WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 1 MONTH)
                ) as usuarios_antiguos
            ")->fetchColumn();
            
            $cambioUsuarios = $usuariosMesPasado > 0 ? 
                round((($totalUsuarios - $usuariosMesPasado) / $usuariosMesPasado) * 100) : 0;
            
            $estadisticas['usuarios_totales'] = $totalUsuarios;
            $estadisticas['cambio_usuarios'] = $cambioUsuarios;
            
            // Propiedades activas
            $propiedadesActivas = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'aprobada'")->fetchColumn();
            $propiedadesMesPasado = $conn->query("
                SELECT COUNT(*) FROM propiedades 
                WHERE estado_publicacion = 'aprobada' 
                AND fecha_revision < DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ")->fetchColumn();
            
            $cambioPropiedades = $propiedadesMesPasado > 0 ? 
                round((($propiedadesActivas - $propiedadesMesPasado) / $propiedadesMesPasado) * 100) : 0;
            
            $estadisticas['propiedades_activas'] = $propiedadesActivas;
            $estadisticas['cambio_propiedades'] = $cambioPropiedades;
            
            // Solicitudes pendientes
            $solicitudesPendientes = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
            $solicitudesSemanaPasada = $conn->query("
                SELECT COUNT(*) FROM propiedades 
                WHERE estado_publicacion = 'pendiente' 
                AND fecha_solicitud < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ")->fetchColumn();
            
            $cambioSolicitudes = $solicitudesSemanaPasada > 0 ? 
                round((($solicitudesPendientes - $solicitudesSemanaPasada) / $solicitudesSemanaPasada) * 100) : 0;
            
            $estadisticas['solicitudes_pendientes'] = $solicitudesPendientes;
            $estadisticas['cambio_solicitudes'] = $cambioSolicitudes;
            
            // Tasa de aprobación
            $totalProcesadas = $conn->query("
                SELECT COUNT(*) FROM propiedades 
                WHERE estado_publicacion IN ('aprobada', 'rechazada')
                AND fecha_revision >= '$fecha_inicio'
            ")->fetchColumn();
            
            $aprobadas = $conn->query("
                SELECT COUNT(*) FROM propiedades 
                WHERE estado_publicacion = 'aprobada'
                AND fecha_revision >= '$fecha_inicio'
            ")->fetchColumn();
            
            $tasaAprobacion = $totalProcesadas > 0 ? round(($aprobadas / $totalProcesadas) * 100) : 0;
            
            $estadisticas['tasa_aprobacion'] = $tasaAprobacion;
            $estadisticas['cambio_aprobacion'] = 0; // Simplificado
            
            // Gráficos
            $graficos = [];
            
            // Actividad diaria
            $actividadDiaria = [];
            $labels = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $fecha = date('Y-m-d', strtotime("-$i days"));
                $diaSemana = date('D', strtotime($fecha));
                $labels[] = substr($diaSemana, 0, 3);
                
                $nuevosUsuarios = $conn->query("
                    SELECT COUNT(*) FROM (
                        SELECT id FROM usuario_admin WHERE DATE(fecha_creacion) = '$fecha'
                        UNION ALL
                        SELECT id FROM usuario_propietario WHERE DATE(fecha_creacion) = '$fecha'
                        UNION ALL
                        SELECT id FROM usuario_visitante WHERE DATE(fecha_creacion) = '$fecha'
                    ) as total
                ")->fetchColumn();
                
                $propiedadesPublicadas = $conn->query("
                    SELECT COUNT(*) FROM propiedades 
                    WHERE estado_publicacion = 'aprobada' 
                    AND DATE(fecha_revision) = '$fecha'
                ")->fetchColumn();
                
                $solicitudesDia = $conn->query("
                    SELECT COUNT(*) FROM propiedades 
                    WHERE estado_publicacion = 'pendiente' 
                    AND DATE(fecha_solicitud) = '$fecha'
                ")->fetchColumn();
                
                $actividadDiaria[] = [
                    'nuevos_usuarios' => $nuevosUsuarios,
                    'propiedades_publicadas' => $propiedadesPublicadas,
                    'solicitudes' => $solicitudesDia
                ];
            }
            
            $graficos['actividad_diaria'] = [
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Nuevos Usuarios', 'data' => array_column($actividadDiaria, 'nuevos_usuarios')],
                    ['label' => 'Propiedades Publicadas', 'data' => array_column($actividadDiaria, 'propiedades_publicadas')],
                    ['label' => 'Solicitudes', 'data' => array_column($actividadDiaria, 'solicitudes')]
                ]
            ];
            
            $graficos['distribucion_usuarios'] = [
                'labels' => ['Administradores', 'Propietarios', 'Visitantes'],
                'data' => [$totalAdmins, $totalPropietarios, $totalVisitantes]
            ];
            
            $totalAprobadas = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'aprobada'")->fetchColumn();
            $totalRechazadas = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'rechazada'")->fetchColumn();
            
            $graficos['estado_solicitudes'] = [
                'labels' => ['Aprobadas', 'Pendientes', 'Rechazadas'],
                'data' => [$totalAprobadas, $solicitudesPendientes, $totalRechazadas]
            ];
            
            // Actividad reciente
            $actividadReciente = $conn->query("
                SELECT usuario_nombre, rol, accion, fecha 
                FROM logs_actividad 
                ORDER BY fecha DESC 
                LIMIT 8
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'estadisticas' => $estadisticas,
                'graficos' => $graficos,
                'actividad_reciente' => $actividadReciente
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener estadísticas']);
        }
        exit;
    }
    
    // ===== GESTIONAR SOLICITUD =====
    if ($accion === 'gestionar_solicitud') {
        $id_propiedad = isset($_POST['id_propiedad']) ? (int)$_POST['id_propiedad'] : 0;
        $decision = $_POST['decision'] ?? '';
        $motivo_rechazo = isset($_POST['motivo_rechazo']) ? trim($_POST['motivo_rechazo']) : '';
        
        if ($id_propiedad <= 0 || !in_array($decision, ['aprobar', 'rechazar'])) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
            exit;
        }
        
        if ($decision === 'rechazar' && empty($motivo_rechazo)) {
            echo json_encode(['success' => false, 'error' => 'Debes ingresar un motivo para rechazar.']);
            exit;
        }
        
        try {
            $sqlPropiedad = "SELECT p.*, up.nombre as propietario_nombre, up.correo as propietario_correo 
                            FROM propiedades p 
                            LEFT JOIN usuario_propietario up ON p.id_propietario = up.id 
                            WHERE p.id = :id";
            $stmtProp = $conn->prepare($sqlPropiedad);
            $stmtProp->execute([':id' => $id_propiedad]);
            $propiedad = $stmtProp->fetch(PDO::FETCH_ASSOC);
            
            if (!$propiedad) {
                echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada.']);
                exit;
            }
            
            if ($propiedad['estado_publicacion'] !== 'pendiente') {
                echo json_encode(['success' => false, 'error' => 'Esta solicitud ya fue procesada.']);
                exit;
            }
            
            $conn->beginTransaction();
            
            if ($decision === 'aprobar') {
                $sqlAprobar = "UPDATE propiedades SET 
                              estado_publicacion = 'aprobada',
                              fecha_revision = NOW(),
                              id_admin_revisor = :admin_id,
                              motivo_rechazo = NULL
                              WHERE id = :id";
                
                $stmtAprobar = $conn->prepare($sqlAprobar);
                $stmtAprobar->execute([
                    ':admin_id' => $_SESSION['admin_id'],
                    ':id' => $id_propiedad
                ]);
                
                $accionLog = "Aprobó propiedad: {$propiedad['titulo']} (ID: $id_propiedad)";
                
            } else {
                $sqlRechazar = "UPDATE propiedades SET 
                               estado_publicacion = 'rechazada',
                               fecha_revision = NOW(),
                               id_admin_revisor = :admin_id,
                               motivo_rechazo = :motivo
                               WHERE id = :id";
                
                $stmtRechazar = $conn->prepare($sqlRechazar);
                $stmtRechazar->execute([
                    ':admin_id' => $_SESSION['admin_id'],
                    ':motivo' => $motivo_rechazo,
                    ':id' => $id_propiedad
                ]);
                
                $accionLog = "Rechazó propiedad: {$propiedad['titulo']} (ID: $id_propiedad)";
            }
            
            $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
            $conn->prepare($logSql)->execute([$adminNombre, $accionLog]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $decision === 'aprobar' ? 'Propiedad aprobada correctamente' : 'Propiedad rechazada correctamente',
                'nuevo_estado' => $decision === 'aprobar' ? 'aprobada' : 'rechazada'
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ===== OBTENER DETALLES DE SOLICITUD =====
    if ($accion === 'obtener_detalles_solicitud') {
        $id_propiedad = isset($_POST['id_propiedad']) ? (int)$_POST['id_propiedad'] : 0;
        
        if ($id_propiedad <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de propiedad inválido.']);
            exit;
        }
        
        try {
            $sql = "SELECT 
                p.*,
                up.nombre as propietario_nombre,
                up.correo as propietario_correo,
                up.telefono as propietario_telefono,
                GROUP_CONCAT(ip.ruta SEPARATOR '||') as imagenes_rutas,
                GROUP_CONCAT(ip.nombre_archivo SEPARATOR '||') as imagenes_nombres
            FROM propiedades p
            LEFT JOIN usuario_propietario up ON p.id_propietario = up.id
            LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad
            WHERE p.id = :id_propiedad
            GROUP BY p.id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id_propiedad' => $id_propiedad]);
            $propiedad = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$propiedad) {
                echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada.']);
                exit;
            }
            
            if (!empty($propiedad['servicios'])) {
                $propiedad['servicios_array'] = explode(',', $propiedad['servicios']);
            } else {
                $propiedad['servicios_array'] = [];
            }
            
            $imagenes = [];
            if (!empty($propiedad['imagenes_rutas'])) {
                $rutas = explode('||', $propiedad['imagenes_rutas']);
                $nombres = explode('||', $propiedad['imagenes_nombres']);
                
                for ($i = 0; $i < count($rutas); $i++) {
                    if (!empty($rutas[$i])) {
                        $imagenes[] = [
                            'ruta' => $rutas[$i],
                            'nombre' => $nombres[$i] ?? 'imagen_' . ($i + 1)
                        ];
                    }
                }
            }
            $propiedad['imagenes_array'] = $imagenes;
            
            if ($propiedad['precio_no_publicado'] == 1) {
                $propiedad['precio_display'] = 'No publicado';
            } else {
                $propiedad['precio_display'] = '$' . number_format($propiedad['precio'], 0, ',', '.');
            }
            
            $propiedad['fecha_solicitud_formateada'] = date('d/m/Y H:i', strtotime($propiedad['fecha_solicitud']));
            
            echo json_encode([
                'success' => true,
                'propiedad' => $propiedad
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
        }
        exit;
    }
    
    // ===== GESTIONAR PROPIEDAD (ocultar/mostrar) =====
    if ($accion === 'gestionar_propiedad') {
        $id_propiedad = isset($_POST['id_propiedad']) ? (int)$_POST['id_propiedad'] : 0;
        $tipo_accion = $_POST['tipo_accion'] ?? '';
        
        if ($id_propiedad <= 0 || !in_array($tipo_accion, ['ocultar', 'mostrar'])) {
            echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
            exit;
        }
        
        try {
            $sqlPropiedad = "SELECT p.*, up.nombre as propietario_nombre 
                            FROM propiedades p 
                            LEFT JOIN usuario_propietario up ON p.id_propietario = up.id 
                            WHERE p.id = :id";
            $stmtProp = $conn->prepare($sqlPropiedad);
            $stmtProp->execute([':id' => $id_propiedad]);
            $propiedad = $stmtProp->fetch(PDO::FETCH_ASSOC);
            
            if (!$propiedad) {
                echo json_encode(['success' => false, 'error' => 'Propiedad no encontrada.']);
                exit;
            }
            
            if (!in_array($propiedad['estado_publicacion'], ['aprobada', 'inactiva'])) {
                echo json_encode(['success' => false, 'error' => 'Solo se pueden gestionar propiedades aprobadas o inactivas.']);
                exit;
            }
            
            $nuevo_estado = $tipo_accion === 'ocultar' ? 'inactiva' : 'aprobada';
            
            $sqlActualizar = "UPDATE propiedades SET estado_publicacion = :estado WHERE id = :id";
            $stmtActualizar = $conn->prepare($sqlActualizar);
            $stmtActualizar->execute([
                ':estado' => $nuevo_estado,
                ':id' => $id_propiedad
            ]);
            
            $accionTexto = $tipo_accion === 'ocultar' ? 'Ocultó' : 'Volvió a mostrar';
            $logSql = "INSERT INTO logs_actividad (usuario_nombre, rol, accion, fecha) VALUES (?, 'admin', ?, NOW())";
            $conn->prepare($logSql)->execute([$adminNombre, "$accionTexto propiedad: {$propiedad['titulo']} (ID: $id_propiedad)"]);
            
            echo json_encode([
                'success' => true,
                'message' => $tipo_accion === 'ocultar' ? 'Propiedad ocultada del sitio principal' : 'Propiedad visible nuevamente',
                'nuevo_estado' => $nuevo_estado
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
        }
        exit;
    }
    
    // ===== OBTENER ACTIVIDAD RECIENTE =====
    if ($accion === 'obtener_actividad_reciente') {
        $limite = isset($_POST['limite']) ? (int)$_POST['limite'] : 8;
        
        try {
            $actividad = $conn->query("
                SELECT usuario_nombre, rol, accion, fecha 
                FROM logs_actividad 
                ORDER BY fecha DESC 
                LIMIT $limite
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'actividad' => $actividad
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener actividad']);
        }
        exit;
    }

    // ====== BUSCAR LOGS (nuevo endpoint) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'buscar_logs') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $busqueda = $_POST['busqueda'] ?? '';
    $solo_hoy = isset($_POST['solo_hoy']) && $_POST['solo_hoy'] === '1';
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $por_pagina = 8;
    $offset = ($pagina - 1) * $por_pagina;
    
    try {
        // Construir consulta con filtros
        $sql = "SELECT SQL_CALC_FOUND_ROWS 
                usuario_nombre, rol, accion, 
                DATE(fecha) as fecha_simple,
                DATE_FORMAT(fecha, '%H:%i:%s') as hora
                FROM logs_actividad 
                WHERE 1=1";
        
        $params = [];
        
        if ($solo_hoy) {
            $sql .= " AND DATE(fecha) = CURDATE()";
        }
        
        if (!empty($busqueda)) {
            $sql .= " AND (usuario_nombre LIKE :busqueda 
                        OR rol LIKE :busqueda 
                        OR accion LIKE :busqueda 
                        OR DATE_FORMAT(fecha, '%d/%m/%Y') LIKE :busqueda
                        OR DATE_FORMAT(fecha, '%H:%i:%s') LIKE :busqueda)";
            $params[':busqueda'] = "%$busqueda%";
        }
        
        $sql .= " ORDER BY fecha DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($busqueda)) {
            $stmt->bindValue(':busqueda', $params[':busqueda'], PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener total de registros
        $total = $conn->query("SELECT FOUND_ROWS()")->fetchColumn();
        $total_paginas = ceil($total / $por_pagina);
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'total' => $total,
            'pagina' => $pagina,
            'total_paginas' => $total_paginas
        ]);
        
    } catch (Exception $e) {
        error_log("Error buscando logs: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error en la búsqueda']);
    }
    exit;
}
}

/* ====== OBTENER DATOS PARA LA VISTA ====== */
$usuariosPorPagina = 8;
$logsPorPagina = 8;

// Paginación logs
$paginaLogs = isset($_GET['pagina_logs']) ? max(1, (int)$_GET['pagina_logs']) : 1;
$offsetLogs = ($paginaLogs - 1) * $logsPorPagina;

try {
    $totalLogs = $conn->query("SELECT COUNT(*) FROM logs_actividad")->fetchColumn();
    $totalPaginasLogs = ceil($totalLogs / $logsPorPagina);
} catch (Exception $e) {
    $totalLogs = 0;
    $totalPaginasLogs = 1;
}

// Paginación usuarios
$paginaAdmins = isset($_GET['pagina_admins']) ? max(1, (int)$_GET['pagina_admins']) : 1;
$paginaPropietarios = isset($_GET['pagina_propietarios']) ? max(1, (int)$_GET['pagina_propietarios']) : 1;
$paginaVisitantes = isset($_GET['pagina_visitantes']) ? max(1, (int)$_GET['pagina_visitantes']) : 1;

$offsetAdmins = ($paginaAdmins - 1) * $usuariosPorPagina;
$offsetPropietarios = ($paginaPropietarios - 1) * $usuariosPorPagina;
$offsetVisitantes = ($paginaVisitantes - 1) * $usuariosPorPagina;

// Obtener logs
try {
    $logs = $conn->prepare("
        SELECT usuario_nombre, rol, accion, fecha, DATE(fecha) as fecha_simple
        FROM logs_actividad
        ORDER BY fecha DESC
        LIMIT :limit OFFSET :offset
    ");
    $logs->bindValue(':limit', $logsPorPagina, PDO::PARAM_INT);
    $logs->bindValue(':offset', $offsetLogs, PDO::PARAM_INT);
    $logs->execute();
    $logs = $logs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
}

// Totales
try {
    $totalAdmins = $conn->query("SELECT COUNT(*) FROM usuario_admin")->fetchColumn();
    $totalPropietarios = $conn->query("SELECT COUNT(*) FROM usuario_propietario")->fetchColumn();
    $totalVisitantes = $conn->query("SELECT COUNT(*) FROM usuario_visitante")->fetchColumn();
} catch (Exception $e) {
    $totalAdmins = $totalPropietarios = $totalVisitantes = 0;
}

// Datos paginados
$admins = obtenerUsuariosPaginados($conn, 'usuario_admin', $usuariosPorPagina, $offsetAdmins);
$propietarios = obtenerUsuariosPaginados($conn, 'usuario_propietario', $usuariosPorPagina, $offsetPropietarios);
$visitantes = obtenerUsuariosPaginados($conn, 'usuario_visitante', $usuariosPorPagina, $offsetVisitantes);

$totalUsuarios = $totalAdmins + $totalPropietarios + $totalVisitantes;

// Estadísticas adicionales
try {
    $logsHoy = $conn->query("
        SELECT COUNT(*) FROM logs_actividad 
        WHERE DATE(fecha) = CURDATE()
    ")->fetchColumn();
    
    $usuariosActivos = $conn->query("
        SELECT COUNT(DISTINCT usuario_nombre) FROM logs_actividad 
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ")->fetchColumn();
} catch (Exception $e) {
    $logsHoy = 0;
    $usuariosActivos = 0;
}

$totalPaginasAdmins = ceil($totalAdmins / $usuariosPorPagina);
$totalPaginasPropietarios = ceil($totalPropietarios / $usuariosPorPagina);
$totalPaginasVisitantes = ceil($totalVisitantes / $usuariosPorPagina);

$seccionActiva = isset($_GET['seccion']) ? $_GET['seccion'] : 'inicio';
$tablaActiva = isset($_GET['tabla']) && in_array($_GET['tabla'], ['admins', 'propietarios', 'visitantes', 'logs']) 
    ? $_GET['tabla'] 
    : 'admins';

// Solicitudes pendientes
try {
    $solicitudesPendientes = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
} catch (Exception $e) {
    $solicitudesPendientes = 0;
}

$solicitudesPorPagina = 5;
$paginaSolicitudes = isset($_GET['pagina_solicitudes']) ? max(1, (int)$_GET['pagina_solicitudes']) : 1;
$offsetSolicitudes = ($paginaSolicitudes - 1) * $solicitudesPorPagina;

try {
    $totalSolicitudes = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
    $totalPaginasSolicitudes = ceil($totalSolicitudes / $solicitudesPorPagina);
    
    $sqlSolicitudes = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios,
        p.superficie,
        p.direccion,
        p.fecha_solicitud,
        up.nombre as propietario_nombre,
        up.correo as propietario_correo,
        ip.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN usuario_propietario up ON p.id_propietario = up.id
    LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad AND ip.es_principal = 1
    WHERE p.estado_publicacion = 'pendiente'
    ORDER BY p.fecha_solicitud DESC
    LIMIT :limit OFFSET :offset";
    
    $stmtSolicitudes = $conn->prepare($sqlSolicitudes);
    $stmtSolicitudes->bindValue(':limit', $solicitudesPorPagina, PDO::PARAM_INT);
    $stmtSolicitudes->bindValue(':offset', $offsetSolicitudes, PDO::PARAM_INT);
    $stmtSolicitudes->execute();
    $solicitudes = $stmtSolicitudes->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalSolicitudes = 0;
    $totalPaginasSolicitudes = 1;
    $solicitudes = [];
}

// Propiedades publicadas
$propiedadesPorPagina = 8;
$paginaPropiedades = isset($_GET['pagina_propiedades']) ? max(1, (int)$_GET['pagina_propiedades']) : 1;
$offsetPropiedades = ($paginaPropiedades - 1) * $propiedadesPorPagina;

try {
    $totalPropiedades = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion IN ('aprobada', 'inactiva')")->fetchColumn();
    $propiedadesVisibles = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'aprobada'")->fetchColumn();
    $totalPaginasPropiedades = ceil($totalPropiedades / $propiedadesPorPagina);
    
    $sqlPropiedades = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios,
        p.superficie,
        p.direccion,
        p.estado_publicacion,
        p.fecha_solicitud,
        p.fecha_revision,
        up.nombre as propietario_nombre,
        up.correo as propietario_correo,
        ip.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN usuario_propietario up ON p.id_propietario = up.id
    LEFT JOIN imagenes_propiedades ip ON p.id = ip.id_propiedad AND ip.es_principal = 1
    WHERE p.estado_publicacion IN ('aprobada', 'inactiva')
    ORDER BY 
        CASE 
            WHEN p.estado_publicacion = 'aprobada' THEN 1 
            WHEN p.estado_publicacion = 'inactiva' THEN 2 
        END,
        p.fecha_revision DESC
    LIMIT :limit OFFSET :offset";
    
    $stmtPropiedades = $conn->prepare($sqlPropiedades);
    $stmtPropiedades->bindValue(':limit', $propiedadesPorPagina, PDO::PARAM_INT);
    $stmtPropiedades->bindValue(':offset', $offsetPropiedades, PDO::PARAM_INT);
    $stmtPropiedades->execute();
    $propiedadesPublicadas = $stmtPropiedades->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalPropiedades = 0;
    $propiedadesVisibles = 0;
    $totalPaginasPropiedades = 1;
    $propiedadesPublicadas = [];
}

// Servicios disponibles
$servicios_disponibles = obtenerServicios($conn);

// Contar solicitudes para AJAX
if (isset($_GET['accion']) && $_GET['accion'] === 'contar_solicitudes' && isset($_GET['ajax'])) {
    try {
        $total = $conn->query("SELECT COUNT(*) FROM propiedades WHERE estado_publicacion = 'pendiente'")->fetchColumn();
        echo json_encode(['success' => true, 'total' => $total]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'total' => 0]);
    }
    exit;
}

// Verificar mensajes de confirmación
if (isset($_SESSION['mensaje_confirmacion'])) {
    $mensajeConfirmacion = $_SESSION['mensaje_confirmacion'];
    $tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'success';
    unset($_SESSION['mensaje_confirmacion']);
    unset($_SESSION['tipo_mensaje']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin | RENTNONO</title>
    <link rel="stylesheet" href="../estilos/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="layout">

   <!-- SIDEBAR -->
<aside class="sidebar">
    <h2 class="logo">RENTNONO</h2>

<!-- INICIO -->
<button class="menu-btn <?= $seccionActiva === 'inicio' ? 'activo' : '' ?>" data-seccion="inicio" id="btnInicio">
    <i class="fa-solid fa-house"></i> <span class="menu-text">Inicio</span>
</button>

    <!-- USUARIOS -->
    <button class="menu-btn <?= $seccionActiva === 'usuarios' ? 'activo' : '' ?>" id="btnUsuarios">
        <i class="fa-solid fa-users"></i> <span class="menu-text">Usuarios</span>
        <i class="fa-solid fa-chevron-down flecha"></i>
    </button>

    <div class="submenu" id="submenuUsuarios" style="<?= $seccionActiva === 'usuarios' ? 'max-height: 240px;' : '' ?>">
        <button class="submenu-btn <?= $tablaActiva === 'admins' ? 'activo' : '' ?>" data-tabla="admins">Administradores</button>
        <button class="submenu-btn <?= $tablaActiva === 'propietarios' ? 'activo' : '' ?>" data-tabla="propietarios">Propietarios</button>
        <button class="submenu-btn <?= $tablaActiva === 'visitantes' ? 'activo' : '' ?>" data-tabla="visitantes">Visitantes</button>
        <button class="submenu-btn <?= $tablaActiva === 'logs' ? 'activo' : '' ?>" data-tabla="logs">Logs</button>
    </div>

    <!-- PROPIEDADES -->
   <button class="menu-btn <?= in_array($seccionActiva, ['agregarpropiedad', 'propiedadespublicadas']) ? 'activo' : '' ?>" id="btnPropiedadesMenu">
    <i class="fa-solid fa-building"></i> <span class="menu-text">Propiedades</span>
    <i class="fa-solid fa-chevron-down flecha"></i>
</button>

<div class="submenu" id="submenuPropiedades" style="<?= in_array($seccionActiva, ['agregarpropiedad', 'propiedadespublicadas']) ? 'max-height: 240px;' : '' ?>">
    <button class="submenu-btn <?= $seccionActiva === 'agregarpropiedad' ? 'activo' : '' ?>" data-seccion="agregarpropiedad" data-tabla="agregarpropiedad">Agregar Propiedad</button>
    <button class="submenu-btn <?= $seccionActiva === 'propiedadespublicadas' ? 'activo' : '' ?>" data-seccion="propiedadespublicadas" data-tabla="propiedadespublicadas">Propiedades Publicadas</button>
</div>

    <!-- SOLICITUDES -->
    <button class="menu-btn <?= $seccionActiva === 'solicitudes' ? 'activo' : '' ?>" data-seccion="solicitudes" id="btnSolicitudes">
        <i class="fa-solid fa-clock"></i> 
        <span class="menu-text">Solicitudes Pendientes</span>
        <?php if ($solicitudesPendientes > 0): ?>
        <span class="badge-notificacion" id="badgeSolicitudes">
            <?= $solicitudesPendientes ?>
        </span>
        <?php endif; ?>
    </button>

    <!-- ESTADÍSTICAS -->
    <button class="menu-btn <?= $seccionActiva === 'estadisticas' ? 'activo' : '' ?>" data-seccion="estadisticas" id="btnEstadisticas">
        <i class="fa-solid fa-chart-bar"></i> <span class="menu-text">Estadísticas</span>
    </button>

    <!-- CERRAR SESIÓN -->
    <button class="menu-btn logout-btn" id="btnLogout">
        <i class="fa-solid fa-right-from-bracket"></i> <span class="menu-text">Cerrar sesión</span>
    </button>
</aside>
    <!-- CONTENIDO PRINCIPAL -->
    <main class="contenido">
        <!-- Token CSRF oculto -->
        <input type="hidden" id="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" id="es_superadmin" value="<?= $es_superadmin ? '1' : '0' ?>">

        <!-- INICIO - DASHBOARD -->
        <section id="inicio" class="seccion <?= $seccionActiva === 'inicio' ? 'visible' : '' ?>">
            <div class="dashboard-main-header">
                <div class="welcome-section">
                    <div class="welcome-content">
                        <h1><i class="fa-solid fa-hand-wave"></i> ¡Hola, <?= htmlspecialchars($adminNombre, ENT_QUOTES, 'UTF-8') ?>!</h1>
                        <p class="welcome-subtitle">
                            <i class="fa-solid fa-calendar-check"></i> 
                            <?php
                            $hora = date('H');
                            if ($hora < 12) echo '¡Buenos días!';
                            elseif ($hora < 19) echo '¡Buenas tardes!';
                            else echo '¡Buenas noches!';
                            ?>
                            · Tu panel está listo para gestionar.
                        </p>
                    </div>
                    <div class="welcome-time">
                        <div class="time-display">
                            <i class="fa-solid fa-clock"></i>
                            <div class="time-details">
                                <span id="currentDate"><?= date('l, d F Y') ?></span>
                                <span id="currentTime"><?= date('H:i:s') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ACCIONES RÁPIDAS -->
                <div class="quick-actions-bar">
                    <div class="actions-title">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Acciones Rápidas</span>
                    </div>
                    <div class="actions-buttons">
                        <button class="action-btn action-primary" onclick="mostrarSeccion('agregarpropiedad')">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Nueva Propiedad</span>
                        </button>
                        <button class="action-btn action-success" onclick="mostrarSeccion('usuarios')">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>Agregar Usuario</span>
                        </button>
                        <button class="action-btn action-warning" onclick="mostrarSeccion('solicitudes')">
                            <i class="fa-solid fa-clock"></i>
                            <span>Ver Solicitudes</span>
                            <?php if ($solicitudesPendientes > 0): ?>
                            <span class="action-badge"><?= $solicitudesPendientes ?></span>
                            <?php endif; ?>
                        </button>
                        <button class="action-btn action-info" onclick="mostrarSeccion('propiedadespublicadas')">
                            <i class="fa-solid fa-eye"></i>
                            <span>Ver Propiedades</span>
                        </button>
                        <button class="action-btn action-dark" id="quickRefresh">
                            <i class="fa-solid fa-sync-alt"></i>
                            <span>Actualizar Todo</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- WIDGETS PRINCIPALES -->
            <div class="dashboard-widgets-grid">
                <!-- RESUMEN DEL SISTEMA -->
                <div class="widget-main widget-system">
                    <div class="widget-header">
                        <h3><i class="fa-solid fa-chart-pie"></i> Resumen del Sistema</h3>
                    </div>
                    <div class="widget-content">
                        <div class="system-metrics">
                            <div class="metric-item">
                                <div class="metric-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <i class="fa-solid fa-database"></i>
                                </div>
                                <div class="metric-details">
                                    <span class="metric-label">Total Registros</span>
                                    <span class="metric-value" id="totalRegistrosSistema">
                                        <?= number_format($totalUsuarios + $propiedadesVisibles + $solicitudesPendientes, 0, ',', '.') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                                    <i class="fa-solid fa-server"></i>
                                </div>
                                <div class="metric-details">
                                    <span class="metric-label">Tablas Activas</span>
                                    <span class="metric-value"><?= count($tablasUsuarios) + 5 ?></span>
                                </div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                                    <i class="fa-solid fa-microchip"></i>
                                </div>
                                <div class="metric-details">
                                    <span class="metric-label">Rendimiento</span>
                                    <span class="metric-value" id="systemPerformance">98%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIVIDAD RECIENTE -->
                <div class="widget-main widget-activity">
                    <div class="widget-header">
                        <h3><i class="fa-solid fa-history"></i> Actividad Reciente</h3>
                        <button class="widget-settings" onclick="mostrarSeccion('usuarios')">
                            <i class="fa-solid fa-external-link-alt"></i>
                        </button>
                    </div>
                    <div class="widget-content">
                        <div class="activity-list" id="recentActivityList">
                            <?php
                            try {
                                $actividadReciente = $conn->query("
                                    SELECT usuario_nombre, accion, fecha 
                                    FROM logs_actividad 
                                    ORDER BY fecha DESC 
                                    LIMIT 5
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($actividadReciente)):
                                    foreach ($actividadReciente as $actividad):
                                        $horaActividad = date('H:i', strtotime($actividad['fecha']));
                                        $icono = 'fa-circle';
                                        $color = '#6c757d';
                                        
                                        if (strpos($actividad['accion'], 'agregó') !== false || strpos($actividad['accion'], 'Agregó') !== false) {
                                            $icono = 'fa-plus-circle';
                                            $color = '#28a745';
                                        } elseif (strpos($actividad['accion'], 'editó') !== false || strpos($actividad['accion'], 'Editó') !== false) {
                                            $icono = 'fa-edit';
                                            $color = '#17a2b8';
                                        } elseif (strpos($actividad['accion'], 'eliminó') !== false || strpos($actividad['accion'], 'Eliminó') !== false) {
                                            $icono = 'fa-trash';
                                            $color = '#dc3545';
                                        } elseif (strpos($actividad['accion'], 'aprobó') !== false || strpos($actividad['accion'], 'Aprobó') !== false) {
                                            $icono = 'fa-check-circle';
                                            $color = '#28a745';
                                        }
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fa-solid <?= $icono ?>" style="color: <?= $color ?>;"></i>
                                </div>
                                <div class="activity-details">
                                    <span class="activity-user"><?= htmlspecialchars($actividad['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="activity-action"><?= htmlspecialchars($actividad['accion'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="activity-time"><?= $horaActividad ?></span>
                            </div>
                            <?php 
                                    endforeach;
                                else:
                            ?>
                            <div class="no-activity">
                                <i class="fa-solid fa-inbox"></i>
                                <p>No hay actividad reciente</p>
                            </div>
                            <?php
                                endif;
                            } catch (Exception $e) {
                            ?>
                            <div class="no-activity">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <p>Error al cargar actividad</p>
                            </div>
                            <?php } ?>
                        </div>
                        <button class="btn-view-all" onclick="mostrarSeccion('usuarios')">
                            <i class="fa-solid fa-list"></i> Ver todo el historial
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- LOGS -->
        <section id="logs" class="seccion <?= $seccionActiva === 'usuarios' && $tablaActiva === 'logs' ? 'visible' : '' ?>">
            <div class="logs-header">
                <div class="tabla-title">
                    <h2>Logs de actividad (<span id="totalLogsCount"><?= $totalLogs ?></span> total)</h2>
                    <span class="tabla-subtitle">Registros de actividad del sistema</span>
                </div>
                <div class="logs-filters">
                    <input type="text" id="searchLogs" placeholder="Buscar en logs..." class="buscador-tabla">
                    <button class="filter-btn" id="filterToday">
                        <i class="fa-solid fa-calendar-day"></i> Hoy
                    </button>
                    <button class="filter-btn" id="clearFilters">
                        <i class="fa-solid fa-filter-circle-xmark"></i> Limpiar
                    </button>
                </div>
            </div>
            
            <?php if ($totalLogs > 0 && $totalPaginasLogs > 1): ?>
            <div class="paginacion-tabla logs-paginacion">
                <?php if ($paginaLogs > 1): ?>
                    <a href="?pagina_logs=<?= $paginaLogs - 1 ?>&seccion=usuarios&tabla=logs" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <span class="info-pagina">
                    Pág. <?= $paginaLogs ?> de <?= $totalPaginasLogs ?>
                </span>
                
                <?php if ($paginaLogs < $totalPaginasLogs): ?>
                    <a href="?pagina_logs=<?= $paginaLogs + 1 ?>&seccion=usuarios&tabla=logs" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="tabla-contenedor">
                <table id="tablaLogs">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Acción</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="rol-badge rol-<?= htmlspecialchars($l['rol'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($l['rol'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($l['accion'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($l['fecha_simple'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="hora-log"><?= date('H:i:s', strtotime($l['fecha'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="sin-datos-tabla">
                                    <i class="fa-solid fa-inbox"></i>
                                    No hay logs registrados
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- USUARIOS -->
        <section id="usuarios" class="seccion <?= $seccionActiva === 'usuarios' && $tablaActiva !== 'logs' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <h2 id="tituloUsuarios">Usuarios - <?= ucfirst($tablaActiva === 'admins' ? 'Administradores' : ($tablaActiva === 'propietarios' ? 'Propietarios' : ($tablaActiva === 'visitantes' ? 'Visitantes' : 'Logs'))) ?></h2>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-users"></i>
                        <span id="totalUsuariosActivos">-</span> activos
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-clock"></i>
                        Actualizado: <span id="lastUpdate"><?= date('H:i:s') ?></span>
                    </span>
                    <?php if ($tablaActiva !== 'logs'): ?>
                        <?php if ($es_superadmin || $tablaActiva !== 'admins'): ?>
                        <button class="agregar-usuario-btn" id="btnAgregarUsuario">
                            <i class="fa-solid fa-user-plus"></i> Nuevo Usuario
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contenedor de tablas -->
            <div class="tabla-contenedor">
                <!-- Tabla Admins -->
                <div id="contenedorAdmins" class="contenedor-tabla-usuarios" style="<?= $tablaActiva === 'admins' ? '' : 'display:none;' ?>">
                    <div class="tabla-header">
                        <div class="tabla-title">
                            <h3>Administradores (<span id="totalAdmins"><?= $totalAdmins ?></span> total)</h3>
                            <span class="tabla-subtitle">Usuarios con acceso total al sistema</span>
                        </div>
                        <?php if ($totalPaginasAdmins > 1): ?>
                        <div class="paginacion-tabla">
                            <?php if ($paginaAdmins > 1): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('admins', <?= $paginaAdmins - 1 ?>)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <span class="info-pagina">
                                Pág. <span id="paginaActualAdmins"><?= $paginaAdmins ?></span> de <?= $totalPaginasAdmins ?>
                            </span>
                            
                            <?php if ($paginaAdmins < $totalPaginasAdmins): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('admins', <?= $paginaAdmins + 1 ?>)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="text" class="buscador-tabla" placeholder="Buscar administradores..." data-tabla="admins">
                    
                    <div class="tabla-wrapper">
                        <table id="tablaAdmins" class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyAdmins">
                                <?php if (!empty($admins)): ?>
                                    <?php foreach ($admins as $u): ?>
                                    <tr data-id="<?= $u['id'] ?>" data-rol="admin">
                                        <td>
                                            <div class="usuario-info">
                                                <i class="fa-solid fa-user-shield"></i>
                                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="rol-badge rol-admin">Admin</span></td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="toggle-estado" 
                                                       data-id="<?= $u['id'] ?>" 
                                                       data-rol="admin"
                                                       <?= $u['estado'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="estado-texto"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                                            </label>
                                        </td>
                                        <td class="acciones-td">
                                            <div class="acciones-container">
                                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="admin" title="Editar usuario">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <?php if ($es_superadmin): ?>
                                                <button class="eliminarBtn" data-id="<?= $u['id'] ?>" data-rol="admin" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="verDetallesBtn" data-id="<?= $u['id'] ?>" data-rol="admin" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="sin-datos-tabla">
                                            <i class="fa-solid fa-users"></i>
                                            No hay administradores registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabla Propietarios -->
                <div id="contenedorPropietarios" class="contenedor-tabla-usuarios" style="<?= $tablaActiva === 'propietarios' ? '' : 'display:none;' ?>">
                    <div class="tabla-header">
                        <div class="tabla-title">
                            <h3>Propietarios (<span id="totalPropietarios"><?= $totalPropietarios ?></span> total)</h3>
                            <span class="tabla-subtitle">Usuarios que publican propiedades</span>
                        </div>
                        <?php if ($totalPaginasPropietarios > 1): ?>
                        <div class="paginacion-tabla">
                            <?php if ($paginaPropietarios > 1): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('propietarios', <?= $paginaPropietarios - 1 ?>)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <span class="info-pagina">
                                Pág. <span id="paginaActualPropietarios"><?= $paginaPropietarios ?></span> de <?= $totalPaginasPropietarios ?>
                            </span>
                            
                            <?php if ($paginaPropietarios < $totalPaginasPropietarios): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('propietarios', <?= $paginaPropietarios + 1 ?>)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="text" class="buscador-tabla" placeholder="Buscar propietarios..." data-tabla="propietarios">
                    
                    <div class="tabla-wrapper">
                        <table id="tablaPropietarios" class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyPropietarios">
                                <?php if (!empty($propietarios)): ?>
                                    <?php foreach ($propietarios as $u): ?>
                                    <tr data-id="<?= $u['id'] ?>" data-rol="propietario">
                                        <td>
                                            <div class="usuario-info">
                                                <i class="fa-solid fa-house-user"></i>
                                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="rol-badge rol-propietario">Propietario</span></td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="toggle-estado" 
                                                       data-id="<?= $u['id'] ?>" 
                                                       data-rol="propietario"
                                                       <?= $u['estado'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="estado-texto"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                                            </label>
                                        </td>
                                        <td class="acciones-td">
                                            <div class="acciones-container">
                                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="propietario" title="Editar usuario">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button class="eliminarBtn" data-id="<?= $u['id'] ?>" data-rol="propietario" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <button class="verDetallesBtn" data-id="<?= $u['id'] ?>" data-rol="propietario" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="sin-datos-tabla">
                                            <i class="fa-solid fa-users"></i>
                                            No hay propietarios registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabla Visitantes -->
                <div id="contenedorVisitantes" class="contenedor-tabla-usuarios" style="<?= $tablaActiva === 'visitantes' ? '' : 'display:none;' ?>">
                    <div class="tabla-header">
                        <div class="tabla-title">
                            <h3>Visitantes (<span id="totalVisitantes"><?= $totalVisitantes ?></span> total)</h3>
                            <span class="tabla-subtitle">Usuarios que buscan propiedades</span>
                        </div>
                        <?php if ($totalPaginasVisitantes > 1): ?>
                        <div class="paginacion-tabla">
                            <?php if ($paginaVisitantes > 1): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('visitantes', <?= $paginaVisitantes - 1 ?>)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <span class="info-pagina">
                                Pág. <span id="paginaActualVisitantes"><?= $paginaVisitantes ?></span> de <?= $totalPaginasVisitantes ?>
                            </span>
                            
                            <?php if ($paginaVisitantes < $totalPaginasVisitantes): ?>
                                <button class="pagina-btn small" onclick="cambiarPagina('visitantes', <?= $paginaVisitantes + 1 ?>)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php else: ?>
                                <span class="pagina-btn small disabled">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <input type="text" class="buscador-tabla" placeholder="Buscar visitantes..." data-tabla="visitantes">
                    
                    <div class="tabla-wrapper">
                        <table id="tablaVisitantes" class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyVisitantes">
                                <?php if (!empty($visitantes)): ?>
                                    <?php foreach ($visitantes as $u): ?>
                                    <tr data-id="<?= $u['id'] ?>" data-rol="visitante">
                                        <td>
                                            <div class="usuario-info">
                                                <i class="fa-solid fa-user"></i>
                                                <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="rol-badge rol-visitante">Visitante</span></td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="toggle-estado" 
                                                       data-id="<?= $u['id'] ?>" 
                                                       data-rol="visitante"
                                                       <?= $u['estado'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                                <span class="estado-texto"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span>
                                            </label>
                                        </td>
                                        <td class="acciones-td">
                                            <div class="acciones-container">
                                                <button class="editarBtn" data-id="<?= $u['id'] ?>" data-rol="visitante" title="Editar usuario">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button class="eliminarBtn" data-id="<?= $u['id'] ?>" data-rol="visitante" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" title="Eliminar usuario">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <button class="verDetallesBtn" data-id="<?= $u['id'] ?>" data-rol="visitante" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>" data-correo="<?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?>" title="Ver detalles">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="sin-datos-tabla">
                                            <i class="fa-solid fa-users"></i>
                                            No hay visitantes registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- SOLICITUDES PENDIENTES -->
        <section id="solicitudes" class="seccion <?= $seccionActiva === 'solicitudes' ? 'visible' : '' ?>">
            <div class="usuarios-header solicitudes-mejoradas">
                <div class="tabla-title">
                    <h2>Solicitudes Pendientes</h2>
                    <span class="tabla-subtitle">
                        <i class="fa-solid fa-filter"></i> 
                        Propiedades en espera de revisión • 
                        <span id="solicitudesFiltradas"><?= $totalSolicitudes ?></span> mostradas de <?= $totalSolicitudes ?>
                    </span>
                </div>
                
                <!-- CONTROLES DE FILTROS -->
                <div class="filtros-avanzados-solicitudes">
                    <div class="filtro-grupo">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="buscadorSolicitudes" placeholder="Buscar en título, propietario, dirección..." class="buscador-avanzado">
                    </div>
                    
                    <div class="filtro-grupo">
                        <select id="filtroFecha" class="selector-filtro">
                            <option value="todas">Todas las fechas</option>
                            <option value="hoy">Hoy</option>
                            <option value="semana">Esta semana</option>
                            <option value="mes">Este mes</option>
                            <option value="antiguas">Más antiguas (7+ días)</option>
                        </select>
                    </div>
                    
                    <div class="filtro-grupo">
                        <select id="filtroPrioridad" class="selector-filtro">
                            <option value="todas">Todas las prioridades</option>
                            <option value="urgente">Urgentes (7+ días)</option>
                            <option value="normal">Normales (3-6 días)</option>
                            <option value="nuevas">Nuevas (1-2 días)</option>
                        </select>
                    </div>
                    
                    <div class="filtro-grupo">
                        <select id="ordenSolicitudes" class="selector-filtro">
                            <option value="antiguas">Más antiguas primero</option>
                            <option value="nuevas">Más recientes primero</option>
                            <option value="titulo">Por título (A-Z)</option>
                            <option value="propietario">Por propietario</option>
                        </select>
                    </div>
                    
                    <div class="acciones-filtros">
                        <button class="btn-filtro" id="aplicarFiltros" title="Aplicar filtros">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button class="btn-filtro btn-secundario" id="limpiarFiltros" title="Limpiar todos los filtros">
                            <i class="fa-solid fa-broom"></i> Limpiar
                        </button>
                        <button class="btn-refresh" id="refreshSolicitudes" title="Actualizar lista">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- ESTADÍSTICAS RÁPIDAS -->
                <div class="stats-rapidas-solicitudes">
                    <div class="stat-card mini">
                        <i class="fa-solid fa-clock"></i>
                        <div>
                            <span class="stat-numero" id="totalSolicitudes"><?= $totalSolicitudes ?></span>
                            <span class="stat-label">Pendientes</span>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <i class="fa-solid fa-calendar-day"></i>
                        <div>
                            <span class="stat-numero" id="solicitudesHoy">0</span>
                            <span class="stat-label">Hoy</span>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <i class="fa-solid fa-fire"></i>
                        <div>
                            <span class="stat-numero" id="solicitudesUrgentes">0</span>
                            <span class="stat-label">Urgentes</span>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <i class="fa-solid fa-user-clock"></i>
                        <div>
                            <span class="stat-numero" id="promedioDias">0</span>
                            <span class="stat-label">Dias promedio</span>
                        </div>
                    </div>
                </div>
            </div>
                    
            <?php if ($totalPaginasSolicitudes > 1): ?>
            <div class="paginacion-tabla">
                <?php if ($paginaSolicitudes > 1): ?>
                    <a href="?pagina_solicitudes=<?= $paginaSolicitudes - 1 ?>&seccion=solicitudes" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <span class="info-pagina">
                    Pág. <?= $paginaSolicitudes ?> de <?= $totalPaginasSolicitudes ?>
                </span>
                
                <?php if ($paginaSolicitudes < $totalPaginasSolicitudes): ?>
                    <a href="?pagina_solicitudes=<?= $paginaSolicitudes + 1 ?>&seccion=solicitudes" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="tabla-contenedor solicitudes-container">
                <?php if (!empty($solicitudes)): ?>
                    <div class="lista-solicitudes">
                        <?php foreach ($solicitudes as $solicitud): ?>
                        <?php
                        $fechaSolicitud = new DateTime($solicitud['fecha_solicitud']);
                        $hoy = new DateTime();
                        $diasPendiente = $hoy->diff($fechaSolicitud)->days;
                        
                        $claseDias = '';
                        if ($diasPendiente > 7) $claseDias = 'dias-alto';
                        elseif ($diasPendiente > 3) $claseDias = 'dias-medio';
                        
                        $precio = $solicitud['precio_no_publicado'] ? 'No publicado' : '$' . number_format($solicitud['precio'], 0, ',', '.');
                        
                        $imagen = !empty($solicitud['imagen_principal']) ? 
                            '../media/' . $solicitud['imagen_principal'] : 
                            'https://images.unsplash.com/photo-1518780664697-55e3ad937233?w=150';
                        ?>
                        <div class="tarjeta-solicitud" data-id="<?= $solicitud['id'] ?>">
                            <div class="solicitud-imagen" style="background-image: url('<?= $imagen ?>')">
                                <span class="badge-dias <?= $claseDias ?>"><?= $diasPendiente ?> días</span>
                            </div>
                            <div class="solicitud-info">
                                <div class="solicitud-header">
                                    <h3><?= htmlspecialchars($solicitud['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <span class="solicitud-precio"><?= $precio ?></span>
                                </div>
                                
                                <div class="solicitud-datos">
                                    <div class="dato-item">
                                        <i class="fa-solid fa-user"></i>
                                        <span><strong>Propietario:</strong> <?= htmlspecialchars($solicitud['propietario_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-item">
                                        <i class="fa-solid fa-envelope"></i>
                                        <span><?= htmlspecialchars($solicitud['propietario_correo'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-item">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span><?= htmlspecialchars($solicitud['direccion'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-item">
                                        <i class="fa-solid fa-ruler-combined"></i>
                                        <span><?= $solicitud['superficie'] ?> m² • <?= $solicitud['ambientes'] ?> amb • <?= $solicitud['sanitarios'] ?> baños</span>
                                    </div>
                                </div>
                                
                                <div class="solicitud-descripcion">
                                    <p><?= htmlspecialchars(substr($solicitud['descripcion'], 0, 150)) ?>...</p>
                                </div>
                                
                                <div class="solicitud-footer">
                                    <span class="solicitud-fecha">
                                        <i class="fa-solid fa-calendar"></i>
                                        Enviada: <?= date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])) ?>
                                    </span>
                                    <div class="solicitud-acciones">
                                        <button class="btn-ver-solicitud" data-id="<?= $solicitud['id'] ?>">
                                            <i class="fa-solid fa-eye"></i> Ver detalles
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="sin-datos-tabla">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <h3>¡No hay solicitudes pendientes!</h3>
                        <p>Todas las solicitudes han sido revisadas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- PROPIEDADES PUBLICADAS -->
        <section id="propiedadespublicadas" class="seccion <?= $seccionActiva === 'propiedadespublicadas' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <div class="tabla-title">
                    <h2>Propiedades Publicadas</h2>
                    <span class="tabla-subtitle">Gestiona las propiedades visibles en el sitio principal</span>
                </div>
                <div class="usuarios-stats">
                    <span class="stat-item">
                        <i class="fa-solid fa-building"></i>
                        <span id="totalPropiedades"><?= $totalPropiedades ?></span> propiedades
                    </span>
                    <span class="stat-item">
                        <i class="fa-solid fa-eye"></i>
                        Visibles: <span id="propiedadesVisibles"><?= $propiedadesVisibles ?></span>
                    </span>
                    <div class="filtros-propiedades">
                        <select class="selector-filtro" id="filtroPropiedades">
                            <option value="todas">Todas</option>
                            <option value="aprobada">Visibles</option>
                            <option value="inactiva">Ocultas</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($totalPaginasPropiedades > 1): ?>
            <div class="paginacion-tabla">
                <?php if ($paginaPropiedades > 1): ?>
                    <a href="?pagina_propiedades=<?= $paginaPropiedades - 1 ?>&seccion=propiedadespublicadas" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <span class="info-pagina">
                    Pág. <?= $paginaPropiedades ?> de <?= $totalPaginasPropiedades ?>
                </span>
                
                <?php if ($paginaPropiedades < $totalPaginasPropiedades): ?>
                    <a href="?pagina_propiedades=<?= $paginaPropiedades + 1 ?>&seccion=propiedadespublicadas" class="pagina-btn small">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="pagina-btn small disabled">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="tabla-contenedor propiedades-container">
                <?php if (!empty($propiedadesPublicadas)): ?>
                    <div class="grid-propiedades" id="gridPropiedades">
                        <?php foreach ($propiedadesPublicadas as $propiedad): ?>
                        <?php
                        $estado = $propiedad['estado_publicacion'];
                        $esVisible = $estado === 'aprobada';
                        $claseEstado = $esVisible ? 'estado-visible' : 'estado-oculta';
                        $textoEstado = $esVisible ? 'Visible' : 'Oculta';
                        $iconoEstado = $esVisible ? 'fa-eye' : 'fa-eye-slash';
                        
                        $precio = $propiedad['precio_no_publicado'] ? 'No publicado' : '$' . number_format($propiedad['precio'], 0, ',', '.');
                        
                        $imagen = !empty($propiedad['imagen_principal']) ? 
                            '../media/' . $propiedad['imagen_principal'] : 
                            'https://images.unsplash.com/photo-1518780664697-55e3ad937233?w=300&h=200&fit=crop';
                        
                        $fecha = $propiedad['fecha_revision'] ? date('d/m/Y', strtotime($propiedad['fecha_revision'])) : 
                                date('d/m/Y', strtotime($propiedad['fecha_solicitud']));
                        ?>
                        <div class="tarjeta-propiedad" data-id="<?= $propiedad['id'] ?>" data-estado="<?= $estado ?>">
                            <div class="propiedad-imagen" style="background-image: url('<?= $imagen ?>')">
                                <span class="badge-estado-propiedad <?= $claseEstado ?>">
                                    <i class="fa-solid <?= $iconoEstado ?>"></i> <?= $textoEstado ?>
                                </span>
                            </div>
                            <div class="propiedad-info">
                                <div class="propiedad-header">
                                    <h3><?= htmlspecialchars($propiedad['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <span class="propiedad-precio"><?= $precio ?></span>
                                </div>
                                
                                <div class="propiedad-datos">
                                    <div class="dato-propiedad">
                                        <i class="fa-solid fa-user"></i>
                                        <span><?= htmlspecialchars($propiedad['propietario_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-propiedad">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span><?= htmlspecialchars($propiedad['direccion'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dato-propiedad">
                                        <i class="fa-solid fa-expand-arrows-alt"></i>
                                        <span><?= $propiedad['superficie'] ?> m² • <?= $propiedad['ambientes'] ?> amb • <?= $propiedad['sanitarios'] ?> baños</span>
                                    </div>
                                </div>
                                
                                <div class="propiedad-descripcion">
                                    <p><?= htmlspecialchars(substr($propiedad['descripcion'], 0, 100)) ?>...</p>
                                </div>
                                
                                <div class="propiedad-footer">
                                    <span class="propiedad-fecha">
                                        <i class="fa-solid fa-calendar"></i>
                                        <?= $esVisible ? 'Publicada' : 'Oculta desde' ?>: <?= $fecha ?>
                                    </span>
                                    <div class="propiedad-acciones">
                                        <?php if ($esVisible): ?>
                                        <button class="btn-ocultar-propiedad" data-id="<?= $propiedad['id'] ?>" data-titulo="<?= htmlspecialchars($propiedad['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-eye-slash"></i> Ocultar
                                        </button>
                                        <?php else: ?>
                                        <button class="btn-mostrar-propiedad" data-id="<?= $propiedad['id'] ?>" data-titulo="<?= htmlspecialchars($propiedad['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-eye"></i> Mostrar
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn-ver-propiedad" data-id="<?= $propiedad['id'] ?>">
                                            <i class="fa-solid fa-info-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="sin-datos-tabla">
                        <i class="fa-solid fa-building"></i>
                        <h3>No hay propiedades publicadas</h3>
                        <p>Las propiedades aparecerán aquí una vez que sean aprobadas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- AGREGAR PROPIEDAD -->
        <section id="agregarpropiedad" class="seccion <?= $seccionActiva === 'agregarpropiedad' ? 'visible' : '' ?>">
            <div class="usuarios-header">
                <div class="tabla-title">
                    <h2>Subir Propiedad</h2>
                    <span class="tabla-subtitle">Agrega propiedades directamente al sitio (sin necesidad de aprobación)</span>
                </div>
                <div class="usuarios-stats">
                    <button class="btn-agregar-servicio" id="btnAgregarServicio">
                        <i class="fa-solid fa-plus"></i> Agregar Servicio
                    </button>
                </div>
            </div>
            
            <div class="formulario-admin-container">
                <form class="formulario-propiedad-admin" id="formulario-propiedad-admin" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="accion" value="subir_propiedad_admin">
                    
                    <div class="grid-formulario">
                        <div class="grupo-formulario">
                            <label for="titulo" class="etiqueta-formulario">Título de la propiedad *</label>
                            <input type="text" id="titulo" name="titulo" class="entrada-formulario" placeholder="Ej: Casa amplia de 3 ambientes" required>
                        </div>

                        <div class="grupo-formulario">
                            <label for="descripcion" class="etiqueta-formulario">Descripción detallada *</label>
                            <textarea id="descripcion" name="descripcion" class="area-texto-formulario" rows="4" placeholder="Describe la propiedad..." required></textarea>
                        </div>

                        <div class="grupo-formulario">
                            <label for="precio" class="etiqueta-formulario">Precio mensual *</label>
                            <div class="contenedor-precio">
                                <div class="entrada-con-icono">
                                    <i class="fa-solid fa-dollar-sign"></i>
                                    <input type="number" id="precio" name="precio" class="entrada-formulario" placeholder="120000" min="0" step="1" required>
                                </div>
                                <label class="etiqueta-checkbox">
                                    <input type="checkbox" id="no-decirlo" name="no_decirlo">
                                    <span>No publicar precio</span>
                                </label>
                            </div>
                        </div>

                        <div class="grupo-formulario ancho-completo">
                            <h3 class="titulo-seccion-formulario">Características principales</h3>
                            <div class="grid-caracteristicas">
                                <div class="entrada-caracteristica">
                                    <label for="ambientes" class="etiqueta-formulario">Ambientes *</label>
                                    <div class="entrada-con-icono">
                                        <i class="fa-solid fa-door-open"></i>
                                        <input type="number" id="ambientes" name="ambientes" class="entrada-formulario" placeholder="3" min="1" required>
                                    </div>
                                </div>
                                <div class="entrada-caracteristica">
                                    <label for="banios" class="etiqueta-formulario">Baños *</label>
                                    <div class="entrada-con-icono">
                                        <i class="fa-solid fa-bath"></i>
                                        <input type="number" id="banios" name="banios" class="entrada-formulario" placeholder="2" min="1" required>
                                    </div>
                                </div>
                                <div class="entrada-caracteristica">
                                    <label for="superficie" class="etiqueta-formulario">Superficie (m²) *</label>
                                    <div class="entrada-con-icono">
                                        <i class="fa-solid fa-ruler-combined"></i>
                                        <input type="number" id="superficie" name="superficie" class="entrada-formulario" placeholder="80" min="10" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grupo-formulario ancho-completo">
                            <h3 class="titulo-seccion-formulario">
                                Servicios incluidos
                                <button type="button" class="btn-pequeno" id="btnRefreshServicios" title="Actualizar lista">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </h3>
                            <div class="grid-servicios" id="gridServicios">
                                <?php if (!empty($servicios_disponibles)): ?>
                                    <?php foreach ($servicios_disponibles as $servicio): ?>
                                    <label class="checkbox-servicio">
                                        <input type="checkbox" name="servicios[]" value="<?= htmlspecialchars($servicio['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="item-servicio">
                                            <i class="<?= htmlspecialchars($servicio['icono'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <span><?= htmlspecialchars($servicio['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="sin-servicios">No hay servicios disponibles. Agrega algunos primero.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grupo-formulario">
                            <label for="direccion" class="etiqueta-formulario">Dirección *</label>
                            <input type="text" id="direccion" name="direccion" class="entrada-formulario" placeholder="Calle, número, ciudad" required>
                        </div>

                        <div class="grupo-formulario">
                            <label for="ciudad" class="etiqueta-formulario">Ciudad</label>
                            <input type="text" id="ciudad" name="ciudad" class="entrada-formulario" placeholder="Ciudad">
                        </div>

                        <div class="grupo-formulario">
                            <label for="provincia" class="etiqueta-formulario">Provincia</label>
                            <input type="text" id="provincia" name="provincia" class="entrada-formulario" placeholder="Provincia">
                        </div>

                        <div class="grupo-formulario ancho-completo">
                            <h3 class="titulo-seccion-formulario">Imágenes de la propiedad</h3>
                            <div class="area-subida-archivos" id="areaSubidaArchivosAdmin">
                                <i class="fa-solid fa-cloud-upload-alt icono-subida"></i>
                                <p class="texto-subida">Arrastra y suelta imágenes aquí o haz clic para seleccionar</p>
                                <input type="file" id="imagenes" name="imagenes[]" multiple accept="image/*">
                                <div class="lista-archivos" id="listaArchivosAdmin"></div>
                            </div>
                        </div>

                        <div class="grupo-formulario ancho-completo acciones-formulario">
                            <button type="button" class="boton-secundario" onclick="limpiarFormularioPropiedad()">
                                <i class="fa-solid fa-broom"></i> Limpiar
                            </button>
                            <button type="submit" class="boton-principal" id="btnSubirPropiedadAdmin">
                                <i class="fa-solid fa-cloud-upload"></i> Publicar Propiedad
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- ESTADÍSTICAS -->
        <section id="estadisticas" class="seccion <?= $seccionActiva === 'estadisticas' ? 'visible' : '' ?>">
            <div class="dashboard-estadisticas-header">
                <div class="dashboard-title">
                    <h2><i class="fa-solid fa-chart-line"></i> Dashboard de Estadísticas</h2>
                    <p class="dashboard-subtitle">Métricas y análisis del sistema en tiempo real</p>
                </div>
                
                <div class="dashboard-controls">
                    <div class="control-group">
                        <label for="periodoEstadisticas"><i class="fa-solid fa-calendar-alt"></i> Período:</label>
                        <select id="periodoEstadisticas" class="selector-periodo">
                            <option value="hoy">Hoy</option>
                            <option value="semana" selected>Esta semana</option>
                            <option value="mes">Este mes</option>
                            <option value="trimestre">Este trimestre</option>
                            <option value="anio">Este año</option>
                            <option value="personalizado">Personalizado</option>
                        </select>
                    </div>
                    
                    <div class="control-group rango-fechas" id="rangoFechasContainer" style="display: none;">
                        <label for="fechaDesde"><i class="fa-solid fa-calendar"></i> Desde:</label>
                        <input type="date" id="fechaDesde" class="input-fecha">
                        <label for="fechaHasta">Hasta:</label>
                        <input type="date" id="fechaHasta" class="input-fecha">
                    </div>
                    
                    <div class="control-group">
                        <label for="tipoGrafico"><i class="fa-solid fa-chart-bar"></i> Vista:</label>
                        <select id="tipoGrafico" class="selector-vista">
                            <option value="general">Vista General</option>
                            <option value="usuarios">Enfoque Usuarios</option>
                            <option value="propiedades">Enfoque Propiedades</option>
                            <option value="actividad">Enfoque Actividad</option>
                        </select>
                    </div>
                    
                    <div class="dashboard-actions">
                        <button class="btn-dashboard" id="actualizarEstadisticas">
                            <i class="fa-solid fa-refresh"></i> Actualizar
                        </button>
                        <button class="btn-dashboard btn-secundario" id="exportarReporte">
                            <i class="fa-solid fa-download"></i> Exportar
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- WIDGETS DE MÉTRICAS -->
            <div class="widgets-container">
                <div class="widget-card widget-primary">
                    <div class="widget-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Usuarios Totales</h3>
                        <div class="widget-number" id="widgetUsuariosTotal"><?= $totalUsuarios ?></div>
                        <div class="widget-trend">
                            <i class="fa-solid fa-arrow-up trend-positive"></i>
                            <span class="trend-value" id="trendUsuarios">+12%</span>
                            <span class="trend-label">vs mes anterior</span>
                        </div>
                    </div>
                </div>
                
                <div class="widget-card widget-success">
                    <div class="widget-icon">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Propiedades Activas</h3>
                        <div class="widget-number" id="widgetPropiedadesActivas"><?= $propiedadesVisibles ?? 0 ?></div>
                        <div class="widget-trend">
                            <i class="fa-solid fa-arrow-up trend-positive"></i>
                            <span class="trend-value" id="trendPropiedades">+8%</span>
                            <span class="trend-label">vs mes anterior</span>
                        </div>
                    </div>
                </div>
                
                <div class="widget-card widget-warning">
                    <div class="widget-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Solicitudes Pendientes</h3>
                        <div class="widget-number" id="widgetSolicitudesPendientes"><?= $solicitudesPendientes ?? 0 ?></div>
                        <div class="widget-trend">
                            <i class="fa-solid fa-arrow-down trend-negative"></i>
                            <span class="trend-value" id="trendSolicitudes">-5%</span>
                            <span class="trend-label">vs semana anterior</span>
                        </div>
                    </div>
                </div>
                
                <div class="widget-card widget-info">
                    <div class="widget-icon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div class="widget-content">
                        <h3>Tasa de Aprobación</h3>
                        <div class="widget-number" id="widgetTasaAprobacion">87%</div>
                        <div class="widget-trend">
                            <i class="fa-solid fa-arrow-up trend-positive"></i>
                            <span class="trend-value" id="trendAprobacion">+3%</span>
                            <span class="trend-label">vs mes anterior</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- GRÁFICOS -->
            <div class="graficos-container">
                <div class="grafico-card grafico-grande">
                    <div class="grafico-header">
                        <h3><i class="fa-solid fa-calendar-day"></i> Actividad Diaria</h3>
                        <div class="grafico-legend">
                            <span class="legend-item"><i class="fa-solid fa-circle" style="color: #4a6cf7;"></i> Nuevos Usuarios</span>
                            <span class="legend-item"><i class="fa-solid fa-circle" style="color: #10b981;"></i> Propiedades Publicadas</span>
                            <span class="legend-item"><i class="fa-solid fa-circle" style="color: #f59e0b;"></i> Solicitudes</span>
                        </div>
                    </div>
                    <div class="grafico-body">
                        <canvas id="graficoActividadDiaria" height="250"></canvas>
                    </div>
                </div>
                
                <div class="grafico-card">
                    <div class="grafico-header">
                        <h3><i class="fa-solid fa-user-pie"></i> Distribución de Usuarios</h3>
                    </div>
                    <div class="grafico-body">
                        <canvas id="graficoDistribucionUsuarios" height="220"></canvas>
                    </div>
                    <div class="grafico-footer">
                        <div class="distribucion-detalle">
                            <div class="detalle-item">
                                <span class="detalle-color" style="background: #4a6cf7;"></span>
                                <span>Admins: <strong id="countAdmins"><?= $totalAdmins ?></strong></span>
                            </div>
                            <div class="detalle-item">
                                <span class="detalle-color" style="background: #10b981;"></span>
                                <span>Propietarios: <strong id="countPropietarios"><?= $totalPropietarios ?></strong></span>
                            </div>
                            <div class="detalle-item">
                                <span class="detalle-color" style="background: #f59e0b;"></span>
                                <span>Visitantes: <strong id="countVisitantes"><?= $totalVisitantes ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grafico-card">
                    <div class="grafico-header">
                        <h3><i class="fa-solid fa-tasks"></i> Estado de Solicitudes</h3>
                    </div>
                    <div class="grafico-body">
                        <canvas id="graficoEstadoSolicitudes" height="220"></canvas>
                    </div>
                    <div class="grafico-footer">
                        <div class="solicitudes-resumen">
                            <div class="resumen-item aprobadas">
                                <i class="fa-solid fa-check-circle"></i>
                                <span><strong id="countAprobadas">0</strong> aprobadas</span>
                            </div>
                            <div class="resumen-item pendientes">
                                <i class="fa-solid fa-clock"></i>
                                <span><strong id="countPendientes"><?= $solicitudesPendientes ?? 0 ?></strong> pendientes</span>
                            </div>
                            <div class="resumen-item rechazadas">
                                <i class="fa-solid fa-times-circle"></i>
                                <span><strong id="countRechazadas">0</strong> rechazadas</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ACTIVIDAD RECIENTE -->
            <div class="tabla-actividad-container">
                <div class="tabla-header">
                    <h3><i class="fa-solid fa-history"></i> Actividad Reciente</h3>
                    <button class="btn-ver-todo" id="verTodaActividad">
                        <i class="fa-solid fa-list"></i> Ver todo
                    </button>
                </div>
                <div class="tabla-contenedor tabla-estrecha">
                    <table id="tablaActividadReciente">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Detalles</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyActividadReciente">
                            <tr>
                                <td colspan="4" class="sin-datos-tabla">
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                    Cargando actividad reciente...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- MODALES -->
<div class="modal-overlay" id="modalOverlay"></div>

<!-- MODAL AGREGAR USUARIO -->
<div class="modal" id="modalAgregarUsuario" style="display:none;">
    <div class="modal-contenido modal-mejorado">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus"></i> Agregar Nuevo Usuario</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formAgregarUsuario">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="accion" value="agregar_usuario">

                <div class="form-group">
                    <label for="agregarNombre"><i class="fa-solid fa-user"></i> Nombre Completo</label>
                    <input type="text" name="nombre" id="agregarNombre" required maxlength="100" placeholder="Ej: Juan Pérez">
                    <div class="form-help">Mínimo 2 caracteres, máximo 100</div>
                    <div class="form-error" id="errorAgregarNombre"></div>
                </div>

                <div class="form-group">
                    <label for="agregarCorreo"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" name="correo" id="agregarCorreo" required maxlength="255" placeholder="usuario@ejemplo.com">
                    <div class="form-help">Formato válido de correo electrónico</div>
                    <div class="form-error" id="errorAgregarCorreo"></div>
                </div>

                <div class="form-group">
                    <label for="agregarRol"><i class="fa-solid fa-shield"></i> Tipo de Usuario</label>
                    <select name="rol" id="agregarRol" required class="form-select">
                        <option value="" selected disabled>Seleccionar rol...</option>
                        <?php if ($es_superadmin): ?>
                        <option value="admin">Administrador</option>
                        <?php endif; ?>
                        <option value="propietario">Propietario</option>
                        <option value="visitante">Visitante</option>
                    </select>
                    <div class="form-help">Selecciona el tipo de usuario</div>
                    <div class="form-error" id="errorAgregarRol"></div>
                </div>

                <div class="form-group">
                    <label for="agregarPassword"><i class="fa-solid fa-lock"></i> Contraseña</label>
                    <input type="password" name="password" id="agregarPassword" required minlength="8" placeholder="Mínimo 8 caracteres">
                    <div class="form-help">Mínimo 8 caracteres</div>
                    <div class="form-error" id="errorAgregarPassword"></div>
                </div>

                <div class="form-group">
                    <label for="agregarConfirmPassword"><i class="fa-solid fa-lock"></i> Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" id="agregarConfirmPassword" required placeholder="Repite la contraseña">
                    <div class="form-help">Ambas contraseñas deben coincidir</div>
                    <div class="form-error" id="errorAgregarConfirmPassword"></div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" id="cancelarAgregarUsuario">Cancelar</button>
                    <button type="submit" class="btn-guardar" id="submitAgregarUsuario">
                        <i class="fa-solid fa-user-plus"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div class="modal" id="modalEditar" style="display:none;">
    <div class="modal-contenido modal-mejorado">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-edit"></i> Editar Usuario</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formEditar" method="POST" action="indexadmin.php">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="rol" id="editRol">

                <div class="form-group">
                    <label for="editNombre"><i class="fa-solid fa-user"></i> Nombre</label>
                    <input type="text" name="nombre" id="editNombre" required maxlength="100">
                    <div class="form-help">Mínimo 2 caracteres, máximo 100</div>
                    <div class="form-error" id="errorNombre"></div>
                </div>

                <div class="form-group">
                    <label for="editCorreo"><i class="fa-solid fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" name="correo" id="editCorreo" required maxlength="255">
                    <div class="form-help">Formato: usuario@ejemplo.com</div>
                    <div class="form-error" id="errorCorreo"></div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-shield"></i> Tipo de Usuario</label>
                    <div class="rol-display" id="displayRol">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Administrador</span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" id="cancelarEditar">Cancelar</button>
                    <button type="submit" class="btn-guardar" id="submitEditar">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL VER DETALLES -->
<div class="modal" id="modalVerDetalles" style="display:none;">
    <div class="modal-contenido modal-detalles">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-circle"></i> Detalles del Usuario</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="detalles-content" id="detallesContent">
                <div class="detalles-header">
                    <div class="avatar-detalles">
                        <i class="fa-solid fa-user" id="detallesIcono"></i>
                    </div>
                    <div class="detalles-titulo">
                        <h4 id="detallesNombre">Nombre Usuario</h4>
                        <span class="rol-badge" id="detallesRol">Admin</span>
                    </div>
                </div>
                
                <div class="detalles-info" id="detallesInfo">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancelar" id="cerrarDetalles">Cerrar</button>
            <button type="button" class="btn-editar-detalles" id="editarDesdeDetalles">
                <i class="fa-solid fa-pen"></i> Editar Usuario
            </button>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR ELIMINAR -->
<div class="modal" id="modalConfirmarEliminar" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Confirmar Eliminación</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-trash"></i>
            </div>
            <p id="textoConfirmacion">¿Estás seguro de que quieres eliminar este usuario?</p>
            <p class="confirmacion-detalle">Esta acción no se puede deshacer.</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancelar" id="cancelarEliminar">Cancelar</button>
                <button type="button" class="btn-eliminar-confirmar" id="confirmarEliminar">
                    <i class="fa-solid fa-trash"></i> Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR LOGOUT -->
<div class="modal" id="modalConfirmarLogout" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-door-open"></i> Cerrar Sesión</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
            <p>¿Estás seguro de que quieres cerrar sesión?</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancelar" id="cancelarLogout">Cancelar</button>
                <button type="button" class="btn-logout-confirmar" id="confirmarLogout">
                    <i class="fa-solid fa-right-from-bracket"></i> Sí, Cerrar Sesión
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL AGREGAR SERVICIO -->
<div class="modal" id="modalAgregarServicio" style="display:none;">
    <div class="modal-contenido modal-mejorado">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus-circle"></i> Agregar Nuevo Servicio</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formAgregarServicio">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="accion" value="agregar_servicio">

                <div class="form-group">
                    <label for="nombreServicio"><i class="fa-solid fa-tag"></i> Nombre del Servicio</label>
                    <input type="text" name="nombre_servicio" id="nombreServicio" required maxlength="50" 
                           placeholder="Ej: Gimnasio, Sauna, Lavandería...">
                    <div class="form-help">Nombre descriptivo del servicio (máx. 50 caracteres)</div>
                    <div class="form-error" id="errorNombreServicio"></div>
                </div>

                <div class="form-group">
                    <label for="iconoServicio"><i class="fa-solid fa-icons"></i> Icono (FontAwesome)</label>
                    <div class="entrada-con-icono">
                        <i class="fa-solid fa-star" id="previewIcono"></i>
                        <input type="text" name="icono_servicio" id="iconoServicio" 
                               placeholder="fa-solid fa-star" value="fa-solid fa-star">
                    </div>
                    <div class="form-help">
                        Usa clases de FontAwesome. Ej: fa-solid fa-wifi, fa-solid fa-car, etc.
                    </div>
                    <div class="form-error" id="errorIconoServicio"></div>
                </div>

                <div class="iconos-populares">
                    <h4>Íconos populares:</h4>
                    <div class="grid-iconos">
                        <button type="button" class="icono-option" data-icono="fa-solid fa-wifi"><i class="fa-solid fa-wifi"></i> WiFi</button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-car"><i class="fa-solid fa-car"></i> Cochera</button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-swimming-pool"><i class="fa-solid fa-swimming-pool"></i> Pileta</button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-dumbbell"><i class="fa-solid fa-dumbbell"></i> Gimnasio</button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-hot-tub"><i class="fa-solid fa-hot-tub"></i> Jacuzzi</button>
                        <button type="button" class="icono-option" data-icono="fa-solid fa-utensils"><i class="fa-solid fa-utensils"></i> Cocina</button>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" id="cancelarAgregarServicio">Cancelar</button>
                    <button type="submit" class="btn-guardar" id="submitAgregarServicio">
                        <i class="fa-solid fa-plus"></i> Agregar Servicio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETALLES SOLICITUD -->
<div class="modal" id="modalDetallesSolicitud" style="display:none;">
    <div class="modal-contenido modal-detalles-solicitud">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-circle-check"></i> Revisar Solicitud</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div id="detallesSolicitudContent"></div>
        </div>
        <div class="modal-footer solicitud-acciones">
            <button type="button" class="btn-cancelar" id="cancelarRechazo">Cancelar</button>
            <button type="button" class="btn-rechazar" id="btnRechazarSolicitud">
                <i class="fa-solid fa-times-circle"></i> Rechazar
            </button>
            <button type="button" class="btn-aprobar" id="btnAprobarSolicitud">
                <i class="fa-solid fa-check-circle"></i> Aprobar
            </button>
        </div>
    </div>
</div>

<!-- MODAL RECHAZAR SOLICITUD -->
<div class="modal" id="modalRechazarSolicitud" style="display:none;">
    <div class="modal-contenido modal-rechazar">
        <div class="modal-header">
            <h3><i class="fa-solid fa-times-circle"></i> Rechazar Solicitud</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <p id="textoRechazar">¿Estás seguro de que quieres rechazar esta solicitud? Ingresa el motivo:</p>
            <div class="form-group">
                <textarea id="motivoRechazo" rows="4" placeholder="Motivo del rechazo (obligatorio)" maxlength="500"></textarea>
                <small class="form-help">Máximo 500 caracteres. Este motivo se mostrará al propietario.</small>
                <div class="form-error" id="errorMotivoRechazo"></div>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cancelarRechazar">Cancelar</button>
            <button type="button" class="btn-confirmar-rechazar" id="confirmarRechazar">
                <i class="fa-solid fa-times-circle"></i> Sí, Rechazar
            </button>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR APROBACIÓN -->
<div class="modal" id="modalConfirmarAprobacion" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-check-circle"></i> Confirmar Aprobación</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <p id="textoConfirmarAprobacion">¿Estás seguro de que quieres aprobar esta propiedad?</p>
            <p class="confirmacion-detalle">
                <i class="fa-solid fa-info-circle"></i>
                La propiedad se mostrará públicamente y se notificará al propietario.
            </p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cancelarAprobacion">Cancelar</button>
            <button type="button" class="btn-aprobar-confirmar" id="confirmarAprobacion">
                <i class="fa-solid fa-check-circle"></i> Sí, Aprobar
            </button>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR SUBIR PROPIEDAD -->
<div class="modal" id="modalConfirmarSubirPropiedad" style="display:none;">
    <div class="modal-contenido modal-confirmacion">
        <div class="modal-header">
            <h3><i class="fa-solid fa-cloud-upload"></i> Confirmar Publicación</h3>
            <span class="cerrar">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmacion-icono">
                <i class="fa-solid fa-building"></i>
            </div>
            <p>¿Estás seguro de que quieres publicar esta propiedad?</p>
            <p class="confirmacion-detalle">
                <i class="fa-solid fa-info-circle"></i>
                La propiedad será visible inmediatamente en el sitio principal.
            </p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancelar" id="cancelarSubirPropiedad">Cancelar</button>
            <button type="button" class="btn-confirmar-subir" id="confirmarSubirPropiedad">
                <i class="fa-solid fa-cloud-upload"></i> Sí, Publicar
            </button>
        </div>
    </div>
</div>

<!-- Pasar variables de PHP a JavaScript -->
<script>
const GLOBAL_TOTAL_PAGINAS_LOGS = <?= $totalPaginasLogs ?? 1 ?>;
const GLOBAL_CSRF_TOKEN = "<?= $csrf_token ?>";
const GLOBAL_ES_SUPERADMIN = "<?= $es_superadmin ? '1' : '0' ?>";
</script>

<!-- Cargar el archivo JavaScript principal -->
<script src="../script/admin.js"></script>

<!-- Script inline para mensajes de confirmación -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($mensajeConfirmacion)): ?>
    mostrarMensajeConfirmacion('<?= addslashes($mensajeConfirmacion) ?>', '<?= $tipoMensaje ?>');
    <?php endif; ?>
});
</script>

</body>
</html>