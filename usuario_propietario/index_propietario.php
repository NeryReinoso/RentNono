<?php
// Verificar sesión y rol
session_start();
include("../database/conexion.php");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "propietario") {
    header("Location: ../index.php");
    exit;
}

$nombre = $_SESSION["nombre"];
$id_propietario = $_SESSION["id"];

// Habilitar errores para depuración (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // ============================================
    // ESTADÍSTICAS DEL PROPIETARIO
    // ============================================
    $sql_propiedades = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado_publicacion = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN estado_publicacion = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_publicacion = 'rechazada' THEN 1 ELSE 0 END) as rechazadas
    FROM propiedades 
    WHERE id_propietario = :id_propietario";
    
    $stmt_propiedades = $conn->prepare($sql_propiedades);
    $stmt_propiedades->execute([':id_propietario' => $id_propietario]);
    $estadisticas = $stmt_propiedades->fetch(PDO::FETCH_ASSOC);
    
    // Si no hay resultados, inicializar con ceros
    if (!$estadisticas) {
        $estadisticas = ['total' => 0, 'aprobadas' => 0, 'pendientes' => 0, 'rechazadas' => 0];
    }
    
    // ============================================
    // NOTIFICACIONES - HISTORIAL COMPLETO
    // ============================================
    
    // Contar notificaciones no leídas
    $sql_notif_no_leidas = "SELECT COUNT(*) as total FROM notificaciones 
                          WHERE id_usuario = :id_usuario AND leida = 0";
    $stmt_notif = $conn->prepare($sql_notif_no_leidas);
    $stmt_notif->execute([':id_usuario' => $id_propietario]);
    $notificaciones_no_leidas = $stmt_notif->fetchColumn();
    
    // Obtener TODAS las notificaciones
    $sql_notif_lista = "SELECT 
        n.id,
        n.titulo,
        n.mensaje,
        n.tipo,
        n.leida,
        n.fecha,
        p.titulo as propiedad_titulo,
        p.id as propiedad_id
    FROM notificaciones n
    LEFT JOIN propiedades p ON n.id_propiedad = p.id
    WHERE n.id_usuario = :id_usuario
    ORDER BY n.fecha DESC
    LIMIT 100";
    
    $stmt_notif_lista = $conn->prepare($sql_notif_lista);
    $stmt_notif_lista->execute([':id_usuario' => $id_propietario]);
    $lista_notificaciones = $stmt_notif_lista->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // PROPIEDADES DEL PROPIETARIO (TODAS)
    // ============================================
    $sql_mis_propiedades = "SELECT 
        p.id,
        p.titulo,
        p.descripcion,
        p.precio,
        p.precio_no_publicado,
        p.ambientes,
        p.sanitarios as banios,
        p.superficie,
        p.direccion,
        p.latitud,
        p.longitud,
        p.estado_publicacion,
        p.fecha_solicitud,
        p.fecha_aprobacion,
        i.ruta as imagen_principal
    FROM propiedades p
    LEFT JOIN imagenes_propiedades i ON p.id = i.id_propiedad AND i.es_principal = 1
    WHERE p.id_propietario = :id_propietario
    ORDER BY 
        CASE p.estado_publicacion 
            WHEN 'aprobada' THEN 1
            WHEN 'pendiente' THEN 2
            WHEN 'rechazada' THEN 3
        END,
        p.fecha_solicitud DESC";
    
    $stmt_props = $conn->prepare($sql_mis_propiedades);
    $stmt_props->execute([':id_propietario' => $id_propietario]);
    $mis_propiedades = $stmt_props->fetchAll(PDO::FETCH_ASSOC);

     // ===== NUEVO CÓDIGO: Inicializar y contar propiedades =====    
    $propiedades_aprobadas = 0;
    $propiedades_pendientes = 0;
    $propiedades_rechazadas = 0;
    
    // Si hay propiedades, contamos cada una por su estado
    if (!empty($mis_propiedades)) {
        foreach ($mis_propiedades as $prop) {
            switch($prop['estado_publicacion']) {
                case 'aprobada':
                    $propiedades_aprobadas++;
                    break;
                case 'pendiente':
                    $propiedades_pendientes++;
                    break;
                case 'rechazada':
                    $propiedades_rechazadas++;
                    break;
            }
        }
    }
    
    // Contar propiedades por estado
    $propiedades_aprobadas = 0;
    $propiedades_pendientes = 0;
    $propiedades_rechazadas = 0;
    
    foreach ($mis_propiedades as $prop) {
        switch($prop['estado_publicacion']) {
            case 'aprobada': $propiedades_aprobadas++; break;
            case 'pendiente': $propiedades_pendientes++; break;
            case 'rechazada': $propiedades_rechazadas++; break;
        }
    }
    
    // ============================================
    // COMENTARIOS Y FAVORITOS
    // ============================================
    
    // Comentarios totales aprobados
    $sql_comentarios = "SELECT COUNT(*) as total FROM opiniones o
                       INNER JOIN propiedades p ON o.propiedad_id = p.id
                       WHERE p.id_propietario = :id_propietario
                       AND o.estado = 'aprobada'";
    $stmt_comentarios = $conn->prepare($sql_comentarios);
    $stmt_comentarios->execute([':id_propietario' => $id_propietario]);
    $total_comentarios = $stmt_comentarios->fetchColumn();
    
    // Comentarios NO LEÍDOS
    $sql_comentarios_no_leidos = "SELECT COUNT(*) as total FROM opiniones o
                                 INNER JOIN propiedades p ON o.propiedad_id = p.id
                                 WHERE p.id_propietario = :id_propietario
                                 AND o.estado = 'aprobada'
                                 AND o.leido = 0";
    $stmt_no_leidos = $conn->prepare($sql_comentarios_no_leidos);
    $stmt_no_leidos->execute([':id_propietario' => $id_propietario]);
    $comentarios_no_leidos = $stmt_no_leidos->fetchColumn();
    
    // Favoritos totales
    $sql_favoritos = "SELECT COUNT(*) as total FROM favoritos f
                      INNER JOIN propiedades p ON f.propiedad_id = p.id
                      WHERE p.id_propietario = :id_propietario";
    $stmt_favoritos = $conn->prepare($sql_favoritos);
    $stmt_favoritos->execute([':id_propietario' => $id_propietario]);
    $total_favoritos = $stmt_favoritos->fetchColumn();
    
    // Lista de comentarios
    $sql_comentarios_lista = "SELECT 
        o.id,
        o.propiedad_id,
        o.usuario_id,
        o.rating,
        o.comentario,
        o.fecha,
        o.estado,
        o.leido,
        u.nombre as usuario_nombre,
        p.titulo as propiedad_titulo,
        p.id_propietario,
        (SELECT COUNT(*) FROM favoritos f 
         WHERE f.propiedad_id = o.propiedad_id 
         AND f.usuario_id = o.usuario_id) as es_favorito
    FROM opiniones o
    INNER JOIN propiedades p ON o.propiedad_id = p.id
    INNER JOIN usuario_visitante u ON o.usuario_id = u.id
    WHERE p.id_propietario = :id_propietario
    AND o.estado = 'aprobada'
    ORDER BY o.leido ASC, o.fecha DESC
    LIMIT 20";
    
    $stmt_comentarios_lista = $conn->prepare($sql_comentarios_lista);
    $stmt_comentarios_lista->execute([':id_propietario' => $id_propietario]);
    $lista_comentarios = $stmt_comentarios_lista->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular rating promedio
    $rating_promedio = 0;
    $suma_ratings = 0;
    $contador_ratings = 0;
    
    foreach ($lista_comentarios as $comentario) {
        if ($comentario['rating'] > 0) {
            $suma_ratings += $comentario['rating'];
            $contador_ratings++;
        }
    }
    
    if ($contador_ratings > 0) {
        $rating_promedio = round($suma_ratings / $contador_ratings, 1);
    }
    
} catch (Exception $e) {
    error_log("Error en index_propietario: " . $e->getMessage());
    $estadisticas = ['total' => 0, 'aprobadas' => 0, 'pendientes' => 0, 'rechazadas' => 0];
    $notificaciones_no_leidas = 0;
    $lista_notificaciones = [];
    $mis_propiedades = [];
    $propiedades_aprobadas = 0;
    $propiedades_pendientes = 0;
    $propiedades_rechazadas = 0;
    $total_comentarios = 0;
    $comentarios_no_leidos = 0;
    $total_favoritos = 0;
    $lista_comentarios = [];
    $rating_promedio = 0;
}

