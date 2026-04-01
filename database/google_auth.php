<?php
// database/google_auth.php

require_once __DIR__ . '/vendor/autoload.php';

//Esas variables no hay que subir
// Configuración de Google OAuth
$clientID = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = 'http://localhost/RentNono/database/google_callback.php';

// Crear cliente de Google
$client = new Google\Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

// Función para obtener URL de autorización
function getGoogleAuthUrl() {
    global $client;
    return $client->createAuthUrl();
}

// Función para manejar el callback
function handleGoogleCallback($code) {
    global $client;
    
    try {
        // Intercambiar código por token
        $token = $client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new Exception('Error obteniendo token: ' . $token['error']);
        }
        
        $client->setAccessToken($token['access_token']);
        
        // Obtener información del usuario
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        return [
            'id' => $google_account_info->id,
            'email' => $google_account_info->email,
            'name' => $google_account_info->name,
            'picture' => $google_account_info->picture,
            'verified_email' => $google_account_info->verifiedEmail
        ];
        
    } catch (Exception $e) {
        error_log("Error Google Auth: " . $e->getMessage());
        return null;
    }
}

// Función para registrar/login usuario con Google
function loginOrRegisterWithGoogle($userInfo, $conn) {
    if (!$userInfo) {
        return false;
    }
    
    $google_id = $userInfo['id'];
    $email = $userInfo['email'];
    $name = $userInfo['name'];
    
    // Primero verificar si ya existe con este email en propietarios
    $sql_propietario = "SELECT * FROM usuario_propietario WHERE correo = :email OR google_id = :google_id";
    $stmt_prop = $conn->prepare($sql_propietario);
    $stmt_prop->execute([
        ':email' => $email,
        ':google_id' => $google_id
    ]);
    
    if ($stmt_prop->rowCount() > 0) {
        $usuario = $stmt_prop->fetch(PDO::FETCH_ASSOC);
        
        // Si no tiene google_id, actualizarlo
        if (empty($usuario['google_id'])) {
            $update_sql = "UPDATE usuario_propietario SET google_id = :google_id WHERE id = :id";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([
                ':google_id' => $google_id,
                ':id' => $usuario['id']
            ]);
        }
        
        // Iniciar sesión como propietario
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['correo'] = $usuario['correo'];
        $_SESSION['rol'] = 'propietario';
        $_SESSION['tipo_usuario'] = 'propietario';
        $_SESSION['google_login'] = true;
        
        return 'propietario';
    }
    
    // Verificar en visitantes
    $sql_visitante = "SELECT * FROM usuario_visitante WHERE correo = :email OR google_id = :google_id";
    $stmt_vis = $conn->prepare($sql_visitante);
    $stmt_vis->execute([
        ':email' => $email,
        ':google_id' => $google_id
    ]);
    
    if ($stmt_vis->rowCount() > 0) {
        $usuario = $stmt_vis->fetch(PDO::FETCH_ASSOC);
        
        // Si no tiene google_id, actualizarlo
        if (empty($usuario['google_id'])) {
            $update_sql = "UPDATE usuario_visitante SET google_id = :google_id WHERE id = :id";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([
                ':google_id' => $google_id,
                ':id' => $usuario['id']
            ]);
        }
        
        // Iniciar sesión como visitante
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['correo'] = $usuario['correo'];
        $_SESSION['rol'] = 'visitante';
        $_SESSION['tipo_usuario'] = 'visitante';
        $_SESSION['google_login'] = true;
        
        return 'visitante';
    }
    
    // Si no existe, guardar datos en sesión para preguntar tipo de cuenta
    $_SESSION['google_user_data'] = [
        'google_id' => $google_id,
        'email' => $email,
        'name' => $name,
        'picture' => $userInfo['picture']
    ];
    
    return 'new_user';
}
?>