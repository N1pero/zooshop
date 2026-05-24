<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Каталог — ZOOSHOP';
$activePage = 'catalog';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $sessionToken = session_id();
    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($userId) {
        $check = $conn->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
        $check->bind_param('ii', $userId, $productId);
    } else {
        $check = $conn->prepare("SELECT id, quantity FROM cart_items WHERE session_token = ? AND user_id IS NULL AND product_id = ?");
        $check->bind_param('si', $sessionToken, $productId);
    }

    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        $newQty = (int)$existing['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $stmt->bind_param('ii', $newQty, $existing['id']);
        $stmt->execute();
    } else {
        if ($userId) {
            $stmt = $conn->prepare("INSERT INTO cart_items (user_id, session_token, product_id, quantity, added_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('isii', $userId, $sessionToken, $productId, $quantity);
        } else {
            $stmt = $conn->prepare("INSERT INTO cart_items (user_id, session_token, product_id, quantity, added_at) VALUES (NULL, ?, ?, ?, NOW())");
            $stmt->bind_param('sii', $sessionToken, $productId, $quantity);
        }
        $stmt->execute();
    }

    flash_set('Товар добавлен в корзину.');
    header('Location: catalog.php');
    exit;
}

$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");

$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$animalType = trim($_GET['animal_type'] ?? '');
$minPrice = trim($_GET['min_price'] ?? '');
$maxPrice = trim($_GET['max_price'] ?? '');
$sort = ($_GET['sort'] ?? 'price_asc');

$sql = "
    SELECT p.*, c.name AS category_name, s.name AS supplier_name, s.country
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    INNER JOIN suppliers s ON s.id = p.supplier_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.product_type LIKE ? OR p.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
    $types .= 'i';
}
if ($supplierId > 0) {
    $sql .= " AND p.supplier_id = ?";
    $params[] = $supplierId;
    $types .= 'i';
}
if ($animalType !== '') {
    $sql .= " AND p.animal_type = ?";
    $params[] = $animalType;
    $types .= 's';
}
if ($minPrice !== '') {
    $sql .= " AND p.price >= ?";
    $params[] = (float)$minPrice;
    $types .= 'd';
}
if ($maxPrice !== '') {
    $sql .= " AND p.price <= ?";
    $params[] = (float)$maxPrice;
    $types .= 'd';
}

$orderSql = ' ORDER BY p.price ASC';
if ($sort === 'price_desc') {
    $orderSql = ' ORDER BY p.price DESC';
} elseif ($sort === 'name_asc') {
    $orderSql = ' ORDER BY p.name ASC';
}
$sql .= $orderSql;

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2 class="page-title">Каталог товаров</h2>
    <p>Выберите товары для питомца, используйте поиск и фильтры, затем добавьте нужные позиции в корзину.</p>
</section>

<section class="filter-box">
    <form method="GET">
        <div class="form-group">
            <label>Поиск</label>
            <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Название товара">
        </div>

        <div class="form-group">
            <label>Категория</label>
            <select name="category_id">
                <option value="0">Все категории</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Бренд</label>
            <select name="supplier_id">
                <option value="0">Все бренды</option>
                <?php while ($sup = $suppliers->fetch_assoc()): ?>
                    <option value="<?php echo $sup['id']; ?>" <?php echo $supplierId === (int)$sup['id'] ? 'selected' : ''; ?>>
                        <?php echo e($sup['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Питомец</label>
            <select name="animal_type">
                <option value="">Все</option>
                <option value="Кошка" <?php echo $animalType === 'Кошка' ? 'selected' : ''; ?>>Кошка</option>
                <option value="Собака" <?php echo $animalType === 'Собака' ? 'selected' : ''; ?>>Собака</option>
            </select>
        </div>

        <div class="form-group">
            <label>Цена от</label>
            <input type="number" name="min_price" value="<?php echo e($minPrice); ?>">
        </div>

        <div class="form-group">
            <label>Цена до</label>
            <input type="number" name="max_price" value="<?php echo e($maxPrice); ?>">
        </div>

        <div class="form-group">
            <label>Сортировка</label>
            <select name="sort">
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Цена по возрастанию</option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Цена по убыванию</option>
                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Название А-Я</option>
            </select>
        </div>

        <button class="btn btn-success" type="submit">Показать</button>
        <a class="btn btn-secondary" href="catalog.php">Сбросить</a>
    </form>
</section>

<div class="product-grid">
    <?php if ($products->num_rows > 0): ?>
        <?php while ($p = $products->fetch_assoc()): ?>
            <article class="product-card">
                <?php echo product_img($p['photo'], $p['name']); ?>
                <span class="badge"><?php echo e($p['category_name']); ?> · <?php echo e($p['animal_type']); ?></span>
                <h3><?php echo e($p['name']); ?></h3>
                <p><?php echo e($p['description']); ?></p>
                <p class="muted">Тип: <?php echo e($p['product_type']); ?> · Бренд: <?php echo e($p['supplier_name']); ?> · <?php echo e($p['country']); ?></p>
                <div class="price"><?php echo money($p['price']); ?></div>
                <form method="POST" class="actions">
                    <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                    <input type="number" name="quantity" value="1" min="1" style="width:80px">
                    <button class="btn btn-small" type="submit" name="add_to_cart">В корзину</button>
                </form>
            </article>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <p>По выбранным параметрам товары не найдены.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
