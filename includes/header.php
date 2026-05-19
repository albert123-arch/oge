<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../authentication/auth.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
function nav_active(string $path, string $currentPath): string {
    return $path === $currentPath ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="icon" href="/assets/icons/favicon.ico" sizes="any">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <link rel="manifest" href="/assets/icons/site.webmanifest">
    <link rel="mask-icon" href="/assets/icons/safari-pinned-tab.svg" color="#0f766e">
    <meta name="theme-color" content="#0f766e">
    <meta property="og:image" content="https://oge.maths4u.sbs/assets/icons/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    <?php require_once __DIR__ . '/mathjax.php'; ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg site-navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/">
                <span class="brand-mark">9</span>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php if (is_user_logged_in()): ?>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('/practice.php', $currentPath); ?>" href="<?php echo SITE_URL; ?>/practice.php">Практика</a></li>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('/tasks.php', $currentPath); ?>" href="<?php echo SITE_URL; ?>/tasks.php">Задания</a></li>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('/variants.php', $currentPath); ?>" href="<?php echo SITE_URL; ?>/variants.php">Варианты</a></li>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('/bookmarks.php', $currentPath); ?>" href="<?php echo SITE_URL; ?>/bookmarks.php">Закладки</a></li>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('/results.php', $currentPath); ?>" href="<?php echo SITE_URL; ?>/results.php">Результаты</a></li>
                        <?php if (get_user_role() === 'admin' || get_user_role() === 'teacher'): ?>
                            <li class="nav-item"><a class="nav-link<?php echo nav_active('/teachers.php', $currentPath); ?>" href="<?php echo SITE_URL; ?>/teachers.php">Учителю</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><span class="nav-link user-pill"><?php echo htmlspecialchars(get_user_name()); ?></span></li>
                        <li class="nav-item"><a class="btn btn-sm btn-outline-dark" href="<?php echo SITE_URL; ?>/authentication/logout.php">Выход</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/topics.php">Темы</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/tasks.php">Задания</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/authentication/login.php">Вход</a></li>
                        <li class="nav-item"><a class="btn btn-sm btn-primary" href="<?php echo SITE_URL; ?>/authentication/register.php">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="site-main">
        <div class="container">
