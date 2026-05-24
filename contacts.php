<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Контакты — ZOOSHOP';
$activePage = 'contacts';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '') {
        $errors[] = 'Введите имя.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный e-mail.';
    }
    if ($subject === '') {
        $errors[] = 'Введите тему.';
    }
    if ($message === '') {
        $errors[] = 'Введите сообщение.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('ssss', $name, $email, $subject, $message);
        $stmt->execute();

        $mailText = "Имя: $name\nEmail: $email\n\n$message";
        @mail('info@zooshop.local', $subject, $mailText, 'From: ' . $email);

        flash_set('Сообщение сохранено и передано через форму контактов.');
        header('Location: contacts.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2 class="page-title">Контакты</h2>
    <p>Есть вопросы по ассортименту, заказу или доставке? Напишите нам через форму ниже — мы свяжемся с вами в ближайшее время.</p>
</section>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="grid-3">
    <div class="card">
        <h3>Телефон</h3>
        <p>+7 (812) 000-00-00</p>
    </div>
    <div class="card">
        <h3>E-mail</h3>
        <p>info@zooshop.local</p>
    </div>
    <div class="card">
        <h3>Адрес</h3>
        <p>Санкт-Петербург, ZOOSHOP — магазин товаров для домашних животных</p>
    </div>
</section>

<section class="form-card">
    <h2>Связаться с нами</h2>
    <form method="POST" class="form-grid">
        <div class="form-group">
            <label>Имя</label>
            <input type="text" name="name" required value="<?php echo e($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" required value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>Тема</label>
            <input type="text" name="subject" required value="<?php echo e($_POST['subject'] ?? ''); ?>">
        </div>

        <div class="form-group" style="width:100%">
            <label>Сообщение</label>
            <textarea name="message" required><?php echo e($_POST['message'] ?? ''); ?></textarea>
        </div>

        <button class="btn btn-success" type="submit">Отправить</button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
