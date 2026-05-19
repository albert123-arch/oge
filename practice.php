<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/authentication/auth.php';

$isLoggedIn = is_user_logged_in();
$errorMessage = '';
$taskCards = [];
$subtopicStats = [];

try {
    $taskSql = '
        SELECT
            tt.*,
            COUNT(q.id) AS question_count
        FROM oge_task_types tt
        LEFT JOIN oge_questions q
            ON q.task_type_id = tt.id
           AND q.is_published = 1
        WHERE tt.is_active = 1
        GROUP BY tt.id
        ORDER BY tt.part_number, tt.task_number';
    $taskResult = $mysqli->query($taskSql);
    while ($row = $taskResult->fetch_assoc()) {
        $taskCards[] = $row;
    }
    $taskResult->free();

    $subtopicSql = '
        SELECT
            st.id,
            st.task_type_id,
            st.title,
            st.sort_order,
            tt.task_number,
            COUNT(q.id) AS question_count
        FROM oge_task_subtopics st
        JOIN oge_task_types tt ON tt.id = st.task_type_id
        LEFT JOIN oge_questions q
            ON q.subtopic_id = st.id
           AND q.is_published = 1
        WHERE st.is_active = 1
          AND tt.is_active = 1
        GROUP BY st.id
        ORDER BY tt.task_number, st.sort_order, st.title';
    $subtopicResult = $mysqli->query($subtopicSql);
    while ($row = $subtopicResult->fetch_assoc()) {
        $subtopicStats[] = $row;
    }
    $subtopicResult->free();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить данные для практики.';
}

$page_title = 'Практика';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Практика ОГЭ</h1>
        <p class="text-muted mb-0">Хаб для тренировки по номерам, подтемам и будущим вариантам.</p>
    </div>
    <a class="btn btn-outline-primary" href="/tasks.php">Все номера ОГЭ</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Быстрый старт</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="/task.php?number=1">Задание №1</a>
            <a class="btn btn-outline-primary" href="/task.php?number=13">Первая задача второй части</a>
            <a class="btn btn-outline-primary" href="/task.php?number=19">Сложная задача №19</a>
            <?php if ($isLoggedIn): ?>
                <a class="btn btn-outline-secondary" href="/results.php">Мой прогресс</a>
                <a class="btn btn-outline-secondary" href="/bookmarks.php">Мои закладки</a>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="/authentication/login.php">Войти для прогресса</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="mb-4">
    <h2 class="h4 mb-3">Каталог номеров 1-19</h2>

    <?php if (empty($taskCards)): ?>
        <div class="alert alert-info">Каталог пока пуст.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($taskCards as $task): ?>
                <div class="col-md-6 col-xl-4">
                    <a class="text-decoration-none text-reset" href="/task.php?number=<?= (int)$task['task_number'] ?>">
                        <article class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <span class="badge text-bg-primary">№<?= (int)$task['task_number'] ?></span>
                                    <span class="badge text-bg-light"><?= (int)$task['question_count'] ?> задач</span>
                                </div>
                                <h3 class="h5 mb-2"><?= e($task['title']) ?></h3>
                                <div class="small text-muted">
                                    Часть <?= (int)$task['part_number'] ?>
                                    · <?= e($task['difficulty_level']) ?>
                                    · <?= e($task['answer_format']) ?>
                                    · <?= (int)$task['max_score'] ?> балл.
                                </div>
                            </div>
                        </article>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Подтемы с количеством задач</h2>
        <?php if (empty($subtopicStats)): ?>
            <div class="alert alert-info mb-0">Подтемы пока не добавлены.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Номер</th>
                            <th>Подтема</th>
                            <th>Задач</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subtopicStats as $item): ?>
                            <tr>
                                <td>#<?= (int)$item['task_number'] ?></td>
                                <td><?= e($item['title']) ?></td>
                                <td><?= (int)$item['question_count'] ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/task.php?number=<?= (int)$item['task_number'] ?>&subtopic_id=<?= (int)$item['id'] ?>">Открыть</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-2">Конструктор случайного варианта</h2>
        <p class="text-muted mb-3">Скоро здесь будет сборка random variant по blueprint задач 1-19.</p>
        <button class="btn btn-outline-secondary" type="button" disabled>Скоро доступно</button>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>