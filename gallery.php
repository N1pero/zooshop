<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Галерея товаров — ZOOSHOP';
$activePage = 'gallery';

$items = $conn->query("
    SELECT p.name, p.photo, p.price, c.name AS category_name
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    ORDER BY p.name
");

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2 class="page-title">Галерея товаров</h2>
    <p>Посмотрите подборку популярных товаров для кошек и собак: корма, лакомства, игрушки, аксессуары и средства ухода.</p>
</section>

<div class="gallery-grid">
    <?php while ($item = $items->fetch_assoc()): ?>
        <article class="gallery-card">
            <?php echo product_img($item['photo'], $item['name'], 'gallery-img'); ?>
            <span class="badge"><?php echo e($item['category_name']); ?></span>
            <h3><?php echo e($item['name']); ?></h3>
            <div class="price"><?php echo money($item['price']); ?></div>
        </article>
    <?php endwhile; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
