<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/authentication/auth.php';

require_login();

$currentUserId = (int)get_current_user_id();
$errorMessage = '';

$summary = [
    'total_attempts' => 0,
    'correct_attempts' => 0,
    'accuracy_percent' => 0,
    'total_score' => 0,
    'total_max_score' => 0,
    'score_percent' => 0,
];
$byTask = [];
$bySubtopic = [];
$latestAttempts = [];

try {
    $stmtSummary = $mysqli->prepare(
        'SELECT
            COUNT(*) AS total_attempts,
            COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct_attempts,
            CASE WHEN COUNT(*) > 0 THEN ROUND(COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) / COUNT(*) * 100, 1) ELSE 0 END AS accuracy_percent,
            COALESCE(SUM(COALESCE(score, 0)), 0) AS total_score,
            COALESCE(SUM(COALESCE(max_score, 0)), 0) AS total_max_score,
            CASE WHEN COALESCE(SUM(COALESCE(max_score, 0)), 0) > 0 THEN ROUND(COALESCE(SUM(COALESCE(score, 0)), 0) / SUM(COALESCE(max_score, 0)) * 100, 1) ELSE 0 END AS score_percent
         FROM oge_question_attempts
         WHERE user_id = ?'
    );
    $stmtSummary->bind_param('i', $currentUserId);
    $stmtSummary->execute();
    $summary = $stmtSummary->get_result()->fetch_assoc() ?: $summary;
    $stmtSummary->close();

    $stmtByTask = $mysqli->prepare(
        'SELECT
            tt.task_number,
            tt.title,
            COUNT(a.id) AS attempts,
            COALESCE(SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct_attempts,
            CASE WHEN COUNT(a.id) > 0 THEN ROUND(COALESCE(SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END), 0) / COUNT(a.id) * 100, 1) ELSE 0 END AS accuracy_percent,
            COALESCE(SUM(COALESCE(a.score, 0)), 0) AS total_score,
            COALESCE(SUM(COALESCE(a.max_score, 0)), 0) AS total_max_score,
            CASE WHEN COALESCE(SUM(COALESCE(a.max_score, 0)), 0) > 0 THEN ROUND(COALESCE(SUM(COALESCE(a.score, 0)), 0) / SUM(COALESCE(a.max_score, 0)) * 100, 1) ELSE 0 END AS score_percent
         FROM oge_question_attempts a
         JOIN oge_questions q ON q.id = a.question_id
         JOIN oge_task_types tt ON tt.id = q.task_type_id
         WHERE a.user_id = ?
         GROUP BY tt.id
         ORDER BY tt.task_number'
    );
    $stmtByTask->bind_param('i', $currentUserId);
    $stmtByTask->execute();
    $resultByTask = $stmtByTask->get_result();
    while ($row = $resultByTask->fetch_assoc()) {
        $byTask[] = $row;
    }
    $stmtByTask->close();

    $stmtBySubtopic = $mysqli->prepare(
        'SELECT
            tt.task_number,
            COALESCE(st.title, "Без подтемы") AS subtopic_title,
            COUNT(a.id) AS attempts,
            COALESCE(SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct_attempts,
            COALESCE(SUM(COALESCE(a.score, 0)), 0) AS total_score,
            COALESCE(SUM(COALESCE(a.max_score, 0)), 0) AS total_max_score,
            CASE WHEN COALESCE(SUM(COALESCE(a.max_score, 0)), 0) > 0 THEN ROUND(COALESCE(SUM(COALESCE(a.score, 0)), 0) / SUM(COALESCE(a.max_score, 0)) * 100, 1) ELSE 0 END AS score_percent
         FROM oge_question_attempts a
         JOIN oge_questions q ON q.id = a.question_id
         JOIN oge_task_types tt ON tt.id = q.task_type_id
         LEFT JOIN oge_task_subtopics st ON st.id = q.subtopic_id
         WHERE a.user_id = ?
         GROUP BY tt.task_number, st.id
         ORDER BY tt.task_number, st.sort_order, st.title'
    );
    $stmtBySubtopic->bind_param('i', $currentUserId);
    $stmtBySubtopic->execute();
    $resultBySubtopic = $stmtBySubtopic->get_result();
    while ($row = $resultBySubtopic->fetch_assoc()) {
        $bySubtopic[] = $row;
    }
    $stmtBySubtopic->close();

    $stmtLatest = $mysqli->prepare(
        'SELECT
            a.*,
            q.title AS question_title,
            tt.task_number,
            tt.title AS task_title,
            COALESCE(st.title, "Без подтемы") AS subtopic_title
         FROM oge_question_attempts a
         JOIN oge_questions q ON q.id = a.question_id
         JOIN oge_task_types tt ON tt.id = q.task_type_id
         LEFT JOIN oge_task_subtopics st ON st.id = q.subtopic_id
         WHERE a.user_id = ?
         ORDER BY a.created_at DESC
         LIMIT 25'
    );
    $stmtLatest->bind_param('i', $currentUserId);
    $stmtLatest->execute();
    $resultLatest = $stmtLatest->get_result();
    while ($row = $resultLatest->fetch_assoc()) {
        $latestAttempts[] = $row;
    }
    $stmtLatest->close();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить результаты.';
}

