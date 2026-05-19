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
    $result = $mysqli->query("SELECT * FROM oge_task_types WHERE is_active = 1 ORDER BY task_number ASC");
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $result->free();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить список заданий.';
}

$page_title = 'Диагностика';
require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Диагностика</h1>
    <p class="text-muted mb-0">Пока это стартовая страница диагностики. Позже здесь будет тест, который определит слабые номера.</p>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Как пользоваться сейчас</h2>
        <p class="mb-0">Выбери номер ОГЭ, реши несколько задач и затем проверь прогресс в разделе результатов.</p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($tasks as $task): ?>
        <div class="col-md-6 col-xl-4">
            <a class="text-decoration-none text-reset" href="/task.php?number=<?= (int)$task['task_number'] ?>">
                <article class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="badge text-bg-primary mb-2">№<?= e($task['task_number']) ?></div>
                        <h2 class="h5 mb-2"><?= e($task['title']) ?></h2>
                        <?php if (!empty($task['short_description'])): ?>
                            <p class="text-muted mb-0"><?= e($task['short_description']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
