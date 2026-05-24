<?php
session_start();

// ЛР5: ошибки MySQLi обрабатываются вручную через mysqli_error(), как в методичке.
// Поэтому исключения mysqli специально отключены.
mysqli_report(MYSQLI_REPORT_OFF);

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'zooshop_lr4_db';

$conn = mysqli_connect($host, $user, $password);
if (!$conn) {
    echo 'Ошибка подключения к базе данных: ' . mysqli_connect_error();
    exit();
}

$sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!mysqli_query($conn, $sql)) {
    echo 'Ошибка создания базы данных: ' . mysqli_error($conn);
    exit();
}

if (!mysqli_select_db($conn, $database)) {
    echo 'Ошибка выбора базы данных: ' . mysqli_error($conn);
    exit();
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    echo 'Ошибка установки кодировки: ' . mysqli_error($conn);
    exit();
}

require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/error_handler.php';
?>
