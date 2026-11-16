<?php
require __DIR__ . '/../includes/functions.php';
require_login();

$id = (int)input($_GET, 'id');
$customer = ['ho_ten' => '', 'so_dien_thoai' => '', 'email' => '', 'ngay_sinh' => '', 'gioi_tinh' => '',
    'dia_chi' => '', 'id_nhom' => '', 'nguon_khach_hang' => '', 'ghi_chu' => ''];

if ($id) {
    $stmt = $pdo->prepare('SELECT ho_ten, so_dien_thoai, email, ngay_sinh, gioi_tinh, dia_chi, id_nhom, nguon_khach_hang, ghi_chu
        FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        flash('danger', 'Không tìm thấy khách hàng.');
        redirect('customers.php');
    }
    if (!can_manage($customer['id_nhom'])) {
        deny("customer_view.php?id=$id");
    }
}
$lockedGroup = is_admin() ? null : current_user()['id_nhom']; // nhân viên: nhóm cố định theo nhóm mình phụ trách

$groups = $pdo->query('SELECT id, ten_nhom FROM customer_groups ORDER BY ten_nhom')->fetchAll(PDO::FETCH_KEY_PAIR);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($customer as $field => $_) {
        $customer[$field] = input($_POST, $field);
    }
    $customer['id_nhom'] = $lockedGroup ?? $customer['id_nhom'];

    if ($customer['ho_ten'] === '' || mb_strlen($customer['ho_ten']) > 100) {
        $errors['ho_ten'] = 'Vui lòng nhập họ tên (tối đa 100 ký tự).';
    }
    if (!preg_match('/^0\d{9}$/', $customer['so_dien_thoai'])) {
        $errors['so_dien_thoai'] = 'Số điện thoại gồm đúng 10 chữ số và bắt đầu bằng 0.';
    }
    if ($customer['email'] !== '' && (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL) || strlen($customer['email']) > 100)) {
        $errors['email'] = 'Email không hợp lệ.';
    }
    if ($customer['ngay_sinh'] !== '' && (!valid_date($customer['ngay_sinh']) || $customer['ngay_sinh'] > date('Y-m-d') || $customer['ngay_sinh'] < '1900-01-01')) {
        $errors['ngay_sinh'] = 'Ngày sinh không hợp lệ.';
    }
    if ($customer['gioi_tinh'] !== '' && !in_array($customer['gioi_tinh'], GIOI_TINH, true)) {
        $errors['gioi_tinh'] = 'Giới tính không hợp lệ.';
    }
    if (mb_strlen($customer['dia_chi']) > 255) {
        $errors['dia_chi'] = 'Địa chỉ tối đa 255 ký tự.';
    }
    if (!isset($groups[(int)$customer['id_nhom']])) {
        $errors['id_nhom'] = 'Vui lòng chọn nhóm khách hàng.';
    }
    if ($customer['nguon_khach_hang'] !== '' && !in_array($customer['nguon_khach_hang'], NGUON_KH, true)) {
        $errors['nguon_khach_hang'] = 'Nguồn khách hàng không hợp lệ.';
    }
    if (mb_strlen($customer['ghi_chu']) > 2000) {
        $errors['ghi_chu'] = 'Ghi chú tối đa 2000 ký tự.';
    }

    if (!$errors) {
        // Trường không bắt buộc để trống -> NULL (email UNIQUE cho phép nhiều NULL)
        $values = array_values(array_map(fn($v) => $v === '' ? null : $v, $customer));
        try {
            if ($id) {
                $pdo->prepare('UPDATE customers SET ho_ten = ?, so_dien_thoai = ?, email = ?, ngay_sinh = ?, gioi_tinh = ?,
                    dia_chi = ?, id_nhom = ?, nguon_khach_hang = ?, ghi_chu = ? WHERE id = ?')->execute([...$values, $id]);
                flash('success', 'Đã cập nhật thông tin khách hàng.');
            } else {
                $pdo->prepare('INSERT INTO customers (ho_ten, so_dien_thoai, email, ngay_sinh, gioi_tinh, dia_chi, id_nhom,
                    nguon_khach_hang, ghi_chu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($values);
                $id = (int)$pdo->lastInsertId();
                flash('success', 'Đã thêm khách hàng mới.');
            }
            redirect("customer_view.php?id=$id");
        } catch (PDOException $e) {
            if (!is_duplicate($e)) {
                throw $e;
            }
            if (str_contains($e->getMessage(), 'uq_email')) {
                $errors['email'] = 'Email này đã thuộc về khách hàng khác.';
            } else {
                $errors['so_dien_thoai'] = 'Số điện thoại này đã thuộc về khách hàng khác.';
            }
        }
    }
    flash('danger', 'Lưu thất bại. Vui lòng kiểm tra các trường được đánh dấu.');
}

$title = $id ? 'Sửa khách hàng' : 'Thêm khách hàng';
$back = $id ? "customer_view.php?id=$id" : 'customers.php';
$nav = 'customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
    <h1><?= $title ?></h1>
    <a href="<?= $back ?>" class="btn btn-outline-secondary">← Quay lại</a>
</div>

<div class="card">
    <div class="card-body p-4">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="ho_ten" class="form-label">Họ tên <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= invalid($errors, 'ho_ten') ?>" id="ho_ten" name="ho_ten" value="<?= e($customer['ho_ten']) ?>" maxlength="100" required>
                    <?= feedback($errors, 'ho_ten') ?>
                </div>
                <div class="col-md-3">
                    <label for="so_dien_thoai" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control<?= invalid($errors, 'so_dien_thoai') ?>" id="so_dien_thoai" name="so_dien_thoai" value="<?= e($customer['so_dien_thoai']) ?>" maxlength="10" pattern="0[0-9]{9}" placeholder="0901234567" required>
                    <?= feedback($errors, 'so_dien_thoai') ?>
                </div>
                <div class="col-md-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control<?= invalid($errors, 'email') ?>" id="email" name="email" value="<?= e($customer['email']) ?>" maxlength="100">
                    <?= feedback($errors, 'email') ?>
                </div>

                <div class="col-md-3">
                    <label for="ngay_sinh" class="form-label">Ngày sinh</label>
                    <input type="date" class="form-control<?= invalid($errors, 'ngay_sinh') ?>" id="ngay_sinh" name="ngay_sinh" value="<?= e($customer['ngay_sinh']) ?>" min="1900-01-01" max="<?= date('Y-m-d') ?>">
                    <?= feedback($errors, 'ngay_sinh') ?>
                </div>
                <div class="col-md-3">
                    <label for="gioi_tinh" class="form-label">Giới tính</label>
                    <select class="form-select<?= invalid($errors, 'gioi_tinh') ?>" id="gioi_tinh" name="gioi_tinh">
                        <option value="">— Chọn —</option>
                        <?php foreach (GIOI_TINH as $option): ?>
                            <option<?= $option === $customer['gioi_tinh'] ? ' selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= feedback($errors, 'gioi_tinh') ?>
                </div>
                <div class="col-md-3">
                    <label for="id_nhom" class="form-label">Nhóm khách hàng <span class="text-danger">*</span></label>
                    <select class="form-select<?= invalid($errors, 'id_nhom') ?>" id="id_nhom" name="id_nhom" required<?= $lockedGroup ? ' disabled' : '' ?>>
                        <option value="">— Chọn nhóm —</option>
                        <?php foreach ($groups as $gid => $gname): ?>
                            <option value="<?= $gid ?>"<?= (string)$gid === (string)($lockedGroup ?? $customer['id_nhom']) ? ' selected' : '' ?>><?= e($gname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= feedback($errors, 'id_nhom') ?>
                </div>
                <div class="col-md-3">
                    <label for="nguon_khach_hang" class="form-label">Nguồn khách hàng</label>
                    <select class="form-select<?= invalid($errors, 'nguon_khach_hang') ?>" id="nguon_khach_hang" name="nguon_khach_hang">
                        <option value="">— Chọn —</option>
                        <?php foreach (NGUON_KH as $option): ?>
                            <option<?= $option === $customer['nguon_khach_hang'] ? ' selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= feedback($errors, 'nguon_khach_hang') ?>
                </div>

                <div class="col-12">
                    <label for="dia_chi" class="form-label">Địa chỉ</label>
                    <input type="text" class="form-control<?= invalid($errors, 'dia_chi') ?>" id="dia_chi" name="dia_chi" value="<?= e($customer['dia_chi']) ?>" maxlength="255">
                    <?= feedback($errors, 'dia_chi') ?>
                </div>
                <div class="col-12">
                    <label for="ghi_chu" class="form-label">Ghi chú đặc điểm</label>
                    <textarea class="form-control<?= invalid($errors, 'ghi_chu') ?>" id="ghi_chu" name="ghi_chu" rows="3" maxlength="2000" placeholder="Sở thích, thói quen mua hàng, lưu ý khi chăm sóc..."><?= e($customer['ghi_chu']) ?></textarea>
                    <?= feedback($errors, 'ghi_chu') ?>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $id ? 'Lưu thay đổi' : 'Thêm khách hàng' ?></button>
                <a href="<?= $back ?>" class="btn btn-outline-secondary">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
