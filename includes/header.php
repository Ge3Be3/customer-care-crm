<?php
// Biến trang truyền vào: $title, $nav (mục menu đang chọn). Trang trong /admin dùng đường dẫn '../'.
$root = basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin' ? '../' : '';
$nav ??= '';
$menu = [
    'dashboard' => ['dashboard.php', 'Tổng quan'],
    'customers' => ['customers.php', 'Khách hàng'],
    'contacts'  => ['contacts.php', 'Lịch sử liên hệ'],
];
if (is_admin()) {
    $menu['groups'] = ['groups.php', 'Nhóm khách hàng'];
    $menu['staff'] = ['staff.php', 'Nhân viên'];
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'CRM') ?> · Gia Bao Group</title>
    <link rel="icon" type="image/png" href="<?= $root ?>assets/img/favicon.png">
    <link rel="stylesheet" href="<?= $root ?>assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $root ?>assets/css/app.css">
</head>
<body>
<?php if (current_user()): ?>
<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="<?= $root ?>assets/img/logo-mark.png" alt="" width="40" height="40">
            <span class="brand-text">Gia Bao Group</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto ms-lg-4">
                <?php foreach ($menu as $key => [$href, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $nav === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="dropdown">
                <button class="btn btn-link nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= e(current_user()['ho_ten']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-secondary"><?= is_admin() ? 'Quản trị viên' : 'Nhân viên · ' . e(current_user()['ten_nhom']) ?></span></li>
                    <li><a class="dropdown-item" href="change_password.php">Đổi mật khẩu</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="../logout.php">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item">Đăng xuất</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container py-4">
    <?php foreach ($_SESSION['flash'] ?? [] as [$type, $message]): ?>
        <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endforeach; unset($_SESSION['flash']); ?>
