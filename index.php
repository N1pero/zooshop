<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_panel_id'])) {
    header('Location: panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, login, password_hash, is_admin FROM users WHERE login = ? AND is_admin = 1");
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_panel_id'] = (int)$admin['id'];
        $_SESSION['admin_panel_login'] = $admin['login'];
        header('Location: panel.php');
        exit;
    }

    $error = 'Неверный логин или пароль администратора.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админ-панель — ZOOSHOP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-login-page">
    <section class="admin-login-card">
        <h1>Административная панель</h1>
        <p class="muted">Вход выполняется отдельно от пользовательской части сайта.</p>
        <p class="muted">Демо: <strong>admin</strong> / <strong>admin123</strong></p>

        <?php if ($error): ?>
            <div class="flash error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <div class="form-group">
                <label>Логин администратора</label>
                <input type="text" name="login" required>
            </div>
            <div class="form-group">
                <label>Пароль администратора</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn btn-success" type="submit">Войти в админ-панель</button>
            <a class="btn btn-secondary" href="../index.php">Вернуться на сайт</a>
        </form>
    </section>
</body>
</html>
