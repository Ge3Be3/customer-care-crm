<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$editId = (int)input($_GET, 'edit');
$form = ['username' => '', 'ho_ten' => '', 'id_nhom' => ''];
if ($editId) {
    $stmt = $pdo->prepare("SELECT username, ho_ten, id_nhom FROM users WHERE id = ? AND vai_tro = 'nhan_vien'");
    $stmt->execute([$editId]);
    $form = $stmt->fetch();
    if (!$form) {
        flash('danger', 'Không tìm thấy nhân viên.');
        redirect('staff.php');
    }
}

$groups = $pdo->query('SELECT id, ten_nhom FROM customer_groups ORDER BY ten_nhom')->fetchAll(PDO::FETCH_KEY_PAIR);
$errors = [];

// Khóa / mở khóa. Không xóa cứng tài khoản để giữ "người ghi nhận" trong lịch sử liên hệ.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input($_POST, 'action') === 'toggle') {
    $stmt = $pdo->prepare("UPDATE users SET hoat_dong = 1 - hoat_dong WHERE id = ? AND vai_tro = 'nhan_vien'");
    $stmt->execute([(int)input($_POST, 'id')]);
    $stmt->rowCount()
        ? flash('success', 'Đã cập nhật trạng thái tài khoản.')
        : flash('danger', 'Thao tác thất bại: không tìm thấy nhân viên.');
    redirect('staff.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $_) {
        $form[$field] = input($_POST, $field);
    }
    $password = (string)($_POST['mat_khau'] ?? '');

    if (!preg_match('/^[a-z0-9_.]{3,50}$/', $form['username'])) {
        $errors['username'] = 'Tên đăng nhập 3–50 ký tự, chỉ gồm chữ thường không dấu, số, dấu chấm hoặc gạch dưới.';
    }
    if ($form['ho_ten'] === '' || mb_strlen($form['ho_ten']) > 100) {
        $errors['ho_ten'] = 'Vui lòng nhập họ tên (tối đa 100 ký tự).';
    }
    if (!isset($groups[(int)$form['id_nhom']])) {
        $errors['id_nhom'] = 'Vui lòng chọn nhóm phụ trách.';
    }
    // Thêm mới: bắt buộc mật khẩu. Sửa: để trống = giữ nguyên, nhập = đặt lại mật khẩu.
    if (($password !== '' || !$editId) && password_error($password)) {
        $errors['mat_khau'] = password_error($password);
    }

    if (!$errors) {
        try {
            if ($editId) {
                $pdo->prepare("UPDATE users SET username = ?, ho_ten = ?, id_nhom = ? WHERE id = ? AND vai_tro = 'nhan_vien'")
                    ->execute([$form['username'], $form['ho_ten'], (int)$form['id_nhom'], $editId]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET mat_khau = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $editId]);
                }
                flash('success', 'Đã cập nhật nhân viên' . ($password !== '' ? ' và đặt lại mật khẩu.' : '.'));
            } else {
                $pdo->prepare("INSERT INTO users (username, ho_ten, mat_khau, vai_tro, id_nhom) VALUES (?, ?, ?, 'nhan_vien', ?)")
                    ->execute([$form['username'], $form['ho_ten'], password_hash($password, PASSWORD_DEFAULT), (int)$form['id_nhom']]);
                flash('success', 'Đã tạo tài khoản nhân viên.');
            }
            redirect('staff.php');
        } catch (PDOException $e) {
            if (!is_duplicate($e)) {
                throw $e;
            }
            $errors['username'] = 'Tên đăng nhập này đã tồn tại.';
        }
    }
    flash('danger', 'Lưu thất bại. Vui lòng kiểm tra các trường được đánh dấu.');
}

$staff = $pdo->query("SELECT u.id, u.username, u.ho_ten, u.hoat_dong, g.ten_nhom, g.mau_nhan,
    (SELECT COUNT(*) FROM contact_history h WHERE h.id_nguoi_ghi = u.id) AS so_lan_ghi
    FROM users u JOIN customer_groups g ON g.id = u.id_nhom
    WHERE u.vai_tro = 'nhan_vien' ORDER BY g.ten_nhom, u.ho_ten")->fetchAll();

$title = 'Nhân viên';
$nav = 'staff';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title"><h1>Nhân viên</h1></div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Họ tên</th><th>Tên đăng nhập</th><th>Nhóm phụ trách</th><th class="text-center">Lần ghi nhận</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr<?= $s['id'] === $editId ? ' class="table-active"' : '' ?>>
                            <td class="fw-semibold"><?= e($s['ho_ten']) ?></td>
                            <td><?= e($s['username']) ?></td>
                            <td><?= group_badge($s['ten_nhom'], $s['mau_nhan']) ?></td>
                            <td class="text-center"><?= $s['so_lan_ghi'] ?></td>
                            <td><?= $s['hoat_dong'] ? '<span class="badge grp-green">Hoạt động</span>' : '<span class="badge fu-da_hen">Đã khóa</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                <form method="post" class="d-inline" data-confirm="<?= $s['hoat_dong'] ? 'Khóa' : 'Mở khóa' ?> tài khoản &quot;<?= e($s['ho_ten']) ?>&quot;?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><?= $s['hoat_dong'] ? 'Khóa' : 'Mở khóa' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$staff): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-5">Chưa có nhân viên nào.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= $editId ? 'Sửa nhân viên' : 'Thêm nhân viên' ?></div>
            <div class="card-body">
                <form method="post" action="?<?= $editId ? 'edit=' . $editId : '' ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="ho_ten" class="form-label">Họ tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= invalid($errors, 'ho_ten') ?>" id="ho_ten" name="ho_ten" value="<?= e($form['ho_ten']) ?>" maxlength="100" required>
                        <?= feedback($errors, 'ho_ten') ?>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= invalid($errors, 'username') ?>" id="username" name="username" value="<?= e($form['username']) ?>" maxlength="50" pattern="[a-z0-9_.]{3,50}" autocomplete="off" required>
                        <?= feedback($errors, 'username') ?>
                    </div>
                    <div class="mb-3">
                        <label for="id_nhom" class="form-label">Nhóm phụ trách <span class="text-danger">*</span></label>
                        <select class="form-select<?= invalid($errors, 'id_nhom') ?>" id="id_nhom" name="id_nhom" required>
                            <option value="">— Chọn nhóm —</option>
                            <?php foreach ($groups as $gid => $gname): ?>
                                <option value="<?= $gid ?>"<?= (string)$gid === (string)$form['id_nhom'] ? ' selected' : '' ?>><?= e($gname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= feedback($errors, 'id_nhom') ?>
                    </div>
                    <div class="mb-4">
                        <label for="mat_khau" class="form-label"><?= $editId ? 'Đặt lại mật khẩu' : 'Mật khẩu <span class="text-danger">*</span>' ?></label>
                        <input type="password" class="form-control<?= invalid($errors, 'mat_khau') ?>" id="mat_khau" name="mat_khau" autocomplete="new-password"<?= $editId ? '' : ' required' ?>>
                        <?= feedback($errors, 'mat_khau') ?>
                        <?php if ($editId): ?><div class="form-text">Để trống nếu giữ nguyên mật khẩu hiện tại.</div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editId ? 'Lưu thay đổi' : 'Thêm nhân viên' ?></button>
                    <?php if ($editId): ?><a href="staff.php" class="btn btn-outline-secondary">Hủy</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
