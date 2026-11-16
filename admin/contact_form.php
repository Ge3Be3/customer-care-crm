<?php
require __DIR__ . '/../includes/functions.php';
require_login();

$id = (int)input($_GET, 'id');
$contact = [
    'id_khach_hang'      => (int)input($_GET, 'customer_id') ?: '',
    'loai_lien_he'       => '',
    'ngay_lien_he'       => date('Y-m-d\TH:i'),
    'noi_dung'           => '',
    'ngay_hen_tiep_theo' => '',
];

if ($id) {
    $stmt = $pdo->prepare('SELECT h.id_khach_hang, h.loai_lien_he, h.ngay_lien_he, h.noi_dung, h.ngay_hen_tiep_theo, c.id_nhom
        FROM contact_history h JOIN customers c ON c.id = h.id_khach_hang WHERE h.id = ?');
    $stmt->execute([$id]);
    $contact = $stmt->fetch();
    if (!$contact) {
        flash('danger', 'Không tìm thấy bản ghi liên hệ.');
        redirect('contacts.php');
    }
    $groupId = $contact['id_nhom'];
    unset($contact['id_nhom']); // $contact chỉ giữ các trường của form
    if (!can_manage($groupId)) {
        deny('contacts.php');
    }
    $contact['ngay_lien_he'] = date('Y-m-d\TH:i', strtotime($contact['ngay_lien_he'])); // định dạng cho input datetime-local
}

// Khách hàng đang chọn; null nếu không tồn tại hoặc (với nhân viên) không thuộc nhóm mình phụ trách.
$findCustomer = function ($customerId) use ($pdo): ?array {
    $stmt = $pdo->prepare("SELECT id, id_nhom, CONCAT(ho_ten, ' – ', so_dien_thoai) AS label FROM customers WHERE id = ?");
    $stmt->execute([(int)$customerId]);
    $customer = $stmt->fetch();
    return $customer && can_manage($customer['id_nhom']) ? $customer : null;
};
$customer = $findCustomer($contact['id_khach_hang']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($contact as $field => $_) {
        $contact[$field] = input($_POST, $field);
    }

    $customer = $findCustomer($contact['id_khach_hang']);
    if (!$customer) {
        $errors['id_khach_hang'] = 'Vui lòng chọn khách hàng từ danh sách gợi ý' . (is_admin() ? '.' : ' (thuộc nhóm bạn phụ trách).');
    }
    if (!in_array($contact['loai_lien_he'], LOAI_LH, true)) {
        $errors['loai_lien_he'] = 'Vui lòng chọn hình thức liên hệ.';
    }
    if (!valid_date($contact['ngay_lien_he'], 'Y-m-d\TH:i')) {
        $errors['ngay_lien_he'] = 'Thời gian liên hệ không hợp lệ.';
    } elseif ($contact['ngay_lien_he'] > date('Y-m-d\TH:i')) {
        $errors['ngay_lien_he'] = 'Thời gian liên hệ không được ở tương lai.';
    }
    if ($contact['noi_dung'] === '' || mb_strlen($contact['noi_dung']) > 2000) {
        $errors['noi_dung'] = 'Vui lòng nhập nội dung (tối đa 2000 ký tự).';
    }
    if ($contact['ngay_hen_tiep_theo'] !== '') {
        if (!valid_date($contact['ngay_hen_tiep_theo'])) {
            $errors['ngay_hen_tiep_theo'] = 'Ngày hẹn không hợp lệ.';
        } elseif ($contact['ngay_hen_tiep_theo'] < substr($contact['ngay_lien_he'], 0, 10)) {
            $errors['ngay_hen_tiep_theo'] = 'Ngày hẹn phải từ ngày liên hệ trở về sau.';
        }
    }

    if (!$errors) {
        $values = [
            (int)$contact['id_khach_hang'],
            $contact['loai_lien_he'],
            $contact['ngay_lien_he'], // MySQL nhận định dạng ISO "2026-09-19T08:30"
            $contact['noi_dung'],
            $contact['ngay_hen_tiep_theo'] ?: null,
        ];
        if ($id) {
            $pdo->prepare('UPDATE contact_history SET id_khach_hang = ?, loai_lien_he = ?, ngay_lien_he = ?, noi_dung = ?,
                ngay_hen_tiep_theo = ? WHERE id = ?')->execute([...$values, $id]);
            flash('success', 'Đã cập nhật bản ghi liên hệ.');
        } else {
            $pdo->prepare('INSERT INTO contact_history (id_khach_hang, loai_lien_he, ngay_lien_he, noi_dung, ngay_hen_tiep_theo, id_nguoi_ghi)
                VALUES (?, ?, ?, ?, ?, ?)')->execute([...$values, current_user()['id']]);
            flash('success', 'Đã ghi nhận liên hệ' . ($values[4] ? ' và đặt lịch hẹn ngày ' . fmt_date($values[4]) : '') . '.');
        }
        redirect('customer_view.php?id=' . $values[0]);
    }
    flash('danger', 'Lưu thất bại. Vui lòng kiểm tra các trường được đánh dấu.');
}

$title = $id ? 'Sửa bản ghi liên hệ' : 'Ghi nhận liên hệ';
$back = $customer ? 'customer_view.php?id=' . $customer['id'] : 'contacts.php';
$nav = 'contacts';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
    <h1><?= $title ?></h1>
    <a href="<?= $back ?>" class="btn btn-outline-secondary">← Quay lại</a>
</div>

<div class="card" style="max-width: 760px">
    <div class="card-body p-4">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label for="customer_search" class="form-label">Khách hàng <span class="text-danger">*</span></label>
                    <input type="search" class="form-control<?= invalid($errors, 'id_khach_hang') ?>" id="customer_search" list="customer_options"
                           value="<?= e($customer['label'] ?? '') ?>" placeholder="Gõ ít nhất 2 ký tự của tên hoặc số điện thoại..." autocomplete="off" required>
                    <datalist id="customer_options"></datalist>
                    <input type="hidden" id="id_khach_hang" name="id_khach_hang" value="<?= $customer['id'] ?? '' ?>">
                    <?= feedback($errors, 'id_khach_hang') ?>
                </div>
                <div class="col-md-6">
                    <label for="loai_lien_he" class="form-label">Hình thức liên hệ <span class="text-danger">*</span></label>
                    <select class="form-select<?= invalid($errors, 'loai_lien_he') ?>" id="loai_lien_he" name="loai_lien_he" required>
                        <option value="">— Chọn —</option>
                        <?php foreach (LOAI_LH as $option): ?>
                            <option<?= $option === $contact['loai_lien_he'] ? ' selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= feedback($errors, 'loai_lien_he') ?>
                </div>
                <div class="col-md-6">
                    <label for="ngay_lien_he" class="form-label">Thời gian liên hệ <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control<?= invalid($errors, 'ngay_lien_he') ?>" id="ngay_lien_he" name="ngay_lien_he" value="<?= e($contact['ngay_lien_he']) ?>" max="<?= date('Y-m-d\TH:i') ?>" required>
                    <?= feedback($errors, 'ngay_lien_he') ?>
                </div>
                <div class="col-12">
                    <label for="noi_dung" class="form-label">Nội dung <span class="text-danger">*</span></label>
                    <textarea class="form-control<?= invalid($errors, 'noi_dung') ?>" id="noi_dung" name="noi_dung" rows="4" maxlength="2000" required placeholder="Trao đổi gì với khách, kết quả, nhu cầu..."><?= e($contact['noi_dung']) ?></textarea>
                    <?= feedback($errors, 'noi_dung') ?>
                </div>
                <div class="col-md-6">
                    <label for="ngay_hen_tiep_theo" class="form-label">Hẹn liên hệ tiếp theo</label>
                    <input type="date" class="form-control<?= invalid($errors, 'ngay_hen_tiep_theo') ?>" id="ngay_hen_tiep_theo" name="ngay_hen_tiep_theo" value="<?= e($contact['ngay_hen_tiep_theo']) ?>">
                    <?= feedback($errors, 'ngay_hen_tiep_theo') ?>
                    <div class="form-text">Để trống nếu không cần chăm sóc tiếp.</div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $id ? 'Lưu thay đổi' : 'Ghi nhận' ?></button>
                <a href="<?= $back ?>" class="btn btn-outline-secondary">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
