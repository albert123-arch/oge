<?php
require_once __DIR__ . '/../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Синхронизировать данные пользователя из БД в сессию.
 */
function sync_current_user_session() {
    global $mysqli;

    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return;
    }

    $userId = (int)$_SESSION['user_id'];
    if ($userId <= 0) {
        return;
    }

    $stmt = $mysqli->prepare("SELECT email, full_name, role, status FROM oge_users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || ($user['status'] ?? '') !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['full_name'], $_SESSION['role']);
        return;
    }

    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
}

/**
 * Хешировать пароль
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Проверить пароль
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Зарегистрировать нового пользователя
 */
function register_user($email, $password, $full_name) {
    global $mysqli;
    
    // Проверить, существует ли email
    $stmt = $mysqli->prepare("SELECT id FROM oge_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Пользователь с таким email уже существует'];
    }
    $stmt->close();
    
    // Вставить нового пользователя
    $password_hash = hash_password($password);
    $role = 'student';
    $status = 'active';
    
    $stmt = $mysqli->prepare(
        "INSERT INTO oge_users (email, password_hash, full_name, role, status) 
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $email, $password_hash, $full_name, $role, $status);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();
        return ['success' => true, 'message' => 'Пользователь успешно зарегистрирован', 'user_id' => $user_id];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Ошибка при регистрации: ' . $mysqli->error];
    }
}

/**
 * Авторизовать пользователя
 */
function login_user($email, $password) {
    global $mysqli;
    
    $stmt = $mysqli->prepare(
        "SELECT id, password_hash, full_name, role, status FROM oge_users WHERE email = ? AND status = 'active'"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Email или пароль неверны'];
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!verify_password($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Email или пароль неверны'];
    }
    
    // Обновить last_login_at
    $user_id = $user['id'];
    $stmt = $mysqli->prepare("UPDATE oge_users SET last_login_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Установить сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $email;
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    
    return ['success' => true, 'message' => 'Успешная авторизация', 'user_id' => $user['id']];
}

/**
 * Проверить, авторизован ли пользователь
 */
function is_user_logged_in() {
    // Поддержка старых ключей сессии.
    if (!isset($_SESSION['user_id']) && isset($_SESSION['oge_user_id'])) {
        $_SESSION['user_id'] = $_SESSION['oge_user_id'];
    }
    if (!isset($_SESSION['role']) && isset($_SESSION['oge_role'])) {
        $_SESSION['role'] = $_SESSION['oge_role'];
    }

    static $isSynced = false;
    if (!$isSynced && isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        sync_current_user_session();
        $isSynced = true;
    }

    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Получить ID текущего пользователя
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Получить email текущего пользователя
 */
function get_user_email() {
    return $_SESSION['email'] ?? null;
}

/**
 * Получить полное имя текущего пользователя
 */
function get_user_name() {
    return $_SESSION['full_name'] ?? 'Пользователь';
}

/**
 * Получить роль текущего пользователя
 */
function get_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Требовать, чтобы пользователь был авторизован
 */
function require_login($mysqli = null) {
    if (!is_user_logged_in()) {
        header('Location: ' . SITE_URL . '/authentication/login.php');
        exit();
    }
}

/**
 * Требовать, чтобы пользователь имел одну из ролей
 */
function require_role($mysqli = null, array $roles = []) {
    require_login();

    if (empty($roles)) {
        return;
    }

    if (!in_array(get_user_role(), $roles, true)) {
        http_response_code(403);
        die('Доступ запрещен');
    }
}

/**
 * Требовать, чтобы пользователь был администратором
 */
function require_admin() {
    require_role(null, ['admin']);
}

/**
 * Требовать, чтобы пользователь был администратором или учителем
 */
function require_teacher() {
    require_role(null, ['admin', 'teacher']);
}

/**
 * Завершить сессию пользователя
 */
function logout_user() {
    session_destroy();
}
