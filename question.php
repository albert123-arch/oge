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

$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($questionId <= 0) {
    http_response_code(404);
    die('Вопрос не найден');
}

$errorMessage = '';
$successMessage = '';
$showSolution = true;

$question = null;
try {
    $stmtQuestion = $mysqli->prepare(
        'SELECT
            q.*,
            tt.task_number,
            tt.title AS task_title,
            st.title AS subtopic_title,
            t.title AS topic_title,
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
         WHERE q.id = ?
         LIMIT 1'
    );
    $stmtQuestion->bind_param('iii', $currentUserId, $currentUserId, $questionId);
    $stmtQuestion->execute();
    $question = $stmtQuestion->get_result()->fetch_assoc();
    $stmtQuestion->close();
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить вопрос.';
}

if (!$question) {
    http_response_code(404);
    die('Вопрос не найден');
}

$canViewUnpublished = $isLoggedIn && in_array($currentRole, ['admin', 'teacher'], true);
if ((int)$question['is_published'] !== 1 && !$canViewUnpublished) {
    http_response_code(404);
    die('Вопрос не найден');
}

$mediaByRole = [
    'question' => [],
    'solution' => [],
    'hint' => [],
    'extra' => [],
];

try {
    $resultMediaTable = $mysqli->query("SHOW TABLES LIKE 'oge_question_media'");
    $hasQuestionMediaTable = $resultMediaTable && $resultMediaTable->num_rows > 0;
    if ($resultMediaTable) {
        $resultMediaTable->free();
    }

    if ($hasQuestionMediaTable) {
        $stmtMedia = $mysqli->prepare(
            'SELECT id, role, file_path, file_type, alt_text, sort_order
             FROM oge_question_media
             WHERE question_id = ?
             ORDER BY sort_order ASC, id ASC'
        );

        if ($stmtMedia) {
            $stmtMedia->bind_param('i', $questionId);
            $stmtMedia->execute();
            $resultMedia = $stmtMedia->get_result();
            while ($row = $resultMedia->fetch_assoc()) {
                $role = (string)($row['role'] ?? 'question');
                if (!isset($mediaByRole[$role])) {
                    $mediaByRole[$role] = [];
                }
                $mediaByRole[$role][] = $row;
            }
            $stmtMedia->close();
        }
    }
} catch (Throwable $exception) {
    // Optional media block should not break question rendering.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bookmark_action'])) {
        if (!$isLoggedIn) {
            $errorMessage = 'Для закладок нужно войти в аккаунт.';
        } else {
            try {
                $stmtBookmarkCheck = $mysqli->prepare(
                    'SELECT 1 FROM oge_bookmarks WHERE user_id = ? AND question_id = ? LIMIT 1'
                );
                $stmtBookmarkCheck->bind_param('ii', $currentUserId, $questionId);
                $stmtBookmarkCheck->execute();
                $bookmarkExists = (bool)$stmtBookmarkCheck->get_result()->fetch_assoc();
                $stmtBookmarkCheck->close();

                if ($bookmarkExists) {
                    $stmtDelete = $mysqli->prepare('DELETE FROM oge_bookmarks WHERE user_id = ? AND question_id = ?');
                    $stmtDelete->bind_param('ii', $currentUserId, $questionId);
                    $stmtDelete->execute();
                    $stmtDelete->close();
                    $question['is_bookmarked'] = 0;
                    $successMessage = 'Закладка удалена.';
                } else {
                    $stmtInsert = $mysqli->prepare(
                        'INSERT INTO oge_bookmarks (user_id, question_id, created_at) VALUES (?, ?, NOW())'
                    );
                    $stmtInsert->bind_param('ii', $currentUserId, $questionId);
                    $stmtInsert->execute();
                    $stmtInsert->close();
                    $question['is_bookmarked'] = 1;
                    $successMessage = 'Вопрос добавлен в закладки.';
                }
            } catch (Throwable $exception) {
                $errorMessage = 'Не удалось обновить закладку.';
            }
        }
    }

    if (isset($_POST['submit_short_answer'])) {
        $submittedAnswer = trim((string)($_POST['submitted_answer'] ?? ''));

        if ((string)$question['answer_type'] !== 'short') {
            $errorMessage = 'Это задание требует развернутого ответа.';
        } elseif ($submittedAnswer === '') {
            $errorMessage = 'Введите ответ перед проверкой.';
        } else {
            $rightAnswer = trim((string)($question['answer_text'] ?? ''));
            if ($rightAnswer === '') {
                $errorMessage = 'Для этого вопроса не задан эталонный ответ.';
            } else {
                $normalizedSubmitted = mb_strtolower(str_replace(',', '.', preg_replace('/\s+/u', ' ', $submittedAnswer)), 'UTF-8');
                $normalizedRight = mb_strtolower(str_replace(',', '.', preg_replace('/\s+/u', ' ', $rightAnswer)), 'UTF-8');
                $isCorrect = $normalizedSubmitted !== '' && $normalizedSubmitted === $normalizedRight;
                $maxScore = isset($question['max_score']) ? (float)$question['max_score'] : 1.0;
                $score = $isCorrect ? 1.0 : 0.0;
                $existingAttempt = null;

                if ($isLoggedIn) {
                    try {
                        $stmtFirstAttempt = $mysqli->prepare(
                            'SELECT id FROM oge_question_attempts WHERE user_id = ? AND question_id = ? ORDER BY id ASC LIMIT 1'
                        );
                        $stmtFirstAttempt->bind_param('ii', $currentUserId, $questionId);
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
                                $questionId,
                                $submittedAnswer,
                                $correctInt,
                                $checkMode,
                                $score,
                                $maxScore
                            );
                            $stmtAttempt->execute();
                            $stmtAttempt->close();
                        }
                    } catch (Throwable $exception) {
                        $errorMessage = 'Ответ проверен, но сохранить попытку не удалось.';
                    }
                }

                if ($errorMessage === '') {
                    if ($isLoggedIn && !empty($existingAttempt)) {
                        $successMessage = $isCorrect
                            ? 'Верно! Проверка выполнена. В прогресс сохраняется только первая попытка.'
                            : 'Неверно. Проверка выполнена. В прогресс сохраняется только первая попытка.';
                    } else {
                        $successMessage = $isCorrect
                            ? 'Верно! Сохранена первая попытка.'
                            : 'Неверно. Сохранена первая попытка.';
                    }
                }
            }
        }
    }

    if (isset($_POST['submit_self_mark'])) {
        if ((string)$question['answer_type'] !== 'full') {
            $errorMessage = 'Self-marking доступен только для заданий с развернутым ответом.';
        } elseif (!$isLoggedIn) {
            $errorMessage = 'Чтобы сохранить самооценку, войдите в аккаунт.';
        } else {
            $selectedScore = (float)($_POST['self_score'] ?? 0);
            $maxScore = isset($question['max_score']) ? (float)$question['max_score'] : 1.0;
            if ($selectedScore < 0) {
                $selectedScore = 0;
            }
            if ($selectedScore > $maxScore) {
                $selectedScore = $maxScore;
            }
            $isCorrect = abs($selectedScore - $maxScore) < 0.0001 ? 1 : 0;

            try {
                $stmtFirstAttempt = $mysqli->prepare(
                    'SELECT id FROM oge_question_attempts WHERE user_id = ? AND question_id = ? ORDER BY id ASC LIMIT 1'
                );
                $stmtFirstAttempt->bind_param('ii', $currentUserId, $questionId);
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
                        $questionId,
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
            } catch (Throwable $exception) {
                $errorMessage = 'Не удалось сохранить самооценку.';
            }
        }
    }

    if (isset($_POST['reveal_solution'])) {
        $showSolution = true;
    }
}

