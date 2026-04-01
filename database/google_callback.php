<?php
// database/google_callback.php

session_start();
include("conexion.php");

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Configuración de Google OAuth
$clientID = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
    $redirectUri = 'http://localhost/RentNono/database/google_callback.php';
    
    // 1. Intercambiar código por token de acceso
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $tokenResponse = curl_exec($ch);
    curl_close($ch);
    
    $tokenData = json_decode($tokenResponse, true);
    
    if (isset($tokenData['access_token'])) {
        // 2. Obtener información del usuario
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $ch = curl_init($userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token']
        ]);
        
        $userInfoResponse = curl_exec($ch);
        curl_close($ch);
        
        $userInfo = json_decode($userInfoResponse, true);
        
        if (isset($userInfo['id'])) {
            $google_id = $userInfo['id'];
            $email = $userInfo['email'];
            $name = $userInfo['name'];
            
            // 3. Verificar si el usuario ya existe
            // Buscar en propietarios primero
            $sql_propietario = "SELECT * FROM usuario_propietario WHERE correo = :email";
            $stmt_prop = $conn->prepare($sql_propietario);
            $stmt_prop->execute([':email' => $email]);
            
            if ($stmt_prop->rowCount() > 0) {
                $usuario = $stmt_prop->fetch(PDO::FETCH_ASSOC);
                
                // Actualizar google_id si no existe
                if (empty($usuario['google_id'])) {
                    $update_sql = "UPDATE usuario_propietario SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->execute([
                        ':google_id' => $google_id,
                        ':id' => $usuario['id']
                    ]);
                }
                
                // Iniciar sesión
                $_SESSION['id'] = $usuario['id'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['correo'] = $usuario['correo'];
                $_SESSION['rol'] = 'propietario';
                $_SESSION['tipo_usuario'] = 'propietario';
                $_SESSION['google_login'] = true;
                
                header("Location: ../usuario_propietario/index_propietario.php");
                exit;
            }
            
            // Buscar en visitantes
            $sql_visitante = "SELECT * FROM usuario_visitante WHERE correo = :email";
            $stmt_vis = $conn->prepare($sql_visitante);
            $stmt_vis->execute([':email' => $email]);
            
            if ($stmt_vis->rowCount() > 0) {
                $usuario = $stmt_vis->fetch(PDO::FETCH_ASSOC);
                
                // Actualizar google_id si no existe
                if (empty($usuario['google_id'])) {
                    $update_sql = "UPDATE usuario_visitante SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->execute([
                        ':google_id' => $google_id,
                        ':id' => $usuario['id']
                    ]);
                }
                
                // Iniciar sesión
                $_SESSION['id'] = $usuario['id'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['correo'] = $usuario['correo'];
                $_SESSION['rol'] = 'visitante';
                $_SESSION['tipo_usuario'] = 'visitante';
                $_SESSION['google_login'] = true;
                
                header("Location: ../usuario_visitante/ixusuario.php");
                exit;
            }
            
            // 4. Si no existe, guardar datos para registro
            $_SESSION['google_user_data'] = [
                'google_id' => $google_id,
                'email' => $email,
                'name' => $name,
                'picture' => $userInfo['picture'] ?? null
            ];
            
            header("Location: ../google_register.php");
            exit;
        }
    }
}

// Si hay error o no hay código
header("Location: ../index.php?error=google_auth_failed");
exit;
?>