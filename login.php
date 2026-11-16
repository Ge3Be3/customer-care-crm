<?php
require __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('admin/dashboard.php');
}

$username = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = input($_POST, 'username');
    $password = (string)($_POST['password'] ?? '');

    // Chống dò mật khẩu: trong 15 phút, sai 5 lần cùng tên đăng nhập + IP, hoặc 20 lần từ một IP, thì tạm chặn.
    // Khóa theo cặp tên + IP để kẻ tấn công ở máy khác không làm khóa được tài khoản thật.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $logName = mb_substr($username, 0, 50);
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(username = ?), 0) AS user_fails, COUNT(*) AS ip_fails
        FROM login_attempts WHERE ip = ? AND thoi_gian > NOW() - INTERVAL 15 MINUTE');
    $stmt->execute([$logName, $ip]);
    $fails = $stmt->fetch();

    if ($fails['user_fails'] >= 5 || $fails['ip_fails'] >= 20) {
        $error = 'Bạn đã đăng nhập sai quá nhiều lần. Vui lòng đợi tối đa 15 phút rồi thử lại.';
    } elseif ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $pdo->prepare('SELECT id, ho_ten, mat_khau, hoat_dong FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['mat_khau'])) {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            $pdo->prepare('INSERT INTO login_attempts (username, ip) VALUES (?, ?)')->execute([$logName, $ip]);
            $pdo->exec('DELETE FROM login_attempts WHERE thoi_gian < NOW() - INTERVAL 1 DAY'); // dọn bản ghi cũ
        } elseif (!$user['hoat_dong']) {
            $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
        } else {
            $pdo->prepare('DELETE FROM login_attempts WHERE username = ? AND ip = ?')->execute([$logName, $ip]);
            session_regenerate_id(true); // chống session fixation
            $_SESSION['user_id'] = $user['id'];
            flash('success', 'Đăng nhập thành công. Xin chào ' . $user['ho_ten'] . '!');
            redirect('admin/dashboard.php');
        }
    }
}

$title = 'Đăng nhập';
require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap">
    <div class="card login-card">
        <div class="card-body p-4 p-md-5">
            <img src="assets/img/logo-full.png" alt="Gia Bao Group" class="login-logo" width="200" height="149">
            <h1 class="h4 text-center mb-1">Hệ thống quản lý khách hàng</h1>
            <p class="text-center text-secondary small mb-4">Đăng nhập khu vực quản trị</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Tên đăng nhập</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" maxlength="50" required autofocus autocomplete="username">
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
