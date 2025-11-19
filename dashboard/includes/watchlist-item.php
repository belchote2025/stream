<?php
if (!function_exists('watchlist_time_elapsed')) {
    function watchlist_time_elapsed($datetime) {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$timestamp) {
            return '';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) return 'Hace segundos';
        if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
        if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' h';
        if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' d';
        return date('d/m/Y', $timestamp);
    }
}

$baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$defaultThumb = $baseUrl . '/assets/img/default-poster.svg';
$thumbnail = !empty($item['thumbnail_url']) ? $item['thumbnail_url'] : $defaultThumb;
$contentId = (int)($item['content_id'] ?? 0);
$title = $item['title'] ?? 'Contenido';
$contentType = $item['content_type'] ?? 'movie';
$releaseYear = $item['release_year'] ?? '';
$ageRating = $item['age_rating'] ?? 'NR';
$duration = $item['duration'] ?? null;
$addedAt = $item['added_at'] ?? ($item['created_at'] ?? '');
$lastWatched = $item['last_watched'] ?? null;
$totalEpisodes = (int)($item['total_episodes'] ?? 0);
$completedEpisodes = (int)($item['completed_episodes'] ?? 0);
$isCompleted = !empty($item['is_completed']);

$progress = 0;
if ($contentType === 'movie') {
    if ($isCompleted) {
        $progress = 100;
    } elseif (isset($item['progress'])) {
        $progress = max(0, min(100, (float)$item['progress']));
    }
} else {
    if ($totalEpisodes > 0) {
        $progress = max(0, min(100, ($completedEpisodes / $totalEpisodes) * 100));
    } elseif ($isCompleted) {
        $progress = 100;
    }
}

$typeLabel = $contentType === 'movie' ? 'Película' : 'Serie';
$detailUrl = $baseUrl . '/content-detail.php?id=' . $contentId;
$watchUrl = $baseUrl . '/watch.php?id=' . $contentId;
?>

<div class="col watchlist-item"
     data-title="<?php echo htmlspecialchars(strtolower($title)); ?>"
     data-added="<?php echo htmlspecialchars($addedAt); ?>"
     data-release-year="<?php echo htmlspecialchars($releaseYear); ?>"
     data-progress="<?php echo number_format($progress, 2, '.', ''); ?>">
    <div class="card watchlist-card h-100">
        <div class="watchlist-thumb">
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="stretched-link"></a>
            <img src="<?php echo htmlspecialchars($thumbnail); ?>"
                 alt="<?php echo htmlspecialchars($title); ?>"
                 onerror="this.src='<?php echo $defaultThumb; ?>'">
            <?php if ($progress > 0): ?>
                <div class="watchlist-progress">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo (int)$progress; ?>%;"
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span><?php echo (int)$progress; ?>%</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <span class="badge <?php echo $contentType === 'movie' ? 'bg-danger' : 'bg-primary'; ?>">
                <?php echo $typeLabel; ?>
            </span>
            <h5 class="card-title text-truncate mt-2 mb-1"><?php echo htmlspecialchars($title); ?></h5>
            <p class="card-meta mb-2">
                <?php if ($releaseYear): ?>
                    <span><?php echo htmlspecialchars($releaseYear); ?></span> ·
                <?php endif; ?>
                <span><?php echo htmlspecialchars($ageRating); ?></span>
                <?php if ($duration && $contentType === 'movie'): ?>
                    · <span><?php echo (int)$duration; ?> min</span>
                <?php endif; ?>
            </p>
            <?php if ($contentType !== 'movie' && $totalEpisodes > 0): ?>
                <small class="text-muted">
                    <?php echo $completedEpisodes; ?> / <?php echo $totalEpisodes; ?> episodios
                </small>
            <?php elseif ($lastWatched): ?>
                <small class="text-muted">
                    Último visto: <?php echo htmlspecialchars(watchlist_time_elapsed($lastWatched)); ?>
                </small>
            <?php endif; ?>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <a href="<?php echo htmlspecialchars($watchUrl); ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-play"></i> Reproducir
            </a>
            <button class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#removeFromListModal"
                    data-content-id="<?php echo $contentId; ?>"
                    data-content-type="<?php echo htmlspecialchars($contentType); ?>"
                    data-content-title="<?php echo htmlspecialchars($title); ?>">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    </div>
</div>

