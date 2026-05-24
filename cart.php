<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Корзина — ZOOSHOP';
$activePage = 'cart';

function cart_where(&$params, &$types)
{
    return cart_owner_condition($params, $types);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        $cartId = (int)$_POST['cart_id'];
        $quantity = max(1, (int)$_POST['quantity']);
        $params = [$quantity, $cartId];
        $types = 'ii';
        $where = cart_where($params, $types);

        $stmt = $conn->prepare("UPDATE cart_items ci SET ci.quantity = ? WHERE ci.id = ? AND $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        flash_set('Количество обновлено.');
    }

    if (isset($_POST['remove_item'])) {
        $cartId = (int)$_POST['cart_id'];
        $params = [$cartId];
        $types = 'i';
        $where = cart_where($params, $types);

        $stmt = $conn->prepare("DELETE ci FROM cart_items ci WHERE ci.id = ? AND $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        flash_set('Товар удален из корзины.');
    }

    if (isset($_POST['clear_cart'])) {
        $params = [];
        $types = '';
        $where = cart_where($params, $types);

        $stmt = $conn->prepare("DELETE ci FROM cart_items ci WHERE $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        flash_set('Корзина очищена.');
    }

    header('Location: cart.php');
    exit;
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$params = [];
$types = '';
$where = cart_where($params, $types);

$stmt = $conn->prepare("
    SELECT ci.id AS cart_id, ci.quantity, p.id AS product_id, p.name, p.price, p.photo, c.name AS category_name
    FROM cart_items ci
    INNER JOIN products p ON p.id = ci.product_id
    INNER JOIN categories c ON c.id = p.category_id
    WHERE $where
    ORDER BY ci.id DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = $stmt->get_result();

$total = 0;
$cartRows = [];
while ($row = $items->fetch_assoc()) {
    $row['sum'] = (float)$row['price'] * (int)$row['quantity'];
    $total += $row['sum'];
    $cartRows[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <div class="card-header">
        <div>
            <h2 class="page-title">Корзина</h2>
            <p>Корзина показывает товары текущего пользователя. У каждого аккаунта свой отдельный список.</p>
        </div>
        <?php if ($cartRows): ?>
            <form method="POST">
                <button class="btn btn-danger" type="submit" name="clear_cart" onclick="return confirm('Удалить все товары из корзины?')">Удалить всё</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($cartRows): ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th>Фото</th>
                <th>Товар</th>
                <th>Категория</th>
                <th>Цена</th>
                <th>Количество</th>
                <th>Сумма</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($cartRows as $item): ?>
                <?php
                    $isEditing = $editId === (int)$item['cart_id'];
                    $editFormId = 'cart-edit-' . (int)$item['cart_id'];
                ?>
                <tr class="<?php echo $isEditing ? 'editing-row' : ''; ?>">
                    <td><?php echo product_img($item['photo'], $item['name'], 'table-img'); ?></td>
                    <td><strong><?php echo e($item['name']); ?></strong></td>
                    <td><?php echo e($item['category_name']); ?></td>
                    <td><?php echo money($item['price']); ?></td>
                    <td>
                        <?php if ($isEditing): ?>
                            <input
                                class="cart-qty-input"
                                form="<?php echo e($editFormId); ?>"
                                type="number"
                                name="quantity"
                                value="<?php echo (int)$item['quantity']; ?>"
                                min="1"
                                required
                            >
                        <?php else: ?>
                            <strong><?php echo (int)$item['quantity']; ?></strong>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo money($item['sum']); ?></strong></td>
                    <td>
                        <?php if ($isEditing): ?>
                            <form id="<?php echo e($editFormId); ?>" method="POST" class="actions">
                                <input type="hidden" name="cart_id" value="<?php echo (int)$item['cart_id']; ?>">
                                <button class="btn btn-success btn-small" type="submit" name="update_cart">Подтвердить</button>
                                <a class="btn btn-secondary btn-small" href="cart.php">Отменить</a>
                            </form>
                        <?php else: ?>
                            <div class="actions">
                                <a class="btn btn-small" href="cart.php?edit=<?php echo (int)$item['cart_id']; ?>">Изменить</a>
                                <form method="POST">
                                    <input type="hidden" name="cart_id" value="<?php echo (int)$item['cart_id']; ?>">
                                    <button class="btn btn-danger btn-small" type="submit" name="remove_item" onclick="return confirm('Удалить товар?')">Удалить</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <section class="card row-between">
        <div class="total-box">Итого: <?php echo money($total); ?></div>
        <div class="actions">
            <a class="btn btn-secondary" href="catalog.php">Продолжить покупки</a>
            <a class="btn btn-success" href="order.php">Оформить заказ</a>
        </div>
    </section>
<?php else: ?>
    <section class="card">
        <p>Корзина пока пустая.</p>
        <a class="btn btn-success" href="catalog.php">Перейти в каталог</a>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
