<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'ZOOSHOP — зоомагазин';
$activePage = 'home';

$productsCount = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc();
$usersCount = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc();
$ordersCount = $conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc();

$popular = $conn->query("
    SELECT p.id, p.name, p.price, p.photo, c.name AS category_name
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    ORDER BY p.price ASC, p.name ASC
    LIMIT 4
");

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <span class="eyebrow">Зоомагазин для кошек и собак</span>
    <h1>Все для питомца в одном удобном каталоге</h1>
    <p>
        ZOOSHOP помогает быстро подобрать корм, лакомства, игрушки, переноски и средства ухода.
        Зарегистрируйтесь, добавляйте товары в корзину, оформляйте заказы и смотрите историю покупок
        в личном кабинете.
    </p>
    <div class="hero-actions">
        <a class="btn btn-success" href="pages/catalog.php">Перейти в каталог</a>
        <?php if (!$currentUser): ?>
            <a class="btn" href="auth/register.php">Создать аккаунт</a>
        <?php else: ?>
            <a class="btn" href="pages/profile.php">Личный кабинет</a>
        <?php endif; ?>
    </div>
</section>

<section class="stats-grid">
    <div class="card stat-card">
        <div class="stat-value"><?php echo (int)$productsCount['total']; ?></div>
        <p>товаров в каталоге</p>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?php echo (int)$usersCount['total']; ?></div>
        <p>зарегистрированных пользователей</p>
    </div>
    <div class="card stat-card">
        <div class="stat-value"><?php echo (int)$ordersCount['total']; ?></div>
        <p>оформленных заказов</p>
    </div>
</section>

<section class="grid-3">
    <article class="card">
        <h3>Подбор товаров</h3>
        <p>Используйте поиск, категории, бренды, тип питомца и диапазон цены.</p>
    </article>
    <article class="card">
        <h3>Корзина и заказ</h3>
        <p>Добавляйте товары в корзину и оформляйте заказ после авторизации.</p>
    </article>
    <article class="card">
        <h3>Личный кабинет</h3>
        <p>После входа доступны личные данные пользователя и история заказов.</p>
    </article>
</section>

<?php if ($popular && $popular->num_rows > 0): ?>
<section class="card">
    <div class="card-header">
        <div>
            <h2>Популярные товары</h2>
            <p>Позиции, с которых удобно начать выбор.</p>
        </div>
        <a class="btn btn-small" href="pages/catalog.php">Смотреть все</a>
    </div>

    <div class="product-grid">
        <?php while ($item = $popular->fetch_assoc()): ?>
            <article class="product-card">
                <?php echo product_img($item['photo'], $item['name']); ?>
                <span class="badge"><?php echo e($item['category_name']); ?></span>
                <h3><?php echo e($item['name']); ?></h3>
                <div class="price"><?php echo money($item['price']); ?></div>
                <a class="btn btn-small" href="pages/catalog.php?search=<?php echo urlencode($item['name']); ?>">Подробнее</a>
            </article>
        <?php endwhile; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
