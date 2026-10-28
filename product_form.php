<?php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $id > 0;

$data = [
    'sku' => '', 'name' => '', 'category_id' => '', 'description' => '', 'supplier' => '',
    'quantity' => 0, 'min_quantity' => 5, 'cost_price' => '0.00', 'price' => '0.00',
];

if ($editing) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $existing = $st->fetch();
    if (!$existing) {
        flash('error', 'المنتج المطلوب غير موجود.');
        redirect('products.php');
    }
    $data = array_merge($data, $existing);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (['sku', 'name', 'description', 'supplier'] as $f) {
        $data[$f] = trim($_POST[$f] ?? '');
    }
    $data['category_id']  = (int)($_POST['category_id'] ?? 0) ?: '';
    $data['quantity']     = $_POST['quantity'] ?? '';
    $data['min_quantity'] = $_POST['min_quantity'] ?? '';
    $data['cost_price']   = $_POST['cost_price'] ?? '';
    $data['price']        = $_POST['price'] ?? '';

    if ($data['sku'] === '')  $errors[] = 'رمز المنتج (SKU) مطلوب.';
    if ($data['name'] === '') $errors[] = 'اسم المنتج مطلوب.';
    if (!ctype_digit((string)$data['quantity']))     $errors[] = 'الكمية يجب أن تكون عدداً صحيحاً لا يقل عن صفر.';
    if (!ctype_digit((string)$data['min_quantity'])) $errors[] = 'الحد الأدنى يجب أن يكون عدداً صحيحاً لا يقل عن صفر.';
    if (!is_numeric($data['cost_price']) || $data['cost_price'] < 0) $errors[] = 'سعر التكلفة غير صحيح.';
    if (!is_numeric($data['price']) || $data['price'] < 0)           $errors[] = 'سعر البيع غير صحيح.';

    if (!$errors) {
        $dup = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND id <> ?');
        $dup->execute([$data['sku'], $id]);
        if ($dup->fetch()) $errors[] = 'رمز المنتج مستخدم لمنتج آخر.';
    }

    if (!$errors) {
        $qty = (int)$data['quantity'];
        $vals = [
            $data['sku'], $data['name'], $data['category_id'] ?: null,
            $data['description'] ?: null, $data['supplier'] ?: null,
            $qty, (int)$data['min_quantity'], (float)$data['cost_price'], (float)$data['price'],
        ];
        $uid = current_user()['id'];

        $pdo->beginTransaction();
        try {
            if ($editing) {
                $oldQty = (int)$existing['quantity'];
                $pdo->prepare(
                    'UPDATE products SET sku=?, name=?, category_id=?, description=?, supplier=?,
                     quantity=?, min_quantity=?, cost_price=?, price=? WHERE id=?'
                )->execute(array_merge($vals, [$id]));
                if ($qty !== $oldQty) {
                    $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,type,quantity,note) VALUES (?,?,'adjust',?,?)")
                        ->execute([$id, $uid, $qty - $oldQty, "تعديل يدوي للكمية من $oldQty إلى $qty"]);
                }
                flash('success', 'تم حفظ التعديلات.');
            } else {
                $pdo->prepare(
                    'INSERT INTO products (sku,name,category_id,description,supplier,quantity,min_quantity,cost_price,price)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute($vals);
                $newId = (int)$pdo->lastInsertId();
                if ($qty > 0) {
                    $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,type,quantity,note) VALUES (?,?,'in',?,'رصيد افتتاحي')")
                        ->execute([$newId, $uid, $qty]);
                }
                flash('success', 'تمت إضافة المنتج.');
            }
            $pdo->commit();
            redirect('products.php');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'حدث خطأ أثناء الحفظ، حاول مرة أخرى.';
        }
    }
}

$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$page = 'products';
$pageTitle = $editing ? 'تعديل منتج' : 'إضافة منتج';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= $editing ? 'تعديل بيانات المنتج' : 'إضافة منتج جديد' ?></h1>
    <p>الحقول المطلوبة: الرمز والاسم والكميات والأسعار.</p>
  </div>
  <div class="actions"><a class="btn" href="products.php">العودة إلى القائمة</a></div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="panel">
  <form class="panel-body" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-grid">
      <div class="field">
        <label for="sku">رمز المنتج (SKU)</label>
        <input type="text" id="sku" name="sku" value="<?= e($data['sku']) ?>" required>
      </div>
      <div class="field">
        <label for="name">اسم المنتج</label>
        <input type="text" id="name" name="name" value="<?= e($data['name']) ?>" required>
      </div>
      <div class="field">
        <label for="category_id">التصنيف</label>
        <select id="category_id" name="category_id">
          <option value="">بدون تصنيف</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$data['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="supplier">المورّد</label>
        <input type="text" id="supplier" name="supplier" value="<?= e($data['supplier']) ?>">
      </div>
      <div class="field">
        <label for="quantity">الكمية الحالية</label>
        <input type="number" id="quantity" name="quantity" min="0" step="1" value="<?= e($data['quantity']) ?>" required>
        <?php if ($editing): ?><span class="hint">تغيير الكمية هنا يُسجَّل كتعديل في حركة المخزون.</span><?php endif; ?>
      </div>
      <div class="field">
        <label for="min_quantity">الحد الأدنى للتنبيه</label>
        <input type="number" id="min_quantity" name="min_quantity" min="0" step="1" value="<?= e($data['min_quantity']) ?>" required>
        <span class="hint">يظهر تنبيه عندما تصل الكمية إلى هذا الحد أو أقل.</span>
      </div>
      <div class="field">
        <label for="cost_price">سعر التكلفة (<?= CURRENCY ?>)</label>
        <input type="number" id="cost_price" name="cost_price" min="0" step="0.01" value="<?= e($data['cost_price']) ?>" required>
      </div>
      <div class="field">
        <label for="price">سعر البيع (<?= CURRENCY ?>)</label>
        <input type="number" id="price" name="price" min="0" step="0.01" value="<?= e($data['price']) ?>" required>
      </div>
      <div class="field full">
        <label for="description">الوصف</label>
        <textarea id="description" name="description"><?= e($data['description']) ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= $editing ? 'حفظ التعديلات' : 'إضافة المنتج' ?></button>
      <a class="btn" href="products.php">إلغاء</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
