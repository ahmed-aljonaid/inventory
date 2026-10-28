<?php
/**
 * ملف الإعدادات والدوال المساعدة
 * عدّل بيانات الاتصال بقاعدة البيانات حسب الاستضافة التي تستخدمها.
 */
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('CURRENCY', 'ر.س');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function money($n): string
{
    return number_format((float)$n, 2) . ' ' . CURRENCY;
}

/* ---------- الجلسة والصلاحيات ---------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'هذه الصفحة متاحة لمدير النظام فقط.');
        redirect('index.php');
    }
}

/* ---------- رسائل التنبيه ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------- الحماية من CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مرة أخرى.');
    }
}

/* ---------- حالة المخزون ---------- */
function stock_status(int $qty, int $min): array
{
    if ($qty <= 0) {
        return ['out', 'نفد المخزون'];
    }
    if ($qty <= $min) {
        return ['low', 'كمية منخفضة'];
    }
    return ['ok', 'متوفر'];
}

function status_badge(int $qty, int $min): string
{
    [$cls, $label] = stock_status($qty, $min);
    return '<span class="badge badge-' . $cls . '">' . e($label) . '</span>';
}

function movement_label(string $type): string
{
    return ['in' => 'إدخال', 'out' => 'إخراج', 'adjust' => 'تعديل'][$type] ?? $type;
}

/** يبني رابطاً مع الحفاظ على معاملات البحث الحالية */
function url_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    return '?' . http_build_query($q);
}
