<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'admin_header.php';

// Функции для фильтрации
function admin_orders_query_string() {
    $params = [];
    if (!empty($_GET['user_id'])) { $params['user_id'] = (int)$_GET['user_id']; }
    if (!empty($_GET['date_from'])) { $params['date_from'] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $params['date_to'] = $_GET['date_to']; }
    if (!empty($_GET['statuses']) && is_array($_GET['statuses'])) { $params['statuses'] = array_values($_GET['statuses']); }
    $query = http_build_query($params);
    return $query ? '?' . $query . '#orders' : '#orders';
}

function bind_params_dynamic($stmt, $types, &$params) {
    if ($types === '') return;
    $refs = [$types];
    foreach ($params as $key => $value) { $refs[] = &$params[$key]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

$statusList = ['Новый', 'В обработке', 'Передан в доставку', 'Выполнен', 'Отменен'];

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'bulk_update_order_status') {
        $newStatus = trim($_POST['new_status'] ?? '');
        $orderIds = array_values(array_filter(array_map('intval', (array)($_POST['order_ids'] ?? []))));
        if ($newStatus === '' || !in_array($newStatus, $statusList, true)) {
            admin_flash('Выберите корректный статус заказа.', 'error');
        } elseif (count($orderIds) === 0) {
            admin_flash('Выберите хотя бы один заказ для изменения.', 'error');
        } else {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $updated = 0;
            foreach ($orderIds as $orderId) {
                $stmt->bind_param('si', $newStatus, $orderId);
                $stmt->execute();
                $updated += $stmt->affected_rows >= 0 ? 1 : 0;
            }
            admin_flash('Статус обновлен у выбранных заказов: ' . $updated . '.');
        }
        header('Location: orders.php' . admin_orders_query_string());
        exit;
    }
}

// Получение данных для фильтров
$usersForSelect = $conn->query("SELECT id, login, email FROM users WHERE is_admin = 0 ORDER BY login");

// Параметры фильтрации
$selectedUserId = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$selectedStatuses = $_GET['statuses'] ?? [];
$selectedStatuses = is_array($selectedStatuses) ? array_values(array_intersect($selectedStatuses, $statusList)) : [];

// Построение запроса заказов
$orderWhere = [];
$orderParams = [];
$orderTypes = '';

if ($selectedUserId > 0) {
    $orderWhere[] = 'o.user_id = ?';
    $orderParams[] = $selectedUserId;
    $orderTypes .= 'i';
}
if ($dateFrom !== '') {
    $orderWhere[] = 'DATE(o.order_date) >= ?';
    $orderParams[] = $dateFrom;
    $orderTypes .= 's';
}
if ($dateTo !== '') {
    $orderWhere[] = 'DATE(o.order_date) <= ?';
    $orderParams[] = $dateTo;
    $orderTypes .= 's';
}
if (count($selectedStatuses) > 0) {
    $placeholders = implode(',', array_fill(0, count($selectedStatuses), '?'));
    $orderWhere[] = "o.status IN ($placeholders)";
    foreach ($selectedStatuses as $status) {
        $orderParams[] = $status;
        $orderTypes .= 's';
    }
}

$orderSql = "SELECT o.*, u.login, u.email FROM orders o INNER JOIN users u ON u.id = o.user_id";
if ($orderWhere) { $orderSql .= ' WHERE ' . implode(' AND ', $orderWhere); }
$orderSql .= ' ORDER BY o.order_date DESC, o.id DESC';
$ordersStmt = $conn->prepare($orderSql);
bind_params_dynamic($ordersStmt, $orderTypes, $orderParams);
$ordersStmt->execute();
$orders = $ordersStmt->get_result();
?>

