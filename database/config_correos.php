<?php
// CONFIGURACIÓN DE CORREOS RENTNONO CON PHPMailer

// Verificar si ya se ha definido la constante para evitar inclusiones múltiples
if (!defined('CONFIG_CORREOS_CARGADO')) {
    define('CONFIG_CORREOS_CARGADO', true);

    // Modo: 'gmail' para enviar, 'prueba' para desarrollo
    define('MODO_CORREO', 'gmail');

    // Configuración GMAIL
    define('GMAIL_USER', 'rentnono.oficial@gmail.com');
    define('GMAIL_APP_PASSWORD', 'rjky myys rned ebyb');

    // Direcciones
    define('CORREO_FROM', 'no-reply@rentnono.com');
    define('NOMBRE_FROM', 'RentNono');
    define('CORREO_SOPORTE', 'soporte@rentnono.com');

    /**
     * Envía un correo electrónico usando PHPMailer
     */
    if (!function_exists('enviarCorreoRentNono')) {
        function enviarCorreoRentNono($destinatario, $nombre, $asunto, $mensajeHTML, $mensajeTexto = '') {
            
            // En modo prueba, solo mostrar en pantalla
            if (MODO_CORREO === 'prueba') {
                echo '<div style="background:#e3f2fd;padding:15px;margin:10px;border-radius:5px;border-left:4px solid #82b16d;">';
                echo '<strong>📧 Correo simulado (Modo desarrollo):</strong><br>';
                echo '<strong>Para:</strong> ' . htmlspecialchars($nombre) . ' &lt;' . htmlspecialchars($destinatario) . '&gt;<br>';
                echo '<strong>Asunto:</strong> ' . htmlspecialchars($asunto) . '<br>';
                echo '<strong>Mensaje:</strong> ' . htmlspecialchars(strip_tags($mensajeHTML));
                echo '</div>';
                return true;
            }
            
            try {
                // Cargar PHPMailer solo si no se ha cargado antes
                if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
                    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
                    require_once __DIR__ . '/PHPMailer/src/Exception.php';
                }
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                
                // Configurar SMTP GMAIL
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = GMAIL_USER;
                $mail->Password = GMAIL_APP_PASSWORD;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Para XAMPP en Windows (desactivar verificación SSL)
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Remitente
                $mail->setFrom(CORREO_FROM, NOMBRE_FROM);
                $mail->addReplyTo(CORREO_SOPORTE, 'Soporte RentNono');
                
                // Destinatario
                $mail->addAddress($destinatario, $nombre);
                
                // Contenido
                $mail->isHTML(true);
                $mail->Subject = $asunto;
                $mail->Body = $mensajeHTML;
                
                if ($mensajeTexto) {
                    $mail->AltBody = $mensajeTexto;
                } else {
                    $mail->AltBody = strip_tags($mensajeHTML);
                }
                
                // Enviar
                if ($mail->send()) {
                    error_log("✅ Correo enviado a: $destinatario");
                    return true;
                } else {
                    error_log("❌ Error al enviar: " . $mail->ErrorInfo);
                    return false;
                }
                
            } catch (Exception $e) {
                error_log("❌ Excepción PHPMailer: " . $e->getMessage());
                
                // Intentar método alternativo
                return enviarCorreoAlternativo($destinatario, $asunto, $mensajeHTML);
            }
        }
    }

    /**
     * Método alternativo si PHPMailer falla
     */
    if (!function_exists('enviarCorreoAlternativo')) {
        function enviarCorreoAlternativo($destinatario, $asunto, $mensajeHTML) {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . NOMBRE_FROM . " <" . CORREO_FROM . ">\r\n";
            $headers .= "Reply-To: " . CORREO_SOPORTE . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            if (mail($destinatario, $asunto, $mensajeHTML, $headers)) {
                error_log("✅ Correo alternativo enviado a: $destinatario");
                return true;
            }
            
            error_log("❌ Error en correo alternativo a: $destinatario");
            return false;
        }
    }

    /**
     * Plantilla para correo de recuperación
     */
    if (!function_exists('plantillaRecuperacion')) {
        function plantillaRecuperacion($nombre, $enlace) {
            return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                    .header { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 30px 20px; text-align: center; }
                    .content { padding: 30px; }
                    .button { display: inline-block; padding: 14px 28px; background: #4CAF50; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; margin: 20px 0; }
                    .link-box { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; word-break: break-all; font-size: 14px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #eee; margin-top: 30px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>RENTNONO</h1>
                        <p>Recuperación de contraseña</p>
                    </div>
                    
                    <div class="content">
                        <h2>¡Hola ' . htmlspecialchars($nombre) . '!</h2>
                        
                        <p>Has solicitado recuperar tu contraseña en <strong>RentNono</strong>.</p>
                        
                        <p style="text-align: center;">
                            <a href="' . $enlace . '" class="button">🔐 RESTABLECER CONTRASEÑA</a>
                        </p>
                        
                        <p>O copia y pega este enlace en tu navegador:</p>
                        
                        <div class="link-box">
                            ' . $enlace . '
                        </div>
                        
                        <p><strong>⏰ Este enlace expirará en 1 hora.</strong></p>
                        
                        <p>Si no solicitaste esto, ignora este correo.</p>
                        
                        <div class="footer">
                            <p>© ' . date('Y') . ' RentNono. Todos los derechos reservados.</p>
                            <p>Este es un correo automático, por favor no respondas.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ';
        }
    }

    /**
     * Plantilla para correo de bienvenida
     */
    if (!function_exists('plantillaBienvenida')) {
        function plantillaBienvenida($nombre, $password_temporal, $tipo_usuario) {
            // Determinar la URL base correcta
            $base_url = "http://" . $_SERVER['HTTP_HOST'];
            $ruta_base = dirname($_SERVER['PHP_SELF']);
            
            // Si estamos en una subcarpeta (como /rentnono), incluirla
            if ($ruta_base != '/') {
                $base_url .= $ruta_base;
            }
            
            $inicio_url = $base_url . "/index.php";
            
            return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                    .header { background: #4CAF50; color: white; padding: 30px 20px; text-align: center; }
                    .content { padding: 30px; }
                    .password-box { background: #e8f5e9; padding: 20px; border-radius: 8px; border: 2px dashed #4CAF50; text-align: center; margin: 25px 0; }
                    .password { font-size: 24px; font-weight: bold; letter-spacing: 2px; color: #2e7d32; margin: 10px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #eee; margin-top: 30px; }
                    .button { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>RENTNONO</h1>
                        <p>¡Bienvenido!</p>
                    </div>
                    
                    <div class="content">
                        <h2>¡Hola ' . htmlspecialchars($nombre) . '!</h2>
                        
                        <p>Tu registro como <strong>' . htmlspecialchars($tipo_usuario) . '</strong> ha sido exitoso.</p>
                        
                        <div class="password-box">
                            <p>Tu contraseña temporal es:</p>
                            <div class="password">' . htmlspecialchars($password_temporal) . '</div>
                            <p><small>Guarda esta contraseña de manera segura</small></p>
                        </div>
                        
                        <p>Te recomendamos cambiar esta contraseña en tu primera sesión.</p>
                        
                        <p style="text-align: center; margin-top: 30px;">
                            <a href="' . $inicio_url . '" class="button">
                                🚀 INICIAR SESIÓN
                            </a>
                        </p>
                        
                        <div class="footer">
                            <p>© ' . date('Y') . ' RentNono. Todos los derechos reservados.</p>
                            <p>Este es un correo automático.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ';
        }
    }
}
?>