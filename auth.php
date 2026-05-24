<?php
function current_user($conn)
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, login, email, photo, registration_date, is_admin FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function require_login($conn)
{
    if (!is_logged_in()) {
        flash_set('Для просмотра страницы нужно войти в аккаунт.', 'error');
        header('Location: ' . url_to('auth/login.php'));
        exit;
    }
}

function require_admin($conn)
{
    $user = current_user($conn);

    if (!$user || (int)$user['is_admin'] !== 1) {
        flash_set('Доступ разрешен только администратору.', 'error');
        header('Location: ' . url_to('index.php'));
        exit;
    }
}
?>
