<?php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();

/* ---------- حذف منتج (للمدير فقط) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    require_admin();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $st->execute([$id]);
    flash($st->rowCount() ? 'success' : 'error', $st->rowCount() ? 'تم حذف المنتج.' : 'المنتج غير موجود.');
    redirect('products.php');
}

/* ---------- البحث والتصفية ---------- */
$q   = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$st  = $_GET['st'] ?? '';
$pg  = max(1, (int)($_GET['p'] ?? 1));
$per = 10;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
    $params[':q1'] = $params[':q2'] = '%' . $q . '%';
}
if ($cat > 0) {
    $where[] = 'p.category_id = :cat';
    $params[':cat'] = $cat;
}
if ($st === 'low')      $where[] = 'p.quantity > 0 AND p.quantity <= p.min_quantity';
elseif ($st === 'out')  $where[] = 'p.quantity = 0';
elseif ($st === 'ok')   $where[] = 'p.quantity > p.min_quantity';
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM products p $w");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$pg = min($pg, $pages);
$offset = ($pg - 1) * $per;

$list = $pdo->prepare(
    "SELECT p.*, c.name AS cat_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     $w ORDER BY p.id DESC LIMIT $per OFFSET $offset"
);
$list->execute($params);
$rows = $list->fetchAll();
$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$page = 'products';
$pageTitle = 'المنتجات';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>المنتجات</h1>
    <p>عرض جميع المنتجات وإدارة بياناتها.</p>
  </div>
  <div class="actions"><a class="btn btn-primary" href="product_form.php">إضافة منتج</a></div>
</div>

<section class="panel">
  <form class="filters" method="get">
    <div class="field" style="flex:2;min-width:200px">
      <label for="q">بحث بالاسم أو الرمز</label>
      <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="مثال: طابعة أو EL-1001">
    </div>
    <div class="field" style="flex:1;min-width:150px">
      <label for="cat">التصنيف</label>
      <select id="cat" name="cat">
        <option value="0">كل التصنيفات</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="flex:1;min-width:150px">
      <label for="st">حالة الكمية</label>
      <select id="st" name="st">
        <option value="">الكل</option>
        <option value="ok"  <?= $st === 'ok'  ? 'selected' : '' ?>>متوفر</option>
        <option value="low" <?= $st === 'low' ? 'selected' : '' ?>>كمية منخفضة</option>
        <option value="out" <?= $st === 'out' ? 'selected' : '' ?>>نفد المخزون</option>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">تطبيق</button>
    <?php if ($q !== '' || $cat || $st !== ''): ?><a class="btn" href="products.php">إعادة ضبط</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">لا توجد منتجات مطابقة. <a href="product_form.php">أضف منتجاً جديداً</a>.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>المنتج</th><th>التصنيف</th><th>الكمية</th><th>الحالة</th><th>سعر التكلفة</th><th>سعر البيع</th><th>إجراءات</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['name']) ?><br><span class="sku"><?= e($r['sku']) ?></span></td>
          <td><?= e($r['cat_name'] ?? '—') ?></td>
          <td class="num-cell"><?= (int)$r['quantity'] ?></td>
          <td><?= status_badge((int)$r['quantity'], (int)$r['min_quantity']) ?></td>
          <td class="num-cell"><?= money($r['cost_price']) ?></td>
          <td class="num-cell"><?= money($r['price']) ?></td>
          <td>
            <div class="row-actions">
              <a href="product_form.php?id=<?= (int)$r['id'] ?>">تعديل</a>
              <a href="stock.php?product=<?= (int)$r['id'] ?>">حركة</a>
              <?php if (is_admin()): ?>
              <form method="post" data-confirm="هل تريد حذف المنتج «<?= e($r['name']) ?>» نهائياً مع سجل حركاته؟">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn-link danger" type="submit">حذف</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="التنقل بين الصفحات">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $pg): ?><span><?= $i ?></span><?php else: ?><a href="<?= e(url_with(['p' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<p class="print-title">إجمالي النتائج: <?= $total ?></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
