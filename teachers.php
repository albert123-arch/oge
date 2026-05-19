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
$currentRole = (string)get_user_role();
if (!in_array($currentRole, ['admin', 'teacher'], true)) {
    http_response_code(403);
    die('Доступ запрещен');
}

if (!function_exists('can_manage_question')) {
    function can_manage_question(array $question, string $role, int $userId): bool {
        if ($role === 'admin') {
            return true;
        }

        return (int)($question['created_by'] ?? 0) === $userId;
    }
}

$allowedDifficulty = ['низкая', 'повышенная', 'высокая'];
$allowedAnswerType = ['short', 'full'];
$allowedSourcePeriods = ['demo', 'march', 'april', 'may', 'june', 'reserve', 'early', 'main', 'teacher', 'training', 'other'];

$successMessage = '';
$errorMessage = '';

$taskTypes = [];
$subtopics = [];
$topics = [];

try {
    $taskResult = $mysqli->query('SELECT id, task_number, title FROM oge_task_types WHERE is_active = 1 ORDER BY part_number, task_number');
    while ($row = $taskResult->fetch_assoc()) {
        $taskTypes[] = $row;
    }
    $taskResult->free();

    $subtopicResult = $mysqli->query('SELECT id, task_type_id, title, sort_order FROM oge_task_subtopics WHERE is_active = 1 ORDER BY task_type_id, sort_order, title');
    while ($row = $subtopicResult->fetch_assoc()) {
        $subtopics[] = $row;
    }
    $subtopicResult->free();

    $topicResult = $mysqli->query('SELECT id, title FROM oge_topics WHERE is_active = 1 ORDER BY sort_order, title');
    while ($row = $topicResult->fetch_assoc()) {
        $topics[] = $row;
    }
    $topicResult->free();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить справочники.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete_question') {
        $questionId = (int)($_POST['question_id'] ?? 0);

        if ($questionId > 0) {
            try {
                $stmtLoad = $mysqli->prepare('SELECT id, created_by FROM oge_questions WHERE id = ? LIMIT 1');
                $stmtLoad->bind_param('i', $questionId);
                $stmtLoad->execute();
                $existing = $stmtLoad->get_result()->fetch_assoc();
                $stmtLoad->close();

                if (!$existing || !can_manage_question($existing, $currentRole, $currentUserId)) {
                    throw new RuntimeException('Нет прав на удаление этого вопроса.');
                }

                $stmtDelete = $mysqli->prepare('DELETE FROM oge_questions WHERE id = ?');
                $stmtDelete->bind_param('i', $questionId);
                $stmtDelete->execute();
                $stmtDelete->close();

                $successMessage = 'Вопрос удален.';
            } catch (Throwable $exception) {
                $errorMessage = 'Не удалось удалить вопрос: ' . $exception->getMessage();
            }
        }
    }

    if ($action === 'save_question') {
        $questionId = (int)($_POST['question_id'] ?? 0);

        $taskTypeId = (int)($_POST['task_type_id'] ?? 0);
        $subtopicIdRaw = (int)($_POST['subtopic_id'] ?? 0);
        $topicIdRaw = (int)($_POST['topic_id'] ?? 0);

        $subtopicId = $subtopicIdRaw > 0 ? $subtopicIdRaw : null;
        $topicId = $topicIdRaw > 0 ? $topicIdRaw : null;

        $title = trim((string)($_POST['title'] ?? ''));
        $bodyHtml = trim((string)($_POST['body_html'] ?? ''));
        $solutionHtml = trim((string)($_POST['solution_html'] ?? ''));
        $markingSchemeHtml = trim((string)($_POST['marking_scheme_html'] ?? ''));
        $answerText = trim((string)($_POST['answer_text'] ?? ''));

        $answerType = trim((string)($_POST['answer_type'] ?? 'short'));
        if (!in_array($answerType, $allowedAnswerType, true)) {
            $answerType = 'short';
        }

        $maxScore = (int)($_POST['max_score'] ?? 1);
        if ($maxScore < 1) {
            $maxScore = 1;
        }

        $difficulty = trim((string)($_POST['difficulty'] ?? 'низкая'));
        if (!in_array($difficulty, $allowedDifficulty, true)) {
            $difficulty = 'низкая';
        }

        $source = trim((string)($_POST['source'] ?? ''));
        $sourceName = trim((string)($_POST['source_name'] ?? ''));

        $sourceYearRaw = trim((string)($_POST['source_year'] ?? ''));
        $sourceYear = ctype_digit($sourceYearRaw) ? (int)$sourceYearRaw : null;

        $sourceMonth = trim((string)($_POST['source_month'] ?? ''));

        $sourcePeriod = trim((string)($_POST['source_period'] ?? ''));
        if ($sourcePeriod === '' || !in_array($sourcePeriod, $allowedSourcePeriods, true)) {
            $sourcePeriod = null;
        }

        $sourceVariantCode = trim((string)($_POST['source_variant_code'] ?? ''));

        $sourceTaskNumberRaw = trim((string)($_POST['source_task_number'] ?? ''));
        $sourceTaskNumber = ctype_digit($sourceTaskNumberRaw) ? (int)$sourceTaskNumberRaw : null;

        $sourceUrl = trim((string)($_POST['source_url'] ?? ''));
        $sourceExternalId = trim((string)($_POST['source_external_id'] ?? ''));

        $isPublished = (int)(($_POST['is_published'] ?? '0') === '1');
        $checked = (int)(($_POST['checked'] ?? '0') === '1');

        if ($taskTypeId <= 0 || $title === '' || $bodyHtml === '') {
            $errorMessage = 'Заполните обязательные поля: номер задания, заголовок и текст задачи.';
        } else {
            try {
                if ($questionId > 0) {
                    $stmtLoad = $mysqli->prepare('SELECT id, created_by FROM oge_questions WHERE id = ? LIMIT 1');
                    $stmtLoad->bind_param('i', $questionId);
                    $stmtLoad->execute();
                    $existing = $stmtLoad->get_result()->fetch_assoc();
                    $stmtLoad->close();

                    if (!$existing || !can_manage_question($existing, $currentRole, $currentUserId)) {
                        throw new RuntimeException('Нет прав на редактирование этого вопроса.');
                    }

                    $stmtUpdate = $mysqli->prepare(
                        'UPDATE oge_questions
                         SET task_type_id = ?, subtopic_id = ?, topic_id = ?, title = ?, body_html = ?, solution_html = ?, marking_scheme_html = ?, answer_text = ?, answer_type = ?, max_score = ?, difficulty = ?, source = ?, source_name = ?, source_year = ?, source_month = ?, source_period = ?, source_variant_code = ?, source_task_number = ?, source_url = ?, source_external_id = ?, is_published = ?, checked = ?
                         WHERE id = ?'
                    );
                    $stmtUpdate->bind_param(
                        'iiissssssisssisssissiii',
                        $taskTypeId,
                        $subtopicId,
                        $topicId,
                        $title,
                        $bodyHtml,
                        $solutionHtml,
                        $markingSchemeHtml,
                        $answerText,
                        $answerType,
                        $maxScore,
                        $difficulty,
                        $source,
                        $sourceName,
                        $sourceYear,
                        $sourceMonth,
                        $sourcePeriod,
                        $sourceVariantCode,
                        $sourceTaskNumber,
                        $sourceUrl,
                        $sourceExternalId,
                        $isPublished,
                        $checked,
                        $questionId
                    );
                    $stmtUpdate->execute();
                    $stmtUpdate->close();

                    $successMessage = 'Вопрос обновлен.';
                } else {
                    $stmtInsert = $mysqli->prepare(
                        'INSERT INTO oge_questions
                        (task_type_id, subtopic_id, topic_id, title, body_html, solution_html, marking_scheme_html, answer_text, answer_type, max_score, difficulty, source, source_name, source_year, source_month, source_period, source_variant_code, source_task_number, source_url, source_external_id, created_by, is_published, checked, created_at, updated_at)
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                    );
                    $stmtInsert->bind_param(
                        'iiissssssisssisssissiii',
                        $taskTypeId,
                        $subtopicId,
                        $topicId,
                        $title,
                        $bodyHtml,
                        $solutionHtml,
                        $markingSchemeHtml,
                        $answerText,
                        $answerType,
                        $maxScore,
                        $difficulty,
                        $source,
                        $sourceName,
                        $sourceYear,
                        $sourceMonth,
                        $sourcePeriod,
                        $sourceVariantCode,
                        $sourceTaskNumber,
                        $sourceUrl,
                        $sourceExternalId,
                        $currentUserId,
                        $isPublished,
                        $checked
                    );
                    $stmtInsert->execute();
                    $stmtInsert->close();

                    $successMessage = 'Вопрос добавлен.';
                }
            } catch (Throwable $exception) {
                $errorMessage = 'Не удалось сохранить вопрос: ' . $exception->getMessage();
            }
        }
    }
}

