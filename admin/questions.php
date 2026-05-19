<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../authentication/auth.php';

require_role($mysqli, ['admin', 'teacher']);

$currentUserId = (int)get_current_user_id();
$currentRole = get_user_role();

$difficulty = trim($_GET['difficulty'] ?? '');
$published = $_GET['is_published'] ?? '';
$checked = $_GET['checked'] ?? '';

$allowedDifficulty = ['easy', 'medium', 'hard'];
if (!in_array($difficulty, $allowedDifficulty, true)) {
	$difficulty = '';
}

$sql = "
	SELECT
		q.id,
		q.title,
		q.difficulty,
		q.is_published,
		q.checked,
		q.created_at,
		q.created_by,
		tt.task_number,
		tt.title AS task_title,
		u.full_name
	FROM oge_questions q
	JOIN oge_task_types tt ON tt.id = q.task_type_id
	LEFT JOIN oge_users u ON u.id = q.created_by
	WHERE 1=1
";

$types = '';
$params = [];

if ($currentRole !== 'admin') {
	$sql .= " AND q.created_by = ?";
	$types .= 'i';
	$params[] = $currentUserId;
}

if ($difficulty !== '') {
	$sql .= " AND q.difficulty = ?";
	$types .= 's';
	$params[] = $difficulty;
}

if ($published === '0' || $published === '1') {
	$sql .= " AND q.is_published = ?";
	$types .= 'i';
	$params[] = (int)$published;
}

if ($checked === '0' || $checked === '1') {
	$sql .= " AND q.checked = ?";
	$types .= 'i';
	$params[] = (int)$checked;
}

$sql .= " ORDER BY q.created_at DESC";

$stmt = $mysqli->prepare($sql);
if ($types !== '') {
	$stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$flash = get_flash_message();

$page_title = 'Управление вопросами';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
	<div>
		<h1 class="h3 mb-1">Управление вопросами</h1>
		<p class="text-muted mb-0"><?= $currentRole === 'admin' ? 'Все вопросы в системе' : 'Ваши вопросы' ?></p>
	</div>
	<a href="<?= SITE_URL ?>/admin/question-create.php" class="btn btn-primary">Добавить вопрос</a>
</div>

<?php if ($flash): ?>
	<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
		<?= e($flash['message']) ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
	<div class="card-body">
		<form method="get" class="row g-2 align-items-end">
			<div class="col-md-3">
				<label class="form-label">Сложность</label>
				<select name="difficulty" class="form-select">
					<option value="">Все</option>
					<option value="easy" <?= $difficulty === 'easy' ? 'selected' : '' ?>>easy</option>
					<option value="medium" <?= $difficulty === 'medium' ? 'selected' : '' ?>>medium</option>
					<option value="hard" <?= $difficulty === 'hard' ? 'selected' : '' ?>>hard</option>
				</select>
			</div>
			<div class="col-md-3">
				<label class="form-label">Публикация</label>
				<select name="is_published" class="form-select">
					<option value="">Все</option>
					<option value="1" <?= $published === '1' ? 'selected' : '' ?>>Опубликованные</option>
					<option value="0" <?= $published === '0' ? 'selected' : '' ?>>Скрытые</option>
				</select>
			</div>
			<div class="col-md-3">
				<label class="form-label">Проверка</label>
				<select name="checked" class="form-select">
					<option value="">Все</option>
					<option value="1" <?= $checked === '1' ? 'selected' : '' ?>>Проверенные</option>
					<option value="0" <?= $checked === '0' ? 'selected' : '' ?>>Непроверенные</option>
				</select>
			</div>
			<div class="col-md-3 d-grid">
				<button class="btn btn-outline-primary" type="submit">Применить</button>
			</div>
		</form>
	</div>
</div>

<div class="card border-0 shadow-sm">
	<div class="table-responsive">
		<table class="table align-middle mb-0">
			<thead>
				<tr>
					<th>ID</th>
					<th>Задание</th>
					<th>Вопрос</th>
					<th>Сложность</th>
					<th>Статус</th>
					<?php if ($currentRole === 'admin'): ?>
						<th>Автор</th>
					<?php endif; ?>
					<th class="text-end">Действия</th>
				</tr>
			</thead>
			<tbody>
				<?php while ($row = $result->fetch_assoc()): ?>
					<tr>
						<td><?= e($row['id']) ?></td>
						<td>#<?= e($row['task_number']) ?> <?= e($row['task_title']) ?></td>
						<td><?= e($row['title']) ?></td>
						<td><span class="badge text-bg-light"><?= e($row['difficulty']) ?></span></td>
						<td>
							<?= (int)$row['is_published'] === 1 ? '<span class="badge text-bg-success">published</span>' : '<span class="badge text-bg-secondary">unpublished</span>' ?>
							<?= (int)$row['checked'] === 1 ? '<span class="badge text-bg-primary">checked</span>' : '<span class="badge text-bg-warning">unchecked</span>' ?>
						</td>
						<?php if ($currentRole === 'admin'): ?>
							<td><?= e($row['full_name'] ?: '—') ?></td>
						<?php endif; ?>
						<td class="text-end">
							<a class="btn btn-sm btn-outline-primary" href="<?= SITE_URL ?>/admin/question-edit.php?id=<?= (int)$row['id'] ?>">Изменить</a>
							<a class="btn btn-sm btn-outline-danger" href="<?= SITE_URL ?>/admin/question-delete.php?id=<?= (int)$row['id'] ?>">Удалить</a>
						</td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