$page_title = 'Вопрос #' . (int)$question['id'];
require_once __DIR__ . '/includes/header.php';

$taskNumberForHeader = trim((string)($question['task_number'] ?? $question['task_type_id'] ?? ''));
$taskTypeTitle = trim((string)($question['task_title'] ?? ''));
$answerTypeValue = (string)($question['answer_type'] ?? '');
$partLabel = $answerTypeValue === 'full' ? 'Часть 2' : 'Часть 1';
$answerTypeLabel = $answerTypeValue === 'full' ? 'Развернутый' : 'Краткий';
$difficultyRaw = mb_strtolower(trim((string)($question['difficulty'] ?? '')), 'UTF-8');
$difficultyMap = [
    'easy' => 'базовая',
    'basic' => 'базовая',
    'medium' => 'повышенная',
    'hard' => 'повышенная',
    'advanced' => 'повышенная',
    'базовая' => 'базовая',
    'повышенная' => 'повышенная',
    'высокая' => 'высокая',
];
$difficultyLabel = $difficultyMap[$difficultyRaw] ?? trim((string)($question['difficulty'] ?? ''));
$maxScoreValue = isset($question['max_score']) ? (string)(float)$question['max_score'] : '0';
$maxScoreLabel = rtrim(rtrim($maxScoreValue, '0'), '.');
if ($maxScoreLabel === '') {
    $maxScoreLabel = '0';
}

