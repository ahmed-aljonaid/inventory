<?php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$errors = [];
$selected = (int)($_GET['product'] ?? $_POST['product_id'] ?? 0);
$type = $_POST['type'] ?? 'in';
$qty = $_POST['quantity'] ?? '';
$note = trim($_POST['note'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $qtyInt = ctype_digit((string)$qty) ? (int)$qty : 0;

    if ($selected <= 0)                  $errors[] = 'اختر المنتج.';
    if (!in_array($type, ['in', 'out'], true)) $errors[] = 'نوع الحركة غير صحيح.';
    if ($qtyInt <= 0)                    $errors[] = 'الكمية يجب أن تكون عدداً صحيحاً أكبر من صفر.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT name, quantity FROM products WHERE id = ? FOR UPDATE');
            $st->execute([$selected]);
            $p = $st->fetch();
            if (!$p) {
                throw new RuntimeException('المنتج غير موجود.');
            }
            if ($type === 'out' && $qtyInt > (int)$p['quantity']) {
                throw new RuntimeException('الكمية المطلوب إخراجها (' . $qtyInt . ') أكبر من المتوفر (' . (int)$p['quantity'] . ').');
            }
            $new = (int)$p['quantity'] + ($type === 'in' ? $qtyInt : -$qtyInt);
            $pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$new, $selected]);
            $pdo->prepare('INSERT INTO stock_movements (product_id,user_id,type,quantity,note) VALUES (?,?,?,?,?)')
                ->execute([$selected, current_user()['id'], $type, $qtyInt, $note ?: null]);
            $pdo->commit();
            flash('success', 'تم تسجيل الحركة. الكمية الحالية للمنتج «' . $p['name'] . '»: ' . $new);
            redirect('stock.php?product=' . $selected);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'حدث خطأ أثناء الحفظ، حاول مرة أخرى.';
        }
    }
}

$products = $pdo->query('SELECT id, sku, name, quantity FROM products ORDER BY name')->fetchAll();

$filter = (int)($_GET['product'] ?? 0);
$sql = 'SELECT m.*, p.name AS product_name, p.sku, u.full_name
        FROM stock_movements m
        JOIN products p ON p.id = m.product_id
        LEFT JOIN users u ON u.id = m.user_id ' .
       ($filter ? 'WHERE m.product_id = ? ' : '') . 'ORDER BY m.id DESC LIMIT 50';
$mv = $pdo->prepare($sql);
$mv->execute($filter ? [$filter] : []);
$movements = $mv->fetchAll();

$page = 'stock';
$pageTitle = 'حركة المخزون';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>حركة المخزون</h1>
    <p>سجّل الكميات الواردة والصادرة وتابع آخر العمليات.</p>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2>تسجيل حركة جديدة</h2></div>
  <form class="panel-body" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field full">
        <label for="product_id">المنتج</label>
        <select id="product_id" name="product_id" required>
          <option value="">— اختر المنتج —</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $selected === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e($p['name']) ?> (<?= e($p['sku']) ?>) — المتوفر: <?= (int)$p['quantity'] ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="type">نوع الحركة</label>
        <select id="type" name="type">
          <option value="in"  <?= $type === 'in'  ? 'selected' : '' ?>>إدخال (توريد)</option>
          <option value="out" <?= $type === 'out' ? 'selected' : '' ?>>إخراج (صرف / بيع)</option>
        </select>
      </div>
      <div class="field">
        <label for="quantity">الكمية</label>
        <input type="number" id="quantity" name="quantity" min="1" step="1" value="<?= e($qty) ?>" required>
      </div>
      <div class="field full">
        <label for="note">ملاحظة (اختياري)</label>
        <input type="text" id="note" name="note" maxlength="255" value="<?= e($note) ?>" placeholder="مثال: فاتورة توريد رقم 1024">
      </div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">تسجيل الحركة</button></div>
  </form>
</section>

<section class="panel">
  <div class="panel-head">
    <h2><?= $filter ? 'حركات المنتج المحدد' : 'آخر 50 حركة' ?></h2>
    <?php if ($filter): ?><a href="stock.php">عرض كل الحركات</a><?php endif; ?>
  </div>
  <?php if (!$movements): ?>
    <div class="empty">لا توجد حركات مسجلة.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>التاريخ</th><th>المنتج</th><th>النوع</th><th>الكمية</th><th>بواسطة</th><th>ملاحظة</th></tr></thead>
      <tbody>
      <?php foreach ($movements as $m): ?>
        <tr>
          <td class="num-cell"><?= e(substr($m['created_at'], 0, 16)) ?></td>
          <td><?= e($m['product_name']) ?><br><span class="sku"><?= e($m['sku']) ?></span></td>
          <td><span class="badge badge-<?= e($m['type']) ?>"><?= e(movement_label($m['type'])) ?></span></td>
          <td class="num-cell"><?= $m['type'] === 'adjust' && $m['quantity'] > 0 ? '+' : '' ?><?= (int)$m['quantity'] ?></td>
          <td><?= e($m['full_name'] ?? '—') ?></td>
          <td><?= e($m['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
