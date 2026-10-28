<?php
/**
 * معالج التثبيت — يُشغَّل مرة واحدة ثم يُحذف.
 * ينشئ الجداول، حساب المدير، وبيانات تجريبية.
 */
require_once __DIR__ . '/config.php';

$log = [];
$failed = false;

try {
    $pdo = db();

    $sql = file_get_contents(__DIR__ . '/database.sql');
    foreach (explode(";\n", $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
    $log[] = 'تم إنشاء الجداول.';

    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?,?)');
        $ins->execute(['مدير النظام', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
        $ins->execute(['موظف المستودع', 'staff', password_hash('staff123', PASSWORD_DEFAULT), 'staff']);
        $log[] = 'تم إنشاء حساب المدير (admin / admin123) وحساب الموظف (staff / staff123).';
    }

    if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $cat = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?,?)');
        foreach ([
            ['أجهزة إلكترونية', 'حواسيب وملحقاتها وأجهزة الشبكات'],
            ['قرطاسية ومكتبية', 'أوراق وأقلام ومستلزمات المكاتب'],
            ['أثاث مكتبي', 'مكاتب وكراسي وخزائن'],
            ['مستلزمات نظافة', 'مواد التنظيف والتعقيم'],
        ] as $c) {
            $cat->execute($c);
        }

        $ids = $pdo->query('SELECT name, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
        $prod = $pdo->prepare(
            'INSERT INTO products (sku,name,category_id,supplier,quantity,min_quantity,cost_price,price,description)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $mov = $pdo->prepare(
            "INSERT INTO stock_movements (product_id,user_id,type,quantity,note) VALUES (?,1,'in',?,'رصيد افتتاحي')"
        );
        $samples = [
            ['EL-1001', 'حاسوب محمول 15 بوصة', 'أجهزة إلكترونية', 'شركة التقنية المتقدمة', 18, 5, 2400, 2999, 'معالج Core i5، ذاكرة 8 جيجابايت'],
            ['EL-1002', 'شاشة عرض 24 بوصة', 'أجهزة إلكترونية', 'شركة التقنية المتقدمة', 4, 6, 520, 699, 'دقة Full HD'],
            ['EL-1003', 'طابعة ليزر أحادية اللون', 'أجهزة إلكترونية', 'مؤسسة الرواد للتجهيزات', 0, 3, 640, 850, 'طباعة على الوجهين'],
            ['EL-1004', 'لوحة مفاتيح لاسلكية', 'أجهزة إلكترونية', 'مؤسسة الرواد للتجهيزات', 42, 10, 45, 79, 'تدعم اللغتين العربية والإنجليزية'],
            ['ST-2001', 'ورق طباعة A4 (رزمة 500 ورقة)', 'قرطاسية ومكتبية', 'مكتبة الأفق', 160, 40, 14, 22, 'وزن 80 جرام'],
            ['ST-2002', 'أقلام حبر جاف (علبة 50)', 'قرطاسية ومكتبية', 'مكتبة الأفق', 9, 15, 18, 30, 'لون أزرق'],
            ['ST-2003', 'دفتر ملاحظات مسطر', 'قرطاسية ومكتبية', 'مكتبة الأفق', 75, 20, 3, 6, '100 ورقة'],
            ['FU-3001', 'مكتب إداري خشبي', 'أثاث مكتبي', 'معرض الأصالة للأثاث', 7, 2, 780, 1150, 'مقاس 160 × 80 سم'],
            ['FU-3002', 'كرسي مكتب طبي', 'أثاث مكتبي', 'معرض الأصالة للأثاث', 2, 4, 310, 480, 'مسند ظهر قابل للتعديل'],
            ['CL-4001', 'معقم أيدي 500 مل', 'مستلزمات نظافة', 'شركة النقاء', 55, 20, 7, 12, 'كحول 70%'],
        ];
        foreach ($samples as $s) {
            $prod->execute([$s[0], $s[1], $ids[$s[2]], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8]]);
            if ($s[4] > 0) {
                $mov->execute([(int)$pdo->lastInsertId(), $s[4]]);
            }
        }
        $log[] = 'تمت إضافة تصنيفات ومنتجات تجريبية.';
    }
} catch (Throwable $ex) {
    $failed = true;
    $log[] = 'تعذّر إكمال التثبيت: ' . $ex->getMessage();
    $log[] = 'تأكد من صحة بيانات الاتصال في ملف config.php وأن قاعدة البيانات منشأة مسبقاً.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تثبيت النظام</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="plain">
<main class="install-box">
  <h1>تثبيت نظام إدارة المخزون</h1>
  <ul class="install-log">
    <?php foreach ($log as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
  </ul>
  <?php if (!$failed): ?>
    <div class="alert alert-warn">لأسباب أمنية، احذف الملف <strong>install.php</strong> من الخادم الآن.</div>
    <a class="btn btn-primary" href="login.php">الانتقال إلى تسجيل الدخول</a>
  <?php endif; ?>
</main>
</body>
</html>
