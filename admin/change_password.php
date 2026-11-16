<?php
require __DIR__ . '/../includes/functions.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    $stmt = $pdo->prepare('SELECT mat_khau FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $hash = (string)$stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors['current'] = 'Mật khẩu hiện tại không đúng.';
    }
    if (password_error($new)) {
        $errors['new'] = password_error($new);
    } elseif (password_verify($new, $hash)) {
        $errors['new'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }
    if ($new !== $confirm) {
        $errors['confirm'] = 'Xác nhận mật khẩu không khớp.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET mat_khau = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        session_regenerate_id(true);
        flash('success', 'Đổi mật khẩu thành công.');
        redirect('dashboard.php');
    }
    flash('danger', 'Đổi mật khẩu thất bại. Vui lòng kiểm tra lại các trường bên dưới.');
}

$fields = [
    'current' => ['Mật khẩu hiện tại', 'current-password'],
    'new'     => ['Mật khẩu mới', 'new-password'],
    'confirm' => ['Nhập lại mật khẩu mới', 'new-password'],
];
$title = 'Đổi mật khẩu';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title"><h1>Đổi mật khẩu</h1></div>
<div class="card" style="max-width: 480px">
    <div class="card-body p-4">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php foreach ($fields as $name => [$label, $autocomplete]): ?>
                <div class="mb-3">
                    <label for="<?= $name ?>" class="form-label"><?= $label ?></label>
                    <input type="password" class="form-control<?= invalid($errors, $name) ?>" id="<?= $name ?>" name="<?= $name ?>" required autocomplete="<?= $autocomplete ?>">
                    <?= feedback($errors, $name) ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Lưu mật khẩu</button>
            <a href="dashboard.php" class="btn btn-outline-secondary">Hủy</a>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