$editQuestion = null;
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId > 0) {
    try {
        $stmtEdit = $mysqli->prepare('SELECT * FROM oge_questions WHERE id = ? LIMIT 1');
        $stmtEdit->bind_param('i', $editId);
        $stmtEdit->execute();
        $row = $stmtEdit->get_result()->fetch_assoc();
        $stmtEdit->close();

        if ($row && can_manage_question($row, $currentRole, $currentUserId)) {
            $editQuestion = $row;
        } else {
            $errorMessage = 'Вопрос для редактирования не найден или нет прав.';
        }
    } catch (Throwable $exception) {
        $errorMessage = 'Не удалось открыть вопрос для редактирования.';
    }
}

$filterTaskNumber = isset($_GET['task_number']) ? (int)$_GET['task_number'] : 0;
$filterSubtopicId = isset($_GET['subtopic_id']) ? (int)$_GET['subtopic_id'] : 0;
$filterYearRaw = trim((string)($_GET['source_year'] ?? ''));
$filterSourceYear = ctype_digit($filterYearRaw) ? (int)$filterYearRaw : 0;
$filterSourcePeriod = trim((string)($_GET['source_period'] ?? ''));
$filterPublished = isset($_GET['is_published']) && $_GET['is_published'] !== '' ? (int)$_GET['is_published'] : -1;
$filterChecked = isset($_GET['checked']) && $_GET['checked'] !== '' ? (int)$_GET['checked'] : -1;

