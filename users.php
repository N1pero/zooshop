<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'admin_header.php';

function user_status_text($user) {
    if ((int)($user['is_blocked'] ?? 0) !== 1) return 'Активен';
    $until = trim((string)($user['blocked_until'] ?? ''));
    if ($until !== '') return 'Заблокирован до ' . $until;
    return 'Заблокирован бессрочно';
}

$currentAdminId = (int)($_SESSION['admin_panel_id'] ?? 0);

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'block_user') {
        $reason = trim($_POST['block_reason'] ?? '');
        $blockedUntilRaw = trim($_POST['blocked_until'] ?? '');
        $blockedUntil = $blockedUntilRaw !== '' ? str_replace('T', ' ', $blockedUntilRaw) . ':00' : null;
        $userCheck = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $userCheck->bind_param('i', $userId);
        $userCheck->execute();
        $targetUser = $userCheck->get_result()->fetch_assoc();
        if (!$targetUser || $userId <= 0 || (int)$targetUser['is_admin'] === 1) {
            admin_flash('Администратора нельзя заблокировать.', 'error');
        } else {
            $stmt = $conn->prepare("UPDATE users SET is_blocked = 1, block_reason = ?, blocked_until = ? WHERE id = ?");
            $stmt->bind_param('ssi', $reason, $blockedUntil, $userId);
            $stmt->execute();
            admin_flash('Пользователь заблокирован.');
        }
        header('Location: users.php#users');
        exit;
    }

    if ($action === 'restore_user') {
        if ($userId > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_blocked = 0, block_reason = NULL, blocked_until = NULL WHERE id = ? AND is_admin = 0");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            admin_flash('Пользователь разблокирован.');
        }
        header('Location: users.php#users');
        exit;
    }

    if ($action === 'delete_user') {
        if ($userId > 0 && $userId !== $currentAdminId) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            admin_flash('Пользователь удален.');
        } else {
            admin_flash('Нельзя удалить текущего администратора.', 'error');
        }
        header('Location: users.php#users');
        exit;
    }
}

$users = $conn->query("SELECT id, login, email, registration_date, is_admin, is_blocked, block_reason, blocked_until FROM users ORDER BY login");
?>

<section class="card" id="users">
    <h2>Пользователи</h2>
    <div class="table-wrap">
        <table class="admin-users-table">
            <thead><tr><th>ID</th><th>Логин</th><th>E-mail</th><th>Дата регистрации</th><th>Роль</th><th>Статус</th><th>Действия</th></tr></thead>
            <tbody>
            <?php while ($user = $users->fetch_assoc()):
                $userId = (int)$user['id'];
                $isAdmin = (int)$user['is_admin'] === 1;
                $isBlocked = (int)($user['is_blocked'] ?? 0) === 1;
                $blockedInputValue = '';
                if (!empty($user['blocked_until'])) {
                    $blockedTimestamp = strtotime($user['blocked_until']);
                    if ($blockedTimestamp !== false) {
                        $blockedInputValue = date('Y-m-d\TH:i', $blockedTimestamp);
                    }
                }
            ?>
            <tr>
                <td><?php echo $userId; ?></td>
                <td><strong><?php echo e($user['login']); ?></strong></td>
                <td><?php echo e($user['email']); ?></td>
                <td><?php echo e($user['registration_date']); ?></td>
                <td><?php echo $isAdmin ? 'Администратор' : 'Пользователь'; ?></td>
                <td><span class="user-status <?php echo $isBlocked ? 'locked' : 'active'; ?>"><?php echo e(user_status_text($user)); ?></span><?php if ($isBlocked && !empty($user['block_reason'])): ?><div class="muted">Причина: <?php echo e($user['block_reason']); ?></div><?php endif; ?></td>
                <td class="actions vertical-actions">
                    <?php if (!$isAdmin): ?>
                        <details class="lock-details">
                            <summary class="btn btn-small btn-warning"><?php echo $isBlocked ? 'Изменить блокировку' : 'Заблокировать'; ?></summary>
                            <form method="POST" class="lock-form">
                                <input type="hidden" name="action" value="block_user">
                                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                <label>Причина</label><input type="text" name="block_reason" value="<?php echo e($user['block_reason'] ?? ''); ?>" placeholder="Например: нарушение правил">
                                <label>Заблокировать до</label><input type="datetime-local" name="blocked_until" value="<?php echo e($blockedInputValue); ?>">
                                <button class="btn btn-danger btn-small" type="submit"><?php echo $isBlocked ? 'Сохранить блокировку' : 'Подтвердить блокировку'; ?></button>
                            </form>
                        </details>
                        <?php if ($isBlocked): ?>
                            <form method="POST"><input type="hidden" name="action" value="restore_user"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><button class="btn btn-success btn-small" type="submit">Разблокировать</button></form>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('Удалить пользователя? Его заказы тоже будут удалены.');"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><button class="btn btn-danger btn-small" type="submit">Удалить</button></form>
                    <?php else: ?>
                        <span class="muted">Администратор защищен</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once 'admin_footer.php'; ?>