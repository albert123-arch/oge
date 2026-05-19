<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/authentication/auth.php';

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

function table_exists(mysqli $mysqli, string $table): bool {
    $safe = $mysqli->real_escape_string($table);
    $result = $mysqli->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $result && $result->num_rows > 0;
    if ($result) { $result->free(); }
    return $exists;
}

$variants = [];
$errorMessage = '';

try {
    if (table_exists($mysqli, 'oge_variants')) {
        $result = $mysqli->query("SELECT * FROM oge_variants WHERE is_published = 1 ORDER BY id DESC");
        while ($row = $result->fetch_assoc()) {
            $variants[] = $row;
        }
        $result->free();
    }
} catch (Throwable $exception) {
    $errorMessage = 'Не удалось загрузить варианты.';
}

$page_title = 'Пробные варианты';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Пробные варианты</h1>
        <p class="text-muted mb-0">Раздел для полных вариантов ОГЭ.</p>
    </div>
    <a class="btn btn-outline-primary" href="/practice.php">Практика</a>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-warning"><?= e($errorMessage) ?></div>
<?php endif; ?>

<?php if (empty($variants)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5">Варианты пока не добавлены</h2>
            <p class="text-muted mb-0">Позже здесь можно хранить полные пробники: вариант, задачи, ответы и результаты.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($variants as $variant): ?>
            <div class="col-md-6 col-xl-4">
                <a class="text-decoration-none text-reset" href="/variant.php?id=<?= (int)$variant['id'] ?>">
                    <article class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-2"><?= e($variant['title'] ?? ('Вариант #' . $variant['id'])) ?></h2>
                            <?php if (!empty($variant['description'])): ?>
                                <p class="text-muted mb-0"><?= e($variant['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