$questions = [];

try {
    $sql = '
        SELECT
            q.*,
            tt.task_number,
            tt.title AS task_title,
            st.title AS subtopic_title,
            t.title AS topic_title,
            u.full_name AS author_name,
            u.email AS author_email
        FROM oge_questions q
        JOIN oge_task_types tt ON tt.id = q.task_type_id
        LEFT JOIN oge_task_subtopics st ON st.id = q.subtopic_id
        LEFT JOIN oge_topics t ON t.id = q.topic_id
        LEFT JOIN oge_users u ON u.id = q.created_by
        WHERE 1 = 1';

    $types = '';
    $params = [];

    if ($currentRole === 'teacher') {
        $sql .= ' AND q.created_by = ?';
        $types .= 'i';
        $params[] = $currentUserId;
    }

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

    if ($filterSourceYear > 0) {
        $sql .= ' AND q.source_year = ?';
        $types .= 'i';
        $params[] = $filterSourceYear;
    }

    if ($filterSourcePeriod !== '' && in_array($filterSourcePeriod, $allowedSourcePeriods, true)) {
        $sql .= ' AND q.source_period = ?';
        $types .= 's';
        $params[] = $filterSourcePeriod;
    }

    if ($filterPublished === 0 || $filterPublished === 1) {
        $sql .= ' AND q.is_published = ?';
        $types .= 'i';
        $params[] = $filterPublished;
    }

    if ($filterChecked === 0 || $filterChecked === 1) {
        $sql .= ' AND q.checked = ?';
        $types .= 'i';
        $params[] = $filterChecked;
    }

    $sql .= ' ORDER BY q.updated_at DESC, q.id DESC LIMIT 200';

    $stmtList = $mysqli->prepare($sql);
    if ($types !== '') {
        $stmtList->bind_param($types, ...$params);
    }
    $stmtList->execute();
    $resultList = $stmtList->get_result();

    while ($row = $resultList->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmtList->close();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить список вопросов.';
}

$formQuestion = $editQuestion ?: [
    'id' => 0,
    'task_type_id' => $taskTypes[0]['id'] ?? 0,
    'subtopic_id' => 0,
    'topic_id' => 0,
    'title' => '',
    'body_html' => '',
    'solution_html' => '',
    'marking_scheme_html' => '',
    'answer_text' => '',
    'answer_type' => 'short',
    'max_score' => 1,
    'difficulty' => 'низкая',
    'source' => '',
    'source_name' => '',
    'source_year' => '',
    'source_month' => '',
    'source_period' => '',
    'source_variant_code' => '',
    'source_task_number' => '',
    'source_url' => '',
    'source_external_id' => '',
    'is_published' => 1,
    'checked' => 0,
];

$page_title = $currentRole === 'admin' ? 'Teacher/Admin dashboard' : 'Teacher dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= $currentRole === 'admin' ? 'Панель учителей и админа' : 'Кабинет учителя' ?></h1>
        <p class="text-muted mb-0">Создание, редактирование и фильтрация вопросов по новой структуре ОГЭ.</p>
    </div>
    <a class="btn btn-outline-primary" href="/practice.php">Практика</a>
</div>

<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?= e($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= (int)$formQuestion['id'] > 0 ? 'Редактировать вопрос' : 'Создать вопрос' ?></h2>

        <form method="post">
            <input type="hidden" name="action" value="save_question">
            <input type="hidden" name="question_id" value="<?= (int)$formQuestion['id'] ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Номер задания</label>
                    <select class="form-select" name="task_type_id" id="taskTypeSelect" required>
                        <?php foreach ($taskTypes as $taskType): ?>
                            <option value="<?= (int)$taskType['id'] ?>" <?= (int)$formQuestion['task_type_id'] === (int)$taskType['id'] ? 'selected' : '' ?>>
                                #<?= (int)$taskType['task_number'] ?> <?= e($taskType['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Подтема</label>
                    <select class="form-select" name="subtopic_id" id="subtopicSelect">
                        <option value="0">Без подтемы</option>
                        <?php foreach ($subtopics as $subtopic): ?>
                            <option
                                value="<?= (int)$subtopic['id'] ?>"
                                data-task-type-id="<?= (int)$subtopic['task_type_id'] ?>"
                                <?= (int)($formQuestion['subtopic_id'] ?? 0) === (int)$subtopic['id'] ? 'selected' : '' ?>
                            >
                                <?= e($subtopic['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Тема (опционально)</label>
                    <select class="form-select" name="topic_id">
                        <option value="0">Без темы</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?= (int)$topic['id'] ?>" <?= (int)($formQuestion['topic_id'] ?? 0) === (int)$topic['id'] ? 'selected' : '' ?>>
                                <?= e($topic['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Заголовок</label>
                    <input class="form-control" type="text" name="title" value="<?= e($formQuestion['title']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Текст задания (body_html)</label>
                    <textarea class="form-control" name="body_html" rows="6" required><?= e($formQuestion['body_html']) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Тип ответа</label>
                    <select class="form-select" name="answer_type" required>
                        <option value="short" <?= (string)$formQuestion['answer_type'] === 'short' ? 'selected' : '' ?>>short</option>
                        <option value="full" <?= (string)$formQuestion['answer_type'] === 'full' ? 'selected' : '' ?>>full</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Макс. балл</label>
                    <input class="form-control" type="number" name="max_score" min="1" max="100" value="<?= (int)$formQuestion['max_score'] ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Ответ (answer_text)</label>
                    <input class="form-control" type="text" name="answer_text" value="<?= e($formQuestion['answer_text']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Сложность</label>
                    <select class="form-select" name="difficulty" required>
                        <?php foreach ($allowedDifficulty as $difficulty): ?>
                            <option value="<?= e($difficulty) ?>" <?= (string)$formQuestion['difficulty'] === $difficulty ? 'selected' : '' ?>><?= e($difficulty) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Решение (solution_html)</label>
                    <textarea class="form-control" name="solution_html" rows="5"><?= e($formQuestion['solution_html']) ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Критерии (marking_scheme_html)</label>
                    <textarea class="form-control" name="marking_scheme_html" rows="5"><?= e($formQuestion['marking_scheme_html']) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">source</label>
                    <input class="form-control" type="text" name="source" value="<?= e($formQuestion['source']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">source_name</label>
                    <input class="form-control" type="text" name="source_name" value="<?= e($formQuestion['source_name']) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">source_year</label>
                    <input class="form-control" type="number" name="source_year" min="2000" max="2100" value="<?= e((string)$formQuestion['source_year']) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">source_month</label>
                    <input class="form-control" type="text" name="source_month" value="<?= e($formQuestion['source_month']) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">source_period</label>
                    <select class="form-select" name="source_period">
                        <option value="">Не выбрано</option>
                        <?php foreach ($allowedSourcePeriods as $period): ?>
                            <option value="<?= e($period) ?>" <?= (string)$formQuestion['source_period'] === $period ? 'selected' : '' ?>><?= e($period) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">source_variant_code</label>
                    <input class="form-control" type="text" name="source_variant_code" value="<?= e($formQuestion['source_variant_code']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">source_task_number</label>
                    <input class="form-control" type="number" name="source_task_number" min="1" max="99" value="<?= e((string)$formQuestion['source_task_number']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">source_url</label>
                    <input class="form-control" type="url" name="source_url" value="<?= e($formQuestion['source_url']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">source_external_id</label>
                    <input class="form-control" type="text" name="source_external_id" value="<?= e($formQuestion['source_external_id']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Публикация</label>
                    <select class="form-select" name="is_published">
                        <option value="1" <?= (int)$formQuestion['is_published'] === 1 ? 'selected' : '' ?>>Опубликован</option>
                        <option value="0" <?= (int)$formQuestion['is_published'] === 0 ? 'selected' : '' ?>>Черновик</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Проверен</label>
                    <select class="form-select" name="checked">
                        <option value="1" <?= (int)$formQuestion['checked'] === 1 ? 'selected' : '' ?>>checked = 1</option>
                        <option value="0" <?= (int)$formQuestion['checked'] === 0 ? 'selected' : '' ?>>checked = 0</option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="submit"><?= (int)$formQuestion['id'] > 0 ? 'Сохранить изменения' : 'Создать вопрос' ?></button>
                <?php if ((int)$formQuestion['id'] > 0): ?>
                    <a class="btn btn-outline-secondary" href="/teachers.php">Отмена</a>
                    <a class="btn btn-outline-primary" href="/question.php?id=<?= (int)$formQuestion['id'] ?>">Открыть вопрос</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>

<section class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Фильтры списка</h2>

        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Номер</label>
                <select class="form-select" name="task_number">
                    <option value="0">Все</option>
                    <?php foreach ($taskTypes as $taskType): ?>
                        <option value="<?= (int)$taskType['task_number'] ?>" <?= $filterTaskNumber === (int)$taskType['task_number'] ? 'selected' : '' ?>>
                            #<?= (int)$taskType['task_number'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
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

            <div class="col-md-2">
                <label class="form-label">Год</label>
                <input class="form-control" type="number" name="source_year" min="2000" max="2100" value="<?= $filterSourceYear > 0 ? (int)$filterSourceYear : '' ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Период</label>
                <select class="form-select" name="source_period">
                    <option value="">Все</option>
                    <?php foreach ($allowedSourcePeriods as $period): ?>
                        <option value="<?= e($period) ?>" <?= $filterSourcePeriod === $period ? 'selected' : '' ?>><?= e($period) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label">Pub</label>
                <select class="form-select" name="is_published">
                    <option value="" <?= $filterPublished === -1 ? 'selected' : '' ?>>Все</option>
                    <option value="1" <?= $filterPublished === 1 ? 'selected' : '' ?>>1</option>
                    <option value="0" <?= $filterPublished === 0 ? 'selected' : '' ?>>0</option>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label">Chk</label>
                <select class="form-select" name="checked">
                    <option value="" <?= $filterChecked === -1 ? 'selected' : '' ?>>Все</option>
                    <option value="1" <?= $filterChecked === 1 ? 'selected' : '' ?>>1</option>
                    <option value="0" <?= $filterChecked === 0 ? 'selected' : '' ?>>0</option>
                </select>
            </div>

            <div class="col-md-1 d-grid">
                <button class="btn btn-outline-primary" type="submit">OK</button>
            </div>
        </form>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= $currentRole === 'admin' ? 'Список всех вопросов' : 'Список моих вопросов' ?></h2>

        <?php if (empty($questions)): ?>
            <div class="alert alert-info mb-0">По выбранным фильтрам вопросов нет.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>№</th>
                            <th>Заголовок</th>
                            <th>Подтема</th>
                            <th>Источник</th>
                            <th>Статус</th>
                            <?php if ($currentRole === 'admin'): ?>
                                <th>Автор</th>
                            <?php endif; ?>
                            <th class="text-end">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td><?= (int)$question['id'] ?></td>
                                <td>#<?= (int)$question['task_number'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= e($question['title']) ?></div>
                                    <div class="small text-muted"><?= e($question['difficulty']) ?> · <?= e($question['answer_type']) ?> · max <?= (int)$question['max_score'] ?></div>
                                </td>
                                <td><?= e($question['subtopic_title'] ?? '—') ?></td>
                                <td>
                                    <div class="small"><?= e($question['source_name'] ?: $question['source'] ?: '—') ?></div>
                                    <div class="small text-muted">
                                        <?= !empty($question['source_year']) ? (int)$question['source_year'] : '—' ?>
                                        <?= !empty($question['source_period']) ? ' · ' . e($question['source_period']) : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <?= (int)$question['is_published'] === 1 ? '<span class="badge text-bg-success">published</span>' : '<span class="badge text-bg-secondary">draft</span>' ?>
                                    <?= (int)$question['checked'] === 1 ? '<span class="badge text-bg-info">checked</span>' : '<span class="badge text-bg-warning">unchecked</span>' ?>
                                </td>
                                <?php if ($currentRole === 'admin'): ?>
                                    <td><?= e($question['author_name'] ?: $question['author_email'] ?: '—') ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="/question.php?id=<?= (int)$question['id'] ?>">View</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="/teachers.php?edit=<?= (int)$question['id'] ?>">Edit</a>
                                        <form method="post" class="mb-0" onsubmit="return confirm('Удалить вопрос?');">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var taskTypeSelect = document.getElementById('taskTypeSelect');
    var subtopicSelect = document.getElementById('subtopicSelect');
    if (!taskTypeSelect || !subtopicSelect) {
        return;
    }

    function filterSubtopics() {
        var selectedTaskType = taskTypeSelect.value;
        var currentSubtopicValue = subtopicSelect.value;
        var hasVisibleCurrentValue = currentSubtopicValue === '0';

        for (var i = 0; i < subtopicSelect.options.length; i++) {
            var option = subtopicSelect.options[i];
            var optionTaskType = option.getAttribute('data-task-type-id');

            if (!optionTaskType) {
                option.hidden = false;
                continue;
            }

            var visible = optionTaskType === selectedTaskType;
            option.hidden = !visible;
            if (visible && option.value === currentSubtopicValue) {
                hasVisibleCurrentValue = true;
            }
        }

        if (!hasVisibleCurrentValue) {
            subtopicSelect.value = '0';
        }
    }

    taskTypeSelect.addEventListener('change', filterSubtopics);
    filterSubtopics();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>