<section class="card" id="orders">
    <h2>Список заказов с фильтрами</h2>
    <form method="GET" action="orders.php#orders" class="admin-filter-grid">
        <div class="form-group">
            <label>Клиент</label>
            <select name="user_id">
                <option value="">Все клиенты</option>
                <?php while ($user = $usersForSelect->fetch_assoc()): ?>
                    <option value="<?php echo (int)$user['id']; ?>" <?php echo $selectedUserId === (int)$user['id'] ? 'selected' : ''; ?>>
                        <?php echo e($user['login'] . ' — ' . $user['email']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group"><label>Дата от</label><input type="date" name="date_from" value="<?php echo e($dateFrom); ?>"></div>
        <div class="form-group"><label>Дата до</label><input type="date" name="date_to" value="<?php echo e($dateTo); ?>"></div>
        <div class="form-group admin-status-filter">
            <label>Статусы</label>
            <div class="checkbox-list">
                <?php foreach ($statusList as $status): ?>
                    <label><input type="checkbox" name="statuses[]" value="<?php echo e($status); ?>" <?php echo in_array($status, $selectedStatuses, true) ? 'checked' : ''; ?>><?php echo e($status); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-filter-buttons">
            <button class="btn btn-success" type="submit">Применить фильтр</button>
            <a class="btn btn-secondary" href="orders.php#orders">Сбросить</a>
        </div>
    </form>

    <?php if (!$orders || $orders->num_rows === 0): ?>
        <p class="notice">Заказы по выбранным фильтрам не найдены.</p>
    <?php else: ?>
        <form method="POST" class="admin-bulk-form">
            <input type="hidden" name="action" value="bulk_update_order_status">
            <div class="bulk-panel">
                <label class="select-all-line"><input type="checkbox" id="select-all-orders"> выбрать все заказы на странице</label>
                <select name="new_status" required><option value="">-- новый статус --</option><?php foreach ($statusList as $status): ?><option value="<?php echo e($status); ?>"><?php echo e($status); ?></option><?php endforeach; ?></select>
                <button class="btn btn-success" type="submit">Изменить статус выбранных</button>
            </div>
            <div class="table-wrap">
                <table class="admin-orders-table">
                    <thead><tr><th></th><th>ID</th><th>Клиент</th><th>Дата</th><th>Статус</th><th>Сумма</th><th>Получатель</th><th>Состав заказа</th></tr></thead>
                    <tbody>
                    <?php while ($order = $orders->fetch_assoc()):
                        $orderId = (int)$order['id'];
                        $itemsStmt = $conn->prepare("SELECT p.name, oi.quantity, oi.price FROM order_items oi INNER JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY p.name");
                        $itemsStmt->bind_param('i', $orderId);
                        $itemsStmt->execute();
                        $items = $itemsStmt->get_result();
                    ?>
                    <tr>
                        <td><input class="order-check" type="checkbox" name="order_ids[]" value="<?php echo $orderId; ?>"></td>
                        <td><?php echo $orderId; ?></td>
                        <td><strong><?php echo e($order['login']); ?></strong><br><span class="muted"><?php echo e($order['email']); ?></span></td>
                        <td><?php echo e($order['order_date']); ?></td>
                        <td><span class="status-pill"><?php echo e($order['status']); ?></span></td>
                        <td><?php echo money($order['total_amount']); ?></td>
                        <td><?php echo e($order['customer_name']); ?><br><span class="muted"><?php echo e($order['phone']); ?></span><br><span class="muted"><?php echo e($order['address']); ?></span></td>
                        <td><ul class="compact-list"><?php while ($item = $items->fetch_assoc()): ?><li><?php echo e($item['name']); ?> — <?php echo (int)$item['quantity']; ?> шт. × <?php echo money($item['price']); ?></li><?php endwhile; ?></ul></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</section>

<script>
const selectAllOrders = document.getElementById('select-all-orders');
if (selectAllOrders) { selectAllOrders.addEventListener('change', function () { document.querySelectorAll('.order-check').forEach(cb => cb.checked = selectAllOrders.checked); }); }
</script>

<?php require_once 'admin_footer.php'; ?>