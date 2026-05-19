<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../authentication/auth.php';

require_role($mysqli, ['admin', 'teacher']);

$currentUserId = (int)get_current_user_id();
$currentRole = get_user_role();

$stats = [
	'total' => 0,
	'published' => 0,
	'unchecked' => 0,
];

if ($currentRole === 'admin') {
	$statsSql = "
		SELECT
			COUNT(*) AS total,
			SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published,
			SUM(CASE WHEN checked = 0 THEN 1 ELSE 0 END) AS unchecked
		FROM oge_questions
	";
	$statsResult = $mysqli->query($statsSql);
} else {
	$statsSql = "
		SELECT
			COUNT(*) AS total,
			SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published,
			SUM(CASE WHEN checked = 0 THEN 1 ELSE 0 END) AS unchecked
		FROM oge_questions
		WHERE created_by = ?
	";
	$stmtStats = $mysqli->prepare($statsSql);
	$stmtStats->bind_param('i', $currentUserId);
	$stmtStats->execute();
	$statsResult = $stmtStats->get_result();
}

if ($statsResult) {
	$row = $statsResult->fetch_assoc();
	if ($row) {
		$stats['total'] = (int)($row['total'] ?? 0);
		$stats['published'] = (int)($row['published'] ?? 0);
		$stats['unchecked'] = (int)($row['unchecked'] ?? 0);
	}
}

if (isset($stmtStats)) {
	$stmtStats->close();
}

$page_title = 'Панель преподавателя';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
	<div>
		<h1 class="h3 mb-1">Панель <?= $currentRole === 'admin' ? 'администратора' : 'преподавателя' ?></h1>
		<p class="text-muted mb-0">Управление вопросами и модерация контента.</p>
	</div>
	<a href="<?= SITE_URL ?>/admin/question-create.php" class="btn btn-primary">Добавить вопрос</a>
</div>

<div class="row g-3 mb-4">
	<div class="col-md-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<p class="text-muted mb-1">Всего вопросов</p>
				<p class="display-6 mb-0"><?= e($stats['total']) ?></p>
			</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<p class="text-muted mb-1">Опубликовано</p>
				<p class="display-6 mb-0 text-success"><?= e($stats['published']) ?></p>
			</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="card border-0 shadow-sm h-100">
			<div class="card-body">
				<p class="text-muted mb-1">Непроверенные</p>
				<p class="display-6 mb-0 text-warning"><?= e($stats['unchecked']) ?></p>
			</div>
		</div>
	</div>
</div>

<div class="card border-0 shadow-sm">
	<div class="card-body">
		<h2 class="h5 mb-3">Разделы управления</h2>
		<div class="d-flex flex-wrap gap-2">
			<a class="btn btn-outline-primary" href="<?= SITE_URL ?>/admin/questions.php">Мои вопросы<?= $currentRole === 'admin' ? ' / Все вопросы' : '' ?></a>
			<a class="btn btn-outline-secondary" href="<?= SITE_URL ?>/teachers.php">Teacher Dashboard</a>
			<a class="btn btn-outline-success" href="<?= SITE_URL ?>/practice.php">Открыть практику</a>
		</div>
	</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
