<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../authentication/auth.php';

require_role($mysqli, ['admin', 'teacher']);

function detect_question_media_table(mysqli $mysqli): string {
    $candidates = ['question_media', 'oge_question_media'];

    foreach ($candidates as $tableName) {
        $safeName = str_replace('`', '``', $tableName);
        $query = "SHOW TABLES LIKE '" . $mysqli->real_escape_string($safeName) . "'";
        $result = $mysqli->query($query);

        if ($result && $result->num_rows > 0) {
            $result->free();
            return $tableName;
        }

        if ($result) {
            $result->free();
        }
    }

    return '';
}

function resolve_task_media_folder(mysqli $mysqli, int $taskTypeId): array {
    $taskNumber = 0;

    if ($taskTypeId > 0) {
        $stmtTask = $mysqli->prepare("SELECT task_number FROM oge_task_types WHERE id = ? LIMIT 1");
        $stmtTask->bind_param('i', $taskTypeId);
        $stmtTask->execute();
        $taskRow = $stmtTask->get_result()->fetch_assoc();
        $stmtTask->close();

        if ($taskRow) {
            $taskNumber = (int)($taskRow['task_number'] ?? 0);
        }
    }

    $taskCode = $taskNumber > 0 ? ('task_' . $taskNumber) : 'task_misc';

    return [
        'fs' => __DIR__ . '/../uploads/questions/' . $taskCode,
        'web' => '/uploads/questions/' . $taskCode,
        'task_code' => $taskCode,
    ];
}

function upload_question_media_files(mysqli $mysqli, int $questionId, int $taskTypeId, string $tableName, string $role, string $altText, int $sortOrder): array {
    if ($tableName === '' || !isset($_FILES['media_files']) || !is_array($_FILES['media_files']['name'] ?? null)) {
        return ['uploaded' => 0, 'errors' => []];
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $folder = resolve_task_media_folder($mysqli, $taskTypeId);
    $uploadDirFs = $folder['fs'];
    $uploadDirWeb = $folder['web'];
    $taskCode = $folder['task_code'];
    $errors = [];
    $uploaded = 0;
    $currentSort = $sortOrder;

    if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0775, true) && !is_dir($uploadDirFs)) {
        return ['uploaded' => 0, 'errors' => ['Не удалось создать папку uploads/questions']];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($_FILES['media_files']['name'] as $index => $originalName) {
        $tmpName = $_FILES['media_files']['tmp_name'][$index] ?? '';
        $errorCode = (int)($_FILES['media_files']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        $fileSize = (int)($_FILES['media_files']['size'][$index] ?? 0);

        if ($errorCode === UPLOAD_ERR_NO_FILE || $tmpName === '') {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Ошибка загрузки файла: ' . (string)$originalName;
            continue;
        }

        if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
            $errors[] = 'Файл слишком большой (макс. 5MB): ' . (string)$originalName;
            continue;
        }

        $mimeType = $finfo->file($tmpName) ?: '';
        if (!isset($allowedMime[$mimeType])) {
            $errors[] = 'Недопустимый тип файла: ' . (string)$originalName;
            continue;
        }

        $extension = $allowedMime[$mimeType];
        $uniqueCode = strtolower($taskCode . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)));
        $filename = $uniqueCode . '.' . $extension;
        $targetPathFs = $uploadDirFs . '/' . $filename;
        $targetPathWeb = $uploadDirWeb . '/' . $filename;

        if (!move_uploaded_file($tmpName, $targetPathFs)) {
            $errors[] = 'Не удалось сохранить файл: ' . (string)$originalName;
            continue;
        }

        $safeTable = str_replace('`', '``', $tableName);
        $stmtMedia = $mysqli->prepare(
            "INSERT INTO `{$safeTable}` (question_id, role, file_path, file_type, alt_text, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtMedia->bind_param('issssi', $questionId, $role, $targetPathWeb, $mimeType, $altText, $currentSort);
        $stmtMedia->execute();
        $stmtMedia->close();

        $currentSort++;
        $uploaded++;
    }

    return ['uploaded' => $uploaded, 'errors' => $errors];
}

