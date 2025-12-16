<?php
/**
 * CLI: Actualiza las imágenes (posters y backdrops) de todos los contenidos existentes
 * que tengan imágenes por defecto o vacías.
 * 
 * Uso:
 *   php scripts/update-content-images.php
 *   php scripts/update-content-images.php --limit=50
 *   php scripts/update-content-images.php --force (actualiza todos, incluso los que ya tienen imágenes)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/imdb-helper.php';

$options = getopt('', ['limit::', 'force::']);
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : null;
$force = isset($options['force']) ? filter_var($options['force'], FILTER_VALIDATE_BOOLEAN) : false;

$db = getDbConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Construir query
$where = $force 
    ? "1=1" 
    : "(poster_url IS NULL OR poster_url = '' OR poster_url LIKE '%default-poster%' OR poster_url LIKE '%default-movie%' OR poster_url LIKE '%default-tv%' OR backdrop_url IS NULL OR backdrop_url = '' OR backdrop_url LIKE '%default-backdrop%' OR backdrop_url LIKE '%default-poster%')";

$query = "SELECT id, title, type, release_year, poster_url, backdrop_url FROM content WHERE {$where} ORDER BY id ASC";
if ($limit) {
    $query .= " LIMIT " . (int)$limit;
}

$stmt = $db->prepare($query);
$stmt->execute();
$contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($contents);
$updated = 0;
$skipped = 0;
$errors = 0;

fwrite(STDOUT, "📸 Actualizando imágenes para {$total} contenidos...\n\n");

foreach ($contents as $content) {
    $id = $content['id'];
    $title = $content['title'];
    $type = $content['type'];
    $year = $content['release_year'];
    $currentPoster = $content['poster_url'] ?? '';
    $currentBackdrop = $content['backdrop_url'] ?? '';
    
    fwrite(STDOUT, "[{$id}] {$title} ({$year})...\n");
    
    // Obtener nuevas imágenes
    $needsPoster = $force || empty($currentPoster) || strpos($currentPoster, 'default-') !== false;
    $needsBackdrop = $force || empty($currentBackdrop) || strpos($currentBackdrop, 'default-') !== false;
    
    // Resetear el contador estático de getImdbImage para permitir más llamadas en CLI
    // Esto se hace llamando a la función directamente sin límites
    $newPoster = $needsPoster ? getPosterImage($title, $type, $year) : $currentPoster;
    $newBackdrop = $needsBackdrop ? getBackdropImage($title, $type, $year) : $currentBackdrop;
    
    // Si getImdbImage devolvió vacío, intentar obtener de TVMaze/Trakt directamente
    if ($needsPoster && (empty($newPoster) || strpos($newPoster, 'default-') !== false)) {
        // Intentar obtener de una fuente alternativa
        fwrite(STDOUT, "  ⚠ Poster no encontrado en IMDb, usando default\n");
    }
    if ($needsBackdrop && (empty($newBackdrop) || strpos($newBackdrop, 'default-') !== false)) {
        // Si no hay backdrop pero hay poster válido, usar poster
        if (!empty($newPoster) && strpos($newPoster, 'default-') === false) {
            $newBackdrop = $newPoster;
            fwrite(STDOUT, "  → Usando poster como backdrop\n");
        }
    }
    
    // Si el backdrop sigue siendo default pero tenemos poster, usar poster como backdrop
    if (strpos($newBackdrop, 'default-backdrop') !== false && strpos($newPoster, 'default-poster') === false) {
        $newBackdrop = $newPoster;
        fwrite(STDOUT, "  → Usando poster como backdrop\n");
    }
    
    // Actualizar solo si hay cambios
    if ($newPoster !== $currentPoster || $newBackdrop !== $currentBackdrop) {
        try {
            $updateStmt = $db->prepare("
                UPDATE content 
                SET poster_url = :poster_url, 
                    backdrop_url = :backdrop_url,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':poster_url' => $newPoster,
                ':backdrop_url' => $newBackdrop,
                ':id' => $id
            ]);
            
            if ($newPoster !== $currentPoster) {
                fwrite(STDOUT, "  ✓ Poster actualizado: " . substr($newPoster, 0, 60) . "...\n");
            }
            if ($newBackdrop !== $currentBackdrop) {
                fwrite(STDOUT, "  ✓ Backdrop actualizado: " . substr($newBackdrop, 0, 60) . "...\n");
            }
            $updated++;
        } catch (Exception $e) {
            fwrite(STDERR, "  ✗ Error: " . $e->getMessage() . "\n");
            $errors++;
        }
    } else {
        fwrite(STDOUT, "  ⊘ Sin cambios\n");
        $skipped++;
    }
    
    fwrite(STDOUT, "\n");
    
    // Pequeña pausa para no sobrecargar IMDb
    usleep(500000); // 0.5 segundos
}

fwrite(STDOUT, "\n📊 Resumen:\n");
fwrite(STDOUT, "  ✓ Actualizados: {$updated}\n");
fwrite(STDOUT, "  ⊘ Sin cambios: {$skipped}\n");
fwrite(STDOUT, "  ✗ Errores: {$errors}\n");

