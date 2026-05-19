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
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_question_id'])) {
    $questionId = (int)($_POST['remove_question_id'] ?? 0);
    if ($questionId > 0) {
        try {
            $stmtDelete = $mysqli->prepare('DELETE FROM oge_bookmarks WHERE user_id = ? AND question_id = ?');
            $stmtDelete->bind_param('ii', $currentUserId, $questionId);
            $stmtDelete->execute();
            $stmtDelete->close();
            $successMessage = 'Закладка удалена.';
        } catch (Throwable $exception) {
            $errorMessage = 'Не удалось удалить закладку.';
        }
    }
}

$taskTypes = [];
$subtopics = [];
$filterTaskNumber = isset($_GET['task_number']) ? (int)$_GET['task_number'] : 0;
$filterSubtopicId = isset($_GET['subtopic_id']) ? (int)$_GET['subtopic_id'] : 0;

try {
    $taskResult = $mysqli->query('SELECT id, task_number, title FROM oge_task_types WHERE is_active = 1 ORDER BY task_number');
    while ($row = $taskResult->fetch_assoc()) {
        $taskTypes[] = $row;
    }
    $taskResult->free();

    $subtopicResult = $mysqli->query('SELECT id, task_type_id, title FROM oge_task_subtopics WHERE is_active = 1 ORDER BY task_type_id, sort_order, title');
    while ($row = $subtopicResult->fetch_assoc()) {
        $subtopics[] = $row;
    }
    $subtopicResult->free();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить фильтры.';
}

$questions = [];
try {
    $sql = '
        SELECT
            q.*,
            b.created_at AS bookmarked_at,
            tt.task_number,
            tt.title AS task_title,
            st.title AS subtopic_title,
            t.title AS topic_title
        FROM oge_bookmarks b
        JOIN oge_questions q ON q.id = b.question_id
        JOIN oge_task_types tt ON tt.id = q.task_type_id
        LEFT JOIN oge_task_subtopics st ON st.id = q.subtopic_id
        LEFT JOIN oge_topics t ON t.id = q.topic_id
        WHERE b.user_id = ?';

    $types = 'i';
    $params = [$currentUserId];

    if ($filterTaskNumber > 0) {
        $sql .= ' AND tt.task_number = ?';
        $types .= 'i';
        $params[] = $filterTaskNumber;
    }

    if ($filterSubtopicId > 0) {
        $sql .= ' AND q.subtopic_id = ?';
        $types .= 'i';
        $params[] = $filterSubtopicId;
    }

    $sql .= ' ORDER BY b.created_at DESC';

    $stmtList = $mysqli->prepare($sql);
    $stmtList->bind_param($types, ...$params);
    $stmtList->execute();
    $resultList = $stmtList->get_result();
    while ($row = $resultList->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmtList->close();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить закладки.';
}

$page_title = 'Закладки';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Закладки</h1>
        <p class="text-muted mb-0">Сохраненные вопросы с фильтрацией по номеру и подтеме.</p>
    </div>
    <a class="btn btn-outline-primary" href="/practice.php">Практика</a>
</div>

<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?= e($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Номер задания</label>
                <select class="form-select" name="task_number">
                    <option value="0">Все</option>
                    <?php foreach ($taskTypes as $task): ?>
                        <option value="<?= (int)$task['task_number'] ?>" <?= $filterTaskNumber === (int)$task['task_number'] ? 'selected' : '' ?>>
                            #<?= (int)$task['task_number'] ?> <?= e($task['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Подтема</label>
                <select class="form-select" name="subtopic_id">
                    <option value="0">Все</option>
                    <?php foreach ($subtopics as $subtopic): ?>
                        <option value="<?= (int)$subtopic['id'] ?>" <?= $filterSubtopicId === (int)$subtopic['id'] ? 'selected' : '' ?>>
                            #<?= (int)$subtopic['task_type_id'] ?> <?= e($subtopic['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-outline-primary" type="submit">Применить</button>
            </div>
        </div>
    </div>
</form>

<?php if (empty($questions)): ?>
    <div class="alert alert-info">По выбранным фильтрам закладок не найдено.</div>
<?php else: ?>
    <div class="vstack gap-3">
        <?php foreach ($questions as $question): ?>
            <article class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <div class="text-muted small mb-1">
                                #<?= (int)$question['task_number'] ?> <?= e($question['task_title']) ?>
                                <?= !empty($question['subtopic_title']) ? ' · ' . e($question['subtopic_title']) : '' ?>
                            </div>
                            <h2 class="h5 mb-2"><?= e($question['title']) ?></h2>
                            <div class="text-muted small"><?= mb_substr(strip_tags((string)$question['body_html']), 0, 200) ?><?= mb_strlen(strip_tags((string)$question['body_html'])) > 200 ? '...' : '' ?></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-sm btn-primary" href="/question.php?id=<?= (int)$question['id'] ?>">Открыть</a>
                            <form method="post" class="mb-0">
                                <input type="hidden" name="remove_question_id" value="<?= (int)$question['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>