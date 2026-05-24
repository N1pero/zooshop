<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Регистрация — ZOOSHOP';
$activePage = '';

$errors = [];
$old = [
    'login' => '',
    'email' => '',
    'registration_date' => date('Y-m-d'),
];

function generate_captcha()
{
    $_SESSION['captcha_a'] = random_int(1, 9);
    $_SESSION['captcha_b'] = random_int(1, 9);
}

if (empty($_SESSION['captcha_a']) || empty($_SESSION['captcha_b'])) {
    generate_captcha();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordRepeat = (string)($_POST['password_repeat'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $registrationDate = trim($_POST['registration_date'] ?? date('Y-m-d'));
    $captcha = trim($_POST['captcha'] ?? '');
    $honeypot = trim($_POST['site'] ?? '');
    $photoPath = null;

    $old = [
        'login' => $login,
        'email' => $email,
        'registration_date' => $registrationDate,
    ];

    // Антибот-проверка: скрытое поле должно быть пустым, а пример решен правильно.
    if ($honeypot !== '') {
        $errors[] = 'Проверка на робота не пройдена.';
    }

    $captchaAnswer = (int)($_SESSION['captcha_a'] ?? 0) + (int)($_SESSION['captcha_b'] ?? 0);
    if ($captcha === '' || (int)$captcha !== $captchaAnswer) {
        $errors[] = 'Неверный ответ на проверочный вопрос. Подтвердите, что вы не робот.';
    }

    // Проверка правильности введенных данных.
    if ($login === '' || mb_strlen($login) < 3 || mb_strlen($login) > 60) {
        $errors[] = 'Логин должен содержать от 3 до 60 символов.';
    }

    if (!preg_match('/^[a-zA-Z0-9_а-яА-ЯёЁ-]+$/u', $login)) {
        $errors[] = 'Логин может содержать только буквы, цифры, дефис и нижнее подчеркивание.';
    }

    if (mb_strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов.';
    }

    if ($password !== $passwordRepeat) {
        $errors[] = 'Пароли не совпадают.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        $errors[] = 'Введите корректный e-mail длиной до 120 символов.';
    }

    $date = DateTime::createFromFormat('Y-m-d', $registrationDate);
    if (!$date || $date->format('Y-m-d') !== $registrationDate) {
        $errors[] = 'Введите корректную дату регистрации.';
    }

    // Защита от SQL-инъекций: пользовательские данные передаются через параметры, а не склеиваются со строкой SQL.
    $stmt = safe_prepare($conn, 'SELECT id FROM users WHERE login = ? OR email = ? LIMIT 1');
    if (!mysqli_stmt_bind_param($stmt, 'ss', $login, $email)) {
        echo 'Ошибка связывания параметров: ' . mysqli_error($conn);
        exit();
    }
    if (!mysqli_stmt_execute($stmt)) {
        echo 'Ошибка выполнения запроса: ' . mysqli_error($conn);
        exit();
    }
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_fetch_assoc($result)) {
        $errors[] = 'Пользователь с таким логином или e-mail уже существует.';
    }
    mysqli_stmt_close($stmt);

    if (!empty($_FILES['photo']['name'])) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Ошибка загрузки фотографии.';
        } else {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $maxSize = 2 * 1024 * 1024;

            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Фото должно быть в формате jpg, png, gif или webp.';
            } elseif ($_FILES['photo']['size'] > $maxSize) {
                $errors[] = 'Размер фото не должен превышать 2 МБ.';
            } else {
                $imageInfo = @getimagesize($_FILES['photo']['tmp_name']);
                if ($imageInfo === false) {
                    $errors[] = 'Загруженный файл не является изображением.';
                } else {
                    $uploadDir = __DIR__ . '/../images/users/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    $fileName = 'user_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
                    $target = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                        $photoPath = 'images/users/' . $fileName;
                    } else {
                        $errors[] = 'Не удалось сохранить фотографию.';
                    }
                }
            }
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (login, password_hash, email, photo, registration_date) VALUES (?, ?, ?, ?, ?)";

        // ЛР5: обработка ошибок БД сделана один в один по методичке.
        // Здесь НЕ используется trigger_error() и пользовательский обработчик ошибок.
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            echo 'Ошибка подготовки запроса: ' . mysqli_error($conn);
            exit();
        }

        if (!mysqli_stmt_bind_param($stmt, 'sssss', $login, $hash, $email, $photoPath, $registrationDate)) {
            echo 'Ошибка связывания параметров: ' . mysqli_error($conn);
            exit();
        }

        if (!mysqli_stmt_execute($stmt)) {
            echo 'Ошибка выполнения запроса: ' . mysqli_error($conn);
            exit();
        }

        $_SESSION['user_id'] = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        session_regenerate_id(true);
        generate_captcha();

        flash_set('Регистрация прошла успешно. Данные сохранены через защищенный подготовленный запрос.');
        header('Location: ../pages/profile.php');
        exit;
    }

    if ($errors) {
        generate_captcha();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="form-card">
    <h2>Регистрация</h2>
    <p>Создайте аккаунт. Форма проверяет введенные данные, защищает запросы к БД и содержит антибот-проверку.</p>

    <?php lab5_flash_runtime_errors(); ?>

    <?php if ($errors): ?>
        <div class="flash error">
            <strong>Исправьте ошибки:</strong>
            <?php foreach ($errors as $error): ?>
                <div><?php echo e($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-grid" novalidate>
        <div class="form-group">
            <label>Логин</label>
            <input type="text" name="login" required minlength="3" maxlength="60" value="<?php echo e($old['login']); ?>">
        </div>

        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="password" required minlength="6">
        </div>

        <div class="form-group">
            <label>Повторите пароль</label>
            <input type="password" name="password_repeat" required minlength="6">
        </div>

        <div class="form-group">
            <label>Дата регистрации</label>
            <input type="date" name="registration_date" required value="<?php echo e($old['registration_date']); ?>">
        </div>

        <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" required maxlength="120" value="<?php echo e($old['email']); ?>">
        </div>

        <div class="form-group">
            <label>Фото</label>
            <input type="file" name="photo" accept="image/*">
        </div>

        <div class="form-group lab5-hidden-field">
            <label>Не заполняйте это поле</label>
            <input type="text" name="site" autocomplete="off">
        </div>

        <div class="form-group">
            <label>Проверка: сколько будет <?php echo (int)$_SESSION['captcha_a']; ?> + <?php echo (int)$_SESSION['captcha_b']; ?>?</label>
            <input type="number" name="captcha" required>
        </div>

        <button class="btn btn-success" type="submit">Зарегистрироваться</button>
    </form>
</section>

<p class="muted" style="margin-top: -8px; text-align: center; opacity: .65;">
    <a class="muted" href="../pages/error_demo.php">Демонстрация обработки ошибок</a>
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
