<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['admin_panel_id'])) {
    header('Location: index.php');
    exit;
}

function admin_flash($message, $type = 'success') {
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
}

function admin_flash_show() {
    if (!empty($_SESSION['admin_flash'])) {
        $flash = $_SESSION['admin_flash'];
        unset($_SESSION['admin_flash']);
        echo '<div class="flash ' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
    }
}

function admin_orders_query_string() {
    $params = [];

    if (!empty($_GET['user_id'])) {
        $params['user_id'] = (int)$_GET['user_id'];
    }
    if (!empty($_GET['date_from'])) {
        $params['date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $params['date_to'] = $_GET['date_to'];
    }
    if (!empty($_GET['statuses']) && is_array($_GET['statuses'])) {
        $params['statuses'] = array_values($_GET['statuses']);
    }

    $query = http_build_query($params);
    return $query ? '?' . $query . '#orders' : '#orders';
}

function bind_params_dynamic($stmt, $types, &$params) {
    if ($types === '') {
        return;
    }

    $refs = [];
    $refs[] = $types;
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function user_status_text($user) {
    if ((int)($user['is_blocked'] ?? 0) !== 1) {
        return 'Активен';
    }

    $until = trim((string)($user['blocked_until'] ?? ''));
    if ($until !== '') {
        return 'Заблокирован до ' . $until;
    }

    return 'Заблокирован бессрочно';
}

$statusList = ['Новый', 'В обработке', 'Передан в доставку', 'Выполнен', 'Отменен'];
$currentAdminId = (int)($_SESSION['admin_panel_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_update_order_status') {
        $newStatus = trim($_POST['new_status'] ?? '');
        $orderIds = $_POST['order_ids'] ?? [];
        $orderIds = array_values(array_filter(array_map('intval', (array)$orderIds)));

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
        header('Location: panel.php' . admin_orders_query_string());
        exit;
    }

    if ($action === 'update_product_inline') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $animalType = trim($_POST['animal_type'] ?? '');
        $productType = trim($_POST['product_type'] ?? '');
        $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
        $releaseYear = (int)($_POST['release_year'] ?? date('Y'));
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $photo = trim($_POST['photo'] ?? '');

        if ($productId <= 0 || $name === '' || $animalType === '' || $productType === '' || $price <= 0 || $supplierId <= 0 || $categoryId <= 0) {
            admin_flash('Заполните обязательные поля товара.', 'error');
        } else {
            $stmt = $conn->prepare("UPDATE products SET name = ?, animal_type = ?, product_type = ?, price = ?, release_year = ?, supplier_id = ?, category_id = ?, photo = ? WHERE id = ?");
            $stmt->bind_param('sssdiiisi', $name, $animalType, $productType, $price, $releaseYear, $supplierId, $categoryId, $photo, $productId);
            $stmt->execute();
            admin_flash('Товар обновлен прямо в строке таблицы.');
        }
        header('Location: panel.php#products');
        exit;
    }

    if ($action === 'delete_product') {
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
            if (!$stmt) {
                admin_flash('Ошибка подготовки запроса: ' . mysqli_error($conn), 'error');
            } elseif (!mysqli_stmt_bind_param($stmt, 'i', $productId)) {
                admin_flash('Ошибка связывания параметров: ' . mysqli_error($conn), 'error');
                mysqli_stmt_close($stmt);
            } elseif (!mysqli_stmt_execute($stmt)) {
                admin_flash('Ошибка выполнения запроса: ' . mysqli_error($conn), 'error');
                mysqli_stmt_close($stmt);
            } else {
                admin_flash('Товар удален.');
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: panel.php#products');
        exit;
    }

    if ($action === 'block_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
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
        header('Location: panel.php#users');
        exit;
    }

    if ($action === 'restore_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_blocked = 0, block_reason = NULL, blocked_until = NULL WHERE id = ? AND is_admin = 0");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            admin_flash('Пользователь восстановлен.');
        }
        header('Location: panel.php#users');
        exit;
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== $currentAdminId) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            admin_flash('Пользователь удален.');
        } else {
            admin_flash('Нельзя удалить текущего администратора.', 'error');
        }
        header('Location: panel.php#users');
        exit;
    }
}

