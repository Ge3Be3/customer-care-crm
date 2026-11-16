<?php
require __DIR__ . '/../includes/functions.php';
require_login();

// Nhân viên chỉ thấy số liệu nhóm mình phụ trách. id_nhom lấy từ CSDL (không phải dữ liệu người dùng nhập), ép kiểu int.
$groupId = is_admin() ? 0 : (int)current_user()['id_nhom'];
$scope = $groupId ? " AND c.id_nhom = $groupId" : '';
$link = 'customers.php?' . ($groupId ? "group=$groupId&" : '');

$customerStats = $pdo->query("SELECT COUNT(*) AS tong, COALESCE(SUM(c.ngay_tao >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS moi_thang
    FROM customers c WHERE 1 = 1 $scope")->fetch();

// Đếm theo trạng thái lịch hẹn — dùng lại đúng điều kiện của bộ lọc danh sách khách hàng
$followup = $pdo->query('SELECT COALESCE(SUM(' . FOLLOWUP_SQL['qua_han'] . '), 0) AS qua_han,
    COALESCE(SUM(' . FOLLOWUP_SQL['hom_nay'] . '), 0) AS hom_nay,
    COALESCE(SUM(' . FOLLOWUP_SQL['sap_toi'] . '), 0) AS sap_toi
    FROM customers c ' . FOLLOWUP_JOIN . " WHERE 1 = 1 $scope")->fetch();

$byGroup = $pdo->query('SELECT g.id, g.ten_nhom, g.mau_nhan, COUNT(c.id) AS so_khach
    FROM customer_groups g LEFT JOIN customers c ON c.id_nhom = g.id
    GROUP BY g.id, g.ten_nhom, g.mau_nhan ORDER BY so_khach DESC, g.ten_nhom')->fetchAll();
$allCustomers = array_sum(array_column($byGroup, 'so_khach')); // toàn cửa hàng, kể cả khi nhân viên xem

// Danh sách cần liên hệ: quá hạn + hôm nay + 7 ngày tới, gần nhất trước
$dueList = $pdo->query('SELECT c.id, c.ho_ten, c.so_dien_thoai, g.ten_nhom, g.mau_nhan, f.ngay_hen_tiep_theo
    FROM customers c JOIN customer_groups g ON g.id = c.id_nhom ' . FOLLOWUP_JOIN . '
    WHERE f.ngay_hen_tiep_theo <= CURDATE() + INTERVAL 7 DAY' . $scope . '
    ORDER BY f.ngay_hen_tiep_theo, c.ho_ten LIMIT 10')->fetchAll();

$cards = [
    [$link, $customerStats['tong'], $groupId ? 'Khách hàng nhóm mình' : 'Tổng khách hàng', '+' . $customerStats['moi_thang'] . ' khách mới tháng này', false],
    [$link . 'status=qua_han', $followup['qua_han'], 'Quá hạn liên hệ', 'Cần xử lý ngay', $followup['qua_han'] > 0],
    [$link . 'status=hom_nay', $followup['hom_nay'], 'Cần liên hệ hôm nay', fmt_date(date('Y-m-d')), false],
    [$link . 'status=sap_toi', $followup['sap_toi'], 'Sắp tới (7 ngày)', 'Lên kế hoạch chăm sóc', false],
];

$title = 'Tổng quan';
$nav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
    <h1>Tổng quan</h1>
    <?php if ($groupId): ?><span class="text-secondary">Nhóm phụ trách: <?= e(current_user()['ten_nhom']) ?></span><?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$href, $value, $label, $sub, $alert]): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= $href ?>" class="stat-card<?= $alert ? ' is-alert' : '' ?>">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="stat-label"><?= $label ?></div>
                        <div class="stat-value my-1"><?= (int)$value ?></div>
                        <div class="small text-secondary"><?= e($sub) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Khách hàng cần liên hệ</div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Khách hàng</th><th>Số điện thoại</th><th>Nhóm</th><th>Lịch hẹn</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($dueList as $c): ?>
                        <tr>
                            <td><a href="customer_view.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none"><?= e($c['ho_ten']) ?></a></td>
                            <td><?= e($c['so_dien_thoai']) ?></td>
                            <td><?= group_badge($c['ten_nhom'], $c['mau_nhan']) ?></td>
                            <td><?= followup_badge($c['ngay_hen_tiep_theo']) ?></td>
                            <td class="text-end"><a href="contact_form.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary text-nowrap">Ghi nhận</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$dueList): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-5">Không có khách hàng nào cần liên hệ trong 7 ngày tới.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (array_sum([$followup['qua_han'], $followup['hom_nay'], $followup['sap_toi']]) > count($dueList)): ?>
                <div class="card-body small">Chỉ hiển thị 10 lịch gần nhất — bấm vào các ô số liệu phía trên để xem đầy đủ.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Khách hàng theo nhóm</div>
            <div class="card-body">
                <?php foreach ($byGroup as $g): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <a href="customers.php?group=<?= $g['id'] ?>" class="text-decoration-none"><?= group_badge($g['ten_nhom'], $g['mau_nhan']) ?></a>
                            <span class="fw-semibold"><?= $g['so_khach'] ?></span>
                        </div>
                        <div class="bar"><span style="width: <?= $allCustomers ? round($g['so_khach'] / $allCustomers * 100) : 0 ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
