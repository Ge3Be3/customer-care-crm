<?php
require __DIR__ . '/../includes/functions.php';
require_login();

// Xóa khách hàng (POST từ danh sách hoặc trang chi tiết). Lịch sử liên hệ bị xóa theo nhờ ON DELETE CASCADE.
// Nhân viên chỉ xóa được khách thuộc nhóm mình: điều kiện nhóm nằm ngay trong câu DELETE.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = 'DELETE FROM customers WHERE id = ?';
    $params = [(int)input($_POST, 'id')];
    if (!is_admin()) {
        $sql .= ' AND id_nhom = ?';
        $params[] = current_user()['id_nhom'];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stmt->rowCount()
        ? flash('success', 'Đã xóa khách hàng cùng toàn bộ lịch sử liên hệ.')
        : flash('danger', 'Xóa thất bại: không tìm thấy khách hàng hoặc khách không thuộc nhóm bạn phụ trách.');
    redirect('customers.php?' . http_build_query($_GET));
}

// Bộ lọc: từ khóa (tên/SĐT/email), nhóm, trạng thái chăm sóc
$q = input($_GET, 'q');
$group = (int)input($_GET, 'group');
$status = input($_GET, 'status');

$where = [];
$params = [];
if ($q !== '') {
    // Email chỉ khớp phần đầu, tránh "mai" khớp mọi địa chỉ @gmail.com
    $where[] = '(c.ho_ten LIKE ? OR c.so_dien_thoai LIKE ? OR c.email LIKE ?)';
    $escaped = addcslashes($q, '%_\\');
    array_push($params, "%$escaped%", "%$escaped%", "$escaped%");
}
if ($group) {
    $where[] = 'c.id_nhom = ?';
    $params[] = $group;
}
if (isset(FOLLOWUP_SQL[$status])) {
    $where[] = FOLLOWUP_SQL[$status];
}
$from = 'FROM customers c JOIN customer_groups g ON g.id = c.id_nhom ' . FOLLOWUP_JOIN
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

// Xuất CSV theo đúng bộ lọc hiện tại
if (input($_GET, 'export') === 'csv') {
    $stmt = $pdo->prepare("SELECT c.ho_ten, c.so_dien_thoai, c.email, c.ngay_sinh, c.gioi_tinh, c.dia_chi, g.ten_nhom,
        c.nguon_khach_hang, f.ngay_hen_tiep_theo, c.ghi_chu, c.ngay_tao $from ORDER BY c.ho_ten");
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="khach-hang-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel nhận đúng tiếng Việt
    fputcsv($out, ['Họ tên', 'Số điện thoại', 'Email', 'Ngày sinh', 'Giới tính', 'Địa chỉ', 'Nhóm',
        'Nguồn', 'Lịch hẹn', 'Ghi chú', 'Ngày tạo'], ',', '"', '');
    foreach ($stmt as $row) {
        // Chống CSV/formula injection: ô bắt đầu bằng = + - @ bị Excel hiểu là công thức
        $row = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v, $row);
        $row['so_dien_thoai'] = '="' . $row['so_dien_thoai'] . '"'; // giữ số 0 đầu (SĐT đã được kiểm tra chỉ gồm chữ số)
        fputcsv($out, $row, ',', '"', '');
    }
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) $from");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
[$page, $pages, $offset] = paginate($total);

$stmt = $pdo->prepare("SELECT c.id, c.ho_ten, c.so_dien_thoai, c.email, c.id_nhom, g.ten_nhom, g.mau_nhan, f.ngay_hen_tiep_theo,
    (SELECT COUNT(*) FROM contact_history x WHERE x.id_khach_hang = c.id) AS so_lien_he
    $from ORDER BY c.ngay_tao DESC, c.id DESC LIMIT " . PER_PAGE . " OFFSET $offset");
$stmt->execute($params);
$customers = $stmt->fetchAll();

$groups = $pdo->query('SELECT id, ten_nhom FROM customer_groups ORDER BY ten_nhom')->fetchAll(PDO::FETCH_KEY_PAIR);

$title = 'Khách hàng';
$nav = 'customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
    <h1>Khách hàng</h1>
    <div class="d-flex gap-2">
        <a href="?<?= e(http_build_query(['export' => 'csv'] + $_GET)) ?>" class="btn btn-outline-secondary">Xuất CSV</a>
        <a href="customer_form.php" class="btn btn-primary">+ Thêm khách hàng</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label small">Tìm kiếm</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Tên, số điện thoại hoặc email" maxlength="100">
            </div>
            <div class="col-6 col-md-2">
                <label for="group" class="form-label small">Nhóm</label>
                <select class="form-select" id="group" name="group">
                    <option value="">Tất cả</option>
                    <?php foreach ($groups as $gid => $gname): ?>
                        <option value="<?= $gid ?>"<?= $gid === $group ? ' selected' : '' ?>><?= e($gname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small">Trạng thái chăm sóc</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <?php foreach (FOLLOWUP_SQL as $key => $_): ?>
                        <option value="<?= $key ?>"<?= $key === $status ? ' selected' : '' ?>><?= FOLLOWUP_LABEL[$key] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Lọc</button>
                <a href="customers.php" class="btn btn-outline-secondary" title="Xóa bộ lọc">✕</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr><th>Họ tên</th><th>Số điện thoại</th><th>Email</th><th>Nhóm</th><th>Lịch hẹn</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><a href="customer_view.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none"><?= e($c['ho_ten']) ?></a></td>
                    <td><?= e($c['so_dien_thoai']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= group_badge($c['ten_nhom'], $c['mau_nhan']) ?></td>
                    <td><?= followup_badge($c['ngay_hen_tiep_theo']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="customer_view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Xem</a>
                        <?php if (can_manage($c['id_nhom'])): ?>
                            <a href="customer_form.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            <form method="post" action="?<?= e(http_build_query($_GET)) ?>" class="d-inline"
                                  data-confirm="Xóa khách hàng &quot;<?= e($c['ho_ten']) ?>&quot;? <?= $c['so_lien_he'] ?> bản ghi liên hệ cũng sẽ bị xóa.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Xóa</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?>
                <tr><td colspan="6" class="text-center text-secondary py-5">Không tìm thấy khách hàng phù hợp.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-secondary">
            <?= $total ? 'Hiển thị ' . ($offset + 1) . '–' . ($offset + count($customers)) . ' / ' . $total . ' khách hàng' : '' ?>
        </span>
        <?= pagination($page, $pages) ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