$selectedUserId = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$selectedStatuses = $_GET['statuses'] ?? [];
$selectedStatuses = is_array($selectedStatuses) ? array_values(array_intersect($selectedStatuses, $statusList)) : [];

$users = $conn->query("SELECT id, login, email, registration_date, is_admin, is_blocked, block_reason, blocked_until FROM users ORDER BY login");
$usersForSelect = $conn->query("SELECT id, login, email FROM users WHERE is_admin = 0 ORDER BY login");
$suppliersResult = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$categoriesResult = $conn->query("SELECT id, name FROM categories ORDER BY name");
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);
$categories = $categoriesResult->fetch_all(MYSQLI_ASSOC);

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
if ($orderWhere) {
    $orderSql .= ' WHERE ' . implode(' AND ', $orderWhere);
}
$orderSql .= ' ORDER BY o.order_date DESC, o.id DESC';
$ordersStmt = $conn->prepare($orderSql);
bind_params_dynamic($ordersStmt, $orderTypes, $orderParams);
$ordersStmt->execute();
$orders = $ordersStmt->get_result();

$productSortFields = [
    'id' => 'p.id',
    'name' => 'p.name',
    'type' => 'p.product_type',
    'price' => 'p.price',
    'year' => 'p.release_year',
    'supplier' => 'supplier_name',
    'category' => 'category_name'
];
$productSort = $_GET['product_sort'] ?? 'id';
$productDir = strtolower($_GET['product_dir'] ?? 'desc');
$productSortColumn = $productSortFields[$productSort] ?? $productSortFields['id'];
$productDirSql = $productDir === 'asc' ? 'ASC' : 'DESC';
$productsResult = $conn->query("SELECT p.*, s.name AS supplier_name, c.name AS category_name FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id INNER JOIN categories c ON c.id = p.category_id ORDER BY $productSortColumn $productDirSql");
$products = $productsResult->fetch_all(MYSQLI_ASSOC);

