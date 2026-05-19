<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/authentication/auth.php';

$isLoggedIn = is_user_logged_in();
$currentUserId = $isLoggedIn ? (int)get_current_user_id() : 0;
$currentRole = $isLoggedIn ? (string)get_user_role() : '';

$number = isset($_GET['number']) ? (int)$_GET['number'] : 0;
$view = trim((string)($_GET['view'] ?? ''));
$subtopicId = isset($_GET['subtopic_id']) ? (int)$_GET['subtopic_id'] : 0;

$allowedViews = ['unsolved', 'all', 'wrong', 'bookmarked'];
if ($view === '') {
    $view = $isLoggedIn ? 'unsolved' : 'all';
}
if (!in_array($view, $allowedViews, true)) {
    $view = $isLoggedIn ? 'unsolved' : 'all';
}

if (!$isLoggedIn && $view !== 'all') {
    $view = 'all';
}

if (!function_exists('build_task_url')) {
    function build_task_url(int $taskNumber, string $view, int $subtopicId = 0): string {
        $params = ['number' => $taskNumber];

        if ($view !== '') {
            $params['view'] = $view;
        }
        if ($subtopicId > 0) {
            $params['subtopic_id'] = $subtopicId;
        }

        return '/task.php?' . http_build_query($params);
    }
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $postQuestionId = (int)($_POST['question_id'] ?? 0);
    if ($postQuestionId > 0 && isset($_POST['bookmark_action'])) {
        try {
            $stmtBookmarkCheck = $mysqli->prepare(
                'SELECT 1 FROM oge_bookmarks WHERE user_id = ? AND question_id = ? LIMIT 1'
            );
            $stmtBookmarkCheck->bind_param('ii', $currentUserId, $postQuestionId);
            $stmtBookmarkCheck->execute();
            $bookmarkExists = (bool)$stmtBookmarkCheck->get_result()->fetch_assoc();
            $stmtBookmarkCheck->close();

            if ($bookmarkExists) {
                $stmtDelete = $mysqli->prepare('DELETE FROM oge_bookmarks WHERE user_id = ? AND question_id = ?');
                $stmtDelete->bind_param('ii', $currentUserId, $postQuestionId);
                $stmtDelete->execute();
                $stmtDelete->close();
                $successMessage = 'Закладка удалена.';
            } else {
                $stmtInsert = $mysqli->prepare(
                    'INSERT INTO oge_bookmarks (user_id, question_id, created_at) VALUES (?, ?, NOW())'
                );
                $stmtInsert->bind_param('ii', $currentUserId, $postQuestionId);
                $stmtInsert->execute();
                $stmtInsert->close();
                $successMessage = 'Вопрос добавлен в закладки.';
            }
        } catch (Throwable $exception) {
            $errorMessage = 'Не удалось обновить закладку.';
        }
    }

    if ($postQuestionId > 0 && isset($_POST['submit_short_answer'])) {
        $submittedAnswer = trim((string)($_POST['submitted_answer'] ?? ''));

        if ($submittedAnswer === '') {
            $errorMessage = 'Введите ответ перед проверкой.';
        } else {
            try {
                $stmtQuestion = $mysqli->prepare(
                    'SELECT id, answer_type, answer_text, max_score FROM oge_questions WHERE id = ? AND is_published = 1 LIMIT 1'
                );
                $stmtQuestion->bind_param('i', $postQuestionId);
                $stmtQuestion->execute();
                $questionRow = $stmtQuestion->get_result()->fetch_assoc();
                $stmtQuestion->close();

                if (!$questionRow || (string)$questionRow['answer_type'] !== 'short') {
                    $errorMessage = 'Вопрос не найден или недоступен для авто-проверки.';
                } else {
                    $rightAnswer = trim((string)($questionRow['answer_text'] ?? ''));
                    $normalizedSubmitted = mb_strtolower(str_replace(',', '.', preg_replace('/\s+/u', ' ', $submittedAnswer)), 'UTF-8');
                    $normalizedRight = mb_strtolower(str_replace(',', '.', preg_replace('/\s+/u', ' ', $rightAnswer)), 'UTF-8');
                    $isCorrect = $normalizedSubmitted !== '' && $normalizedSubmitted === $normalizedRight;
                    $maxScore = isset($questionRow['max_score']) ? (float)$questionRow['max_score'] : 1.0;
                    $score = $isCorrect ? 1.0 : 0.0;

                    $stmtFirstAttempt = $mysqli->prepare(
                        'SELECT id FROM oge_question_attempts WHERE user_id = ? AND question_id = ? ORDER BY id ASC LIMIT 1'
                    );
                    $stmtFirstAttempt->bind_param('ii', $currentUserId, $postQuestionId);
                    $stmtFirstAttempt->execute();
                    $existingAttempt = $stmtFirstAttempt->get_result()->fetch_assoc();
                    $stmtFirstAttempt->close();

                    if (!$existingAttempt) {
                        $stmtAttempt = $mysqli->prepare(
                            'INSERT INTO oge_question_attempts (user_id, question_id, answer_text, is_correct, check_mode, score, max_score, self_marked, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())'
                        );
                        $checkMode = 'auto';
                        $correctInt = $isCorrect ? 1 : 0;
                        $stmtAttempt->bind_param(
                            'iisisdd',
                            $currentUserId,
                            $postQuestionId,
                            $submittedAnswer,
                            $correctInt,
                            $checkMode,
                            $score,
                            $maxScore
                        );
                        $stmtAttempt->execute();
                        $stmtAttempt->close();

                        $successMessage = $isCorrect
                            ? 'Верно! Сохранена первая попытка.'
                            : 'Ответ неверный. Сохранена первая попытка.';
                    } else {
                        $successMessage = $isCorrect
                            ? 'Верно! Проверка выполнена. В прогресс сохраняется только первая попытка.'
                            : 'Ответ неверный. В прогресс сохраняется только первая попытка.';
                    }
                }
            } catch (Throwable $exception) {
                $errorMessage = 'Не удалось сохранить результат проверки.';
            }
        }
    }

    if ($postQuestionId > 0 && isset($_POST['submit_self_mark'])) {
        $selectedScore = (float)($_POST['self_score'] ?? 0);

        try {
            $stmtQuestion = $mysqli->prepare(
                'SELECT id, answer_type, max_score FROM oge_questions WHERE id = ? AND is_published = 1 LIMIT 1'
            );
            $stmtQuestion->bind_param('i', $postQuestionId);
            $stmtQuestion->execute();
            $questionRow = $stmtQuestion->get_result()->fetch_assoc();
            $stmtQuestion->close();

            if (!$questionRow || (string)$questionRow['answer_type'] !== 'full') {
                $errorMessage = 'Вопрос не найден или недоступен для self-marking.';
            } else {
                $maxScore = isset($questionRow['max_score']) ? (float)$questionRow['max_score'] : 1.0;
                if ($selectedScore < 0) {
                    $selectedScore = 0;
                }
                if ($selectedScore > $maxScore) {
                    $selectedScore = $maxScore;
                }
                $isCorrect = abs($selectedScore - $maxScore) < 0.0001 ? 1 : 0;

                $stmtFirstAttempt = $mysqli->prepare(
                    'SELECT id FROM oge_question_attempts WHERE user_id = ? AND question_id = ? ORDER BY id ASC LIMIT 1'
                );
                $stmtFirstAttempt->bind_param('ii', $currentUserId, $postQuestionId);
                $stmtFirstAttempt->execute();
                $existingAttempt = $stmtFirstAttempt->get_result()->fetch_assoc();
                $stmtFirstAttempt->close();

                if (!$existingAttempt) {
                    $stmtAttempt = $mysqli->prepare(
                        'INSERT INTO oge_question_attempts (user_id, question_id, answer_text, is_correct, check_mode, score, max_score, self_marked, created_at) VALUES (?, ?, NULL, ?, ?, ?, ?, 1, NOW())'
                    );
                    $checkMode = 'self';
                    $stmtAttempt->bind_param(
                        'iiisdd',
                        $currentUserId,
                        $postQuestionId,
                        $isCorrect,
                        $checkMode,
                        $selectedScore,
                        $maxScore
                    );
                    $stmtAttempt->execute();
                    $stmtAttempt->close();

                    $successMessage = 'Сохранена первая самооценка.';
                } else {
                    $successMessage = 'Оценка просмотрена. В прогресс сохраняется только первая попытка.';
                }
            }
        } catch (Throwable $exception) {
            $errorMessage = 'Не удалось сохранить самооценку.';
        }
    }
}