$sourceValue = trim((string)($question['source'] ?? ''));
$sourceName = trim((string)($question['source_name'] ?? ''));
$sourceMonth = trim((string)($question['source_month'] ?? ''));
$sourcePeriod = trim((string)($question['source_period'] ?? ''));
$sourceVariantCode = trim((string)($question['source_variant_code'] ?? ''));
$subtopicTitle = trim((string)($question['subtopic_title'] ?? ''));

$periodLabelMap = [
    'demo' => 'демоверсия',
    'early' => 'досрочный период',
    'march' => 'март',
    'april' => 'апрель',
    'may' => 'май',
    'june' => 'июнь',
    'main' => 'основная волна',
    'reserve' => 'резервный день',
    'teacher' => 'учительский вариант',
    'training' => 'тренировочный вариант',
    'other' => 'другое',
];
$sourcePeriodLabel = $sourcePeriod !== '' ? ($periodLabelMap[$sourcePeriod] ?? $sourcePeriod) : '';

$shortVariantCode = '';
if ($sourceVariantCode !== '') {
    $dashPos = strrpos($sourceVariantCode, '-');
    $shortVariantCode = $dashPos === false ? $sourceVariantCode : substr($sourceVariantCode, $dashPos + 1);
}

$topMetaParts = [];
if ($subtopicTitle !== '') {
    $topMetaParts[] = $subtopicTitle;
}
if ($sourceName !== '') {
    $topMetaParts[] = $sourceName;
}

$hasExamDateMeta = ($sourceMonth !== '' || $sourcePeriodLabel !== '');
if ($sourceValue === 'ОГЭ' && $sourceName !== '' && $hasExamDateMeta) {
    if ($sourceMonth !== '') {
        $topMetaParts[] = $sourceMonth;
    }
    if ($sourcePeriodLabel !== '') {
        $topMetaParts[] = $sourcePeriodLabel;
    }
}

if ($shortVariantCode !== '') {
    if ($sourceValue === 'ФИПИ') {
        $topMetaParts[] = '№ ' . $shortVariantCode;
    } elseif ($sourceValue === 'ОГЭ' && !$hasExamDateMeta) {
        $variantLabel = ctype_digit($shortVariantCode) ? (string)(int)$shortVariantCode : $shortVariantCode;
        $topMetaParts[] = 'Вариант ' . $variantLabel;
    } elseif ($sourceValue !== 'ОГЭ') {
        $topMetaParts[] = '№ ' . $shortVariantCode;
    }
}

$topMetaLine = implode(' · ', $topMetaParts);
?>

<div class="d-flex justify-content-end align-items-center mb-4 gap-3 flex-wrap">
    <a class="btn btn-outline-secondary" href="/task.php?number=<?= (int)$question['task_number'] ?>">Назад к номеру</a>
</div>

