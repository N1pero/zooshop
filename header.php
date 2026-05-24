<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user($conn);
$cartTotal = cart_count($conn);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($pageTitle ?? 'ZOOSHOP'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo e(url_to('css/style.css')); ?>">
</head>
<body>
<div class="wrapper">
<header class="site-header">
    <div class="container header-row">
        <a class="logo" href="<?php echo e(url_to('index.php')); ?>">
            <span class="logo-mark">Z</span>
            <span>
                <strong>ZOOSHOP</strong>
                <small>зоомагазин для кошек и собак</small>
            </span>
        </a>

        <div class="header-gif-wrap">
            <img class="header-gif" src="<?php echo e(url_to('images/decor/header_anim.gif')); ?>" alt="Бегущее животное">
        </div>

        <div class="header-actions">
            <a class="cart-link" href="<?php echo e(url_to('pages/cart.php')); ?>">Корзина: <?php echo $cartTotal; ?></a>

            <?php if ($currentUser): ?>
                <a class="user-pill" href="<?php echo e(url_to('pages/profile.php')); ?>"><?php echo e($currentUser['login']); ?></a>
                <a class="plain-link" href="<?php echo e(url_to('auth/logout.php')); ?>">Выход</a>
            <?php else: ?>
                <a class="plain-link" href="<?php echo e(url_to('auth/login.php')); ?>">Вход</a>
                <a class="btn btn-small" href="<?php echo e(url_to('auth/register.php')); ?>">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<nav class="site-nav">
    <div class="container nav-row">
        <a class="<?php echo active($activePage ?? '', 'home'); ?>" href="<?php echo e(url_to('index.php')); ?>">Главная</a>
        <a class="<?php echo active($activePage ?? '', 'about'); ?>" href="<?php echo e(url_to('pages/about.php')); ?>">О магазине</a>
        <a class="<?php echo active($activePage ?? '', 'gallery'); ?>" href="<?php echo e(url_to('pages/gallery.php')); ?>">Галерея</a>
        <a class="<?php echo active($activePage ?? '', 'catalog'); ?>" href="<?php echo e(url_to('pages/catalog.php')); ?>">Каталог</a>
        <a class="<?php echo active($activePage ?? '', 'cart'); ?>" href="<?php echo e(url_to('pages/cart.php')); ?>">Корзина</a>
        <a class="<?php echo active($activePage ?? '', 'guestbook'); ?>" href="<?php echo e(url_to('pages/guestbook.php')); ?>">Гостевая книга</a>
        <a class="<?php echo active($activePage ?? '', 'contacts'); ?>" href="<?php echo e(url_to('pages/contacts.php')); ?>">Контакты</a>
    </div>
</nav>

<main class="content">
    <div class="container">
        <?php flash_show(); ?>
