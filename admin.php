<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

flash_set('Административная панель открывается отдельно от сайта.', 'error');
header('Location: ../admin/index.php');
exit;
?>
