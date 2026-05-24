<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'admin_header.php';

$suppliersResult = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
$categoriesResult = $conn->query("SELECT id, name FROM categories ORDER BY name");
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);
$categories = $categoriesResult->fetch_all(MYSQLI_ASSOC);

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Массовое удаление товаров
    if ($action === 'bulk_delete_products') {
        $productIds = $_POST['product_ids'] ?? [];
        $productIds = array_values(array_filter(array_map('intval', (array)$productIds)));
        
        if (count($productIds) === 0) {
            admin_flash('Выберите хотя бы один товар для удаления.', 'error');
        } else {
            $deleted = 0;
            $errors = 0;
            
            foreach ($productIds as $productId) {
                $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
                if (!$stmt) {
                    $errors++;
                    error_log('Ошибка подготовки запроса: ' . mysqli_error($conn));
                    continue;
                }
                if (!mysqli_stmt_bind_param($stmt, 'i', $productId)) {
                    $errors++;
                    error_log('Ошибка связывания параметров: ' . mysqli_error($conn));
                    mysqli_stmt_close($stmt);
                    continue;
                }
                if (!mysqli_stmt_execute($stmt)) {
                    $errors++;
                    error_log('Ошибка выполнения запроса: ' . mysqli_error($conn));
                } elseif (mysqli_stmt_affected_rows($stmt) > 0) {
                    $deleted++;
                }
                mysqli_stmt_close($stmt);
            }
            
            if ($deleted > 0) {
                admin_flash("Удалено товаров: {$deleted}." . ($errors > 0 ? " Ошибок: {$errors}." : ""));
            } else {
                admin_flash("Не удалось удалить выбранные товары (возможно, они есть в заказах).", 'error');
            }
        }
        header('Location: products.php');
        exit;
    }
    
    // Одиночное обновление товара
    if ($action === 'update_product') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $animalType = trim($_POST['animal_type'] ?? '');
        $productType = trim($_POST['product_type'] ?? '');
        $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
        $releaseYear = (int)($_POST['release_year'] ?? date('Y'));
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $photo = trim($_POST['photo'] ?? '');

        if ($productId <= 0 || $name === '' || $animalType === '' || $productType === '' || $price <= 0 || $supplierId <= 0 || $categoryId <= 0) {
            admin_flash('Заполните обязательные поля товара.', 'error');
        } else {
            $stmt = $conn->prepare("UPDATE products SET name = ?, animal_type = ?, product_type = ?, price = ?, release_year = ?, supplier_id = ?, category_id = ?, photo = ? WHERE id = ?");
            $stmt->bind_param('sssdiiisi', $name, $animalType, $productType, $price, $releaseYear, $supplierId, $categoryId, $photo, $productId);
            $stmt->execute();
            admin_flash('Товар обновлен.');
        }
        header('Location: products.php');
        exit;
    }
    
    // Одиночное удаление товара
    if ($action === 'delete_product') {
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
            if (!$stmt) {
                admin_flash('Ошибка подготовки запроса: ' . mysqli_error($conn), 'error');
            } elseif (!mysqli_stmt_bind_param($stmt, 'i', $productId)) {
                admin_flash('Ошибка связывания параметров: ' . mysqli_error($conn), 'error');
                mysqli_stmt_close($stmt);
            } elseif (!mysqli_stmt_execute($stmt)) {
                admin_flash('Ошибка выполнения запроса: ' . mysqli_error($conn), 'error');
                mysqli_stmt_close($stmt);
            } else {
                admin_flash('Товар удален.');
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: products.php');
        exit;
    }
}

// Получение списка товаров
$productSort = $_GET['product_sort'] ?? 'id';
$productDir = strtolower($_GET['product_dir'] ?? 'asc');
$productSortFields = ['id' => 'p.id', 'name' => 'p.name', 'price' => 'p.price'];
$productSortColumn = $productSortFields[$productSort] ?? 'p.id';
$productDirSql = $productDir === 'asc' ? 'ASC' : 'DESC';
$productsResult = $conn->query("SELECT p.*, s.name AS supplier_name, c.name AS category_name FROM products p INNER JOIN suppliers s ON s.id = p.supplier_id INNER JOIN categories c ON c.id = p.category_id ORDER BY $productSortColumn $productDirSql");
$products = $productsResult->fetch_all(MYSQLI_ASSOC);
?>

