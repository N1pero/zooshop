<?php
require_once __DIR__ . '/../includes/config.php';
unset($_SESSION['admin_panel_id'], $_SESSION['admin_panel_login']);
header('Location: index.php');
exit;
?>
