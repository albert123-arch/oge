<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/authentication/auth.php';

function fetch_one(mysqli $mysqli, string $sql): ?array {
    try {
        $result = $mysqli->query($sql);
    } catch (Throwable $exception) {
        return null;
    }
    $row = $result->fetch_assoc();
    $result->free();
    return $row ?: null;
}

function fetch_all(mysqli $mysqli, string $sql): array {
    try {
        $result = $mysqli->query($sql);
    } catch (Throwable $exception) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

$page = fetch_one($mysqli, "
    SELECT title, meta_description, h1, intro_html
    FROM oge_pages
    WHERE slug = 'home' AND is_published = 1
    LIMIT 1
");

$homeBlocks = fetch_all($mysqli, "
    SELECT title, body_html, button_text, button_url
    FROM oge_home_blocks
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
");

$topics = fetch_all($mysqli, "
    SELECT slug, title, short_description, icon
    FROM oge_topics
    WHERE is_active = 1
    ORDER BY sort_order ASC, title ASC
    LIMIT 6
");

$taskTypes = fetch_all($mysqli, "
    SELECT task_number, title, short_description
    FROM oge_task_types
    WHERE is_active = 1
    ORDER BY task_number ASC
");

$page_title = $page['title'] ?? 'Подготовка к ОГЭ по математике';
$pageDescription = $page['meta_description'] ?? 'Тренировка заданий, варианты, темы и личный прогресс для подготовки к ОГЭ по математике.';
$h1 = $page['h1'] ?? 'ОГЭ по математике без суеты';
$introHtml = $page['intro_html'] ?? '<p>Разбирайте темы, тренируйтесь по номерам и собирайте уверенность перед экзаменом в одном кабинете Maths4U.</p>';

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero-oge">
    <div class="row g-4 align-items-end">
        <div class="col-lg-8">
            <p class="text-uppercase fw-bold small text-muted mb-3">Maths4U для 9 класса</p>
            <h1><?= e($h1) ?></h1>
            <div class="lead mt-3"><?= $introHtml ?></div>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="btn btn-main btn-lg" href="/practice.php">Начать практику</a>
                <a class="btn btn-outline-primary btn-lg" href="/tasks.php">Задания по номерам</a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="hero-panel p-4">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div>
                        <div class="metric"><?= count($taskTypes) ?></div>
                        <div class="text-muted">номеров в базе</div>
                    </div>
                    <div>
                        <div class="metric"><?= count($topics) ?></div>
                        <div class="text-muted">тем открыто</div>
                    </div>
                </div>
                <p class="mb-0 text-muted">База стартует пустой: добавьте темы, задания и варианты через кабинет учителя или админку.</p>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 class="h3 section-title mb-0">Разделы подготовки</h2>
        <a class="btn btn-sm btn-outline-primary" href="/topics.php">Все темы</a>
    </div>
    <div class="row g-3">
        <?php
        $fallbackBlocks = [
            ['title' => 'Темы', 'body_html' => '<p>Соберите теорию и типовые приемы по школьной программе.</p>', 'button_text' => 'Открыть темы', 'button_url' => '/topics.php'],
            ['title' => 'Задания', 'body_html' => '<p>Разложите подготовку по номерам ОГЭ и уровню сложности.</p>', 'button_text' => 'Выбрать номер', 'button_url' => '/tasks.php'],
            ['title' => 'Варианты', 'body_html' => '<p>Публикуйте тренировочные и диагностические работы для учеников.</p>', 'button_text' => 'Смотреть варианты', 'button_url' => '/variants.php'],
        ];
        $blocksToRender = $homeBlocks ?: $fallbackBlocks;
        ?>
        <?php foreach ($blocksToRender as $block): ?>
            <div class="col-md-4">
                <article class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5"><?= e($block['title']) ?></h3>
                        <div class="text-muted"><?= $block['body_html'] ?></div>
                        <?php if (!empty($block['button_url']) && !empty($block['button_text'])): ?>
                            <a class="btn btn-sm btn-primary mt-2" href="<?= e($block['button_url']) ?>"><?= e($block['button_text']) ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mb-5">
    <h2 class="h3 section-title mb-3">Темы</h2>
    <?php if (empty($topics)): ?>
        <div class="empty-state">
            <h3 class="h5">Темы пока не добавлены</h3>
            <p class="mb-0 text-muted">После импорта или ручного добавления в админке здесь появятся алгебра, геометрия, статистика и другие разделы ОГЭ.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($topics as $topic): ?>
                <div class="col-md-6 col-xl-4">
                    <a class="text-decoration-none text-reset" href="/topic.php?slug=<?= e($topic['slug']) ?>">
                        <article class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h3 class="h5"><?= e($topic['title']) ?></h3>
                                <p class="text-muted mb-0"><?= e($topic['short_description'] ?? '') ?></p>
                            </div>
                        </article>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section>
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 class="h3 section-title mb-0">Задания по номерам</h2>
        <a class="btn btn-sm btn-outline-primary" href="/tasks.php">Открыть список</a>
    </div>
    <?php if (empty($taskTypes)): ?>
        <div class="empty-state">
            <h3 class="h5">Номера заданий еще не настроены</h3>
            <p class="mb-0 text-muted">Схема базы готова. Добавьте структуру ОГЭ через SQL или админку, и карточки появятся автоматически.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach (array_slice($taskTypes, 0, 8) as $task): ?>
                <div class="col-sm-6 col-lg-3">
                    <a class="text-decoration-none text-reset" href="/task.php?number=<?= (int)$task['task_number'] ?>">
                        <article class="card h-100 shadow-sm">
                            <div class="card-body">
                                <span class="badge text-bg-primary mb-2">№<?= e($task['task_number']) ?></span>
                                <h3 class="h6"><?= e($task['title']) ?></h3>
                                <p class="small text-muted mb-0"><?= e($task['short_description'] ?? '') ?></p>
                            </div>
                        </article>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
