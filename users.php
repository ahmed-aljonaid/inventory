<?php
require_once __DIR__ . '/config.php';
require_admin();

$pdo = db();
$me = current_user();
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$form = ['full_name' => '', 'username' => '', 'role' => 'staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)$me['id']) {
            flash('error', 'لا يمكنك حذف حسابك الحالي.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'تم حذف المستخدم.');
        }
        redirect('users.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $form['full_name'] = trim($_POST['full_name'] ?? '');
    $form['username']  = trim($_POST['username'] ?? '');
    $form['role']      = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
    $password          = $_POST['password'] ?? '';

    if ($form['full_name'] === '') $errors[] = 'الاسم الكامل مطلوب.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $form['username'])) $errors[] = 'اسم المستخدم: من 3 إلى 30 حرفاً إنجليزياً أو أرقاماً.';
    if (!$id && strlen($password) < 6) $errors[] = 'كلمة المرور يجب ألا تقل عن 6 أحرف.';
    if ($id && $password !== '' && strlen($password) < 6) $errors[] = 'كلمة المرور الجديدة يجب ألا تقل عن 6 أحرف.';
    if ($id === (int)$me['id'] && $form['role'] !== 'admin') $errors[] = 'لا يمكنك إزالة صلاحية المدير عن حسابك.';

    if (!$errors) {
        $dup = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $dup->execute([$form['username'], $id]);
        if ($dup->fetch()) $errors[] = 'اسم المستخدم مستخدم مسبقاً.';
    }
    if (!$errors) {
        if ($id) {
            $pdo->prepare('UPDATE users SET full_name=?, username=?, role=? WHERE id=?')
                ->execute([$form['full_name'], $form['username'], $form['role'], $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flash('success', 'تم تحديث المستخدم.');
        } else {
            $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?,?)')
                ->execute([$form['full_name'], $form['username'], password_hash($password, PASSWORD_DEFAULT), $form['role']]);
            flash('success', 'تمت إضافة المستخدم.');
        }
        redirect('users.php');
    }
    $editId = $id;
} elseif ($editId) {
    $st = $pdo->prepare('SELECT full_name, username, role FROM users WHERE id = ?');
    $st->execute([$editId]);
    $found = $st->fetch();
    if ($found) $form = $found; else $editId = 0;
}

$rows = $pdo->query('SELECT id, full_name, username, role, created_at FROM users ORDER BY id')->fetchAll();

$page = 'users';
$pageTitle = 'المستخدمون';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>المستخدمون</h1><p>إدارة حسابات الدخول والصلاحيات.</p></div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head"><h2>الحسابات</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>الصلاحية</th><th>تاريخ الإنشاء</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['full_name']) ?></td>
            <td><span class="sku"><?= e($r['username']) ?></span></td>
            <td><span class="badge badge-<?= e($r['role']) ?>"><?= $r['role'] === 'admin' ? 'مدير' : 'موظف' ?></span></td>
            <td class="num-cell"><?= e(substr($r['created_at'], 0, 10)) ?></td>
            <td>
              <div class="row-actions">
                <a href="?edit=<?= (int)$r['id'] ?>">تعديل</a>
                <?php if ((int)$r['id'] !== (int)$me['id']): ?>
                <form method="post" data-confirm="حذف المستخدم «<?= e($r['full_name']) ?>»؟">
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
  </section>

  <section class="panel">
    <div class="panel-head"><h2><?= $editId ? 'تعديل مستخدم' : 'إضافة مستخدم' ?></h2></div>
    <form class="panel-body" method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $editId ?>">
      <div class="field" style="margin-bottom:14px">
        <label for="full_name">الاسم الكامل</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="username">اسم المستخدم</label>
        <input type="text" id="username" name="username" value="<?= e($form['username']) ?>" required style="direction:ltr;text-align:right">
      </div>
      <div class="field" style="margin-bottom:14px">
        <label for="role">الصلاحية</label>
        <select id="role" name="role">
          <option value="staff" <?= $form['role'] === 'staff' ? 'selected' : '' ?>>موظف</option>
          <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>مدير</option>
        </select>
      </div>
      <div class="field">
        <label for="password">كلمة المرور</label>
        <input type="password" id="password" name="password" <?= $editId ? '' : 'required' ?>>
        <?php if ($editId): ?><span class="hint">اتركها فارغة للإبقاء على كلمة المرور الحالية.</span><?php endif; ?>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editId ? 'حفظ' : 'إضافة' ?></button>
        <?php if ($editId): ?><a class="btn" href="users.php">إلغاء</a><?php endif; ?>
      </div>
    </form>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
