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

function table_exists_variant(mysqli $mysqli, string $table): bool {
    $safe = $mysqli->real_escape_string($table);
    $result = $mysqli->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $result && $result->num_rows > 0;
    if ($result) { $result->free(); }
    return $exists;
}

$variantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$variant = null;
$questions = [];
$errorMessage = '';

try {
    if (table_exists_variant($mysqli, 'oge_variants')) {
        $stmt = $mysqli->prepare("SELECT * FROM oge_variants WHERE id = ? AND is_published = 1 LIMIT 1");
        $stmt->bind_param('i', $variantId);
        $stmt->execute();
        $variant = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($variant && table_exists_variant($mysqli, 'oge_variant_questions')) {
        $stmtQ = $mysqli->prepare(" 
            SELECT q.*, tt.task_number, tt.title AS task_title
            FROM oge_variant_questions vq
            JOIN oge_questions q ON q.id = vq.question_id
            JOIN oge_task_types tt ON tt.id = q.task_type_id
            WHERE vq.variant_id = ?
            ORDER BY vq.sort_order ASC, tt.task_number ASC
        ");
        $stmtQ->bind_param('i', $variantId);
        $stmtQ->execute();
        $resultQ = $stmtQ->get_result();
        while ($row = $resultQ->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmtQ->close();
    }
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить вариант.';
}

if (!$variant) {
    http_response_code(404);
    die('Вариант не найден или таблицы вариантов ещё не созданы.');
}

$page_title = $variant['title'] ?? 'Вариант ОГЭ';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= e($variant['title'] ?? 'Вариант ОГЭ') ?></h1>
        <?php if (!empty($variant['description'])): ?>
            <p class="text-muted mb-0"><?= e($variant['description']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary" href="/variants.php">Все варианты</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<?php if (empty($questions)): ?>
    <div class="alert alert-info">В этом варианте пока нет задач.</div>
<?php else: ?>
    <div class="vstack gap-3">
        <?php foreach ($questions as $question): ?>
            <article class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">#<?= e($question['task_number']) ?> <?= e($question['task_title']) ?></div>
                    <h2 class="h5 mb-2"><?= e($question['title']) ?></h2>
                    <a class="btn btn-sm btn-primary" href="/question.php?id=<?= (int)$question['id'] ?>">Открыть задачу</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
