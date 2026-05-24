<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Демонстрация ошибок — ZOOSHOP';
$activePage = '';

$demo = $_GET['demo'] ?? '';
$dbMessage = '';

if ($demo === 'warning') {
    trigger_error('Тестовая пользовательская ошибка типа E_USER_WARNING для ЛР5', E_USER_WARNING);
}

if ($demo === 'notice') {
    trigger_error('Тестовое пользовательское замечание типа E_USER_NOTICE для ЛР5', E_USER_NOTICE);
}

if ($demo === 'user_fatal') {
    lab5_trigger_user_fatal('Тестовая пользовательская фатальная ошибка типа E_USER_ERROR для ЛР5');
}

if ($demo === 'db_ok') {
    $unsafeLogin = "admin' OR '1'='1";
    $stmt = safe_prepare($conn, 'SELECT id, login FROM users WHERE login = ?');
    if (!mysqli_stmt_bind_param($stmt, 's', $unsafeLogin)) {
        echo 'Ошибка связывания параметров: ' . mysqli_error($conn);
        exit();
    }
    if (!mysqli_stmt_execute($stmt)) {
        echo 'Ошибка выполнения запроса: ' . mysqli_error($conn);
        exit();
    }
    $result = mysqli_stmt_get_result($stmt);
    $dbMessage = 'Защищенный запрос выполнен. Записей найдено: ' . mysqli_num_rows($result) . '. SQL-инъекция не сработала, потому что значение передано параметром.';
    mysqli_stmt_close($stmt);
}

if ($demo === 'db_error') {
    // login = 'demo' уже создается в init_db.php, поэтому INSERT специально падает на этапе execute.
    $login = 'demo';
    $email = 'demo_error_' . time() . '@zooshop.local';
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $datetime = date('Y-m-d');

    // ЛР5: пример из методички — prepare, bind_param и execute проверяются через mysqli_error().
    // Пользовательский обработчик ошибок здесь НЕ используется.
    $sql = "INSERT INTO users (login, email, password_hash, registration_date) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo 'Ошибка подготовки запроса: ' . mysqli_error($conn);
        exit();
    }

    if (!mysqli_stmt_bind_param($stmt, 'ssss', $login, $email, $password, $datetime)) {
        echo 'Ошибка связывания параметров: ' . mysqli_error($conn);
        exit();
    }

    if (!mysqli_stmt_execute($stmt)) {
        echo 'Ошибка выполнения запроса: ' . mysqli_error($conn);
        exit();
    }

    mysqli_stmt_close($stmt);
    $dbMessage = 'Демонстрационный SQL-запрос выполнен без ошибок.';
}

if ($demo === 'fatal') {
    undefined_lab5_function();
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h2>Демонстрация обработчика ошибок</h2>
    <p>Эта страница нужна для ЛР5: на ней можно показать обработку нефатальных ошибок, ошибок БД, защищенного SQL-запроса и фатальной ошибки.</p>

    <?php lab5_flash_runtime_errors(); ?>

    <?php if ($dbMessage): ?>
        <div class="flash"><?php echo e($dbMessage); ?></div>
    <?php endif; ?>

    <div class="actions">
        <a class="btn" href="error_demo.php?demo=warning">E_USER_WARNING</a>
        <a class="btn" href="error_demo.php?demo=notice">E_USER_NOTICE</a>
        <a class="btn btn-danger" href="error_demo.php?demo=user_fatal">E_USER_ERROR</a>
        <a class="btn btn-success" href="error_demo.php?demo=db_ok">Защита от SQL-инъекции</a>
        <a class="btn btn-secondary" href="error_demo.php?demo=db_error">Ошибка запроса к БД</a>
        <a class="btn btn-danger" href="error_demo.php?demo=fatal">Фатальная ошибка</a>
    </div>
</section>

<section class="notice">
    <h3>Примеры для отчета</h3>
    <p><strong>E_USER_WARNING</strong> и <strong>E_USER_NOTICE</strong> вызываются через <code>trigger_error()</code>, а <strong>E_USER_ERROR</strong> вызывается через <code>lab5_trigger_user_fatal()</code>; все три типа проходят через пользовательский обработчик <code>set_error_handler</code>.</p>
    <p><strong>Ошибка БД</strong> демонстрируется через <code>mysqli_prepare()</code>, <code>mysqli_stmt_bind_param()</code>, <code>mysqli_stmt_execute()</code> и <code>mysqli_error()</code>, без пользовательского обработчика ошибок.</p>
    <p><strong>SQL-инъекция</strong> проверяется строкой <code>admin' OR '1'='1</code>, которая передается в подготовленный запрос как обычный параметр.</p>
    <p><strong>Фатальная ошибка</strong> обрабатывается через register_shutdown_function.</p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