<section class="card" id="products">
    <div class="section-head-row">
        <h2>Список товаров</h2>
        <a class="btn btn-success" href="add_product.php">Добавить товар</a>
    </div>
    
    <form method="GET" action="products.php" class="product-sort-form">
        <label for="product_sort">Сортировка</label>
        <select id="product_sort" name="product_sort" onchange="this.form.submit()">
            <option value="id" <?php echo $productSort === 'id' ? 'selected' : ''; ?>>ID</option>
            <option value="name" <?php echo $productSort === 'name' ? 'selected' : ''; ?>>Название</option>
            <option value="price" <?php echo $productSort === 'price' ? 'selected' : ''; ?>>Цена</option>
        </select>
        <select name="product_dir" onchange="this.form.submit()">
            <option value="asc" <?php echo $productDir === 'asc' ? 'selected' : ''; ?>>по возрастанию</option>
            <option value="desc" <?php echo $productDir === 'desc' ? 'selected' : ''; ?>>по убыванию</option>
        </select>
        <noscript><button class="btn btn-secondary btn-small" type="submit">Применить</button></noscript>
    </form>

    <?php if (count($products) > 0): ?>
    <form method="POST" id="bulk-delete-form" onsubmit="return confirmBulkDelete();">
        <input type="hidden" name="action" value="bulk_delete_products">
        
        <div class="bulk-panel">
            <label class="select-all-line">
                <input type="checkbox" id="select-all-products"> выбрать все товары на странице
            </label>
            <button class="btn btn-danger" type="submit" id="bulk-delete-btn" disabled>Удалить выбранные</button>
            <span id="selected-count" class="muted" style="margin-left: auto;">Выбрано: 0</span>
        </div>

        <div class="table-wrap">
            <table class="admin-products-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="select-all-products-header" style="width: auto;"></th>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Животное / Тип</th>
                        <th>Цена / Год</th>
                        <th>Поставщик / Категория</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="products-list">
                    <?php foreach ($products as $product): ?>
                    <tr id="product-row-<?php echo $product['id']; ?>" data-product='<?php echo json_encode($product); ?>'>
                        <td class="product-checkbox-cell">
                            <input type="checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" class="product-checkbox" data-id="<?php echo $product['id']; ?>">
                        </td>
                        <td class="product-id"><?php echo $product['id']; ?></td>
                        <td class="product-name"><?php echo e($product['name']); ?></td>
                        <td><?php echo e($product['animal_type']); ?> / <?php echo e($product['product_type']); ?></td>
                        <td><?php echo money($product['price']); ?> / <?php echo $product['release_year']; ?></td>
                        <td><?php echo e($product['supplier_name']); ?> / <?php echo e($product['category_name']); ?></td>
                        <td class="product-actions-cell">
                            <div class="product-actions-cell-inner">
                                <button type="button" class="btn btn-small btn-primary edit-product-btn" data-id="<?php echo $product['id']; ?>">Изменить</button>
                                <button type="button" class="btn btn-small btn-danger delete-single-btn" data-id="<?php echo $product['id']; ?>" data-name="<?php echo e($product['name']); ?>">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php else: ?>
        <p class="notice">Товары не найдены.</p>
    <?php endif; ?>
</section>

<!-- Форма для одиночного удаления -->
<form method="POST" id="single-delete-form" style="display: none;">
    <input type="hidden" name="action" value="delete_product">
    <input type="hidden" name="product_id" id="single-delete-id">
</form>

<script>
// Функция для экранирования HTML
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        if (m === '"') return '&quot;';
        if (m === "'") return '&#039;';
        return m;
    });
}

// Данные для select-ов (передаем из PHP в JS)
const suppliersData = <?php echo json_encode($suppliers); ?>;
const categoriesData = <?php echo json_encode($categories); ?>;

// Обновление счетчика выбранных и состояния кнопки
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const checked = Array.from(checkboxes).filter(cb => cb.checked);
    const count = checked.length;
    
    const selectedCountSpan = document.getElementById('selected-count');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    
    if (selectedCountSpan) selectedCountSpan.textContent = `Выбрано: ${count}`;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = count === 0;
    
    // Обновляем состояние главных чекбоксов
    const allCheckboxes = document.querySelectorAll('.product-checkbox');
    const allChecked = allCheckboxes.length > 0 && Array.from(allCheckboxes).every(cb => cb.checked);
    const someChecked = Array.from(allCheckboxes).some(cb => cb.checked);
    
    const selectAllMain = document.getElementById('select-all-products');
    const selectAllHeader = document.getElementById('select-all-products-header');
    
    if (selectAllMain) selectAllMain.checked = allChecked;
    if (selectAllHeader) {
        selectAllHeader.checked = allChecked;
        selectAllHeader.indeterminate = !allChecked && someChecked;
    }
}

