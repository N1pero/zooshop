<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Изменить пароль — ZOOSHOP';
$activePage = '';
require_login($conn);

$user = current_user($conn);
$userId = (int)$user['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Введите текущий пароль.';
    }

    if (mb_strlen($newPassword) < 6) {
        $errors[] = 'Новый пароль должен содержать минимум 6 символов.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Новый пароль и подтверждение не совпадают.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $passwordRow = $stmt->get_result()->fetch_assoc();

        if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password_hash'])) {
            $errors[] = 'Текущий пароль введен неверно.';
        } elseif (password_verify($newPassword, $passwordRow['password_hash'])) {
            $errors[] = 'Новый пароль должен отличаться от текущего.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $newHash, $userId);
            $stmt->execute();

            flash_set('Пароль успешно изменен.');
            header('Location: profile.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="form-card">
    <h2>Изменить пароль</h2>
    <p class="muted">Введите текущий пароль, затем новый пароль и подтверждение.</p>

    <form method="POST" class="form-grid" style="margin-top: 16px;">
        <div class="form-group">
            <label for="current_password">Текущий пароль</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
            <label for="new_password">Новый пароль</label>
            <input type="password" id="new_password" name="new_password" minlength="6" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Повторите новый пароль</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
        </div>

        <div class="actions">
            <button class="btn btn-success" type="submit">Сменить пароль</button>
            <a class="btn btn-secondary" href="profile.php">Назад в профиль</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
