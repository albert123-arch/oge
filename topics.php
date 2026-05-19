<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/authentication/auth.php';

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$topics = [];
$errorMessage = '';

try {
    $sql = "
        SELECT
            t.*,
            COUNT(q.id) AS question_count
        FROM oge_topics t
        LEFT JOIN oge_questions q ON q.topic_id = t.id AND q.is_published = 1
        WHERE t.is_active = 1
        GROUP BY t.id
        ORDER BY t.sort_order ASC, t.title ASC
    ";
    $result = $mysqli->query($sql);
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
    $result->free();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить темы.';
}

$page_title = 'Темы ОГЭ по математике';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Темы ОГЭ по математике</h1>
        <p class="text-muted mb-0">Разделы подготовки: теория, задания и практика.</p>
    </div>
    <a class="btn btn-outline-primary" href="/practice.php">Перейти к практике</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<?php if (empty($topics)): ?>
    <div class="alert alert-info">Темы пока не добавлены.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($topics as $topic): ?>
            <?php $topicUrl = !empty($topic['slug']) ? '/topic.php?slug=' . urlencode($topic['slug']) : '/topic.php?id=' . (int)$topic['id']; ?>
            <div class="col-md-6 col-xl-4">
                <a class="text-decoration-none text-reset" href="<?= e($topicUrl) ?>">
                    <article class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <div class="fs-3 fw-bold text-primary"><?= e($topic['icon'] ?: '∑') ?></div>
                                <span class="badge text-bg-light"><?= (int)$topic['question_count'] ?> задач</span>
                            </div>
                            <h2 class="h5 mb-2"><?= e($topic['title']) ?></h2>
                            <?php if (!empty($topic['short_description'])): ?>
                                <p class="text-muted mb-0"><?= e($topic['short_description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
