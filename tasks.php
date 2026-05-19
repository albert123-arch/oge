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

$tasks = [];
$errorMessage = '';

try {
    $sql = "
        SELECT
            tt.*,
            COUNT(q.id) AS question_count
        FROM oge_task_types tt
        LEFT JOIN oge_questions q ON q.task_type_id = tt.id AND q.is_published = 1
        WHERE tt.is_active = 1
        GROUP BY tt.id
        ORDER BY tt.part_number, tt.task_number
    ";
    $result = $mysqli->query($sql);
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $result->free();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить задания ОГЭ.';
}

$grouped = [1 => [], 2 => []];
foreach ($tasks as $task) {
    $part = (int)($task['part_number'] ?? ((int)$task['task_number'] <= 12 ? 1 : 2));
    if (!isset($grouped[$part])) {
        $grouped[$part] = [];
    }
    $grouped[$part][] = $task;
}

$page_title = 'Задания ОГЭ по математике';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Задания ОГЭ по математике</h1>
        <p class="text-muted mb-0">Структура ОГЭ: часть 1 и часть 2.</p>
    </div>
    <a class="btn btn-outline-primary" href="/practice.php">Практика</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<?php foreach ([1 => 'Часть 1 — краткий ответ', 2 => 'Часть 2 — развёрнутый ответ'] as $partNumber => $partTitle): ?>
    <section class="mb-5">
        <h2 class="h4 mb-3"><?= e($partTitle) ?></h2>

        <?php if (empty($grouped[$partNumber])): ?>
            <div class="alert alert-info">Задания этой части пока не добавлены.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($grouped[$partNumber] as $task): ?>
                    <div class="col-md-6 col-xl-4">
                        <a class="text-decoration-none text-reset" href="/task.php?number=<?= (int)$task['task_number'] ?>">
                            <article class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                        <span class="badge text-bg-primary fs-6">№<?= e($task['task_number']) ?></span>
                                        <span class="badge text-bg-light"><?= (int)$task['question_count'] ?> задач</span>
                                    </div>
                                    <h3 class="h5 mb-2"><?= e($task['title']) ?></h3>
                                    <div class="small text-muted mb-2">
                                        <?= e($task['difficulty_level'] ?? '') ?>
                                        <?= !empty($task['answer_format']) ? ' · ' . e($task['answer_format']) : '' ?>
                                        <?= isset($task['max_score']) ? ' · ' . (int)$task['max_score'] . ' балл.' : '' ?>
                                    </div>
                                    <?php if (!empty($task['short_description'])): ?>
                                        <p class="text-muted mb-0"><?= e($task['short_description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
