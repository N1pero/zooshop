<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Гостевая книга — ZOOSHOP';
$activePage = 'guestbook';
$currentUser = current_user($conn);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author = trim($_POST['author_name'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $userId = $currentUser ? (int)$currentUser['id'] : null;

    if ($author === '') {
        $errors[] = 'Введите имя.';
    }
    if ($message === '') {
        $errors[] = 'Введите сообщение.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO guestbook (user_id, author_name, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iss', $userId, $author, $message);
        $stmt->execute();

        flash_set('Запись добавлена в гостевую книгу.');
        header('Location: guestbook.php');
        exit;
    }
}

$records = $conn->query("
    SELECT g.author_name, g.message, g.created_at, u.login
    FROM guestbook g
    LEFT JOIN users u ON u.id = g.user_id
    ORDER BY g.created_at DESC
");

require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2 class="page-title">Гостевая книга</h2>
    <p>Поделитесь впечатлениями о магазине, товарах и сервисе — нам важно мнение каждого покупателя.</p>
</section>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="form-card">
    <h2>Оставить отзыв</h2>
    <form method="POST" class="form-grid">
        <div class="form-group">
            <label>Имя</label>
            <input type="text" name="author_name" required value="<?php echo e($_POST['author_name'] ?? ($currentUser['login'] ?? '')); ?>">
        </div>

        <div class="form-group" style="width:100%">
            <label>Сообщение</label>
            <textarea name="message" required><?php echo e($_POST['message'] ?? ''); ?></textarea>
        </div>

        <button class="btn btn-success" type="submit">Опубликовать</button>
    </form>
</section>

<section class="card">
    <h2>Отзывы покупателей</h2>

    <?php if ($records->num_rows === 0): ?>
        <p>Пока нет записей.</p>
    <?php else: ?>
        <?php while ($record = $records->fetch_assoc()): ?>
            <div class="notice">
                <strong><?php echo e($record['author_name']); ?></strong>
                <span class="muted"> · <?php echo e($record['created_at']); ?></span>
                <p><?php echo nl2br(e($record['message'])); ?></p>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
