<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/auth.php';

// Если уже авторизован - редирект на главную
if (is_user_logged_in()) {
    header('Location: ' . SITE_URL . '/');
    exit();
}

$error = '';
$success = '';
$email = '';
$full_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Валидация
    if (empty($email) || empty($full_name) || empty($password) || empty($password_confirm)) {
        $error = 'Пожалуйста, заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email некорректен';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают';
    } else {
        // Попытка регистрации
        $result = register_user($email, $password, $full_name);
        if ($result['success']) {
            // Авторизовать сразу после регистрации
            $login_result = login_user($email, $password);
            if ($login_result['success']) {
                header('Location: ' . SITE_URL . '/');
                exit();
            } else {
                $success = $result['message'] . ' Пожалуйста, <a href="' . SITE_URL . '/authentication/login.php">войдите в систему</a>.';
            }
        } else {
            $error = $result['message'];
        }
    }
}

$page_title = 'Регистрация';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-lg">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Регистрация</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Полное имя</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="full_name" 
                            name="full_name" 
                            value="<?php echo htmlspecialchars($full_name); ?>" 
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($email); ?>" 
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            minlength="6"
                            required
                        >
                        <small class="text-muted">Минимум 6 символов</small>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Подтверждение пароля</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_confirm" 
                            name="password_confirm" 
                            minlength="6"
                            required
                        >
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">Зарегистрироваться</button>
                    </div>
                </form>

                <hr>

                <p class="text-center mb-0">
                    Уже есть аккаунт? 
                    <a href="<?php echo SITE_URL; ?>/authentication/login.php" class="text-decoration-none">Войдите в систему</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
