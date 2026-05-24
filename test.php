<?php
require_once __DIR__ . '/includes/config.php';

$tables = ['users', 'suppliers', 'categories', 'products', 'orders', 'order_items', 'cart_items', 'guestbook', 'contact_messages'];

echo '<h1>Проверка ZOOSHOP LR4</h1>';
echo '<p>Подключение к базе данных выполнено.</p>';
echo '<table border="1" cellpadding="8" cellspacing="0">';
echo '<tr><th>Таблица</th><th>Количество записей</th></tr>';

foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
    $row = $result->fetch_assoc();
    echo '<tr><td>' . htmlspecialchars($table) . '</td><td>' . (int)$row['total'] . '</td></tr>';
}

echo '</table>';
echo '<p><a href="index.php">На главную</a></p>';
?>
