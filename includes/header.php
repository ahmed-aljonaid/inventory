<?php
require_once __DIR__ . '/../config.php';
require_login();
$me = current_user();
$page = $page ?? '';
$pageTitle = $pageTitle ?? 'نظام إدارة المخزون';

$items = [
    ['index',      'index.php',      'لوحة التحكم'],
    ['products',   'products.php',   'المنتجات'],
    ['stock',      'stock.php',      'حركة المخزون'],
    ['reports',    'reports.php',    'التقارير'],
];
$adminItems = [
    ['categories', 'categories.php', 'التصنيفات'],
    ['users',      'users.php',      'المستخدمون'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | نظام إدارة المخزون</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <strong>نظام إدارة المخزون</strong>
      <span>Inventory Management System</span>
    </div>
    <nav class="nav">
      <?php foreach ($items as [$key, $href, $label]): ?>
        <a href="<?= $href ?>" class="<?= $page === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if (is_admin()): ?>
        <div class="group">الإدارة</div>
        <?php foreach ($adminItems as [$key, $href, $label]): ?>
          <a href="<?= $href ?>" class="<?= $page === $key ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <div class="userbox">
      <?= e($me['full_name']) ?>
      <small><?= $me['role'] === 'admin' ? 'مدير النظام' : 'موظف' ?></small>
      <a href="logout.php">تسجيل الخروج</a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <strong>نظام إدارة المخزون</strong>
      <button type="button" id="menuBtn" aria-controls="sidebar" aria-expanded="false">القائمة</button>
    </div>
    <main class="content">
      <?php foreach (take_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
