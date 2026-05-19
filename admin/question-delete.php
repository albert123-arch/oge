<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../authentication/auth.php';

require_role($mysqli, ['admin', 'teacher']);

$currentUserId = (int)get_current_user_id();
$currentRole = get_user_role();
$questionId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

if ($questionId <= 0) {
    http_response_code(404);
    die('Вопрос не найден');
}

$sql = "SELECT id, title, created_by FROM oge_questions WHERE id = ?";
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sqlDelete = "DELETE FROM oge_questions WHERE id = ?";
    $typesDelete = 'i';
    $paramsDelete = [$questionId];

    if ($currentRole !== 'admin') {
        $sqlDelete .= " AND created_by = ?";
        $typesDelete .= 'i';
        $paramsDelete[] = $currentUserId;
    }

    $stmtDelete = $mysqli->prepare($sqlDelete);
    $stmtDelete->bind_param($typesDelete, ...$paramsDelete);
    $stmtDelete->execute();
    $stmtDelete->close();

    set_flash_message('success', 'Вопрос удален.');
    header('Location: ' . SITE_URL . '/admin/questions.php');
    exit();
}

$page_title = 'Удаление вопроса';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h1 class="h4">Удалить вопрос #<?= e($question['id']) ?>?</h1>
        <p class="mb-4">Вы собираетесь удалить вопрос: <strong><?= e($question['title']) ?></strong>.</p>
        <form method="post" class="d-flex gap-2">
            <input type="hidden" name="id" value="<?= (int)$question['id'] ?>">
            <a class="btn btn-outline-secondary" href="<?= SITE_URL ?>/admin/questions.php">Отмена</a>
            <button class="btn btn-danger" type="submit">Удалить</button>
        </form>
    </div>
</div>