$page_title = 'Результаты';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Результаты</h1>
        <p class="text-muted mb-0">Общий прогресс, точность и баллы по заданиям и подтемам.</p>
    </div>
    <a class="btn btn-outline-primary" href="/practice.php">Продолжить практику</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Попыток</div><div class="h3 mb-0"><?= (int)$summary['total_attempts'] ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Верных</div><div class="h3 mb-0"><?= (int)$summary['correct_attempts'] ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Точность</div><div class="h3 mb-0"><?= e($summary['accuracy_percent']) ?>%</div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Сумма баллов</div><div class="h3 mb-0"><?= e($summary['total_score']) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Макс. баллов</div><div class="h3 mb-0"><?= e($summary['total_max_score']) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Score %</div><div class="h3 mb-0"><?= e($summary['score_percent']) ?>%</div></div></div>
    </div>
</div>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Прогресс по номеру задания</h2>
        <?php if (empty($byTask)): ?>
            <div class="alert alert-info mb-0">Пока нет попыток.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Задание</th>
                            <th>Попыток</th>
                            <th>Верных</th>
                            <th>Точность</th>
                            <th>Баллы</th>
                            <th>Score %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byTask as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['task_number'] ?></td>
                                <td><?= e($row['title']) ?></td>
                                <td><?= (int)$row['attempts'] ?></td>
                                <td><?= (int)$row['correct_attempts'] ?></td>
                                <td><?= e($row['accuracy_percent']) ?>%</td>
                                <td><?= e($row['total_score']) ?> / <?= e($row['total_max_score']) ?></td>
                                <td><?= e($row['score_percent']) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Прогресс по подтемам</h2>
        <?php if (empty($bySubtopic)): ?>
            <div class="alert alert-info mb-0">Пока нет попыток по подтемам.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Подтема</th>
                            <th>Попыток</th>
                            <th>Верных</th>
                            <th>Баллы</th>
                            <th>Score %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bySubtopic as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['task_number'] ?></td>
                                <td><?= e($row['subtopic_title']) ?></td>
                                <td><?= (int)$row['attempts'] ?></td>
                                <td><?= (int)$row['correct_attempts'] ?></td>
                                <td><?= e($row['total_score']) ?> / <?= e($row['total_max_score']) ?></td>
                                <td><?= e($row['score_percent']) ?>%</td>
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
        <h2 class="h5 mb-3">Последние попытки</h2>
        <?php if (empty($latestAttempts)): ?>
            <div class="alert alert-info mb-0">Попыток пока нет.</div>
        <?php else: ?>
            <div class="vstack gap-2">
                <?php foreach ($latestAttempts as $attempt): ?>
                    <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <div class="small text-muted">
                                #<?= (int)$attempt['task_number'] ?> <?= e($attempt['task_title']) ?>
                                · <?= e($attempt['subtopic_title']) ?>
                                · <?= e($attempt['check_mode']) ?>
                                · <?= e($attempt['created_at']) ?>
                            </div>
                            <a href="/question.php?id=<?= (int)$attempt['question_id'] ?>" class="fw-bold text-decoration-none"><?= e($attempt['question_title']) ?></a>
                            <div class="small text-muted">Балл: <?= e($attempt['score']) ?> / <?= e($attempt['max_score']) ?></div>
                        </div>
                        <span class="badge <?= (int)$attempt['is_correct'] === 1 ? 'text-bg-success' : 'text-bg-danger' ?>">
                            <?= (int)$attempt['is_correct'] === 1 ? 'Верно' : 'Не полностью верно' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>