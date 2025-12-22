<?php
/**
 * Script para marcar contenido como destacado
 * Acceder a: /api/content/mark-featured.php
 * 
 * Este script marca automáticamente los elementos más populares como destacados
 */

// Establecer headers ANTES de cualquier output
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Limpiar buffer
ob_clean();

// Incluir configuración
require_once __DIR__ . '/../../includes/config.php';

$result = [
    'success' => false,
    'message' => '',
    'updated' => 0,
    'details' => []
];

try {
    // Conectar a la base de datos
    $db = getDbConnection();
    
    // Paso 1: Desmarcar todo como destacado primero
    $stmt = $db->prepare("UPDATE content SET is_featured = 0");
    $stmt->execute();
    $result['details']['unmarked'] = $stmt->rowCount();
    
    // Paso 2: Marcar las 5 películas más populares como destacadas
    $stmt = $db->prepare("
        UPDATE content 
        SET is_featured = 1 
        WHERE type = 'movie' 
        AND id IN (
            SELECT id FROM (
                SELECT id FROM content 
                WHERE type = 'movie' 
                ORDER BY views DESC, rating DESC, release_year DESC 
                LIMIT 5
            ) AS temp
        )
    ");
    $stmt->execute();
    $moviesFeatured = $stmt->rowCount();
    $result['details']['movies_featured'] = $moviesFeatured;
    
    // Paso 3: Marcar las 5 series más populares como destacadas
    $stmt = $db->prepare("
        UPDATE content 
        SET is_featured = 1 
        WHERE type = 'series' 
        AND id IN (
            SELECT id FROM (
                SELECT id FROM content 
                WHERE type = 'series' 
                ORDER BY views DESC, rating DESC, release_year DESC 
                LIMIT 5
            ) AS temp
        )
    ");
    $stmt->execute();
    $seriesFeatured = $stmt->rowCount();
    $result['details']['series_featured'] = $seriesFeatured;
    
    // Paso 4: Si no hay suficientes con views, usar los más recientes con mejor rating
    $totalFeatured = $moviesFeatured + $seriesFeatured;
    if ($totalFeatured < 5) {
        $needed = 5 - $totalFeatured;
        
        $stmt = $db->prepare("
            UPDATE content 
            SET is_featured = 1 
            WHERE is_featured = 0 
            AND id IN (
                SELECT id FROM (
                    SELECT id FROM content 
                    WHERE is_featured = 0 
                    AND rating IS NOT NULL 
                    AND rating >= 7.0
                    ORDER BY rating DESC, release_year DESC 
                    LIMIT :needed
                ) AS temp
            )
        ");
        $stmt->bindValue(':needed', $needed, PDO::PARAM_INT);
        $stmt->execute();
        $result['details']['by_rating'] = $stmt->rowCount();
    }
    
    // Obtener el total de elementos destacados
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE is_featured = 1");
    $total = $stmt->fetch()['total'];
    
    // Obtener lista de elementos destacados
    $stmt = $db->query("
        SELECT id, title, type, rating, views 
        FROM content 
        WHERE is_featured = 1 
        ORDER BY views DESC, rating DESC
    ");
    $featuredItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result['success'] = true;
    $result['message'] = 'Contenido marcado como destacado exitosamente';
    $result['updated'] = $total;
    $result['featured_items'] = $featuredItems;
    
} catch (PDOException $e) {
    $result['message'] = 'Error al actualizar: ' . $e->getMessage();
    $result['error'] = $e->getMessage();
    error_log('Error en mark-featured.php: ' . $e->getMessage());
} catch (Exception $e) {
    $result['message'] = 'Error: ' . $e->getMessage();
    $result['error'] = $e->getMessage();
    error_log('Error en mark-featured.php: ' . $e->getMessage());
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;




