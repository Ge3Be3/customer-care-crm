<?php
require __DIR__ . '/../includes/functions.php';
require_login();

// Xóa bản ghi liên hệ (POST từ danh sách này hoặc từ trang chi tiết khách hàng).
// Nhân viên chỉ xóa được bản ghi của khách thuộc nhóm mình: điều kiện nhóm nằm ngay trong câu DELETE.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = 'DELETE h FROM contact_history h JOIN customers c ON c.id = h.id_khach_hang WHERE h.id = ?';
    $params = [(int)input($_POST, 'id')];
    if (!is_admin()) {
        $sql .= ' AND c.id_nhom = ?';
        $params[] = current_user()['id_nhom'];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stmt->rowCount()
        ? flash('success', 'Đã xóa bản ghi liên hệ.')
        : flash('danger', 'Xóa thất bại: không tìm thấy bản ghi hoặc khách không thuộc nhóm bạn phụ trách.');
    $customerId = (int)input($_POST, 'customer_id');
    redirect($customerId ? "customer_view.php?id=$customerId" : 'contacts.php?' . http_build_query($_GET));
}

// Bộ lọc: khoảng ngày liên hệ, nhóm khách hàng (= nhân viên phụ trách)
$from = input($_GET, 'tu_ngay');
$to = input($_GET, 'den_ngay');
$group = (int)input($_GET, 'group');
$from = valid_date($from) ? $from : '';
$to = valid_date($to) ? $to : '';
$dateError = $from && $to && $from > $to ? 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.' : '';

$where = [];
$params = [];
if ($from && !$dateError) {
    $where[] = 'h.ngay_lien_he >= ?';
    $params[] = $from;
}
if ($to && !$dateError) {
    $where[] = 'h.ngay_lien_he < ? + INTERVAL 1 DAY'; // bao gồm trọn ngày kết thúc
    $params[] = $to;
}
if ($group) {
    $where[] = 'c.id_nhom = ?';
    $params[] = $group;
}
$join = 'FROM contact_history h JOIN customers c ON c.id = h.id_khach_hang JOIN users u ON u.id = h.id_nguoi_ghi'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

$stmt = $pdo->prepare("SELECT COUNT(*) $join");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
[$page, $pages, $offset] = paginate($total);

$stmt = $pdo->prepare("SELECT h.*, c.ho_ten, c.id_nhom, u.ho_ten AS nguoi_ghi $join
    ORDER BY h.ngay_lien_he DESC, h.id DESC LIMIT " . PER_PAGE . " OFFSET $offset");
$stmt->execute($params);
$contacts = $stmt->fetchAll();

// Nhãn nhóm kèm tên nhân viên phụ trách, dùng cho bộ lọc
$groups = $pdo->query("SELECT g.id, CONCAT(g.ten_nhom, COALESCE(CONCAT(' — ', GROUP_CONCAT(u.ho_ten SEPARATOR ', ')), ''))
    FROM customer_groups g LEFT JOIN users u ON u.id_nhom = g.id AND u.hoat_dong = 1
    GROUP BY g.id, g.ten_nhom ORDER BY g.ten_nhom")->fetchAll(PDO::FETCH_KEY_PAIR);

$title = 'Lịch sử liên hệ';
$nav = 'contacts';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
    <h1>Lịch sử liên hệ</h1>
    <a href="contact_form.php" class="btn btn-primary">+ Ghi nhận liên hệ</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="tu_ngay" class="form-label small">Từ ngày</label>
                <input type="date" class="form-control<?= $dateError ? ' is-invalid' : '' ?>" id="tu_ngay" name="tu_ngay" value="<?= e($from) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label for="den_ngay" class="form-label small">Đến ngày</label>
                <input type="date" class="form-control<?= $dateError ? ' is-invalid' : '' ?>" id="den_ngay" name="den_ngay" value="<?= e($to) ?>">
            </div>
            <div class="col-md-4">
                <label for="group" class="form-label small">Nhóm khách hàng / nhân viên phụ trách</label>
                <select class="form-select" id="group" name="group">
                    <option value="">Tất cả</option>
                    <?php foreach ($groups as $gid => $label): ?>
                        <option value="<?= $gid ?>"<?= $gid === $group ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Lọc</button>
                <a href="contacts.php" class="btn btn-outline-secondary" title="Xóa bộ lọc">✕</a>
            </div>
            <?php if ($dateError): ?><div class="col-12 small text-danger"><?= $dateError ?></div><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr><th>Thời gian</th><th>Khách hàng</th><th>Hình thức</th><th>Nội dung</th><th>Hẹn tiếp theo</th><th>Người ghi</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            <?php foreach ($contacts as $h): ?>
                <tr>
                    <td class="text-nowrap"><?= fmt_date($h['ngay_lien_he'], 'd/m/Y H:i') ?></td>
                    <td><a href="customer_view.php?id=<?= $h['id_khach_hang'] ?>" class="fw-semibold text-decoration-none"><?= e($h['ho_ten']) ?></a></td>
                    <td class="text-nowrap"><?= e($h['loai_lien_he']) ?></td>
                    <td><?= e(mb_strimwidth($h['noi_dung'], 0, 80, '…')) ?></td>
                    <td class="text-nowrap"><?= fmt_date($h['ngay_hen_tiep_theo']) ?: '<span class="text-secondary">—</span>' ?></td>
                    <td class="text-nowrap small"><?= e($h['nguoi_ghi']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can_manage($h['id_nhom'])): ?>
                            <a href="contact_form.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            <form method="post" action="?<?= e(http_build_query($_GET)) ?>" class="d-inline" data-confirm="Xóa bản ghi liên hệ này?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Xóa</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$contacts): ?>
                <tr><td colspan="7" class="text-center text-secondary py-5">Không có bản ghi liên hệ phù hợp.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-secondary">
            <?= $total ? 'Hiển thị ' . ($offset + 1) . '–' . ($offset + count($contacts)) . ' / ' . $total . ' bản ghi' : '' ?>
        </span>
        <?= pagination($page, $pages) ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
