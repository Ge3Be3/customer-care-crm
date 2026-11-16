<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$editId = (int)input($_GET, 'edit');
$form = ['ten_nhom' => '', 'mo_ta' => '', 'mau_nhan' => 'slate'];
if ($editId) {
    $stmt = $pdo->prepare('SELECT ten_nhom, mo_ta, mau_nhan FROM customer_groups WHERE id = ?');
    $stmt->execute([$editId]);
    $form = $stmt->fetch();
    if (!$form) {
        flash('danger', 'Không tìm thấy nhóm khách hàng.');
        redirect('groups.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input($_POST, 'action') === 'delete') {
    try {
        $stmt = $pdo->prepare('DELETE FROM customer_groups WHERE id = ?');
        $stmt->execute([(int)input($_POST, 'id')]);
        $stmt->rowCount()
            ? flash('success', 'Đã xóa nhóm khách hàng.')
            : flash('danger', 'Xóa thất bại: không tìm thấy nhóm.');
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) !== 1451) { // 1451 = bị khóa ngoại chặn (còn khách hàng hoặc nhân viên thuộc nhóm)
            throw $e;
        }
        flash('danger', 'Không thể xóa nhóm đang có khách hàng hoặc nhân viên phụ trách. Hãy chuyển họ sang nhóm khác trước.');
    }
    redirect('groups.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $_) {
        $form[$field] = input($_POST, $field);
    }
    if ($form['ten_nhom'] === '' || mb_strlen($form['ten_nhom']) > 100) {
        $errors['ten_nhom'] = 'Vui lòng nhập tên nhóm (tối đa 100 ký tự).';
    }
    if (mb_strlen($form['mo_ta']) > 255) {
        $errors['mo_ta'] = 'Mô tả tối đa 255 ký tự.';
    }
    if (!isset(MAU_NHAN[$form['mau_nhan']])) {
        $errors['mau_nhan'] = 'Màu nhãn không hợp lệ.';
    }

    if (!$errors) {
        $values = [$form['ten_nhom'], $form['mo_ta'] === '' ? null : $form['mo_ta'], $form['mau_nhan']];
        try {
            if ($editId) {
                $pdo->prepare('UPDATE customer_groups SET ten_nhom = ?, mo_ta = ?, mau_nhan = ? WHERE id = ?')->execute([...$values, $editId]);
                flash('success', 'Đã cập nhật nhóm khách hàng.');
            } else {
                $pdo->prepare('INSERT INTO customer_groups (ten_nhom, mo_ta, mau_nhan) VALUES (?, ?, ?)')->execute($values);
                flash('success', 'Đã thêm nhóm khách hàng mới.');
            }
            redirect('groups.php');
        } catch (PDOException $e) {
            if (!is_duplicate($e)) {
                throw $e;
            }
            $errors['ten_nhom'] = 'Tên nhóm này đã tồn tại.';
        }
    }
    flash('danger', 'Lưu thất bại. Vui lòng kiểm tra các trường được đánh dấu.');
}

$groups = $pdo->query("SELECT g.id, g.ten_nhom, g.mo_ta, g.mau_nhan,
    (SELECT COUNT(*) FROM customers c WHERE c.id_nhom = g.id) AS so_khach,
    (SELECT GROUP_CONCAT(CONCAT(u.ho_ten, IF(u.hoat_dong, '', ' (đã khóa)')) SEPARATOR ', ') FROM users u WHERE u.id_nhom = g.id) AS nhan_vien
    FROM customer_groups g ORDER BY g.ten_nhom")->fetchAll();

$title = 'Nhóm khách hàng';
$nav = 'groups';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title"><h1>Nhóm khách hàng</h1></div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Nhóm</th><th>Mô tả</th><th>Nhân viên phụ trách</th><th class="text-center">Số KH</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($groups as $g): ?>
                        <tr<?= $g['id'] === $editId ? ' class="table-active"' : '' ?>>
                            <td><?= group_badge($g['ten_nhom'], $g['mau_nhan']) ?></td>
                            <td class="text-secondary"><?= e($g['mo_ta']) ?></td>
                            <td class="small"><?= $g['nhan_vien'] ? e($g['nhan_vien']) : '<span class="text-secondary">Chưa phân công</span>' ?></td>
                            <td class="text-center"><a href="customers.php?group=<?= $g['id'] ?>"><?= $g['so_khach'] ?></a></td>
                            <td class="text-end text-nowrap">
                                <a href="?edit=<?= $g['id'] ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                <form method="post" class="d-inline" data-confirm="Xóa nhóm &quot;<?= e($g['ten_nhom']) ?>&quot;?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"<?= $g['so_khach'] || $g['nhan_vien'] ? ' disabled title="Nhóm đang có khách hàng hoặc nhân viên"' : '' ?>>Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$groups): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-5">Chưa có nhóm khách hàng nào.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= $editId ? 'Sửa nhóm' : 'Thêm nhóm mới' ?></div>
            <div class="card-body">
                <form method="post" action="?<?= $editId ? 'edit=' . $editId : '' ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="ten_nhom" class="form-label">Tên nhóm <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= invalid($errors, 'ten_nhom') ?>" id="ten_nhom" name="ten_nhom" value="<?= e($form['ten_nhom']) ?>" maxlength="100" required>
                        <?= feedback($errors, 'ten_nhom') ?>
                    </div>
                    <div class="mb-3">
                        <label for="mo_ta" class="form-label">Mô tả / tiêu chí</label>
                        <input type="text" class="form-control<?= invalid($errors, 'mo_ta') ?>" id="mo_ta" name="mo_ta" value="<?= e($form['mo_ta']) ?>" maxlength="255">
                        <?= feedback($errors, 'mo_ta') ?>
                    </div>
                    <div class="mb-4">
                        <label for="mau_nhan" class="form-label">Màu nhãn</label>
                        <select class="form-select<?= invalid($errors, 'mau_nhan') ?>" id="mau_nhan" name="mau_nhan">
                            <?php foreach (MAU_NHAN as $value => $label): ?>
                                <option value="<?= $value ?>"<?= $value === $form['mau_nhan'] ? ' selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= feedback($errors, 'mau_nhan') ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editId ? 'Lưu thay đổi' : 'Thêm nhóm' ?></button>
                    <?php if ($editId): ?><a href="groups.php" class="btn btn-outline-secondary">Hủy</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