<?php if ($successMessage !== ''): ?>
    <div class="alert alert-success"><?= e($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<article class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="question-header mb-4">
            <?php if ($topMetaLine !== ''): ?>
                <div class="text-muted small mb-2"><?= e($topMetaLine) ?></div>
            <?php endif; ?>

            <h1 class="h3 mb-2">Задание №<?= e($taskNumberForHeader) ?>. <?= e($taskTypeTitle) ?></h1>

            <div class="text-muted small">
                <?= e($partLabel) ?> · <?= e($difficultyLabel) ?> · <?= e($answerTypeLabel) ?> · Макс. балл: <?= e($maxScoreLabel) ?>
            </div>
        </div>

        <?php if (!empty($mediaByRole['question'])): ?>
            <div class="row g-4 align-items-start mb-3">
                <div class="col-lg-8">
                    <div><?= $question['body_html'] ?></div>
                </div>
                <div class="col-lg-4">
                    <div class="vstack gap-3">
                        <?php foreach ($mediaByRole['question'] as $media): ?>
                            <figure class="mb-0">
                                <img class="img-fluid rounded border w-100" src="<?= e($media['file_path']) ?>" alt="<?= e($media['alt_text'] ?: 'Иллюстрация к задаче') ?>">
                                <?php if (!empty($media['alt_text'])): ?>
                                    <figcaption class="small text-muted mt-1"><?= e($media['alt_text']) ?></figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-3"><?= $question['body_html'] ?></div>
        <?php endif; ?>

        <?php if ((string)$question['answer_type'] === 'short'): ?>
            <form method="post" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Краткий ответ</label>
                    <input class="form-control" type="text" name="submitted_answer" required>
                </div>
                <div class="col-md-4 d-grid">
                    <button class="btn btn-primary" type="submit" name="submit_short_answer" value="1">Проверить</button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-light border mb-3">
                Это задание с развернутым ответом. Сначала посмотрите решение, затем выставьте самооценку.
            </div>
            <form method="post" class="mb-3">
                <button class="btn btn-outline-secondary" type="submit" name="reveal_solution" value="1">Показать решение</button>
            </form>

            <?php if ($showSolution): ?>
                <?php if (!empty($question['solution_html'])): ?>
                    <div class="mb-3">
                        <h3 class="h6">Решение</h3>
                        <div><?= $question['solution_html'] ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($question['marking_scheme_html'])): ?>
                    <div class="mb-3">
                        <h3 class="h6">Критерии оценивания</h3>
                        <div><?= $question['marking_scheme_html'] ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="post" class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Самооценка (0..<?= e($question['max_score']) ?>)</label>
                            <input class="form-control" type="number" name="self_score" min="0" max="<?= e($question['max_score']) ?>" step="1" required>
                        </div>
                        <div class="col-md-4 d-grid">
                            <button class="btn btn-success" type="submit" name="submit_self_mark" value="1">Сохранить балл</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="small text-muted">Войдите, чтобы сохранять самооценку по заданиям второй части.</div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($showSolution && !empty($question['solution_html']) && (string)$question['answer_type'] === 'short'): ?>
            <hr>
            <h3 class="h6">Решение</h3>
            <div class="mb-3"><?= $question['solution_html'] ?></div>
        <?php endif; ?>

        <?php if ($showSolution && !empty($question['marking_scheme_html']) && (string)$question['answer_type'] === 'short'): ?>
            <h3 class="h6">Критерии оценивания</h3>
            <div class="mb-3"><?= $question['marking_scheme_html'] ?></div>
        <?php endif; ?>

        <?php if ($showSolution && !empty($question['answer_text'])): ?>
            <div class="alert alert-light border mb-0">Правильный ответ: <strong><?= e($question['answer_text']) ?></strong></div>
        <?php endif; ?>

        <?php if (!empty($mediaByRole['solution'])): ?>
            <div class="mt-3">
                <h3 class="h6">Иллюстрации решения</h3>
                <div class="row g-3">
                    <?php foreach ($mediaByRole['solution'] as $media): ?>
                        <div class="col-md-6">
                            <img class="img-fluid rounded border" src="<?= e($media['file_path']) ?>" alt="<?= e($media['alt_text'] ?: 'Иллюстрация решения') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($mediaByRole['hint'])): ?>
            <div class="mt-3">
                <h3 class="h6">Подсказки</h3>
                <div class="row g-3">
                    <?php foreach ($mediaByRole['hint'] as $media): ?>
                        <div class="col-md-6">
                            <img class="img-fluid rounded border" src="<?= e($media['file_path']) ?>" alt="<?= e($media['alt_text'] ?: 'Подсказка') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($mediaByRole['extra'])): ?>
            <div class="mt-3">
                <h3 class="h6">Дополнительно</h3>
                <div class="row g-3">
                    <?php foreach ($mediaByRole['extra'] as $media): ?>
                        <div class="col-md-6">
                            <img class="img-fluid rounded border" src="<?= e($media['file_path']) ?>" alt="<?= e($media['alt_text'] ?: 'Дополнительный материал') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-3">
            <?php if ($isLoggedIn): ?>
                <form method="post" class="mb-0">
                    <button class="btn btn-sm <?= (int)$question['is_bookmarked'] === 1 ? 'btn-warning' : 'btn-outline-warning' ?>" type="submit" name="bookmark_action" value="toggle">
                        <?= (int)$question['is_bookmarked'] === 1 ? 'Убрать из закладок' : 'В закладки' ?>
                    </button>
                </form>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="/authentication/login.php">Войти для закладок</a>
            <?php endif; ?>
        </div>
    </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>