// Función para generar color desde nombre
function generarColorDesdeNombre($nombre) {
    $colores = ['#3498db', '#2ecc71', '#e74c3c', '#9b59b6', '#1abc9c', '#34495e', '#f39c12', '#d35400'];
    $indice = crc32($nombre) % count($colores);
    return $colores[$indice];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Propietario | RentNono</title>
    <link rel="stylesheet" href="propietario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        /* ============================================
           ESTILOS PRINCIPALES
           ============================================ */
        
        /* Animaciones */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        /* ============================================
           NOTIFICACIONES - HISTORIAL MEJORADO
           ============================================ */
        
        /* Estadísticas */
        .estadisticas-notificaciones {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .tarjeta-estadistica-notif {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: transform 0.3s;
        }
        
        .tarjeta-estadistica-notif:hover {
            transform: translateY(-5px);
        }
        
        .icono-estadistica-notif {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .info-estadistica-notif h3 {
            font-size: 28px;
            font-weight: 800;
            color: #2c3e50;
            margin: 0;
        }
        
        .info-estadistica-notif p {
            color: #7f8c8d;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        /* Barra de herramientas */
        .barra-herramientas-notificaciones {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .buscador-notificaciones {
            position: relative;
            flex: 1;
            min-width: 300px;
        }
        
        .buscador-notificaciones i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #82b16d;
            font-size: 18px;
            z-index: 1;
        }
        
        .buscador-notificaciones input {
            width: 100%;
            padding: 14px 45px 14px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .buscador-notificaciones input:focus {
            border-color: #82b16d;
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(130, 177, 109, 0.1);
        }
        
        .btn-limpiar-busqueda {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            border: none;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.3s;
            z-index: 2;
        }
        
        .btn-limpiar-busqueda:hover {
            opacity: 1;
            transform: translateY(-50%) scale(1.1);
            background: #c0392b;
        }
        
        /* Filtros */
        .filtros-notificaciones {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .selector-filtro-notif {
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            min-width: 160px;
            color: #2c3e50;
            transition: all 0.3s;
        }
        
        .selector-filtro-notif:focus {
            border-color: #82b16d;
            outline: none;
            box-shadow: 0 0 0 3px rgba(130, 177, 109, 0.1);
        }
        
        .btn-accion-notif {
            padding: 12px 20px;
            background: #82b16d;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-accion-notif:hover {
            background: #6a9a58;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(130, 177, 109, 0.3);
        }
        
        /* Contenedor de notificaciones */
        .contenedor-notificaciones-historial {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        /* Tarjeta individual */
        .tarjeta-notificacion-historico {
            background: white;
            border-radius: 12px;
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: flex-start;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
            position: relative;
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .tarjeta-notificacion-historico:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .tarjeta-notificacion-historico.no-leida {
            border-left: 4px solid #82b16d;
            background: linear-gradient(90deg, rgba(130, 177, 109, 0.05) 0%, rgba(130, 177, 109, 0.01) 100%);
        }
        
        .tarjeta-notificacion-historico.leida {
            opacity: 0.9;
        }
        
        .tarjeta-notificacion-historico.reciente {
            border-top: 2px solid #ffc107;
        }
        
        /* Indicador de no leída */
        .indicador-no-leida {
            position: absolute;
            top: 15px;
            left: 15px;
        }
        
        .punto-activo {
            display: block;
            width: 12px;
            height: 12px;
            background: #82b16d;
            border-radius: 50%;
            animation: pulse 2s infinite;
            box-shadow: 0 0 0 3px rgba(130, 177, 109, 0.3);
        }
        
        /* Icono */
        .icono-notificacion-historico {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Contenido */
        .contenido-notificacion-historico {
            flex: 1;
            min-width: 0;
        }
        
        .cabecera-notificacion-historico {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .titulo-notificacion {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            line-height: 1.3;
        }
        
        .tiempo-notificacion-historico {
            color: #7f8c8d;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .mensaje-notificacion {
            color: #495057;
            font-size: 15px;
            line-height: 1.5;
            margin: 0 0 15px 0;
        }
        
        /* Propiedad relacionada */
        .propiedad-notificacion-historico {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .propiedad-notificacion-historico i {
            color: #82b16d;
            font-size: 16px;
        }
        
        .nombre-propiedad {
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
        }
        
        .enlace-propiedad {
            background: linear-gradient(135deg, #82b16d, #6a9a58);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .enlace-propiedad:hover {
            background: linear-gradient(135deg, #6a9a58, #5a8a48);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(106, 154, 88, 0.3);
            color: white;
            text-decoration: none;
        }
        
        /* Meta información */
        .meta-info-notificacion {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .badge-tipo {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid;
        }
        
        .badge-reciente {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Acciones */
        .acciones-notificacion-historico {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-marcar-leida,
        .btn-marcar-no-leida {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
            color: #7f8c8d;
        }
        
        .btn-marcar-leida:hover {
            background: #82b16d;
            color: white;
            transform: scale(1.1);
        }
        
        .btn-marcar-no-leida:hover {
            background: #3498db;
            color: white;
            transform: scale(1.1);
        }
        
        /* Pie de página */
        .pie-notificaciones {
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .contador-notificaciones {
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .contador-notificaciones span:first-child {
            font-weight: 800;
            color: #82b16d;
            font-size: 16px;
        }
        
        .info-almacenamiento {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #95a5a6;
        }
        
        .info-almacenamiento i {
            color: #3498db;
        }
        
        /* Estado vacío de notificaciones */
        .estado-vacio-notificaciones {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #ddd;
            margin: 20px 0;
        }
        
        .estado-vacio-notificaciones .icono-estado-vacio {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .estado-vacio-notificaciones h3 {
            font-size: 24px;
            color: #495057;
            margin-bottom: 15px;
        }
        
        .mensaje-estado-vacio {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto 25px;
            line-height: 1.6;
        }
        
        .sugerencias-estado-vacio {
            text-align: left;
            max-width: 500px;
            margin: 0 auto;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #82b16d;
        }
        
        .sugerencias-estado-vacio p {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sugerencias-estado-vacio ul {
            margin: 0;
            padding-left: 20px;
            color: #6c757d;
        }
        
        .sugerencias-estado-vacio li {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        /* Estado vacío por filtros */
        .estado-vacio-filtro {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            border: 2px dashed #ddd;
            margin: 20px 0;
            grid-column: 1 / -1;
        }
        
        .estado-vacio-filtro i {
            font-size: 48px;
            color: #82b16d;
            margin-bottom: 15px;
        }
        
        .estado-vacio-filtro h4 {
            font-size: 20px;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .estado-vacio-filtro p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        /* Colores por tipo de notificación */
        .aprobacion .icono-notificacion-historico {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .rechazo .icono-notificacion-historico {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .solicitud .icono-notificacion-historico {
            background: linear-gradient(135deg, #f39c12, #d35400);
        }
        
        .comentario .icono-notificacion-historico {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .visita .icono-notificacion-historico {
            background: linear-gradient(135deg, #1abc9c, #16a085);
        }
        
        .general .icono-notificacion-historico {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        /* ============================================
           ESTADOS DE PROPIEDADES
           ============================================ */
        
        /* Estado: Esperando aprobación */
        .estado-esperando-aprobacion {
            text-align: center;
            padding: 50px 30px;
            background: white;
            border-radius: 12px;
            border: 2px solid #ff9800;
            margin: 20px 0;
            grid-column: 1 / -1;
        }
        
        .estado-esperando-aprobacion .icono-estado {
            font-size: 72px;
            color: #ff9800;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        .estado-esperando-aprobacion h3 {
            font-size: 28px;
            color: #ff9800;
            margin-bottom: 15px;
        }
        
        .estado-esperando-aprobacion .mensaje-estado {
            color: #7f8c8d;
            max-width: 700px;
            margin: 0 auto 25px;
            line-height: 1.6;
            font-size: 16px;
        }
        
        .estado-esperando-aprobacion .mensaje-estado strong {
            color: #ff9800;
        }
        
        /* Lista de propiedades pendientes */
        .lista-pendientes {
            background: #fff8e1;
            border-radius: 10px;
            padding: 20px;
            margin: 25px auto;
            max-width: 600px;
            text-align: left;
        }
        
        .lista-pendientes h4 {
            color: #ff9800;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .lista-pendientes ul {
            margin: 0;
            padding-left: 25px;
        }
        
        .lista-pendientes li {
            color: #5d4037;
            margin-bottom: 8px;
            padding: 8px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #ff9800;
        }
        
        /* Proceso de publicación */
        .proceso-publicacion {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px auto;
            max-width: 700px;
        }
        
        .proceso-publicacion h4 {
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pasos-proceso {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .paso {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .numero-paso {
            width: 40px;
            height: 40px;
            background: #82b16d;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .info-paso {
            flex: 1;
            text-align: left;
        }
        
        .info-paso h5 {
            color: #2c3e50;
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .info-paso p {
            color: #7f8c8d;
            margin: 0;
            font-size: 14px;
        }
        
        /* Sugerencias */
        .sugerencias-estado {
            text-align: left;
            max-width: 600px;
            margin: 0 auto 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .estado-esperando-aprobacion .sugerencias-estado {
            border-left-color: #ff9800;
        }
        
        .sugerencias-estado p {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sugerencias-estado ul {
            margin: 0;
            padding-left: 20px;
            color: #6c757d;
        }
        
        .sugerencias-estado li {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        /* Acciones de estado */
        .acciones-estado {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* Botón para propiedades publicadas */
        .boton-accion-tarjeta.boton-publicada {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .boton-accion-tarjeta.boton-publicada:hover {
            background: linear-gradient(135deg, #27ae60, #219653);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        }
        
        /* Overlay de imagen mejorado */
        .overlay-imagen-tarjeta .fecha-solicitud {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .overlay-imagen-tarjeta .fa-calendar-check {
            color: #2ecc71;
        }
        
        .overlay-imagen-tarjeta .fa-calendar {
            color: #ff9800;
        }
        
        /* ============================================
           MODAL DE CONFIRMACIÓN
           ============================================ */
        
        .modal-confirmacion {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .contenido-confirmacion {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .contenido-confirmacion h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contenido-confirmacion p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .resumen-envio {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .item-resumen {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .item-resumen:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .item-resumen .etiqueta {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .item-resumen .valor {
            color: #7f8c8d;
            text-align: right;
            max-width: 250px;
            word-break: break-word;
        }
        
        .acciones-confirmacion {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        .acciones-confirmacion button {
            min-width: 120px;
        }
        
        /* ============================================
           MENSAJES FLOTANTES
           ============================================ */
        
        .mensaje-flotante {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            max-width: 400px;
        }
        
        .contenido-mensaje {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
        }
        
        .mensaje-success .contenido-mensaje {
            border-left-color: #2ecc71;
            background: #f0f9f0;
        }
        
        .mensaje-error .contenido-mensaje {
            border-left-color: #e74c3c;
            background: #fdf0f0;
        }
        
        .mensaje-info .contenido-mensaje {
            border-left-color: #3498db;
            background: #f0f8ff;
        }
        
        .contenido-mensaje i {
            font-size: 20px;
        }
        
        .mensaje-success .contenido-mensaje i {
            color: #2ecc71;
        }
        
        .mensaje-error .contenido-mensaje i {
            color: #e74c3c;
        }
        
        .mensaje-info .contenido-mensaje i {
            color: #3498db;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .barra-herramientas-notificaciones {
                flex-direction: column;
                align-items: stretch;
            }
            
            .buscador-notificaciones {
                min-width: 100%;
            }
            
            .filtros-notificaciones {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .tarjeta-notificacion-historico {
                flex-direction: column;
                gap: 15px;
            }
            
            .icono-notificacion-historico {
                align-self: flex-start;
            }
            
            .acciones-notificacion-historico {
                flex-direction: row;
                align-self: flex-end;
            }
            
            .cabecera-notificacion-historico {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .estadisticas-notificaciones {
                grid-template-columns: 1fr;
            }
            
            .pie-notificaciones {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- BARRA LATERAL -->
<aside class="barra-lateral">
    <div class="cabecera-barra">
        <h2 class="logo">Rent<span>Nono</span></h2>
        <div class="info-usuario">
            <i class="fa-solid fa-user-circle icono-usuario"></i>
            <span class="nombre-usuario"><?php echo htmlspecialchars($nombre); ?></span>
            <span class="rol-usuario">Propietario</span>
        </div>
    </div>

    <nav class="navegacion-barra">
        <ul>
            <li class="activo">
                <a href="#inicio" class="enlace-navegacion" id="nav-inicio">
                    <i class="fa-solid fa-house icono-navegacion"></i>
                    <span class="texto-navegacion">Inicio</span>
                </a>
            </li>
            <li>
                <a href="#formulario" class="enlace-navegacion" id="nav-formulario">
                    <i class="fa-solid fa-plus-circle icono-navegacion"></i>
                    <span class="texto-navegacion">Agregar propiedad</span>
                </a>
            </li>
            <li>
                <a href="#propiedades" class="enlace-navegacion" id="nav-propiedades">
                    <i class="fa-solid fa-building icono-navegacion"></i>
                    <span class="texto-navegacion">Mis propiedades</span>
                </a>
            </li>
            <li>
                <a href="#comentarios" class="enlace-navegacion" id="nav-comentarios">
                    <i class="fa-solid fa-comments icono-navegacion"></i>
                    <span class="texto-navegacion">Comentarios</span>
                    <?php if ($comentarios_no_leidos > 0): ?>
                    <span class="badge nuevo"></span>
                    <?php endif; ?>
                    <?php if ($total_comentarios > 0): ?>
                    <span class="badge"><?php echo $total_comentarios; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="#notificaciones" class="enlace-navegacion" id="nav-notificaciones">
                    <i class="fa-solid fa-bell icono-navegacion"></i>
                    <span class="texto-navegacion">Notificaciones</span>
                    <?php if ($notificaciones_no_leidas > 0): ?>
                    <span class="badge nuevo"><?php echo $notificaciones_no_leidas; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="pie-barra">
        <a href="../database/logout.php" class="boton-salir">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- CONTENIDO PRINCIPAL -->
<main class="contenido-principal">
    <!-- CABECERA -->
    <header class="cabecera-principal">
        <div class="izquierda-cabecera">
            <button class="boton-menu" id="botonMenu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h1 class="titulo-pagina" id="tituloPagina">Panel de Control</h1>
        </div>
        <div class="derecha-cabecera">
            <div class="icono-notificacion" onclick="mostrarSeccion('notificaciones')">
                <i class="fa-solid fa-bell" style="color: #28a745;"></i>
                <?php if ($notificaciones_no_leidas > 0): ?>
                <span class="contador-notificacion"><?php echo $notificaciones_no_leidas; ?></span>
                <?php endif; ?>
            </div>
            <div class="fecha-actual">
                <i class="fa-solid fa-calendar-day"></i>
                <span><?php echo date('d/m/Y'); ?></span>
            </div>
        </div>
    </header>

    <!-- SECCIÓN INICIO -->
    <section id="sec-inicio" class="seccion-contenido activa">
        <div class="cabecera-seccion">
            <h2>Bienvenido de nuevo, <?php echo htmlspecialchars($nombre); ?></h2>
            <p class="subtitulo-seccion">Gestión centralizada de tus propiedades en RentNono</p>
        </div>

        <div class="estadisticas-tablero">
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.1s">
                <div class="icono-estadistica" style="background-color: #e3f2fd;">
                    <i class="fa-solid fa-building" style="color: #2196f3;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $estadisticas['total']; ?></h3>
                    <p class="etiqueta-estadistica">Propiedades totales</p>
                </div>
            </div>
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.2s">
                <div class="icono-estadistica" style="background-color: #e8f5e9;">
                    <i class="fa-solid fa-check-circle" style="color: #4caf50;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $estadisticas['aprobadas']; ?></h3>
                    <p class="etiqueta-estadistica">Aprobadas</p>
                </div>
            </div>
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.3s">
                <div class="icono-estadistica" style="background-color: #fff3e0;">
                    <i class="fa-solid fa-clock" style="color: #ff9800;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $estadisticas['pendientes']; ?></h3>
                    <p class="etiqueta-estadistica">Pendientes</p>
                </div>
            </div>
            <div class="tarjeta-estadistica fade-in-up" style="animation-delay: 0.4s">
                <div class="icono-estadistica" style="background-color: #fce4ec;">
                    <i class="fa-solid fa-comment" style="color: #e91e63;"></i>
                </div>
                <div class="info-estadistica">
                    <h3 class="numero-estadistica"><?php echo $total_comentarios; ?></h3>
                    <p class="etiqueta-estadistica">Comentarios</p>
                </div>
            </div>
        </div>

        <div class="tarjetas-tablero">
            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('formulario')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-plus icono-tarjeta"></i>
                    <h3>Agregar propiedad</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Completá el formulario y enviá tu solicitud al administrador para publicar una nueva propiedad.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Comenzar <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>

            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('propiedades')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-building icono-tarjeta"></i>
                    <h3>Mis propiedades</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Revisá el estado de tus propiedades: Pendiente, Aprobada o Rechazada.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Ver propiedades <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>

            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('comentarios')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-comments icono-tarjeta"></i>
                    <h3>Comentarios y reseñas</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Leé lo que opinan los visitantes sobre tus propiedades publicadas.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Ver comentarios <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>

            <div class="tarjeta tarjeta-interactiva" onclick="mostrarSeccion('notificaciones')">
                <div class="cabecera-tarjeta">
                    <i class="fa-solid fa-bell icono-tarjeta"></i>
                    <h3>Notificaciones</h3>
                </div>
                <div class="cuerpo-tarjeta">
                    <p>Tenés novedades recientes sobre el estado de tus propiedades y solicitudes.</p>
                </div>
                <div class="pie-tarjeta">
                    <span class="accion-tarjeta">Ver notificaciones <i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </section>

    <!-- SECCIÓN FORMULARIO (con descripción y ubicación opcionales) -->
    <section id="sec-formulario" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Agregar una propiedad</h2>
            <p class="subtitulo-seccion">Completá los datos para enviar la solicitud al administrador</p>
        </div>

        <form class="formulario-propiedad" id="formulario-propiedad" action="../database/guardar_propiedades.php" method="POST" enctype="multipart/form-data">
            <div class="grid-formulario">
                <div class="grupo-formulario">
                    <label for="titulo" class="etiqueta-formulario">Título de la propiedad *</label>
                    <input type="text" id="titulo" name="titulo" class="entrada-formulario" 
                           placeholder="Ej: Casa amplia de 3 ambientes en zona residencial" required
                           data-tooltip="Escribe un título atractivo que describa la propiedad">
                    <small class="ayuda-formulario">Un título claro y descriptivo atrae más visitas</small>
                </div>

                <div class="grupo-formulario">
                    <label for="descripcion" class="etiqueta-formulario">Descripción detallada</label>
                    <textarea id="descripcion" name="descripcion" class="area-texto-formulario" 
                              rows="4" placeholder="Describí las características principales, ubicación, ventajas..." 
                              data-tooltip="Describe todos los detalles importantes de la propiedad (opcional)"></textarea>
                    <small class="ayuda-formulario">Incluí detalles como orientación, vistas, estado de conservación (opcional)</small>
                </div>

                <div class="grupo-formulario">
                    <label for="precio" class="etiqueta-formulario">Precio mensual *</label>
                    <div class="contenedor-precio">
                        <div class="entrada-con-icono">
                            <i class="fa-solid fa-dollar-sign"></i>
                            <input type="number" id="precio" name="precio" class="entrada-formulario" 
                                   placeholder="120000" min="0" step="1">
                        </div>
                        <label class="etiqueta-checkbox">
                            <input type="checkbox" id="no-decirlo" name="no_decirlo">
                            <span>Prefiero no publicar el precio</span>
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
                                <input type="number" id="ambientes" name="ambientes" class="entrada-formulario" 
                                       placeholder="3" min="1" required>
                            </div>
                        </div>
                        <div class="entrada-caracteristica">
                            <label for="banios" class="etiqueta-formulario">Baños *</label>
                            <div class="entrada-con-icono">
                                <i class="fa-solid fa-bath"></i>
                                <input type="number" id="banios" name="banios" class="entrada-formulario" 
                                       placeholder="1" min="1" required>
                            </div>
                        </div>
                        <div class="entrada-caracteristica">
                            <label for="superficie" class="etiqueta-formulario">Superficie (m²) *</label>
                            <div class="entrada-con-icono">
                                <i class="fa-solid fa-ruler-combined"></i>
                                <input type="number" id="superficie" name="superficie" class="entrada-formulario" 
                                       placeholder="80" min="10" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo">
                    <h3 class="titulo-seccion-formulario">Servicios incluidos</h3>
                    <div class="grid-servicios">
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="wifi">
                            <div class="item-servicio">
                                <i class="fa-solid fa-wifi"></i>
                                <span>WiFi</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="cochera">
                            <div class="item-servicio">
                                <i class="fa-solid fa-car"></i>
                                <span>Cochera</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="patio">
                            <div class="item-servicio">
                                <i class="fa-solid fa-tree"></i>
                                <span>Patio</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="amoblado">
                            <div class="item-servicio">
                                <i class="fa-solid fa-couch"></i>
                                <span>Amoblado</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="aire">
                            <div class="item-servicio">
                                <i class="fa-solid fa-snowflake"></i>
                                <span>Aire acondicionado</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="calefaccion">
                            <div class="item-servicio">
                                <i class="fa-solid fa-fire"></i>
                                <span>Calefacción</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="cable">
                            <div class="item-servicio">
                                <i class="fa-solid fa-tv"></i>
                                <span>Cable TV</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="pileta">
                            <div class="item-servicio">
                                <i class="fa-solid fa-water-ladder"></i>
                                <span>Pileta</span>
                            </div>
                        </label>
                        <label class="checkbox-servicio">
                            <input type="checkbox" name="servicios[]" value="seguridad">
                            <div class="item-servicio">
                                <i class="fa-solid fa-shield-alt"></i>
                                <span>Seguridad 24hs</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo">
                    <label for="autocomplete" class="etiqueta-formulario">
                        <i class="fa-solid fa-map-location-dot"></i> Ubicación de la propiedad
                    </label>
                    
                    <div class="entrada-con-icono">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" 
                               id="autocomplete" 
                               class="entrada-formulario" 
                               placeholder="Escribe una dirección, ciudad o lugar en La Rioja..."
                               autocomplete="off">
                        <div class="loader-autocomplete" id="loaderAutocomplete" style="display: none;">
                            <div class="spinner"></div>
                        </div>
                    </div>
                    <small class="ayuda-formulario">
                        <i class="fa-solid fa-lightbulb"></i> Comienza a escribir para buscar ubicación (opcional)
                    </small>
                    
                    <input type="hidden" id="direccion" name="direccion">
                    <input type="hidden" id="direccion_completa" name="direccion_completa">
                    <input type="hidden" id="latitud" name="latitud">
                    <input type="hidden" id="longitud" name="longitud">
                    <input type="hidden" id="ciudad" name="ciudad">
                    <input type="hidden" id="provincia" name="provincia">
                    <input type="hidden" id="pais" name="pais">
                    <input type="hidden" id="codigo_postal" name="codigo_postal">
                    
                    <div class="info-ubicacion-seleccionada" id="infoUbicacion" style="display: none;">
                        <div class="card-ubicacion">
                            <div class="icono-verificado">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="detalles-ubicacion">
                                <h5>Ubicación seleccionada</h5>
                                <p id="textoDireccion"></p>
                                <div class="detalles-adicionales">
                                    <span class="detalle-item">
                                        <i class="fa-solid fa-city"></i>
                                        <span id="textoCiudad">-</span>
                                    </span>
                                    <span class="detalle-item">
                                        <i class="fa-solid fa-flag"></i>
                                        <span id="textoProvincia">-</span>
                                    </span>
                                    <span class="detalle-item">
                                        <i class="fa-solid fa-globe"></i>
                                        <span id="textoPais">-</span>
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn-cambiar-ubicacion" onclick="limpiarUbicacion()">
                                <i class="fa-solid fa-times"></i> Cambiar
                            </button>
                        </div>
                    </div>
                    
                    <div class="seccion-mapa" id="seccionMapa" style="display: none; margin-top: 20px;">
                        <div class="contenedor-mapa">
                            <div id="map" style="height: 300px;"></div>
                        </div>
                    </div>
                    
                    <div class="mensaje-busqueda-ubicacion" id="mensajeBusqueda">
                        <div class="icono-mensaje">
                            <i class="fa-solid fa-map-marked-alt"></i>
                        </div>
                        <div class="texto-mensaje">
                            <h5>Busca una ubicación con Google</h5>
                            <p>Comienza escribiendo una dirección en el campo superior (opcional)</p>
                        </div>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo">
                    <h3 class="titulo-seccion-formulario">Imágenes de la propiedad *</h3>
                    <p class="ayuda-formulario">Subí imágenes de buena calidad (máximo 5 archivos, formatos: JPG, PNG, máximo 5MB cada una)</p>
                    
                    <div class="area-subida-archivos" id="areaSubidaArchivos">
                        <i class="fa-solid fa-cloud-upload-alt icono-subida"></i>
                        <p class="texto-subida">Arrastrá y soltá imágenes aquí o hacé clic para seleccionar</p>
                        <input type="file" id="imagenes" name="imagenes[]" multiple accept="image/*" required>
                        <div class="lista-archivos" id="listaArchivos"></div>
                    </div>
                    
                    <div class="grid-imagenes" id="gridImagenes"></div>
                    
                    <div class="contador-imagenes">
                        <small><span id="contadorSeleccionadas">0</span> imágenes seleccionadas (Máximo 5)</small>
                    </div>
                </div>

                <div class="grupo-formulario ancho-completo acciones-formulario">
                    <button type="button" class="boton-secundario" onclick="mostrarSeccion('inicio')">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="boton-principal" id="btnEnviarFormulario">
                        <i class="fa-solid fa-paper-plane"></i> Enviar solicitud
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- SECCIÓN MIS PROPIEDADES -->
    <section id="sec-propiedades" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Mis propiedades</h2>
            <p class="subtitulo-seccion">Gestioná todas tus propiedades publicadas en RentNono</p>
            
            <div class="acciones-seccion">
                <div class="buscador-propiedades">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" 
                           id="buscadorPropiedades" 
                           placeholder="Buscar propiedades por título, dirección o descripción..."
                           onkeyup="filtrarPropiedadesTarjetas()">
                </div>
                
                <div class="filtros-propiedades">
                    <select class="selector-filtro" id="filtroEstadoTarjetas" onchange="filtrarPropiedadesTarjetas()">
                        <option value="todas">Todas las propiedades</option>
                        <option value="aprobada">✓ Aprobadas</option>
                        <option value="pendiente">⏳ Pendientes</option>
                        <option value="rechazada">✗ Rechazadas</option>
                    </select>
                    
                    <button class="boton-secundario" onclick="mostrarSeccion('formulario')">
                        <i class="fa-solid fa-plus"></i> Nueva propiedad
                    </button>
                </div>
            </div>
        </div>

        <div class="contenedor-tarjetas-propiedades" id="contenedorTarjetas">
            <?php if (!empty($mis_propiedades)): ?>
                <?php foreach ($mis_propiedades as $propiedad): ?>
                <?php 
                $estado_clase = '';
                $estado_icono = '';
                $estado_texto = '';
                $estado_color = '';
                
                switch($propiedad['estado_publicacion']) {
                    case 'aprobada':
                        $estado_clase = 'estado-aprobada';
                        $estado_icono = 'fa-check-circle';
                        $estado_texto = 'Aprobada';
                        $estado_color = '#4caf50';
                        break;
                    case 'pendiente':
                        $estado_clase = 'estado-pendiente';
                        $estado_icono = 'fa-clock';
                        $estado_texto = 'Pendiente';
                        $estado_color = '#ff9800';
                        break;
                    case 'rechazada':
                        $estado_clase = 'estado-rechazada';
                        $estado_icono = 'fa-times-circle';
                        $estado_texto = 'Rechazada';
                        $estado_color = '#f44336';
                        break;
                }
                
                $precio_display = $propiedad['precio_no_publicado'] ? 
                    '<span class="precio-no-publicado">Precio no publicado</span>' : 
                    '<span class="precio-propiedad">$' . number_format($propiedad['precio'], 0, ',', '.') . '</span>';
                
                $fecha_display = !empty($propiedad['fecha_aprobacion']) ? 
                    date('d/m/Y', strtotime($propiedad['fecha_aprobacion'])) : 
                    date('d/m/Y', strtotime($propiedad['fecha_solicitud']));
                
                $imagen_url = !empty($propiedad['imagen_principal']) ? 
                    '../media/' . $propiedad['imagen_principal'] : 
                    'https://images.unsplash.com/photo-1518780664697-55e3ad937233?w=400&h=300&fit=crop';
                
                $descripcion_corta = strlen($propiedad['descripcion']) > 120 ? 
                    substr($propiedad['descripcion'], 0, 120) . '...' : 
                    $propiedad['descripcion'];
                ?>
                
                <div class="tarjeta-propiedad fade-in-up" 
                     data-estado="<?php echo $propiedad['estado_publicacion']; ?>"
                     data-titulo="<?php echo htmlspecialchars(strtolower($propiedad['titulo'])); ?>"
                     data-descripcion="<?php echo htmlspecialchars(strtolower($propiedad['descripcion'])); ?>"
                     data-direccion="<?php echo htmlspecialchars(strtolower($propiedad['direccion'])); ?>">
                    
                    <div class="badge-estado-tarjeta <?php echo $estado_clase; ?>" style="background-color: <?php echo $estado_color; ?>20; border-left-color: <?php echo $estado_color; ?>;">
                        <i class="fa-solid <?php echo $estado_icono; ?>" style="color: <?php echo $estado_color; ?>;"></i>
                        <span><?php echo $estado_texto; ?></span>
                    </div>
                    
                    <div class="imagen-tarjeta-propiedad" 
                         style="background-image: url('<?php echo $imagen_url; ?>')">
                        <div class="overlay-imagen-tarjeta">
                            <span class="fecha-solicitud">
                                <?php if($propiedad['estado_publicacion'] == 'aprobada'): ?>
                                <i class="fa-solid fa-calendar-check"></i> Publicada: <?php echo $fecha_display; ?>
                                <?php else: ?>
                                <i class="fa-solid fa-calendar"></i> Enviada: <?php echo $fecha_display; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="contenido-tarjeta-propiedad">
                        <h3 class="titulo-tarjeta-propiedad" title="<?php echo htmlspecialchars($propiedad['titulo']); ?>">
                            <?php echo htmlspecialchars($propiedad['titulo']); ?>
                        </h3>
                        
                        <p class="descripcion-tarjeta-propiedad">
                            <?php echo htmlspecialchars($descripcion_corta); ?>
                        </p>
                        
                        <div class="caracteristicas-tarjeta">
                            <span class="caracteristica">
                                <i class="fa-solid fa-door-open"></i>
                                <?php echo $propiedad['ambientes']; ?> amb.
                            </span>
                            <span class="caracteristica">
                                <i class="fa-solid fa-bath"></i>
                                <?php echo $propiedad['banios']; ?> baño<?php echo $propiedad['banios'] > 1 ? 's' : ''; ?>
                            </span>
                            <span class="caracteristica">
                                <i class="fa-solid fa-ruler-combined"></i>
                                <?php echo $propiedad['superficie']; ?> m²
                            </span>
                        </div>
                        
                        <div class="precio-tarjeta">
                            <?php echo $precio_display; ?>
                        </div>
                        
                        <div class="direccion-tarjeta">
                            <i class="fa-solid fa-map-marker-alt"></i>
                            <span title="<?php echo htmlspecialchars($propiedad['direccion']); ?>">
                                <?php echo htmlspecialchars(strlen($propiedad['direccion']) > 40 ? substr($propiedad['direccion'], 0, 40) . '...' : $propiedad['direccion']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="acciones-tarjeta-propiedad">
                        <button class="boton-accion-tarjeta boton-ver" 
                                title="Ver detalles completos"
                                onclick="verDetallesPropiedadTarjeta(<?php echo $propiedad['id']; ?>)">
                            <i class="fa-solid fa-eye"></i>
                            <span>Ver</span>
                        </button>
                        
                        <?php if ($propiedad['estado_publicacion'] == 'pendiente'): ?>
                        <button class="boton-accion-tarjeta boton-editar" 
                                title="Editar propiedad"
                                onclick="editarPropiedadTarjeta(<?php echo $propiedad['id']; ?>)">
                            <i class="fa-solid fa-pen"></i>
                            <span>Editar</span>
                        </button>
                        <?php elseif ($propiedad['estado_publicacion'] == 'aprobada'): ?>
                        <button class="boton-accion-tarjeta boton-publicada" 
                                title="Publicada y visible"
                                onclick="verPropiedadPublicada(<?php echo $propiedad['id']; ?>)">
                            <i class="fa-solid fa-globe"></i>
                            <span>Publicada</span>
                        </button>
                        <?php else: ?>
                        <button class="boton-accion-tarjeta boton-deshabilitado" disabled title="No editable">
                            <i class="fa-solid fa-lock"></i>
                            <span>Bloqueado</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($propiedad['latitud']) && !empty($propiedad['longitud'])): ?>
                        <button class="boton-accion-tarjeta boton-mapa" 
                                title="Ver en mapa"
                                onclick="verMapaPropiedadTarjeta(<?php echo $propiedad['id']; ?>, <?php echo $propiedad['latitud']; ?>, <?php echo $propiedad['longitud']; ?>, '<?php echo htmlspecialchars(addslashes($propiedad['titulo'])); ?>')">
                            <i class="fa-solid fa-map"></i>
                            <span>Mapa</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
            <?php elseif ($propiedades_pendientes > 0 && $propiedades_aprobadas == 0): ?>
                <div class="estado-esperando-aprobacion">
                    <div class="icono-estado">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <h3>¡Tienes <?php echo $propiedades_pendientes; ?> propiedad(es) en revisión!</h3>
                    <p class="mensaje-estado">
                        El administrador está revisando tus solicitudes de publicación. 
                        <strong>Una vez aprobadas, aparecerán aquí automáticamente.</strong>
                    </p>
                    
                    <div class="sugerencias-estado">
                        <p><i class="fa-solid fa-lightbulb"></i> ¿Qué puedes hacer mientras tanto?</p>
                        <ul>
                            <li>Revisar tus <strong>notificaciones</strong> para actualizaciones</li>
                            <li><strong>Editar</strong> las propiedades pendientes si necesitas cambios</li>
                            <li><strong>Agregar más propiedades</strong> para aumentar tus oportunidades</li>
                        </ul>
                    </div>
                    
                    <div class="acciones-estado">
                        <button class="boton-principal" onclick="mostrarSeccion('formulario')">
                            <i class="fa-solid fa-plus"></i> Agregar otra propiedad
                        </button>
                        <button class="boton-secundario" onclick="mostrarSeccion('notificaciones')">
                            <i class="fa-solid fa-bell"></i> Ver notificaciones
                        </button>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="estado-vacio-propiedades">
                    <div class="icono-estado-vacio">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <h3>No tienes propiedades publicadas aún</h3>
                    <p class="mensaje-estado-vacio">
                        Cuando el administrador apruebe tus solicitudes, aparecerán aquí como propiedades publicadas.
                    </p>
                    
                    <div class="proceso-publicacion">
                        <h4><i class="fa-solid fa-play-circle"></i> Cómo publicar una propiedad:</h4>
                        <div class="pasos-proceso">
                            <div class="paso">
                                <div class="numero-paso">1</div>
                                <div class="info-paso">
                                    <h5>Envía tu solicitud</h5>
                                    <p>Completa el formulario con los datos de tu propiedad</p>
                                </div>
                            </div>
                            <div class="paso">
                                <div class="numero-paso">2</div>
                                <div class="info-paso">
                                    <h5>Espera la revisión</h5>
                                    <p>El administrador revisará y aprobará tu solicitud</p>
                                </div>
                            </div>
                            <div class="paso">
                                <div class="numero-paso">3</div>
                                <div class="info-paso">
                                    <h5>¡Publicada!</h5>
                                    <p>Aparecerá aquí automáticamente cuando sea aprobada</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="acciones-estado-vacio">
                        <button class="boton-principal grande" onclick="mostrarSeccion('formulario')">
                            <i class="fa-solid fa-plus"></i> Crear mi primera propiedad
                        </button>
                        <button class="boton-secundario grande" onclick="mostrarSeccion('inicio')">
                            <i class="fa-solid fa-arrow-left"></i> Volver al inicio
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($mis_propiedades)): ?>
        <div class="contador-propiedades">
            <span id="contadorPropiedadesMostradas"><?php echo count($mis_propiedades); ?></span> de 
            <span><?php echo count($mis_propiedades); ?></span> propiedades
        </div>
        <?php endif; ?>
    </section>

    <!-- SECCIÓN COMENTARIOS -->
    <section id="sec-comentarios" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Comentarios y Reseñas</h2>
            <p class="subtitulo-seccion">Feedback de los visitantes sobre tus propiedades</p>
            
            <div class="estadisticas-comentarios">
                <div class="tarjeta-resumen">
                    <div class="icono-resumen" style="background: linear-gradient(135deg, #82b16d, #6a9a58);">
                        <i class="fa-solid fa-comment-dots"></i>
                    </div>
                    <div class="info-resumen">
                        <h3><?php echo $total_comentarios; ?></h3>
                        <p>Comentarios totales</p>
                    </div>
                </div>
                
                <div class="tarjeta-resumen">
                    <div class="icono-resumen" style="background: linear-gradient(135deg, #ffc107, #ff9800);">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="info-resumen">
                        <h3><?php echo $rating_promedio; ?>/5</h3>
                        <p>Rating promedio</p>
                    </div>
                </div>
                
                <div class="tarjeta-resumen">
                    <div class="icono-resumen" style="background: linear-gradient(135deg, #e91e63, #c2185b);">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                    <div class="info-resumen">
                        <h3><?php echo $total_favoritos; ?></h3>
                        <p>En favoritos</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="filtros-comentarios">
            <div class="buscador-comentarios">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="buscadorComentarios" placeholder="Buscar comentarios o nombres...">
            </div>
            
            <div class="filtros-rapidos">
                <button class="filtro-rapido activo" data-filtro="todos">Todos</button>
                <button class="filtro-rapido" data-filtro="no-leidos">No leídos</button>
                <button class="filtro-rapido" data-filtro="5-estrellas">5 estrellas</button>
                <button class="filtro-rapido" data-filtro="favoritos">Con favoritos</button>
            </div>
        </div>

        <div class="contenedor-tarjetas-comentarios" id="contenedorComentarios">
            <?php if (!empty($lista_comentarios)): ?>
                <?php foreach ($lista_comentarios as $comentario): ?>
                <?php 
                $clase_no_leido = $comentario['leido'] == 0 ? 'no-leido' : '';
                $es_favorito = isset($comentario['es_favorito']) && $comentario['es_favorito'] > 0;
                $color_usuario = generarColorDesdeNombre($comentario['usuario_nombre']);
                ?>
                
                <div class="tarjeta-comentario <?php echo $clase_no_leido; ?>" 
                     data-id="<?php echo $comentario['id']; ?>"
                     data-rating="<?php echo $comentario['rating']; ?>"
                     data-favorito="<?php echo $es_favorito ? 'si' : 'no'; ?>"
                     data-usuario="<?php echo htmlspecialchars(strtolower($comentario['usuario_nombre'])); ?>"
                     data-comentario="<?php echo htmlspecialchars(strtolower($comentario['comentario'])); ?>"
                     data-propiedad="<?php echo htmlspecialchars(strtolower($comentario['propiedad_titulo'])); ?>">
                    
                    <?php if ($comentario['leido'] == 0): ?>
                    <div class="badge-no-leido">
                        <span class="punto-rojo"></span>
                        <span>Nuevo</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="cabecera-comentario-tarjeta">
                        <div class="info-usuario-comentario">
                            <div class="avatar-usuario" style="background-color: <?php echo $color_usuario; ?>">
                                <?php echo strtoupper(substr($comentario['usuario_nombre'], 0, 1)); ?>
                            </div>
                            <div class="datos-usuario">
                                <h4 class="nombre-usuario"><?php echo htmlspecialchars($comentario['usuario_nombre']); ?></h4>
                                <div class="calificacion-comentario">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $comentario['rating']): ?>
                                        <i class="fa-solid fa-star"></i>
                                        <?php else: ?>
                                        <i class="fa-regular fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span class="valor-rating"><?php echo $comentario['rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="meta-comentario">
                            <span class="fecha-comentario">
                                <i class="fa-solid fa-calendar"></i>
                                <?php echo date('d/m/Y H:i', strtotime($comentario['fecha'])); ?>
                            </span>
                            <?php if ($es_favorito): ?>
                            <span class="badge-favorito">
                                <i class="fa-solid fa-heart"></i> Agregó a favoritos
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="propiedad-comentario">
                        <i class="fa-solid fa-building"></i>
                        <strong><?php echo htmlspecialchars($comentario['propiedad_titulo']); ?></strong>
                        <a href="#" class="ver-propiedad" 
                           onclick="verPropiedadDesdeComentario(<?php echo $comentario['propiedad_id']; ?>)"
                           title="Ver esta propiedad">
                            <i class="fa-solid fa-external-link-alt"></i> Ver propiedad
                        </a>
                    </div>
                    
                    <div class="cuerpo-comentario-tarjeta">
                        <p class="texto-comentario">"<?php echo htmlspecialchars($comentario['comentario']); ?>"</p>
                        
                        <div class="acciones-comentario">
                            <button class="btn-marcar-leido" 
                                    onclick="marcarComentarioLeido(<?php echo $comentario['id']; ?>, this)">
                                <i class="fa-solid fa-check"></i> 
                                <?php echo $comentario['leido'] == 0 ? 'Marcar como leído' : 'Marcado como leído'; ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="estado-vacio-comentarios">
                    <div class="icono-estado-vacio">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <h3>Todavía no tenés comentarios</h3>
                    <p class="mensaje-estado-vacio">
                        Cuando los visitantes comenten y califiquen tus propiedades, 
                        aparecerán aquí como tarjetas.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- SECCIÓN NOTIFICACIONES (versión mejorada) -->
    <section id="sec-notificaciones" class="seccion-contenido oculto">
        <div class="cabecera-seccion">
            <h2>Historial de Notificaciones</h2>
            <p class="subtitulo-seccion">Todas las novedades de tus propiedades en un solo lugar</p>
            
            <div class="estadisticas-notificaciones">
                <div class="tarjeta-estadistica-notif">
                    <div class="icono-estadistica-notif" style="background: linear-gradient(135deg, #82b16d, #6a9a58);">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="info-estadistica-notif">
                        <h3><?php echo count($lista_notificaciones); ?></h3>
                        <p>Notificaciones totales</p>
                    </div>
                </div>
                
                <div class="tarjeta-estadistica-notif">
                    <div class="icono-estadistica-notif" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="info-estadistica-notif">
                        <h3><?php echo $notificaciones_no_leidas; ?></h3>
                        <p>No leídas</p>
                    </div>
                </div>
                
                <div class="tarjeta-estadistica-notif">
                    <div class="icono-estadistica-notif" style="background: linear-gradient(135deg, #2ecc71, #27ae60);">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="info-estadistica-notif">
                        <h3><?php echo count($lista_notificaciones) - $notificaciones_no_leidas; ?></h3>
                        <p>Leídas</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="barra-herramientas-notificaciones">
            <div class="buscador-notificaciones">
                <i class="fa-solid fa-search"></i>
                <input type="text" 
                       id="buscadorNotificaciones" 
                       placeholder="Buscar en notificaciones por título, mensaje o propiedad...">
                <button class="btn-limpiar-busqueda" onclick="limpiarBusquedaNotificaciones()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            
            <div class="filtros-notificaciones">
                <select id="filtroTipo" class="selector-filtro-notif" onchange="filtrarNotificaciones()">
                    <option value="todas">Todos los tipos</option>
                    <option value="aprobacion">✅ Aprobaciones</option>
                    <option value="rechazo">❌ Rechazos</option>
                    <option value="solicitud">📋 Solicitudes</option>
                    <option value="comentario">💬 Comentarios</option>
                    <option value="visita">👁️ Visitas</option>
                    <option value="general">🔔 Generales</option>
                </select>
                
                <select id="filtroEstado" class="selector-filtro-notif" onchange="filtrarNotificaciones()">
                    <option value="todas">Todas</option>
                    <option value="no-leidas">No leídas</option>
                    <option value="leidas">Leídas</option>
                </select>
                
                <button class="btn-accion-notif" onclick="marcarTodasLeidas()" title="Marcar todas como leídas">
                    <i class="fa-solid fa-check-double"></i> Marcar todas
                </button>
            </div>
        </div>

        <div class="contenedor-notificaciones-historial" id="contenedorNotificaciones">
            <?php if (!empty($lista_notificaciones)): ?>
                <?php foreach ($lista_notificaciones as $notificacion): ?>
                <?php 
                $icono = 'fa-bell';
                $color_clase = 'general';
                $color_fondo = '#3498db';
                
                switch($notificacion['tipo']) {
                    case 'aprobacion':
                        $icono = 'fa-check-circle';
                        $color_clase = 'aprobacion';
                        $color_fondo = '#2ecc71';
                        break;
                    case 'rechazo':
                        $icono = 'fa-times-circle';
                        $color_clase = 'rechazo';
                        $color_fondo = '#e74c3c';
                        break;
                    case 'solicitud':
                        $icono = 'fa-clock';
                        $color_clase = 'solicitud';
                        $color_fondo = '#f39c12';
                        break;
                    case 'comentario':
                        $icono = 'fa-comment';
                        $color_clase = 'comentario';
                        $color_fondo = '#9b59b6';
                        break;
                    case 'visita':
                        $icono = 'fa-eye';
                        $color_clase = 'visita';
                        $color_fondo = '#1abc9c';
                        break;
                }
                
                $fecha = new DateTime($notificacion['fecha']);
                $hoy = new DateTime();
                $diferencia = $hoy->diff($fecha);
                
                $tiempo_texto = '';
                if ($diferencia->days == 0) {
                    if ($diferencia->h == 0) {
                        if ($diferencia->i == 0) {
                            $tiempo_texto = 'Hace unos segundos';
                        } else {
                            $tiempo_texto = 'Hace ' . $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
                        }
                    } else {
                        $tiempo_texto = 'Hace ' . $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
                    }
                } elseif ($diferencia->days == 1) {
                    $tiempo_texto = 'Ayer, ' . $fecha->format('H:i');
                } elseif ($diferencia->days < 7) {
                    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    $tiempo_texto = $dias[$fecha->format('w')] . ', ' . $fecha->format('H:i');
                } elseif ($diferencia->days < 30) {
                    $semanas = floor($diferencia->days / 7);
                    $tiempo_texto = 'Hace ' . $semanas . ' semana' . ($semanas > 1 ? 's' : '');
                } elseif ($diferencia->y > 0) {
                    $tiempo_texto = $fecha->format('d/m/Y');
                } else {
                    $tiempo_texto = 'Hace ' . $diferencia->days . ' días';
                }
                
                $es_reciente = $diferencia->days == 0;
                ?>
                
                <div class="tarjeta-notificacion-historico <?php echo $notificacion['leida'] ? 'leida' : 'no-leida'; ?> 
                     <?php echo $color_clase; ?> <?php echo $es_reciente ? 'reciente' : ''; ?>"
                     data-id="<?php echo $notificacion['id']; ?>"
                     data-tipo="<?php echo $notificacion['tipo']; ?>"
                     data-leida="<?php echo $notificacion['leida']; ?>"
                     data-titulo="<?php echo htmlspecialchars(strtolower($notificacion['titulo'])); ?>"
                     data-mensaje="<?php echo htmlspecialchars(strtolower($notificacion['mensaje'])); ?>"
                     data-propiedad="<?php echo !empty($notificacion['propiedad_titulo']) ? htmlspecialchars(strtolower($notificacion['propiedad_titulo'])) : ''; ?>">
                    
                    <?php if (!$notificacion['leida']): ?>
                    <div class="indicador-no-leida">
                        <span class="punto-activo"></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="icono-notificacion-historico" style="background: <?php echo $color_fondo; ?>;">
                        <i class="fa-solid <?php echo $icono; ?>"></i>
                    </div>
                    
                    <div class="contenido-notificacion-historico">
                        <div class="cabecera-notificacion-historico">
                            <h4 class="titulo-notificacion"><?php echo htmlspecialchars($notificacion['titulo']); ?></h4>
                            <span class="tiempo-notificacion-historico" title="<?php echo $fecha->format('d/m/Y H:i:s'); ?>">
                                <i class="fa-solid fa-clock"></i> <?php echo $tiempo_texto; ?>
                            </span>
                        </div>
                        
                        <p class="mensaje-notificacion"><?php echo htmlspecialchars($notificacion['mensaje']); ?></p>
                        
                        <?php if (!empty($notificacion['propiedad_titulo'])): ?>
                        <div class="propiedad-notificacion-historico">
                            <i class="fa-solid fa-building"></i>
                            <span class="nombre-propiedad"><?php echo htmlspecialchars($notificacion['propiedad_titulo']); ?></span>
                            <?php if ($notificacion['propiedad_id']): ?>
                            <a href="javascript:void(0);" 
                               class="enlace-propiedad" 
                               onclick="verPropiedadDesdeNotificacion(<?php echo $notificacion['propiedad_id']; ?>)">
                                <i class="fa-solid fa-external-link-alt"></i> Ver propiedad
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="meta-info-notificacion">
                            <span class="badge-tipo" style="background: <?php echo $color_fondo; ?>20; color: <?php echo $color_fondo; ?>; border-color: <?php echo $color_fondo; ?>;">
                                <i class="fa-solid <?php echo $icono; ?>"></i>
                                <?php echo ucfirst($notificacion['tipo']); ?>
                            </span>
                            
                            <?php if ($es_reciente): ?>
                            <span class="badge-reciente">
                                <i class="fa-solid fa-bolt"></i> Reciente
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="acciones-notificacion-historico">
                        <?php if (!$notificacion['leida']): ?>
                        <button class="btn-marcar-leida" 
                                onclick="marcarNotificacionLeida(<?php echo $notificacion['id']; ?>, this)"
                                title="Marcar como leída">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn-marcar-no-leida" 
                                onclick="marcarNotificacionNoLeida(<?php echo $notificacion['id']; ?>, this)"
                                title="Marcar como no leída">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="estado-vacio-notificaciones">
                    <div class="icono-estado-vacio">
                        <i class="fa-solid fa-bell-slash"></i>
                    </div>
                    <h3>No tenés notificaciones aún</h3>
                    <p class="mensaje-estado-vacio">
                        Cuando tengas novedades sobre tus propiedades, aparecerán aquí como un historial permanente.
                    </p>
                    <div class="sugerencias-estado-vacio">
                        <p><i class="fa-solid fa-lightbulb"></i> Las notificaciones se guardarán automáticamente cuando:</p>
                        <ul>
                            <li>Enviés una solicitud de publicación</li>
                            <li>El administrador apruebe o rechace una propiedad</li>
                            <li>Alguien comente o califique tus propiedades</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($lista_notificaciones)): ?>
        <div class="pie-notificaciones">
            <div class="contador-notificaciones">
                <span id="contadorMostradas"><?php echo count($lista_notificaciones); ?></span> de 
                <span><?php echo count($lista_notificaciones); ?></span> notificaciones
            </div>
            <div class="info-almacenamiento">
                <i class="fa-solid fa-database"></i>
                <small>Las notificaciones se almacenan permanentemente como historial</small>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>

<!-- MODALES -->
<div class="modal" id="modalDetallesPropiedad" style="display:none;">
    <div class="modal-contenido modal-detalles">
        <div class="modal-header">
            <h3><i class="fa-solid fa-building"></i> Detalles de la Propiedad</h3>
            <span class="cerrar" onclick="cerrarModalDetalles()">&times;</span>
        </div>
        <div class="modal-body" id="detallesPropiedadContent"></div>
    </div>
</div>

<div class="modal" id="modalMapaPropiedad" style="display:none;">
    <div class="modal-contenido modal-mapa">
        <div class="modal-header">
            <h3><i class="fa-solid fa-map"></i> Ubicación de la propiedad</h3>
            <span class="cerrar" onclick="cerrarModalMapa()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="mapa-modal" style="height: 400px; border-radius: 8px;"></div>
            <div class="info-mapa-modal">
                <h4 id="titulo-mapa-modal"></h4>
                <p id="direccion-mapa-modal"></p>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" class="toast-notification"></div>

<div class="modal-error" id="modalErrorGlobal" style="display:none;">
    <div class="contenido-modal-error">
        <h3><i class="fa-solid fa-exclamation-triangle"></i> Error</h3>
        <div id="errorMessage"></div>
        <button onclick="document.getElementById('modalErrorGlobal').style.display='none'">Aceptar</button>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ============================================
// DATOS GLOBALES
// ============================================
const datosUsuario = {
    id: <?php echo $id_propietario; ?>,
    nombre: "<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>"
};

let mapaModal = null;
let filtroTipoNotif = 'todas';
let filtroEstadoNotif = 'todas';
let terminoBusquedaNotif = '';

// ============================================
// FUNCIONES DE MAPA
// ============================================
function verMapaPropiedad(id, lat, lng, direccion) {
    const modal = document.getElementById('modalMapaPropiedad');
    const titulo = document.getElementById('titulo-mapa-modal');
    const direccionElement = document.getElementById('direccion-mapa-modal');
    
    modal.style.display = 'block';
    titulo.textContent = 'Propiedad ID: ' + id;
    direccionElement.textContent = direccion;
    
    if (!mapaModal) {
        mapaModal = L.map('mapa-modal').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(mapaModal);
    } else {
        mapaModal.setView([lat, lng], 15);
    }
    
    mapaModal.eachLayer(function(layer) {
        if (layer instanceof L.Marker) {
            mapaModal.removeLayer(layer);
        }
    });
    
    L.marker([lat, lng])
        .addTo(mapaModal)
        .bindPopup('<b>Propiedad</b><br>' + direccion)
        .openPopup();
}

function cerrarModalMapa() {
    document.getElementById('modalMapaPropiedad').style.display = 'none';
}

// ============================================
// FUNCIONES DE NOTIFICACIONES
// ============================================
function marcarNotificacionLeida(id, boton) {
    fetch('../database/marcar_notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'marcar_leida',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tarjeta = boton.closest('.tarjeta-notificacion-historico');
            if (tarjeta) {
                tarjeta.classList.remove('no-leida');
                tarjeta.classList.add('leida');
                tarjeta.setAttribute('data-leida', '1');
                
                const indicador = tarjeta.querySelector('.indicador-no-leida');
                if (indicador) indicador.remove();
                
                const nuevoBoton = document.createElement('button');
                nuevoBoton.className = 'btn-marcar-no-leida';
                nuevoBoton.title = 'Marcar como no leída';
                nuevoBoton.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
                nuevoBoton.onclick = function() {
                    marcarNotificacionNoLeida(id, this);
                };
                
                boton.parentNode.replaceChild(nuevoBoton, boton);
                actualizarContadoresNotificaciones();
            }
            mostrarToast('✅ Notificación marcada como leída', 'success');
        } else {
            mostrarToast('❌ Error al marcar como leída', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarToast('❌ Error de conexión', 'error');
    });
}

function marcarNotificacionNoLeida(id, boton) {
    fetch('../database/marcar_notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'marcar_no_leida',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tarjeta = boton.closest('.tarjeta-notificacion-historico');
            if (tarjeta) {
                tarjeta.classList.remove('leida');
                tarjeta.classList.add('no-leida');
                tarjeta.setAttribute('data-leida', '0');
                
                const indicadorHTML = `
                    <div class="indicador-no-leida">
                        <span class="punto-activo"></span>
                    </div>
                `;
                tarjeta.insertAdjacentHTML('afterbegin', indicadorHTML);
                
                const nuevoBoton = document.createElement('button');
                nuevoBoton.className = 'btn-marcar-leida';
                nuevoBoton.title = 'Marcar como leída';
                nuevoBoton.innerHTML = '<i class="fa-solid fa-check"></i>';
                nuevoBoton.onclick = function() {
                    marcarNotificacionLeida(id, this);
                };
                
                boton.parentNode.replaceChild(nuevoBoton, boton);
                actualizarContadoresNotificaciones();
            }
            mostrarToast('🔄 Notificación marcada como no leída', 'success');
        } else {
            mostrarToast('❌ Error al marcar como no leída', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarToast('❌ Error de conexión', 'error');
    });
}

function marcarTodasLeidas() {
    if (!confirm('¿Estás seguro de marcar todas las notificaciones como leídas?')) {
        return;
    }
    
    const btn = document.querySelector('.btn-accion-notif');
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';
    btn.disabled = true;
    
    fetch('../database/marcar_notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            accion: 'marcar_todas_leidas'
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
        
        if (data.success) {
            const notificaciones = document.querySelectorAll('.tarjeta-notificacion-historico');
            
            notificaciones.forEach(notif => {
                notif.classList.remove('no-leida');
                notif.classList.add('leida');
                notif.setAttribute('data-leida', '1');
                
                const indicador = notif.querySelector('.indicador-no-leida');
                if (indicador) indicador.remove();
                
                const contenedorAcciones = notif.querySelector('.acciones-notificacion-historico');
                const id = notif.getAttribute('data-id');
                
                const nuevoBoton = document.createElement('button');
                nuevoBoton.className = 'btn-marcar-no-leida';
                nuevoBoton.title = 'Marcar como no leída';
                nuevoBoton.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
                nuevoBoton.onclick = function() {
                    marcarNotificacionNoLeida(id, this);
                };
                
                contenedorAcciones.innerHTML = '';
                contenedorAcciones.appendChild(nuevoBoton);
            });
            
            actualizarContadoresNotificaciones();
            mostrarToast('✅ Todas las notificaciones marcadas como leídas', 'success');
        } else {
            mostrarToast('❌ Error al marcar todas', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
        mostrarToast('❌ Error de conexión', 'error');
    });
}

function actualizarContadoresNotificaciones() {
    const noLeidas = document.querySelectorAll('.tarjeta-notificacion-historico.no-leida').length;
    const total = document.querySelectorAll('.tarjeta-notificacion-historico').length;
    
    const contadorNoLeidas = document.querySelector('.tarjeta-estadistica-notif:nth-child(2) h3');
    const contadorLeidas = document.querySelector('.tarjeta-estadistica-notif:nth-child(3) h3');
    
    if (contadorNoLeidas) contadorNoLeidas.textContent = noLeidas;
    if (contadorLeidas) contadorLeidas.textContent = total - noLeidas;
    
    const contadorMostradas = document.getElementById('contadorMostradas');
    if (contadorMostradas) contadorMostradas.textContent = total;
}

function verPropiedadDesdeNotificacion(idPropiedad) {
    mostrarSeccion('propiedades');
    
    setTimeout(() => {
        const tarjetas = document.querySelectorAll('.tarjeta-propiedad');
        let propiedadEncontrada = null;
        
        tarjetas.forEach(tarjeta => {
            const botones = tarjeta.querySelectorAll('button');
            botones.forEach(boton => {
                const onclick = boton.getAttribute('onclick') || '';
                if (onclick.includes(idPropiedad.toString())) {
                    propiedadEncontrada = tarjeta;
                }
            });
        });
        
        if (propiedadEncontrada) {
            const botonVer = propiedadEncontrada.querySelector('.boton-ver');
            if (botonVer) botonVer.click();
        } else {
            mostrarToast('⚠️ Propiedad no encontrada', 'warning');
        }
    }, 500);
}

function limpiarBusquedaNotificaciones() {
    const buscador = document.getElementById('buscadorNotificaciones');
    if (buscador) {
        buscador.value = '';
        terminoBusquedaNotif = '';
        aplicarFiltrosNotificaciones();
        buscador.focus();
    }
}

function aplicarFiltrosNotificaciones() {
    const notificaciones = document.querySelectorAll('.tarjeta-notificacion-historico');
    let contadorVisibles = 0;
    
    notificaciones.forEach(notif => {
        const tipo = notif.getAttribute('data-tipo');
        const leida = notif.getAttribute('data-leida');
        const titulo = notif.getAttribute('data-titulo') || '';
        const mensaje = notif.getAttribute('data-mensaje') || '';
        const propiedad = notif.getAttribute('data-propiedad') || '';
        
        const pasaTipo = filtroTipoNotif === 'todas' || tipo === filtroTipoNotif;
        const pasaEstado = filtroEstadoNotif === 'todas' || 
                          (filtroEstadoNotif === 'no-leidas' && leida === '0') ||
                          (filtroEstadoNotif === 'leidas' && leida === '1');
        const pasaBusqueda = !terminoBusquedaNotif || 
                            titulo.includes(terminoBusquedaNotif) ||
                            mensaje.includes(terminoBusquedaNotif) ||
                            propiedad.includes(terminoBusquedaNotif);
        
        if (pasaTipo && pasaEstado && pasaBusqueda) {
            notif.style.display = 'flex';
            contadorVisibles++;
        } else {
            notif.style.display = 'none';
        }
    });
    
    const contadorMostradas = document.getElementById('contadorMostradas');
    if (contadorMostradas) contadorMostradas.textContent = contadorVisibles;
}

function filtrarNotificaciones() {
    const tipoSelect = document.getElementById('filtroTipo');
    const estadoSelect = document.getElementById('filtroEstado');
    
    if (tipoSelect) filtroTipoNotif = tipoSelect.value;
    if (estadoSelect) filtroEstadoNotif = estadoSelect.value;
    
    aplicarFiltrosNotificaciones();
}

// ============================================
// FUNCIONES PARA PROPIEDADES
// ============================================
function mostrarSeccion(seccion) {
    document.querySelectorAll('.seccion-contenido').forEach(s => {
        s.classList.remove('activa');
        s.classList.add('oculto');
    });
    
    document.getElementById(`sec-${seccion}`).classList.add('activa');
    document.getElementById(`sec-${seccion}`).classList.remove('oculto');
    
    const titulos = {
        'inicio': 'Panel de Control',
        'formulario': 'Agregar Propiedad',
        'propiedades': 'Mis Propiedades',
        'comentarios': 'Comentarios',
        'notificaciones': 'Notificaciones'
    };
    
    const tituloPagina = document.getElementById('tituloPagina');
    if (tituloPagina && titulos[seccion]) {
        tituloPagina.textContent = titulos[seccion];
    }
    
    document.querySelectorAll('.enlace-navegacion').forEach(enlace => {
        enlace.parentElement.classList.remove('activo');
    });
    
    const enlaceActivo = document.getElementById(`nav-${seccion}`);
    if (enlaceActivo) enlaceActivo.parentElement.classList.add('activo');
}

function filtrarPropiedadesTarjetas() {
    const filtroEstado = document.getElementById('filtroEstadoTarjetas').value;
    const terminoBusqueda = document.getElementById('buscadorPropiedades').value.toLowerCase();
    const tarjetas = document.querySelectorAll('.tarjeta-propiedad');
    
    let contador = 0;
    
    tarjetas.forEach(tarjeta => {
        const estado = tarjeta.getAttribute('data-estado');
        const titulo = tarjeta.getAttribute('data-titulo');
        const descripcion = tarjeta.getAttribute('data-descripcion');
        const direccion = tarjeta.getAttribute('data-direccion');
        
        const pasaEstado = filtroEstado === 'todas' || estado === filtroEstado;
        const pasaBusqueda = !terminoBusqueda || 
                            titulo.includes(terminoBusqueda) ||
                            descripcion.includes(terminoBusqueda) ||
                            direccion.includes(terminoBusqueda);
        
        if (pasaEstado && pasaBusqueda) {
            tarjeta.style.display = 'flex';
            contador++;
        } else {
            tarjeta.style.display = 'none';
        }
    });
    
    const contadorSpan = document.getElementById('contadorPropiedadesMostradas');
    if (contadorSpan) contadorSpan.textContent = contador;
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================
function mostrarToast(mensaje, tipo = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    
    const toast = document.createElement('div');
    toast.className = `toast-content toast-${tipo}`;
    toast.innerHTML = `
        <i class="fa-solid ${tipo === 'success' ? 'fa-check-circle' : tipo === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${mensaje}</span>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function verPropiedadPublicada(idPropiedad) {
    mostrarToast('✅ Propiedad publicada y visible para los visitantes', 'success');
}

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Sistema de RentNono inicializado');
    
    // Configurar navegación
    document.querySelectorAll('.enlace-navegacion').forEach(enlace => {
        enlace.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href) {
                const seccion = href.replace('#', '');
                mostrarSeccion(seccion);
            }
        });
    });
    
    // Configurar buscador de notificaciones
    const buscadorNotif = document.getElementById('buscadorNotificaciones');
    if (buscadorNotif) {
        buscadorNotif.addEventListener('input', function() {
            terminoBusquedaNotif = this.value.toLowerCase().trim();
            aplicarFiltrosNotificaciones();
        });
    }
    
    // Configurar filtros de notificaciones
    const filtroTipo = document.getElementById('filtroTipo');
    const filtroEstado = document.getElementById('filtroEstado');
    
    if (filtroTipo) {
        filtroTipo.addEventListener('change', filtrarNotificaciones);
    }
    if (filtroEstado) {
        filtroEstado.addEventListener('change', filtrarNotificaciones);
    }
    
    // Configurar buscador de propiedades
    const buscadorProp = document.getElementById('buscadorPropiedades');
    if (buscadorProp) {
        buscadorProp.addEventListener('input', filtrarPropiedadesTarjetas);
    }
    
    // Configurar filtro de propiedades
    const filtroProp = document.getElementById('filtroEstadoTarjetas');
    if (filtroProp) {
        filtroProp.addEventListener('change', filtrarPropiedadesTarjetas);
    }
    
    // Cerrar modales con click fuera
    window.onclick = function(event) {
        const modalMapa = document.getElementById('modalMapaPropiedad');
        const modalDetalles = document.getElementById('modalDetallesPropiedad');
        
        if (event.target == modalMapa) {
            cerrarModalMapa();
        }
        if (event.target == modalDetalles) {
            cerrarModalDetalles();
        }
    };
});
</script>

</body>
</html>