$currentUserId = (int)get_current_user_id();
$currentRole = get_user_role();
$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mediaTable = detect_question_media_table($mysqli);
$existingMedia = [];

if ($questionId <= 0) {
    http_response_code(404);
    die('Вопрос не найден');
}

$taskTypes = [];
$topics = [];
$taskResult = $mysqli->query("SELECT id, task_number, title FROM oge_task_types WHERE is_active = 1 ORDER BY task_number ASC");
while ($row = $taskResult->fetch_assoc()) {
    $taskTypes[] = $row;
}
$taskResult->free();

$topicResult = $mysqli->query("SELECT id, title FROM oge_topics WHERE is_active = 1 ORDER BY sort_order ASC, title ASC");
while ($row = $topicResult->fetch_assoc()) {
    $topics[] = $row;
}
$topicResult->free();

$sql = "SELECT * FROM oge_questions WHERE id = ?";
$types = 'i';
$params = [$questionId];
if ($currentRole !== 'admin') {
    $sql .= " AND created_by = ?";
    $types .= 'i';
    $params[] = $currentUserId;
}

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$question) {
    http_response_code(403);
    die('Нет доступа к этому вопросу');
}

if ($mediaTable !== '') {
    $safeTable = str_replace('`', '``', $mediaTable);
    $stmtMediaList = $mysqli->prepare(
        "SELECT id, role, file_path, file_type, alt_text, sort_order FROM `{$safeTable}` WHERE question_id = ? ORDER BY sort_order ASC, id ASC"
    );
    $stmtMediaList->bind_param('i', $questionId);
    $stmtMediaList->execute();
    $resultMediaList = $stmtMediaList->get_result();

    while ($row = $resultMediaList->fetch_assoc()) {
        $existingMedia[] = $row;
    }

    $stmtMediaList->close();
}

