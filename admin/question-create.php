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

$form = [
    'task_type_id' => '',
    'topic_id' => '',
    'title' => '',
    'body_html' => '',
    'solution_html' => '',
    'answer_text' => '',
    'difficulty' => 'medium',
    'source' => '',
    'is_published' => '1',
    'checked' => '0',
];
$error = '';
$mediaTable = detect_question_media_table($mysqli);

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
    $createdBy = (int)get_current_user_id();

    if ($taskTypeId <= 0 || $title === '' || $bodyHtml === '') {
        $error = 'Заполните обязательные поля: задание, заголовок и текст вопроса.';
    } else {
        $stmt = $mysqli->prepare(
            "INSERT INTO oge_questions (
                task_type_id, topic_id, title, body_html, solution_html, answer_text,
                difficulty, source, created_by, is_published, checked
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            'iissssssiii',
            $taskTypeId,
            $topicId,
            $title,
            $bodyHtml,
            $solutionHtml,
            $answerText,
            $difficulty,
            $source,
            $createdBy,
            $isPublished,
            $checked
        );

        $stmt->execute();
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        $mediaRole = $_POST['media_role'] ?? 'question';
        if (!in_array($mediaRole, ['question', 'solution', 'hint', 'extra'], true)) {
            $mediaRole = 'question';
        }

        $mediaAltText = trim((string)($_POST['media_alt_text'] ?? ''));
        $mediaSortOrder = (int)($_POST['media_sort_order'] ?? 0);

        $mediaResult = upload_question_media_files($mysqli, $newId, $taskTypeId, $mediaTable, $mediaRole, $mediaAltText, $mediaSortOrder);

        $flashMessage = 'Вопрос успешно создан (ID: ' . $newId . ').';
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

$page_title = 'Создать вопрос';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Создать вопрос</h1>
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

            <div class="col-12">
                <label class="form-label">Картинки (question_media)</label>
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
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= SITE_URL ?>/admin/questions.php" class="btn btn-outline-secondary">Отмена</a>
        <button class="btn btn-primary" type="submit">Сохранить вопрос</button>
    </div>
</form>
