<?php
require_once __DIR__ . '/config.php';
require_admin();

$pdo = db();
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$form = ['name' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('success', 'تم حذف التصنيف. المنتجات التابعة له أصبحت بدون تصنيف.');
        redirect('categories.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $form['name'] = trim($_POST['name'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    if ($form['name'] === '') $errors[] = 'اسم التصنيف مطلوب.';

    if (!$errors) {
        $dup = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
        $dup->execute([$form['name'], $id]);
        if ($dup->fetch()) $errors[] = 'يوجد تصنيف بنفس الاسم.';
    }
    if (!$errors) {
        if ($id) {
            $pdo->prepare('UPDATE categories SET name=?, description=? WHERE id=?')
                ->execute([$form['name'], $form['description'] ?: null, $id]);
            flash('success', 'تم تحديث التصنيف.');
        } else {
            $pdo->prepare('INSERT INTO categories (name, description) VALUES (?,?)')
                ->execute([$form['name'], $form['description'] ?: null]);
            flash('success', 'تمت إضافة التصنيف.');
        }
        redirect('categories.php');
    }
    $editId = $id;
} elseif ($editId) {
    $st = $pdo->prepare('SELECT name, description FROM categories WHERE id = ?');
    $st->execute([$editId]);
    $found = $st->fetch();
    if ($found) { $form = ['name' => $found['name'], 'description' => (string)$found['description']]; }
    else { $editId = 0; }
}

$rows = $pdo->query(
    'SELECT c.*, COUNT(p.id) AS cnt FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();

$page = 'categories';
$pageTitle = 'التصنيفات';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>التصنيفات</h1><p>تنظيم المنتجات في مجموعات.</p></div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head"><h2>قائمة التصنيفات</h2></div>
    <?php if (!$rows): ?>
      <div class="empty">لا توجد تصنيفات. أضف أول تصنيف من النموذج المجاور.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الاسم</th><th>الوصف</th><th>المنتجات</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['name']) ?></td>
            <td><?= e($r['description'] ?? '') ?></td>
            <td class="num-cell"><?= (int)$r['cnt'] ?></td>
            <td>
              <div class="row-actions">
                <a href="?edit=<?= (int)$r['id'] ?>">تعديل</a>
                <form method="post" data-confirm="حذف التصنيف «<?= e($r['name']) ?>»؟ ستبقى منتجاته بدون تصنيف.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn-link danger" type="submit">حذف</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2><?= $editId ? 'تعديل التصنيف' : 'إضافة تصنيف' ?></h2></div>
    <form class="panel-body" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $editId ?>">
      <div class="field" style="margin-bottom:16px">
        <label for="name">اسم التصنيف</label>
        <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" required>
      </div>
      <div class="field">
        <label for="description">الوصف</label>
        <input type="text" id="description" name="description" value="<?= e($form['description']) ?>">
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editId ? 'حفظ' : 'إضافة' ?></button>
        <?php if ($editId): ?><a class="btn" href="categories.php">إلغاء</a><?php endif; ?>
      </div>
    </form>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