$form = [
    'task_type_id' => (string)$question['task_type_id'],
    'topic_id' => $question['topic_id'] === null ? '' : (string)$question['topic_id'],
    'title' => $question['title'],
    'body_html' => $question['body_html'],
    'solution_html' => (string)$question['solution_html'],
    'answer_text' => (string)$question['answer_text'],
    'difficulty' => $question['difficulty'],
    'source' => (string)$question['source'],
    'is_published' => (string)$question['is_published'],
    'checked' => (string)$question['checked'],
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $default) {
        $form[$field] = trim((string)($_POST[$field] ?? $default));
    }

    $taskTypeId = (int)$form['task_type_id'];
    $topicId = $form['topic_id'] === '' ? null : (int)$form['topic_id'];
    $title = $form['title'];
    $bodyHtml = $form['body_html'];
    $solutionHtml = $form['solution_html'] === '' ? null : $form['solution_html'];
    $answerText = $form['answer_text'] === '' ? null : $form['answer_text'];
    $difficulty = in_array($form['difficulty'], ['easy', 'medium', 'hard'], true) ? $form['difficulty'] : 'medium';
    $source = $form['source'] === '' ? null : $form['source'];
    $isPublished = $form['is_published'] === '1' ? 1 : 0;
    $checked = $form['checked'] === '1' ? 1 : 0;

    if ($taskTypeId <= 0 || $title === '' || $bodyHtml === '') {
        $error = 'Заполните обязательные поля: задание, заголовок и текст вопроса.';
    } else {
        $sqlUpdate = "
            UPDATE oge_questions
            SET
                task_type_id = ?,
                topic_id = ?,
                title = ?,
                body_html = ?,
                solution_html = ?,
                answer_text = ?,
                difficulty = ?,
                source = ?,
                is_published = ?,
                checked = ?
            WHERE id = ?
        ";

        $typesUpdate = 'iissssssiii';
        $paramsUpdate = [
            $taskTypeId,
            $topicId,
            $title,
            $bodyHtml,
            $solutionHtml,
            $answerText,
            $difficulty,
            $source,
            $isPublished,
            $checked,
            $questionId,
        ];

        if ($currentRole !== 'admin') {
            $sqlUpdate .= ' AND created_by = ?';
            $typesUpdate .= 'i';
            $paramsUpdate[] = $currentUserId;
        }

        $stmtUpdate = $mysqli->prepare($sqlUpdate);
        $stmtUpdate->bind_param($typesUpdate, ...$paramsUpdate);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        if ($mediaTable !== '' && !empty($_POST['delete_media_ids']) && is_array($_POST['delete_media_ids'])) {
            $safeTable = str_replace('`', '``', $mediaTable);
            $idsToDelete = array_values(array_filter(array_map('intval', $_POST['delete_media_ids']), static function ($value) {
                return $value > 0;
            }));

            if (!empty($idsToDelete)) {
                foreach ($idsToDelete as $mediaId) {
                    $stmtFindMedia = $mysqli->prepare(
                        "SELECT id, file_path FROM `{$safeTable}` WHERE id = ? AND question_id = ? LIMIT 1"
                    );
                    $stmtFindMedia->bind_param('ii', $mediaId, $questionId);
                    $stmtFindMedia->execute();
                    $mediaRow = $stmtFindMedia->get_result()->fetch_assoc();
                    $stmtFindMedia->close();

                    if (!$mediaRow) {
                        continue;
                    }

                    $stmtDeleteMedia = $mysqli->prepare("DELETE FROM `{$safeTable}` WHERE id = ? AND question_id = ?");
                    $stmtDeleteMedia->bind_param('ii', $mediaId, $questionId);
                    $stmtDeleteMedia->execute();
                    $stmtDeleteMedia->close();

                    $filePath = (string)$mediaRow['file_path'];
                    if (strpos($filePath, '/') === 0) {
                        $absolutePath = __DIR__ . '/..' . $filePath;
                        if (is_file($absolutePath)) {
                            @unlink($absolutePath);
                        }
                    }
                }
            }
        }

        $mediaRole = $_POST['media_role'] ?? 'question';
        if (!in_array($mediaRole, ['question', 'solution', 'hint', 'extra'], true)) {
            $mediaRole = 'question';
        }

        $mediaAltText = trim((string)($_POST['media_alt_text'] ?? ''));
        $mediaSortOrder = (int)($_POST['media_sort_order'] ?? 0);
        $mediaResult = upload_question_media_files($mysqli, $questionId, $taskTypeId, $mediaTable, $mediaRole, $mediaAltText, $mediaSortOrder);

        $flashMessage = 'Вопрос обновлен.';
        if ($mediaTable === '' && isset($_FILES['media_files']) && !empty(array_filter($_FILES['media_files']['name'] ?? []))) {
            $flashMessage .= ' Картинки не сохранены: таблица question_media/oge_question_media не найдена.';
        } elseif (!empty($mediaResult['errors'])) {
            $flashMessage .= ' Часть файлов не загружена: ' . implode('; ', $mediaResult['errors']);
        } elseif (($mediaResult['uploaded'] ?? 0) > 0) {
            $flashMessage .= ' Загружено изображений: ' . (int)$mediaResult['uploaded'] . '.';
        }

        set_flash_message('success', $flashMessage);
        header('Location: ' . SITE_URL . '/admin/questions.php');
        exit();
    }
}

