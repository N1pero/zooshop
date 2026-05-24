<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Личный кабинет — ZOOSHOP';
$activePage = '';

// ЛР5: демонстрация фатальной ошибки вне pages/error_demo.php.
// Для проверки открыть: /pages/profile.php?lab5_fatal=1
// require несуществующего файла вызывает фатальную ошибку, которую фиксирует register_shutdown_function().
if (isset($_GET['lab5_fatal']) && $_GET['lab5_fatal'] === '1') {
    require __DIR__ . '/../includes/not_existing_lab5_file.php';
}

require_login($conn);

$user = current_user($conn);
$userId = (int)$user['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_photo'])) {
    if (empty($_FILES['photo']['name'])) {
        $errors[] = 'Выберите изображение для загрузки.';
    } else {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Фото должно быть в формате jpg, jpeg, png, gif или webp.';
        } else {
            $fileName = 'user_' . $userId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $targetDir = __DIR__ . '/../images/users/';
            $targetPath = $targetDir . $fileName;

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $newPhoto = 'images/users/' . $fileName;

                if (!empty($user['photo'])) {
                    $oldPath = __DIR__ . '/../' . $user['photo'];
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $stmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $stmt->bind_param('si', $newPhoto, $userId);
                $stmt->execute();

                flash_set('Фото профиля обновлено.');
                header('Location: profile.php');
                exit;
            } else {
                $errors[] = 'Не удалось загрузить файл.';
            }
        }
    }
}

$user = current_user($conn);

$stmt = $conn->prepare("
    SELECT id, order_date, total_amount, status, customer_name, phone, address
    FROM orders
    WHERE user_id = ?
    ORDER BY order_date DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<section class="card">
    <div class="profile-head profile-head-extended">
        <div class="profile-photo-block">
            <?php if ($user['photo']): ?>
                <img class="profile-photo" src="<?php echo e(url_to($user['photo'])); ?>" alt="<?php echo e($user['login']); ?>">
            <?php else: ?>
                <div class="profile-photo no-photo">Нет фото</div>
            <?php endif; ?>
        </div>

        <div class="profile-info-block">
            <h2 class="page-title">Личный кабинет</h2>
            <p><strong>Логин:</strong> <?php echo e($user['login']); ?></p>
            <p><strong>E-mail:</strong> <?php echo e($user['email']); ?></p>
            <p><strong>Дата регистрации:</strong> <?php echo e($user['registration_date']); ?></p>
            <p style="margin-top: 14px;"><a class="btn btn-success" href="change_password.php">Изменить пароль</a></p>
        </div>

        <div class="profile-edit-block">
            <h3>Изменить фото профиля</h3>
            <p class="muted">Можно загрузить новое фото в любой момент.</p>
            <form method="POST" enctype="multipart/form-data" class="profile-upload-form">
                <input type="file" name="photo" accept="image/*" required>
                <button class="btn btn-success" type="submit" name="update_photo">Сохранить фото</button>
            </form>
        </div>
    </div>
</section>

<section class="card">
    <h2>Мои заказы</h2>

    <?php if ($orders->num_rows === 0): ?>
        <p>Заказов пока нет.</p>
        <a class="btn btn-success" href="catalog.php">Перейти в каталог</a>
    <?php else: ?>
        <?php while ($order = $orders->fetch_assoc()): ?>
            <div class="notice">
                <div class="row-between">
                    <div>
                        <strong>Заказ №<?php echo (int)$order['id']; ?></strong>
                        <div class="muted"><?php echo e($order['order_date']); ?> · <?php echo e($order['status']); ?></div>
                    </div>
                    <div class="total-box"><?php echo money($order['total_amount']); ?></div>
                </div>

                <p>
                    Получатель: <?php echo e($order['customer_name']); ?>,
                    телефон: <?php echo e($order['phone']); ?>,
                    адрес: <?php echo e($order['address']); ?>
                </p>

                <?php
                $orderId = (int)$order['id'];
                $itemsStmt = $conn->prepare("
                    SELECT p.name, oi.quantity, oi.price
                    FROM order_items oi
                    INNER JOIN products p ON p.id = oi.product_id
                    WHERE oi.order_id = ?
                    ORDER BY p.name
                ");
                $itemsStmt->bind_param('i', $orderId);
                $itemsStmt->execute();
                $orderItems = $itemsStmt->get_result();
                ?>
                <ul>
                    <?php while ($item = $orderItems->fetch_assoc()): ?>
                        <li>
                            <?php echo e($item['name']); ?> —
                            <?php echo (int)$item['quantity']; ?> шт. × <?php echo money($item['price']); ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
