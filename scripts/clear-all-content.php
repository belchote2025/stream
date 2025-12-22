<?php
/**
 * CLI: Elimina todo el contenido (películas y series) de la base de datos.
 * 
 * Este script elimina:
 * - Todas las películas y series (tabla content)
 * - Todos los episodios (se eliminan automáticamente por CASCADE)
 * - Todas las relaciones de géneros (se eliminan automáticamente por CASCADE)
 * - Historial de reproducción relacionado (se elimina automáticamente)
 * - Contenido en listas (se elimina automáticamente por CASCADE)
 * 
 * Uso:
 *   php scripts/clear-all-content.php
 *   php scripts/clear-all-content.php --confirm
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

// Verificar que se ejecute desde CLI
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

$options = getopt('', ['confirm::']);
$confirmed = isset($options['confirm']);

$db = getDbConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Obtener estadísticas antes de eliminar
fwrite(STDOUT, "\n=== ESTADÍSTICAS ACTUALES ===\n");
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM content");
    $totalContent = (int)$stmt->fetch()['total'];
    fwrite(STDOUT, "Total de contenido (películas + series): {$totalContent}\n");
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE type = 'movie'");
    $totalMovies = (int)$stmt->fetch()['total'];
    fwrite(STDOUT, "Películas: {$totalMovies}\n");
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE type = 'series'");
    $totalSeries = (int)$stmt->fetch()['total'];
    fwrite(STDOUT, "Series: {$totalSeries}\n");
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM episodes");
    $totalEpisodes = (int)$stmt->fetch()['total'];
    fwrite(STDOUT, "Episodios: {$totalEpisodes}\n");
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM playback_history");
    $totalHistory = (int)$stmt->fetch()['total'];
    fwrite(STDOUT, "Registros de historial: {$totalHistory}\n");
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM content_genres");
    $totalGenres = (int)$stmt->fetch()['total'];
    fwrite(STDOUT, "Relaciones de géneros: {$totalGenres}\n");
    
} catch (PDOException $e) {
    fwrite(STDERR, "Error al obtener estadísticas: " . $e->getMessage() . "\n");
    exit(1);
}

if ($totalContent === 0) {
    fwrite(STDOUT, "\n✅ La base de datos ya está vacía. No hay nada que eliminar.\n");
    exit(0);
}

// Confirmación
if (!$confirmed) {
    fwrite(STDOUT, "\n⚠️  ADVERTENCIA: Este script eliminará TODO el contenido de la base de datos.\n");
    fwrite(STDOUT, "Esto incluye:\n");
    fwrite(STDOUT, "  - {$totalMovies} películas\n");
    fwrite(STDOUT, "  - {$totalSeries} series\n");
    fwrite(STDOUT, "  - {$totalEpisodes} episodios\n");
    fwrite(STDOUT, "  - {$totalHistory} registros de historial\n");
    fwrite(STDOUT, "  - {$totalGenres} relaciones de géneros\n");
    fwrite(STDOUT, "\nPara confirmar, ejecuta:\n");
    fwrite(STDOUT, "  php scripts/clear-all-content.php --confirm\n");
    fwrite(STDOUT, "\nO presiona Ctrl+C para cancelar.\n");
    exit(0);
}

// Proceder con la eliminación
fwrite(STDOUT, "\n🗑️  Eliminando todo el contenido...\n");

try {
    $db->beginTransaction();
    
    // Eliminar historial de reproducción primero (puede tener FK con SET NULL)
    $stmt = $db->prepare("DELETE FROM playback_history WHERE content_id IS NOT NULL");
    $stmt->execute();
    $deletedHistory = $stmt->rowCount();
    fwrite(STDOUT, "  ✓ Eliminados {$deletedHistory} registros de historial\n");
    
    // Eliminar contenido (esto activará CASCADE en episodios, content_genres, playlist_content)
    $stmt = $db->prepare("DELETE FROM content");
    $stmt->execute();
    $deletedContent = $stmt->rowCount();
    fwrite(STDOUT, "  ✓ Eliminadas {$deletedContent} películas/series\n");
    
    // Verificar que los episodios se eliminaron (por CASCADE)
    $stmt = $db->query("SELECT COUNT(*) as total FROM episodes");
    $remainingEpisodes = (int)$stmt->fetch()['total'];
    if ($remainingEpisodes > 0) {
        // Si quedan episodios huérfanos, eliminarlos manualmente
        $stmt = $db->prepare("DELETE FROM episodes");
        $stmt->execute();
        fwrite(STDOUT, "  ✓ Eliminados {$remainingEpisodes} episodios huérfanos\n");
    } else {
        fwrite(STDOUT, "  ✓ Episodios eliminados automáticamente (CASCADE)\n");
    }
    
    // Verificar relaciones de géneros
    $stmt = $db->query("SELECT COUNT(*) as total FROM content_genres");
    $remainingGenres = (int)$stmt->fetch()['total'];
    if ($remainingGenres > 0) {
        $stmt = $db->prepare("DELETE FROM content_genres");
        $stmt->execute();
        fwrite(STDOUT, "  ✓ Eliminadas {$remainingGenres} relaciones de géneros\n");
    } else {
        fwrite(STDOUT, "  ✓ Relaciones de géneros eliminadas automáticamente (CASCADE)\n");
    }
    
    $db->commit();
    
    fwrite(STDOUT, "\n✅ Limpieza completada exitosamente.\n");
    fwrite(STDOUT, "\n📊 RESUMEN:\n");
    fwrite(STDOUT, "  - Contenido eliminado: {$deletedContent}\n");
    fwrite(STDOUT, "  - Historial eliminado: {$deletedHistory}\n");
    fwrite(STDOUT, "  - Episodios eliminados: {$totalEpisodes}\n");
    fwrite(STDOUT, "  - Relaciones eliminadas: {$totalGenres}\n");
    fwrite(STDOUT, "\n💡 Ahora puedes ejecutar la actualización automática desde el panel de administración\n");
    fwrite(STDOUT, "   o ejecutar: php scripts/fetch-new-content.php --type=movie --limit=30\n");
    
} catch (PDOException $e) {
    $db->rollBack();
    fwrite(STDERR, "\n❌ Error durante la eliminación: " . $e->getMessage() . "\n");
    fwrite(STDERR, "   Se revirtieron todos los cambios.\n");
    exit(1);
}








