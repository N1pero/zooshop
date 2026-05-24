<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Авторизация — ZOOSHOP';
$activePage = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, password_hash, is_blocked, block_reason, blocked_until FROM users WHERE login = ?");
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ((int)($user['is_blocked'] ?? 0) === 1) {
            $until = trim((string)($user['blocked_until'] ?? ''));
            $reason = trim((string)($user['block_reason'] ?? ''));
            $error = 'Аккаунт заблокирован' . ($until !== '' ? ' до ' . $until : '') . ($reason !== '' ? '. Причина: ' . $reason : '.');

            // ЛР5: пример пользовательского уведомления вне error_demo.php.
            // Ошибка уходит в set_error_handler() из includes/error_handler.php и записывается в журнал.
            trigger_error('Попытка входа в заблокированный аккаунт: ' . $login, E_USER_NOTICE);
        } else {
            $userId = (int)$user['id'];
            $oldSessionToken = session_id();

            $_SESSION['user_id'] = $userId;

        $guestStmt = $conn->prepare("SELECT id, product_id, quantity FROM cart_items WHERE session_token = ? AND user_id IS NULL");
        $guestStmt->bind_param('s', $oldSessionToken);
        $guestStmt->execute();
        $guestItems = $guestStmt->get_result();

        while ($guest = $guestItems->fetch_assoc()) {
            $productId = (int)$guest['product_id'];
            $quantity = (int)$guest['quantity'];

            $existStmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
            $existStmt->bind_param('ii', $userId, $productId);
            $existStmt->execute();
            $existing = $existStmt->get_result()->fetch_assoc();

            if ($existing) {
                $newQty = (int)$existing['quantity'] + $quantity;
                $upd = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $upd->bind_param('ii', $newQty, $existing['id']);
                $upd->execute();

                $del = $conn->prepare("DELETE FROM cart_items WHERE id = ?");
                $del->bind_param('i', $guest['id']);
                $del->execute();
            } else {
                $upd = $conn->prepare("UPDATE cart_items SET user_id = ? WHERE id = ?");
                $upd->bind_param('ii', $userId, $guest['id']);
                $upd->execute();
            }
        }

        session_regenerate_id(true);

            flash_set('Вы вошли в аккаунт.');
            header('Location: ../pages/profile.php');
            exit;
        }
    }

    if ($error === '') {
        $error = 'Неверный логин или пароль.';

        // ЛР5: пример пользовательского уведомления при неверной авторизации.
        // Используется E_USER_NOTICE из массива типов ошибок в error_handler.php.
        trigger_error('Неуспешная попытка авторизации для логина: ' . $login, E_USER_NOTICE);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h2>Авторизация</h2>
    <p>
        Демо-пользователь: <strong>demo</strong> / <strong>123456</strong><br>
        Администратор: <strong>admin</strong> / <strong>admin123</strong>
    </p>

    <?php if ($error): ?>
        <div class="flash error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="form-grid">
        <div class="form-group">
            <label>Логин</label>
            <input type="text" name="login" required>
        </div>

        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="password" required>
        </div>

        <button class="btn btn-success" type="submit">Войти</button>
        <a class="btn btn-secondary" href="register.php">Регистрация</a>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