// Выбрать/снять все
function toggleAllProducts(checked) {
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = checked);
    updateSelectedCount();
}

// Подтверждение массового удаления
function confirmBulkDelete() {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    if (checked.length === 0) {
        alert('Выберите хотя бы один товар для удаления.');
        return false;
    }
    return confirm(`Вы уверены, что хотите удалить ${checked.length} товар(ов)? Это действие нельзя отменить.`);
}

// Одиночное удаление с подтверждением
function deleteSingleProduct(id, name) {
    if (confirm(`Удалить товар "${name}"? Это действие нельзя отменить.`)) {
        const form = document.getElementById('single-delete-form');
        document.getElementById('single-delete-id').value = id;
        form.submit();
    }
}

// Режим редактирования
function enterEditMode(rowId, product) {
    const row = document.getElementById(`product-row-${rowId}`);
    
    // Форма редактирования создается вне формы массового удаления.
    // Так кнопка «Сохранить» не конфликтует с кнопкой «Удалить выбранные».
    const oldEditForm = document.getElementById(`edit-form-${rowId}`);
    if (oldEditForm) oldEditForm.remove();

    const editForm = document.createElement('form');
    editForm.method = 'POST';
    editForm.id = `edit-form-${rowId}`;
    editForm.style.display = 'none';
    editForm.innerHTML = `
        <input type="hidden" name="action" value="update_product">
        <input type="hidden" name="product_id" value="${rowId}">
        <input type="hidden" name="photo" value="${escapeHtml(product.photo)}">
    `;
    document.body.appendChild(editForm);
    
    // Сохраняем исходные данные для отмены
    row.dataset.originalName = product.name;
    row.dataset.originalAnimalType = product.animal_type;
    row.dataset.originalProductType = product.product_type;
    row.dataset.originalPrice = product.price;
    row.dataset.originalReleaseYear = product.release_year;
    row.dataset.originalSupplierId = product.supplier_id;
    row.dataset.originalCategoryId = product.category_id;
    
    // Заменяем содержимое ячеек на поля ввода
    row.cells[2].innerHTML = `<input type="text" name="name" value="${escapeHtml(product.name)}" class="edit-input" form="edit-form-${rowId}" style="width: 95%;">`;
    
    row.cells[3].innerHTML = `
        <input type="text" name="animal_type" value="${escapeHtml(product.animal_type)}" class="edit-input" form="edit-form-${rowId}" style="width: 45%;"> / 
        <input type="text" name="product_type" value="${escapeHtml(product.product_type)}" class="edit-input" form="edit-form-${rowId}" style="width: 45%;">
    `;
    
    row.cells[4].innerHTML = `
        <input type="number" step="0.01" name="price" value="${product.price}" class="edit-input" form="edit-form-${rowId}" style="width: 45%;"> / 
        <input type="number" name="release_year" value="${product.release_year}" class="edit-input" form="edit-form-${rowId}" style="width: 40%;">
    `;
    
    // Создаем select для поставщика
    let supplierSelect = `<select name="supplier_id" class="edit-input" form="edit-form-${rowId}" style="width: 45%;">`;
    suppliersData.forEach(supplier => {
        supplierSelect += `<option value="${supplier.id}" ${product.supplier_id == supplier.id ? 'selected' : ''}>${escapeHtml(supplier.name)}</option>`;
    });
    supplierSelect += `</select> / `;
    
    // Создаем select для категории
    let categorySelect = `<select name="category_id" class="edit-input" form="edit-form-${rowId}" style="width: 45%;">`;
    categoriesData.forEach(category => {
        categorySelect += `<option value="${category.id}" ${product.category_id == category.id ? 'selected' : ''}>${escapeHtml(category.name)}</option>`;
    });
    categorySelect += `</select>`;
    
    row.cells[5].innerHTML = supplierSelect + categorySelect;
    
    // Меняем кнопки
    row.cells[6].innerHTML = `
        <div class="product-actions-cell-inner">
            <button class="btn btn-small btn-success" type="submit" form="edit-form-${rowId}">Сохранить</button>
            <button class="btn btn-small btn-secondary cancel-edit-btn" type="button" data-id="${rowId}">Отмена</button>
            <button class="btn btn-small btn-danger delete-single-btn" type="button" data-id="${rowId}" data-name="${escapeHtml(product.name)}">Удалить</button>
        </div>
    `;
    
    // Добавляем обработчики
    row.querySelector(`.cancel-edit-btn`).addEventListener('click', () => cancelEdit(rowId));
    row.querySelector(`.delete-single-btn`).addEventListener('click', (e) => {
        const id = e.currentTarget.dataset.id;
        const name = e.currentTarget.dataset.name;
        deleteSingleProduct(id, name);
    });
}