$task = null;
$subtopics = [];
$questions = [];
$hasQuestionMediaTable = false;

if ($number <= 0) {
    http_response_code(404);
    die('Задание не найдено');
}

try {
    $resultMediaTable = $mysqli->query("SHOW TABLES LIKE 'oge_question_media'");
    $hasQuestionMediaTable = $resultMediaTable && $resultMediaTable->num_rows > 0;
    if ($resultMediaTable) {
        $resultMediaTable->free();
    }

    $stmtTask = $mysqli->prepare('SELECT * FROM oge_task_types WHERE task_number = ? AND is_active = 1 LIMIT 1');
    $stmtTask->bind_param('i', $number);
    $stmtTask->execute();
    $task = $stmtTask->get_result()->fetch_assoc();
    $stmtTask->close();

    if (!$task) {
        http_response_code(404);
        die('Задание не найдено');
    }

    $taskTypeId = (int)$task['id'];

    $stmtSubtopics = $mysqli->prepare(
        'SELECT st.*, COUNT(q.id) AS question_count
         FROM oge_task_subtopics st
         LEFT JOIN oge_questions q ON q.subtopic_id = st.id AND q.is_published = 1
         WHERE st.task_type_id = ? AND st.is_active = 1
         GROUP BY st.id
         ORDER BY st.sort_order, st.title'
    );
    $stmtSubtopics->bind_param('i', $taskTypeId);
    $stmtSubtopics->execute();
    $resultSubtopics = $stmtSubtopics->get_result();
    while ($row = $resultSubtopics->fetch_assoc()) {
        $subtopics[] = $row;
    }
    $stmtSubtopics->close();

    if ($subtopicId > 0) {
        $isSubtopicForTask = false;
        foreach ($subtopics as $subtopicRow) {
            if ((int)$subtopicRow['id'] === $subtopicId) {
                $isSubtopicForTask = true;
                break;
            }
        }
        if (!$isSubtopicForTask) {
            $subtopicId = 0;
        }
    }

    $questionImageSelect = $hasQuestionMediaTable
        ? "(SELECT qm.file_path FROM oge_question_media qm WHERE qm.question_id = q.id AND qm.role = 'question' ORDER BY qm.sort_order ASC, qm.id ASC LIMIT 1)"
        : "''";

    $sql = '
        SELECT
            q.*,
            tt.task_number,
            tt.title AS task_title,
            tt.answer_format,
            tt.max_score AS task_max_score,
            st.title AS subtopic_title,
            t.title AS topic_title,
            ' . $questionImageSelect . ' AS question_image,
            CASE
                WHEN ? > 0 THEN EXISTS(
                    SELECT 1
                    FROM oge_bookmarks b
                    WHERE b.user_id = ? AND b.question_id = q.id
                )
                ELSE 0
            END AS is_bookmarked
        FROM oge_questions q
        JOIN oge_task_types tt ON tt.id = q.task_type_id
        LEFT JOIN oge_task_subtopics st ON st.id = q.subtopic_id
        LEFT JOIN oge_topics t ON t.id = q.topic_id
        WHERE q.task_type_id = ?
          AND q.is_published = 1';

    $types = 'iii';
    $params = [$currentUserId, $currentUserId, $taskTypeId];

    if ($isLoggedIn && $view === 'unsolved') {
        $sql .= '
          AND NOT EXISTS (
              SELECT 1
              FROM oge_question_attempts a
              WHERE a.user_id = ?
                AND a.question_id = q.id
                AND (a.is_correct = 1 OR a.self_marked = 1)
          )';
        $types .= 'i';
        $params[] = $currentUserId;
    }

    if ($isLoggedIn && $view === 'wrong') {
        $sql .= '
          AND EXISTS (
              SELECT 1
              FROM oge_question_attempts a
              WHERE a.user_id = ?
                AND a.question_id = q.id
                AND a.is_correct = 0
          )';
        $types .= 'i';
        $params[] = $currentUserId;
    }

    if ($isLoggedIn && $view === 'bookmarked') {
        $sql .= '
          AND EXISTS (
              SELECT 1
              FROM oge_bookmarks b
              WHERE b.user_id = ?
                AND b.question_id = q.id
          )';
        $types .= 'i';
        $params[] = $currentUserId;
    }

    if ($subtopicId > 0) {
        $sql .= ' AND q.subtopic_id = ?';
        $types .= 'i';
        $params[] = $subtopicId;
    }

    $sql .= ' ORDER BY q.id DESC';

    $stmtQuestions = $mysqli->prepare($sql);
    $stmtQuestions->bind_param($types, ...$params);
    $stmtQuestions->execute();
    $resultQuestions = $stmtQuestions->get_result();
    while ($row = $resultQuestions->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmtQuestions->close();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить задание.';
}

$page_title = 'Задание №' . (int)$task['task_number'] . ' — ' . $task['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Задание №<?= e($task['task_number']) ?>. <?= e($task['title']) ?></h1>
        <p class="text-muted mb-0">
            Часть <?= e($task['part_number']) ?>
            · <?= e($task['difficulty_level']) ?>
            · <?= e($task['answer_format']) ?>
            · Макс. балл: <?= (int)$task['max_score'] ?>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="/tasks.php">Все задания</a>
</div>

<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?= e($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Фильтры</h2>
        <div class="d-flex gap-2 flex-wrap mb-3">
            <?php
            $viewItems = [
                'unsolved' => 'Нерешенные',
                'all' => 'Все',
                'wrong' => 'С ошибками',
                'bookmarked' => 'Закладки',
            ];
            foreach ($viewItems as $key => $label):
                if (!$isLoggedIn && $key !== 'all') {
                    continue;
                }
                $isActive = $view === $key;
                ?>
                <a class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(build_task_url((int)$task['task_number'], $key, $subtopicId)) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!$isLoggedIn): ?>
            <div class="small text-muted">Для фильтров «Нерешенные», «С ошибками» и «Закладки» нужен вход в аккаунт.</div>
        <?php endif; ?>

        <?php if (!empty($subtopics)): ?>
            <hr>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-sm <?= $subtopicId === 0 ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= e(build_task_url((int)$task['task_number'], $view, 0)) ?>">Все подтемы</a>
                <?php foreach ($subtopics as $subtopic): ?>
                    <?php $isActive = $subtopicId === (int)$subtopic['id']; ?>
                    <a class="btn btn-sm <?= $isActive ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= e(build_task_url((int)$task['task_number'], $view, (int)$subtopic['id'])) ?>">
                        <?= e($subtopic['title']) ?> (<?= (int)$subtopic['question_count'] ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($questions)): ?>
    <div class="alert alert-info">По выбранным фильтрам пока нет вопросов.</div>
<?php else: ?>
    <div class="vstack gap-3">
        <?php foreach ($questions as $question): ?>
            <article class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                        <div>
                            <div class="text-muted small mb-1">
                                <?= !empty($question['subtopic_title']) ? e($question['subtopic_title']) . ' · ' : '' ?>
                                <?= !empty($question['source_year']) ? e($question['source_year']) . ' · ' : '' ?>
                                <?= !empty($question['source_period']) ? e($question['source_period']) . ' · ' : '' ?>
                                <?= !empty($question['source_variant_code']) ? 'Вариант ' . e($question['source_variant_code']) : '' ?>
                            </div>
                            <h2 class="h5 mb-2"><?= e($question['title']) ?></h2>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="/question.php?id=<?= (int)$question['id'] ?>">Открыть</a>
                    </div>

                    <?php if (!empty($question['question_image'])): ?>
                        <div class="row g-3 align-items-start mb-3">
                            <div class="col-lg-8">
                                <div><?= $question['body_html'] ?></div>
                            </div>
                            <div class="col-lg-4">
                                <img class="img-fluid rounded border w-100" src="<?= e($question['question_image']) ?>" alt="Иллюстрация к задаче">
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3"><?= $question['body_html'] ?></div>
                    <?php endif; ?>

                    <?php if ((string)$question['answer_type'] === 'short'): ?>
                        <?php if ($isLoggedIn): ?>
                            <form method="post" class="row g-2 align-items-end">
                                <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                                <div class="col-md-8">
                                    <label class="form-label">Краткий ответ</label>
                                    <input class="form-control" type="text" name="submitted_answer" required>
                                </div>
                                <div class="col-md-4 d-grid">
                                    <button class="btn btn-primary" type="submit" name="submit_short_answer" value="1">Проверить</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="small text-muted">Войдите, чтобы проверять краткие ответы и сохранять прогресс.</div>
                        <?php endif; ?>

                        <details class="mt-3">
                            <summary class="mb-2">Показать решение и критерии</summary>
                            <?php if (!empty($question['solution_html'])): ?>
                                <div class="mb-2"><?= $question['solution_html'] ?></div>
                            <?php else: ?>
                                <div class="small text-muted mb-2">Решение пока не добавлено.</div>
                            <?php endif; ?>
                            <?php if (!empty($question['marking_scheme_html'])): ?>
                                <div class="mb-2"><?= $question['marking_scheme_html'] ?></div>
                            <?php endif; ?>
                        </details>
                    <?php else: ?>
                        <details>
                            <summary class="mb-2">Показать решение и критерии</summary>
                            <?php if (!empty($question['solution_html'])): ?>
                                <div class="mb-2"><?= $question['solution_html'] ?></div>
                            <?php endif; ?>
                            <?php if (!empty($question['marking_scheme_html'])): ?>
                                <div class="mb-2"><?= $question['marking_scheme_html'] ?></div>
                            <?php endif; ?>

                            <?php if ($isLoggedIn): ?>
                                <form method="post" class="row g-2 align-items-end">
                                    <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                                    <div class="col-md-6">
                                        <label class="form-label">Самооценка (0..<?= (float)$question['max_score'] ?>)</label>
                                        <input class="form-control" type="number" name="self_score" min="0" max="<?= e($question['max_score']) ?>" step="1" required>
                                    </div>
                                    <div class="col-md-6 d-grid">
                                        <button class="btn btn-outline-success" type="submit" name="submit_self_mark" value="1">Сохранить балл</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="small text-muted">Войдите, чтобы сохранять самооценку и прогресс.</div>
                            <?php endif; ?>
                        </details>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                            <button class="btn btn-sm <?= (int)$question['is_bookmarked'] === 1 ? 'btn-warning' : 'btn-outline-warning' ?>" name="bookmark_action" value="toggle" type="submit">
                                <?= (int)$question['is_bookmarked'] === 1 ? 'Убрать из закладок' : 'В закладки' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>