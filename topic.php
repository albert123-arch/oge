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

$slug = trim((string)($_GET['slug'] ?? ''));
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$topic = null;
$questions = [];
$errorMessage = '';

try {
    if ($slug !== '') {
        $stmt = $mysqli->prepare("SELECT * FROM oge_topics WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('s', $slug);
    } else {
        $stmt = $mysqli->prepare("SELECT * FROM oge_topics WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('i', $id);
    }
    $stmt->execute();
    $topic = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($topic) {
        $stmtQuestions = $mysqli->prepare(" 
            SELECT
                q.*,
                tt.task_number,
                tt.title AS task_title
            FROM oge_questions q
            JOIN oge_task_types tt ON tt.id = q.task_type_id
            WHERE q.topic_id = ?
              AND q.is_published = 1
            ORDER BY tt.task_number ASC, q.id DESC
        ");
        $topicId = (int)$topic['id'];
        $stmtQuestions->bind_param('i', $topicId);
        $stmtQuestions->execute();
        $result = $stmtQuestions->get_result();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmtQuestions->close();
    }
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить тему.';
}

if (!$topic) {
    http_response_code(404);
    die('Тема не найдена');
}

$page_title = $topic['title'] . ' — ОГЭ по математике';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= e($topic['title']) ?></h1>
        <?php if (!empty($topic['short_description'])): ?>
            <p class="text-muted mb-0"><?= e($topic['short_description']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary" href="/topics.php">Все темы</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Задачи по теме</h2>

        <?php if (empty($questions)): ?>
            <div class="alert alert-info mb-0">По этой теме пока нет опубликованных задач.</div>
        <?php else: ?>
            <div class="vstack gap-3">
                <?php foreach ($questions as $question): ?>
                    <article class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="text-muted small mb-1">
                                    Задание #<?= e($question['task_number']) ?> · <?= e($question['task_title']) ?> · <?= e($question['difficulty']) ?>
                                </div>
                                <h3 class="h6 mb-2"><?= e($question['title']) ?></h3>
                                <div class="text-muted small"><?= mb_substr(strip_tags((string)$question['body_html']), 0, 180) ?><?= mb_strlen(strip_tags((string)$question['body_html'])) > 180 ? '...' : '' ?></div>
                            </div>
                            <a class="btn btn-sm btn-primary" href="/question.php?id=<?= (int)$question['id'] ?>">Открыть</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
