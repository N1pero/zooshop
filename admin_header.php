<?php
// admin_header.php
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

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Административная панель — ZOOSHOP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css?v=10">
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
            <a href="orders.php" class="<?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">Заказы</a>
            <a href="products.php" class="<?php echo $current_page == 'products.php' ? 'active' : ''; ?>">Товары</a>
            <a href="add_product.php" class="<?php echo $current_page == 'add_product.php' ? 'active' : ''; ?>">Добавить товар</a>
            <a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">Пользователи</a>
        </div>
    </nav>