function product_sort_url($sort, $currentSort, $currentDir) {
    $nextDir = ($currentSort === $sort && $currentDir === 'asc') ? 'desc' : 'asc';
    return 'panel.php?product_sort=' . urlencode($sort) . '&product_dir=' . urlencode($nextDir) . '#products';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Административная панель — ZOOSHOP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css?v=9">
</head>
<body class="admin-shell">
<header class="admin-topbar">
    <div class="container">
        <div>
            <h1>Административная панель ZOOSHOP</h1>
            <div class="muted">Администратор: <?php echo e($_SESSION['admin_panel_login'] ?? 'admin'); ?></div>
        </div>
        <div class="actions">
            <a class="btn btn-secondary" href="../index.php">Открыть сайт</a>
            <a class="btn btn-danger" href="logout.php">Выйти</a>
        </div>
    </div>
</header>

<main class="container content">
    <?php admin_flash_show(); ?>

    <nav class="site-nav admin-local-nav">
        <div class="nav-row admin-menu-row">
            <a href="#orders">Заказы</a>
            <a href="#products">Товары</a>
            <a href="add_product.php">Добавить товар</a>
            <a href="#users">Пользователи</a>
        </div>
    </nav>

    <section class="card" id="orders">
        <h2>Список заказов с фильтрами</h2>
        <form method="GET" action="panel.php#orders" class="admin-filter-grid">
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

            <div class="form-group">
                <label>Дата от</label>
                <input type="date" name="date_from" value="<?php echo e($dateFrom); ?>">
            </div>

            <div class="form-group">
                <label>Дата до</label>
                <input type="date" name="date_to" value="<?php echo e($dateTo); ?>">
            </div>

            <div class="form-group admin-status-filter">
                <label>Статусы</label>
                <div class="checkbox-list">
                    <?php foreach ($statusList as $status): ?>
                        <label>
                            <input type="checkbox" name="statuses[]" value="<?php echo e($status); ?>" <?php echo in_array($status, $selectedStatuses, true) ? 'checked' : ''; ?>>
                            <?php echo e($status); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-filter-buttons">
                <button class="btn btn-success" type="submit">Применить фильтр</button>
                <a class="btn btn-secondary" href="panel.php#orders">Сбросить</a>
            </div>
        </form>

        <?php if (!$orders || $orders->num_rows === 0): ?>
            <p class="notice">Заказы по выбранным фильтрам не найдены.</p>
        <?php else: ?>
            <form method="POST" class="admin-bulk-form">
                <input type="hidden" name="action" value="bulk_update_order_status">

                <div class="bulk-panel">
                    <label class="select-all-line">
                        <input type="checkbox" id="select-all-orders">
                        выбрать все заказы на странице
                    </label>
                    <select name="new_status" required>
                        <option value="">-- новый статус --</option>
                        <?php foreach ($statusList as $status): ?>
                            <option value="<?php echo e($status); ?>"><?php echo e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success" type="submit">Изменить статус выбранных</button>
                </div>

                <div class="table-wrap">
                    <table class="admin-orders-table">
                        <tr>
                            <th></th>
                            <th>ID</th>
                            <th>Клиент</th>
                            <th>Дата</th>
                            <th>Статус</th>
                            <th>Сумма</th>
                            <th>Получатель</th>
                            <th>Состав заказа</th>
                        </tr>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <?php
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
                                <td>
                                    <ul class="compact-list">
                                        <?php while ($item = $items->fetch_assoc()): ?>
                                            <li><?php echo e($item['name']); ?> — <?php echo (int)$item['quantity']; ?> шт. × <?php echo money($item['price']); ?></li>
                                        <?php endwhile; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="card" id="products">
        <div class="section-head-row">
            <h2>Список товаров</h2>
            <a class="btn btn-success" href="add_product.php">Добавить товар</a>
        </div>
        <form method="GET" action="panel.php#products" class="product-sort-form">
            <label for="product_sort">Сортировка</label>
            <select id="product_sort" name="product_sort">
                <option value="id" <?php echo $productSort === 'id' ? 'selected' : ''; ?>>ID</option>
                <option value="name" <?php echo $productSort === 'name' ? 'selected' : ''; ?>>Название</option>
                <option value="type" <?php echo $productSort === 'type' ? 'selected' : ''; ?>>Тип товара</option>
                <option value="price" <?php echo $productSort === 'price' ? 'selected' : ''; ?>>Цена</option>
                <option value="year" <?php echo $productSort === 'year' ? 'selected' : ''; ?>>Год</option>
                <option value="supplier" <?php echo $productSort === 'supplier' ? 'selected' : ''; ?>>Поставщик</option>
                <option value="category" <?php echo $productSort === 'category' ? 'selected' : ''; ?>>Категория</option>
            </select>

            <select name="product_dir">
                <option value="asc" <?php echo $productDir === 'asc' ? 'selected' : ''; ?>>по возрастанию</option>
                <option value="desc" <?php echo $productDir === 'desc' ? 'selected' : ''; ?>>по убыванию</option>
            </select>

            <button class="btn btn-secondary btn-small" type="submit">Применить</button>
        </form>

        <div class="products-admin-list">
            <div class="products-list-head">
                <div>ID</div>
                <div>Товар</div>
                <div>Цена / год</div>
                <div>Поставщик / категория</div>
                <div>Действия</div>
            </div>

            <?php foreach ($products as $product): ?>
                <?php $productId = (int)$product['id']; ?>
                <form method="POST" id="product-form-<?php echo $productId; ?>">
                    <input type="hidden" name="action" value="update_product_inline">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    <input type="hidden" name="photo" value="<?php echo e($product['photo']); ?>">
                </form>
                <form method="POST" id="delete-product-<?php echo $productId; ?>" onsubmit="return confirm('Удалить товар?');">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    <input type="hidden" name="photo" value="<?php echo e($product['photo']); ?>">
                </form>

                <div class="product-edit-card">
                    <div class="product-card-id"><?php echo $productId; ?></div>

                    <div class="product-card-fields product-card-main">
                        <div class="product-control product-control-wide">
                            <label for="product-name-<?php echo $productId; ?>">Название</label>
                            <input id="product-name-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" type="text" name="name" value="<?php echo e($product['name']); ?>" required>
                        </div>
                        <div class="product-control">
                            <label for="product-animal-<?php echo $productId; ?>">Животное</label>
                            <input id="product-animal-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" type="text" name="animal_type" value="<?php echo e($product['animal_type']); ?>" required>
                        </div>
                        <div class="product-control">
                            <label for="product-type-<?php echo $productId; ?>">Тип</label>
                            <input id="product-type-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" type="text" name="product_type" value="<?php echo e($product['product_type']); ?>" required>
                        </div>
                    </div>

                    <div class="product-card-fields product-card-price">
                        <div class="product-control">
                            <label for="product-price-<?php echo $productId; ?>">Цена</label>
                            <input id="product-price-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" type="number" step="0.01" min="0" name="price" value="<?php echo e($product['price']); ?>" required>
                        </div>
                        <div class="product-control product-control-short">
                            <label for="product-year-<?php echo $productId; ?>">Год</label>
                            <input id="product-year-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" type="number" min="2000" max="<?php echo date('Y') + 1; ?>" name="release_year" value="<?php echo e($product['release_year']); ?>" required>
                        </div>
                    </div>

                    <div class="product-card-fields product-card-selects">
                        <div class="product-control">
                            <label for="product-supplier-<?php echo $productId; ?>">Поставщик</label>
                            <select id="product-supplier-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" name="supplier_id" required>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo (int)$supplier['id']; ?>" <?php echo (int)$product['supplier_id'] === (int)$supplier['id'] ? 'selected' : ''; ?>><?php echo e($supplier['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="product-control">
                            <label for="product-category-<?php echo $productId; ?>">Категория</label>
                            <select id="product-category-<?php echo $productId; ?>" form="product-form-<?php echo $productId; ?>" name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int)$category['id']; ?>" <?php echo (int)$product['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="product-card-actions">
                        <button form="product-form-<?php echo $productId; ?>" class="btn btn-small" type="submit">Сохранить</button>
                        <button form="delete-product-<?php echo $productId; ?>" class="btn btn-danger btn-small" type="submit">Удалить</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card" id="users">
        <h2>Пользователи</h2>
        <div class="table-wrap">
            <table class="admin-users-table">
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>E-mail</th>
                    <th>Дата регистрации</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <?php
                    $userId = (int)$user['id'];
                    $isAdmin = (int)$user['is_admin'] === 1;
                    $isBlocked = (int)($user['is_blocked'] ?? 0) === 1;
                    ?>
                    <tr>
                        <td><?php echo $userId; ?></td>
                        <td><strong><?php echo e($user['login']); ?></strong></td>
                        <td><?php echo e($user['email']); ?></td>
                        <td><?php echo e($user['registration_date']); ?></td>
                        <td><?php echo $isAdmin ? 'Администратор' : 'Пользователь'; ?></td>
                        <td>
                            <span class="user-status <?php echo $isBlocked ? 'locked' : 'active'; ?>"><?php echo e(user_status_text($user)); ?></span>
                            <?php if ($isBlocked && !empty($user['block_reason'])): ?>
                                <div class="muted">Причина: <?php echo e($user['block_reason']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="actions vertical-actions">
                            <?php if (!$isAdmin): ?>
                                <details class="lock-details">
                                    <summary class="btn btn-small btn-warning"><?php echo $isBlocked ? 'Изменить блокировку' : 'Заблокировать'; ?></summary>
                                    <form method="POST" class="lock-form">
                                        <input type="hidden" name="action" value="block_user">
                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                        <label>Причина</label>
                                        <input type="text" name="block_reason" value="<?php echo e($user['block_reason'] ?? ''); ?>" placeholder="Например: нарушение правил">
                                        <label>Заблокировать до</label>
                                        <input type="datetime-local" name="blocked_until" value="<?php echo !empty($user['blocked_until']) ? e(str_replace(' ', 'T', substr($user['blocked_until'], 0, 16))) : ''; ?>">
                                        <button class="btn btn-danger btn-small" type="submit"><?php echo $isBlocked ? 'Обновить блокировку' : 'Заблокировать'; ?></button>
                                    </form>
                                </details>

                                <?php if ($isBlocked): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="restore_user">
                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                        <button class="btn btn-success btn-small" type="submit">Разблокировать</button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" onsubmit="return confirm('Удалить пользователя? Его заказы тоже будут удалены.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                    <button class="btn btn-danger btn-small" type="submit">Удалить</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Администратор защищен</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </section>
</main>

<script>
const selectAllOrders = document.getElementById('select-all-orders');
if (selectAllOrders) {
    selectAllOrders.addEventListener('change', function () {
        document.querySelectorAll('.order-check').forEach(function (checkbox) {
            checkbox.checked = selectAllOrders.checked;
        });
    });
}
</script>
</body>
</html>
