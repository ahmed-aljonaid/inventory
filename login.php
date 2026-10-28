<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            redirect('index.php');
        }
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    } catch (PDOException $ex) {
        $error = 'تعذّر الاتصال بقاعدة البيانات. شغّل ملف install.php أولاً وتأكد من إعدادات config.php.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول | نظام إدارة المخزون</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login">
  <section class="login-side">
    <h1>إدارة دقيقة لكل صنف في مخزونك</h1>
    <p>سجّل المنتجات، تابع الكميات المتاحة، واحصل على تقارير واضحة عن حالة المخزون في أي وقت.</p>
    <div class="en">Inventory Management System</div>
  </section>
  <section class="login-form">
    <form method="post" autocomplete="off">
      <h2>تسجيل الدخول</h2>
      <p class="sub">أدخل بيانات حسابك للمتابعة.</p>
      <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">اسم المستخدم</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">كلمة المرور</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button class="btn btn-primary" type="submit">دخول</button>
      <div class="demo">
        حسابات تجريبية —
        مدير: <code>admin / admin123</code> ·
        موظف: <code>staff / staff123</code>
      </div>
    </form>
  </section>
</div>
</body>
</html>