// Отмена редактирования
function cancelEdit(rowId) {
    const row = document.getElementById(`product-row-${rowId}`);
    const product = JSON.parse(row.dataset.product);
    const editForm = document.getElementById(`edit-form-${rowId}`);
    if (editForm) editForm.remove();
    
    // Восстанавливаем исходное содержимое
    row.cells[2].innerHTML = escapeHtml(product.name);
    row.cells[3].innerHTML = `${escapeHtml(product.animal_type)} / ${escapeHtml(product.product_type)}`;
    row.cells[4].innerHTML = `${formatMoney(product.price)} / ${product.release_year}`;
    row.cells[5].innerHTML = `${escapeHtml(product.supplier_name)} / ${escapeHtml(product.category_name)}`;
    row.cells[6].innerHTML = `
        <div class="product-actions-cell-inner">
            <button type="button" class="btn btn-small btn-primary edit-product-btn" data-id="${product.id}">Изменить</button>
            <button type="button" class="btn btn-small btn-danger delete-single-btn" data-id="${product.id}" data-name="${escapeHtml(product.name)}">Удалить</button>
        </div>
    `;
    
    // Перепривязываем обработчики
    row.querySelector('.edit-product-btn').addEventListener('click', () => enterEditMode(product.id, product));
    row.querySelector('.delete-single-btn').addEventListener('click', (e) => {
        deleteSingleProduct(product.id, product.name);
    });
}

// Форматирование цены
function formatMoney(amount) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount);
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Обработчики для кнопок "Изменить"
    document.querySelectorAll('.edit-product-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const row = document.getElementById(`product-row-${id}`);
            if (row && row.dataset.product) {
                const product = JSON.parse(row.dataset.product);
                enterEditMode(id, product);
            }
        });
    });
    
    // Обработчики для кнопок "Удалить" (одиночные)
    document.querySelectorAll('.delete-single-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const name = this.dataset.name;
            deleteSingleProduct(id, name);
        });
    });
    
    // Обработчики для чекбоксов
    const selectAllMain = document.getElementById('select-all-products');
    const selectAllHeader = document.getElementById('select-all-products-header');
    
    if (selectAllMain) {
        selectAllMain.addEventListener('change', function() {
            toggleAllProducts(this.checked);
        });
    }
    
    if (selectAllHeader) {
        selectAllHeader.addEventListener('change', function() {
            toggleAllProducts(this.checked);
        });
    }
    
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    updateSelectedCount();
});
</script>

<style>
/* Дополнительные стили для страницы товаров */
.admin-products-table .product-checkbox-cell {
    width: 30px;
    text-align: center;
}

.admin-products-table .edit-input {
    padding: 6px 8px;
    border: 1px solid #c7d8df;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
}

.admin-products-table .edit-input:focus {
    outline: none;
    border-color: #1f8f7a;
    box-shadow: 0 0 0 2px rgba(31, 143, 122, 0.2);
}

.admin-products-table .product-actions-cell {
    white-space: nowrap;
    width: 180px;
}

.admin-products-table .btn-small {
    padding: 6px 10px;
    font-size: 12px;
    margin: 0 2px;
}

.bulk-panel {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    margin-bottom: 16px;
    background: #f8fbfd;
    border: 1px solid #dde9ee;
    border-radius: 16px;
}

.select-all-line {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: #1d5269;
    cursor: pointer;
}

#selected-count {
    font-size: 13px;
}

#bulk-delete-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    background: #1d73a7;
}
.btn-primary:hover {
    background: #145a85;
}

/* FIX: кнопки действий больше не обрезаются справа и не отправляют форму массового удаления */
#products .admin-products-table th:last-child,
#products .admin-products-table td:last-child {
    width: 118px;
}

#products .product-actions-cell {
    width: 118px;
    min-width: 118px;
    white-space: normal;
    overflow: visible;
}

#products .product-actions-cell-inner {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    width: 100%;
}

#products .product-actions-cell .btn-small {
    display: block;
    width: 100%;
    min-width: 0;
    margin: 0;
    padding: 7px 5px;
    font-size: 11px;
    line-height: 1.15;
    text-align: center;
    box-sizing: border-box;
}

#products .table-wrap {
    overflow-x: auto;
}

</style>

<?php require_once 'admin_footer.php'; ?>