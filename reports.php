<?php
require_once __DIR__ . '/config.php';
require_login();

$pdo = db();
$type = $_GET['type'] ?? 'summary';
if (!in_array($type, ['summary', 'low', 'out', 'movements'], true)) $type = 'summary';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($dateRe, $from)) $from = date('Y-m-01');
if (!preg_match($dateRe, $to))   $to = date('Y-m-d');

$cols = [];
$rows = [];
$foot = null;

switch ($type) {
    case 'low':
        $title = 'تقرير الكميات المنخفضة';
        $cols = ['الرمز', 'المنتج', 'التصنيف', 'المتوفر', 'الحد الأدنى', 'المورّد'];
        $q = $pdo->query(
            "SELECT p.*, COALESCE(c.name,'—') AS cat FROM products p LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.quantity > 0 AND p.quantity <= p.min_quantity ORDER BY p.quantity, p.name"
        );
        foreach ($q as $r) {
            $rows[] = [$r['sku'], $r['name'], $r['cat'], $r['quantity'], $r['min_quantity'], $r['supplier'] ?? '—'];
        }
        break;

    case 'out':
        $title = 'تقرير الأصناف النافدة';
        $cols = ['الرمز', 'المنتج', 'التصنيف', 'الحد الأدنى', 'المورّد'];
        $q = $pdo->query(
            "SELECT p.*, COALESCE(c.name,'—') AS cat FROM products p LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.quantity = 0 ORDER BY p.name"
        );
        foreach ($q as $r) {
            $rows[] = [$r['sku'], $r['name'], $r['cat'], $r['min_quantity'], $r['supplier'] ?? '—'];
        }
        break;

    case 'movements':
        $title = 'تقرير حركة المخزون';
        $cols = ['التاريخ', 'المنتج', 'النوع', 'الكمية', 'بواسطة', 'ملاحظة'];
        $q = $pdo->prepare(
            "SELECT m.*, p.name AS pname, u.full_name FROM stock_movements m
             JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id
             WHERE DATE(m.created_at) BETWEEN ? AND ? ORDER BY m.id DESC"
        );
        $q->execute([$from, $to]);
        foreach ($q as $r) {
            $rows[] = [substr($r['created_at'], 0, 16), $r['pname'], movement_label($r['type']), $r['quantity'], $r['full_name'] ?? '—', $r['note'] ?? ''];
        }
        break;

    default:
        $title = 'تقرير حالة المخزون';
        $cols = ['الرمز', 'المنتج', 'التصنيف', 'الكمية', 'سعر التكلفة', 'قيمة المخزون', 'الحالة'];
        $q = $pdo->query(
            "SELECT p.*, COALESCE(c.name,'—') AS cat FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY c.name, p.name"
        );
        $sumQty = 0; $sumVal = 0.0;
        foreach ($q as $r) {
            $val = $r['quantity'] * $r['cost_price'];
            $sumQty += $r['quantity']; $sumVal += $val;
            $rows[] = [$r['sku'], $r['name'], $r['cat'], $r['quantity'], number_format((float)$r['cost_price'], 2), number_format($val, 2), stock_status((int)$r['quantity'], (int)$r['min_quantity'])[1]];
        }
        $foot = ['', 'الإجمالي', '', $sumQty, '', number_format($sumVal, 2) . ' ' . CURRENCY, ''];
}

/* ---------- تصدير CSV ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-' . $type . '-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $cols);
    foreach ($rows as $r) fputcsv($out, $r);
    if ($foot) fputcsv($out, $foot);
    fclose($out);
    exit;
}

$tabs = ['summary' => 'حالة المخزون', 'low' => 'الكميات المنخفضة', 'out' => 'الأصناف النافدة', 'movements' => 'حركة المخزون'];
$page = 'reports';
$pageTitle = 'التقارير';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>التقارير</h1>
    <p>اطبع التقرير أو صدّره بصيغة CSV لفتحه في Excel.</p>
  </div>
  <div class="actions">
    <button class="btn" type="button" onclick="window.print()">طباعة</button>
    <a class="btn btn-primary" href="<?= e(url_with(['export' => 'csv'])) ?>">تصدير CSV</a>
  </div>
</div>

<nav class="tabs" aria-label="أنواع التقارير">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="?type=<?= $k ?>" class="<?= $type === $k ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="panel">
  <div class="panel-head"><h2><?= e($title) ?></h2><span class="sku"><?= date('Y-m-d H:i') ?></span></div>
  <?php if ($type === 'movements'): ?>
  <form class="filters" method="get">
    <input type="hidden" name="type" value="movements">
    <div class="field"><label for="from">من تاريخ</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to">إلى تاريخ</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-primary" type="submit">عرض</button>
  </form>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty">لا توجد بيانات لهذا التقرير.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><?php foreach ($r as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($foot): ?>
      <tfoot><tr><?php foreach ($foot as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
