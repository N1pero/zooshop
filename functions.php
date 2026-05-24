<?php
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function root_prefix()
{
    return strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false ? '../' : '';
}

function url_to($path)
{
    return root_prefix() . $path;
}

function money($value)
{
    return number_format((float)$value, 2, ',', ' ') . ' ₽';
}

function active($current, $expected)
{
    return $current === $expected ? 'active' : '';
}

function product_img($photo, $alt, $class = 'product-img')
{
    if (!$photo) {
        return '<div class="no-photo">Нет фото</div>';
    }

    return '<img class="' . e($class) . '" src="' . e(url_to($photo)) . '" alt="' . e($alt) . '">';
}

function cart_owner_condition(&$params, &$types)
{
    if (!empty($_SESSION['user_id'])) {
        $params[] = (int)$_SESSION['user_id'];
        $types .= 'i';
        return 'ci.user_id = ?';
    }

    $params[] = session_id();
    $types .= 's';
    return 'ci.session_token = ? AND ci.user_id IS NULL';
}

function cart_count($conn)
{
    $params = [];
    $types = '';
    $where = cart_owner_condition($params, $types);

    $stmt = $conn->prepare("SELECT COALESCE(SUM(ci.quantity), 0) AS total FROM cart_items ci WHERE $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['total'];
}

function flash_set($message, $type = 'success')
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_show()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="flash ' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
    }
}

function lab5_flash_runtime_errors()
{
    if (empty($_SESSION['lab5_runtime_errors'])) {
        return;
    }

    echo '<div class="flash error"><strong>Сработал пользовательский обработчик ошибок:</strong>';
    foreach ($_SESSION['lab5_runtime_errors'] as $error) {
        echo '<div>' . e($error) . '</div>';
    }
    echo '</div>';

    unset($_SESSION['lab5_runtime_errors']);
}

function safe_prepare($conn, $sql)
{
    // ЛР5: ошибки подготовки SQL-запроса показываются через mysqli_error(), без пользовательского обработчика.
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo 'Ошибка подготовки запроса: ' . mysqli_error($conn);
        exit();
    }

    return $stmt;
}
?>
