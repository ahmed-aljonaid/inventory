<?php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();

$s = $pdo->query(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(quantity),0) AS units,
            COALESCE(SUM(quantity*cost_price),0) AS value,
            COALESCE(SUM(quantity=0),0) AS out_count,
            COALESCE(SUM(quantity>0 AND quantity<=min_quantity),0) AS low_count
     FROM products'
)->fetch();

$lowItems = $pdo->query(
    'SELECT id, sku, name, quantity, min_quantity FROM products
     WHERE quantity <= min_quantity ORDER BY quantity ASC, name LIMIT 8'
)->fetchAll();

$byCat = $pdo->query(
    "SELECT COALESCE(c.name,'بدون تصنيف') AS name, COUNT(p.id) AS cnt,
            COALESCE(SUM(p.quantity*p.cost_price),0) AS val
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     GROUP BY c.id, c.name ORDER BY val DESC"
)->fetchAll();
$maxVal = max(1, ...array_map(fn($r) => (float)$r['val'], $byCat ?: [['val' => 1]]));

$recent = $pdo->query(
    'SELECT m.*, p.name AS product_name, u.full_name
     FROM stock_movements m
     JOIN products p ON p.id = m.product_id
     LEFT JOIN users u ON u.id = m.user_id
     ORDER BY m.id DESC LIMIT 6'
)->fetchAll();

$page = 'index';
$pageTitle = 'لوحة التحكم';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>لوحة التحكم</h1>
    <p>ملخص حالة المخزون الحالية.</p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="product_form.php">إضافة منتج</a>
    <a class="btn" href="stock.php">تسجيل حركة مخزون</a>
  </div>
</div>

<section class="ledger" aria-label="مؤشرات المخزون">
  <div><div class="num"><?= (int)$s['total'] ?></div><div class="lbl">عدد المنتجات</div></div>
  <div><div class="num"><?= number_format((int)$s['units']) ?></div><div class="lbl">إجمالي الوحدات</div></div>
  <div><div class="num"><?= number_format((float)$s['value']) ?> <small><?= CURRENCY ?></small></div><div class="lbl">قيمة المخزون (بسعر التكلفة)</div></div>
  <div class="warn"><div class="num"><?= (int)$s['low_count'] ?></div><div class="lbl">كميات منخفضة</div></div>
  <div class="bad"><div class="num"><?= (int)$s['out_count'] ?></div><div class="lbl">أصناف نفدت</div></div>
</section>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head">
      <h2>أصناف تحتاج إلى تزويد</h2>
      <a href="reports.php?type=low">عرض التقرير</a>
    </div>
    <?php if (!$lowItems): ?>
      <div class="empty">جميع الأصناف ضمن الحد الآمن.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>المنتج</th><th>المتوفر</th><th>الحد الأدنى</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lowItems as $r): ?>
          <tr>
            <td><?= e($r['name']) ?><br><span class="sku"><?= e($r['sku']) ?></span></td>
            <td class="num-cell"><?= (int)$r['quantity'] ?></td>
            <td class="num-cell"><?= (int)$r['min_quantity'] ?></td>
            <td><?= status_badge((int)$r['quantity'], (int)$r['min_quantity']) ?></td>
            <td><a href="stock.php?product=<?= (int)$r['id'] ?>">إضافة كمية</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>قيمة المخزون حسب التصنيف</h2></div>
    <div class="panel-body">
      <?php if (!$byCat): ?>
        <div class="empty">لا توجد بيانات بعد.</div>
      <?php else: ?>
      <ul class="bars">
        <?php foreach ($byCat as $c): ?>
          <li>
            <div class="top"><span><?= e($c['name']) ?> (<?= (int)$c['cnt'] ?>)</span><span class="num-cell"><?= money($c['val']) ?></span></div>
            <div class="track"><div class="fill" style="width:<?= round($c['val'] / $maxVal * 100) ?>%"></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>آخر حركات المخزون</h2>
    <a href="stock.php">عرض الكل</a>
  </div>
  <?php if (!$recent): ?>
    <div class="empty">لم تُسجَّل أي حركة بعد.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>التاريخ</th><th>المنتج</th><th>النوع</th><th>الكمية</th><th>بواسطة</th><th>ملاحظة</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $m): ?>
        <tr>
          <td class="num-cell"><?= e(substr($m['created_at'], 0, 16)) ?></td>
          <td><?= e($m['product_name']) ?></td>
          <td><span class="badge badge-<?= e($m['type']) ?>"><?= e(movement_label($m['type'])) ?></span></td>
          <td class="num-cell"><?= (int)$m['quantity'] ?></td>
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
