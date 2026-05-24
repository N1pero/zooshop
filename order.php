<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Оформление заказа — ZOOSHOP';
$activePage = 'cart';

require_login($conn);

$user = current_user($conn);
$errors = [];

function get_cart_rows_for_order($conn)
{
    $params = [];
    $types = '';
    $where = cart_owner_condition($params, $types);

    $stmt = $conn->prepare("
        SELECT ci.product_id, ci.quantity, p.name, p.price
        FROM cart_items ci
        INNER JOIN products p ON p.id = ci.product_id
        WHERE $where
        ORDER BY ci.id
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $row['sum'] = (float)$row['price'] * (int)$row['quantity'];
        $total += $row['sum'];
        $rows[] = $row;
    }

    return [$rows, $total];
}

[$cartRows, $total] = get_cart_rows_for_order($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$cartRows) {
        $errors[] = 'Корзина пустая.';
    }
    if ($customerName === '') {
        $errors[] = 'Введите имя получателя.';
    }
    if ($phone === '') {
        $errors[] = 'Введите телефон.';
    }
    if ($address === '') {
        $errors[] = 'Введите адрес доставки.';
    }

    if ($errors) {
        // ЛР5: пользовательское предупреждение вне error_demo.php.
        // Обработчик set_error_handler() перехватывает E_USER_WARNING и записывает его в includes/error_log.txt.
        trigger_error('Ошибка проверки данных заказа: ' . implode('; ', $errors), E_USER_WARNING);
    }

    if (!$errors) {
        $conn->begin_transaction();

        try {
            $userId = (int)$user['id'];
            $stmt = $conn->prepare("INSERT INTO orders (user_id, order_date, total_amount, status, customer_name, phone, address) VALUES (?, NOW(), ?, 'Новый', ?, ?, ?)");
            $stmt->bind_param('idsss', $userId, $total, $customerName, $phone, $address);
            $stmt->execute();
            $orderId = $conn->insert_id;

            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cartRows as $row) {
                $productId = (int)$row['product_id'];
                $quantity = (int)$row['quantity'];
                $price = (float)$row['price'];
                $itemStmt->bind_param('iiid', $orderId, $productId, $quantity, $price);
                $itemStmt->execute();
            }

            $params = [];
            $types = '';
            $where = cart_owner_condition($params, $types);
            $clear = $conn->prepare("DELETE ci FROM cart_items ci WHERE $where");
            $clear->bind_param($types, ...$params);
            $clear->execute();

            $conn->commit();
            flash_set('Заказ оформлен. Он появился в личном кабинете.');
            header('Location: profile.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();

            // Ошибки БД не отправляются в пользовательский обработчик.
            // Для них используется стандартный mysqli_error() / сообщение MySQLi.
            error_log('Ошибка оформления заказа через mysqli_error: ' . mysqli_error($conn));

            $errors[] = 'Ошибка оформления заказа: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2 class="page-title">Оформление заказа</h2>
    <p>Заказ создается на основе товаров из корзины. Состав заказа хранится в отдельной таблице.</p>
</section>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$cartRows): ?>
    <section class="card">
        <p>Нельзя оформить заказ: корзина пустая.</p>
        <a class="btn btn-success" href="catalog.php">Перейти в каталог</a>
    </section>
<?php else: ?>
    <section class="form-card">
        <h2>Данные доставки</h2>
        <form method="POST" class="form-grid">
            <div class="form-group">
                <label>Имя получателя</label>
                <input type="text" name="customer_name" required value="<?php echo e($_POST['customer_name'] ?? $user['login']); ?>">
            </div>

            <div class="form-group">
                <label>Телефон</label>
                <input type="text" name="phone" required value="<?php echo e($_POST['phone'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Адрес доставки</label>
                <input type="text" name="address" required value="<?php echo e($_POST['address'] ?? ''); ?>" style="min-width:320px">
            </div>

            <button class="btn btn-success" type="submit">Подтвердить заказ</button>
        </form>
    </section>

    <section class="card">
        <h2>Состав заказа</h2>
        <ul>
            <?php foreach ($cartRows as $row): ?>
                <li><?php echo e($row['name']); ?> — <?php echo (int)$row['quantity']; ?> шт. · <?php echo money($row['sum']); ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="total-box">Итого: <?php echo money($total); ?></div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
