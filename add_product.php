<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'admin_header.php';

$error = '';
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $animalType = trim($_POST['animal_type'] ?? '');
    $productType = trim($_POST['product_type'] ?? '');
    $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
    $releaseYear = (int)($_POST['release_year'] ?? date('Y'));
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $photo = trim($_POST['photo'] ?? '');
    $description = '';

    if ($name === '' || $animalType === '' || $productType === '' || $price <= 0 || $supplierId <= 0 || $categoryId <= 0) {
        $error = 'Заполните обязательные поля товара.';
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, animal_type, product_type, price, release_year, supplier_id, category_id, photo, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssdiiiss', $name, $animalType, $productType, $price, $releaseYear, $supplierId, $categoryId, $photo, $description);
        $stmt->execute();
        admin_flash('Товар успешно добавлен.');
        header('Location: products.php');
        exit;
    }
}
?>
<section class="card">
    <h2>Новый товар</h2>
    <?php if ($error): ?><div class="flash error"><?php echo e($error); ?></div><?php endif; ?>
    <form method="POST" class="form-grid admin-edit-box">
        <div class="form-group"><label>Название</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Животное</label><input type="text" name="animal_type" placeholder="Кошка / Собака" required></div>
        <div class="form-group"><label>Тип товара</label><input type="text" name="product_type" required></div>
        <div class="form-group"><label>Цена</label><input type="number" step="0.01" min="0" name="price" required></div>
        <div class="form-group"><label>Год</label><input type="number" min="2000" max="<?php echo date('Y') + 1; ?>" name="release_year" value="<?php echo date('Y'); ?>" required></div>
        <div class="form-group"><label>Поставщик</label><select name="supplier_id" required><?php while ($supplier = $suppliers->fetch_assoc()): ?><option value="<?php echo (int)$supplier['id']; ?>"><?php echo e($supplier['name']); ?></option><?php endwhile; ?></select></div>
        <div class="form-group"><label>Категория</label><select name="category_id" required><?php while ($category = $categories->fetch_assoc()): ?><option value="<?php echo (int)$category['id']; ?>"><?php echo e($category['name']); ?></option><?php endwhile; ?></select></div>
        <div class="form-group"><label>Фото</label><input type="text" name="photo" placeholder="images/products/name.jpg"></div>
        <button class="btn btn-success" type="submit">Добавить товар</button>
        <a class="btn btn-secondary" href="products.php">Отмена</a>
    </form>
</section>
<?php require_once 'admin_footer.php'; ?>