$page_title = 'Редактировать вопрос';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Редактировать вопрос #<?= e($questionId) ?></h1>
    <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-outline-secondary">Назад к списку</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Номер задания</label>
                <select class="form-select" name="task_type_id" required>
                    <option value="">Выберите...</option>
                    <?php foreach ($taskTypes as $task): ?>
                        <option value="<?= (int)$task['id'] ?>" <?= (string)$task['id'] === $form['task_type_id'] ? 'selected' : '' ?>>
                            #<?= e($task['task_number']) ?> <?= e($task['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Тема</label>
                <select class="form-select" name="topic_id">
                    <option value="">Без темы</option>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= (int)$topic['id'] ?>" <?= (string)$topic['id'] === $form['topic_id'] ? 'selected' : '' ?>>
                            <?= e($topic['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Заголовок</label>
                <input class="form-control" name="title" value="<?= e($form['title']) ?>" maxlength="255" required>
            </div>

            <div class="col-12">
                <label class="form-label">Текст вопроса (HTML)</label>
                <textarea class="form-control" name="body_html" rows="6" required><?= e($form['body_html']) ?></textarea>
            </div>

            <div class="col-12">
                <label class="form-label">Решение (HTML)</label>
                <textarea class="form-control" name="solution_html" rows="5"><?= e($form['solution_html']) ?></textarea>
            </div>

            <div class="col-md-6">
                <label class="form-label">Ответ</label>
                <input class="form-control" name="answer_text" value="<?= e($form['answer_text']) ?>" maxlength="255">
            </div>

            <div class="col-md-6">
                <label class="form-label">Источник</label>
                <input class="form-control" name="source" value="<?= e($form['source']) ?>" maxlength="255">
            </div>

            <div class="col-md-4">
                <label class="form-label">Сложность</label>
                <select class="form-select" name="difficulty">
                    <option value="easy" <?= $form['difficulty'] === 'easy' ? 'selected' : '' ?>>easy</option>
                    <option value="medium" <?= $form['difficulty'] === 'medium' ? 'selected' : '' ?>>medium</option>
                    <option value="hard" <?= $form['difficulty'] === 'hard' ? 'selected' : '' ?>>hard</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Публикация</label>
                <select class="form-select" name="is_published">
                    <option value="1" <?= $form['is_published'] === '1' ? 'selected' : '' ?>>published</option>
                    <option value="0" <?= $form['is_published'] === '0' ? 'selected' : '' ?>>unpublished</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Проверка</label>
                <select class="form-select" name="checked">
                    <option value="1" <?= $form['checked'] === '1' ? 'selected' : '' ?>>checked</option>
                    <option value="0" <?= $form['checked'] === '0' ? 'selected' : '' ?>>unchecked</option>
                </select>
            </div>

            <div class="col-12"><hr></div>

            <?php if ($mediaTable === ''): ?>
                <div class="col-12">
                    <div class="alert alert-warning mb-0">Таблица question_media/oge_question_media не найдена. Загрузка картинок отключена.</div>
                </div>
            <?php else: ?>
                <?php if (!empty($existingMedia)): ?>
                    <div class="col-12">
                        <label class="form-label">Текущие изображения</label>
                        <div class="vstack gap-2">
                            <?php foreach ($existingMedia as $media): ?>
                                <div class="border rounded p-2 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <div>
                                        <div class="small text-muted">#<?= (int)$media['id'] ?> · <?= e($media['role']) ?> · sort <?= (int)$media['sort_order'] ?></div>
                                        <div><a href="<?= e($media['file_path']) ?>" target="_blank" rel="noopener"><?= e($media['file_path']) ?></a></div>
                                        <?php if (!empty($media['alt_text'])): ?>
                                            <div class="small text-muted">alt: <?= e($media['alt_text']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delete_media_ids[]" value="<?= (int)$media['id'] ?>" id="media-del-<?= (int)$media['id'] ?>">
                                        <label class="form-check-label" for="media-del-<?= (int)$media['id'] ?>">Удалить</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label">Добавить картинки</label>
                    <input class="form-control" type="file" name="media_files[]" accept="image/*" multiple>
                    <small class="text-muted">Можно загрузить несколько изображений, каждое до 5MB.</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Роль изображения</label>
                    <select class="form-select" name="media_role">
                        <option value="question">question</option>
                        <option value="solution">solution</option>
                        <option value="hint">hint</option>
                        <option value="extra">extra</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Alt текст</label>
                    <input class="form-control" name="media_alt_text" maxlength="255" placeholder="Описание изображения">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Начальный sort_order</label>
                    <input class="form-control" type="number" name="media_sort_order" value="0" min="0">
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-outline-secondary">Отмена</a>
        <button class="btn btn-primary" type="submit">Сохранить изменения</button>
    </div>
</form>
