<?php
require __DIR__ . '/../includes/functions.php';
require_login();

$id = (int)input($_GET, 'id');
$stmt = $pdo->prepare('SELECT c.*, g.ten_nhom, g.mau_nhan FROM customers c JOIN customer_groups g ON g.id = c.id_nhom WHERE c.id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) {
    flash('danger', 'Không tìm thấy khách hàng.');
    redirect('customers.php');
}

// Toàn bộ lịch sử liên hệ, mới nhất trước; bản ghi đầu tiên quyết định lịch hẹn hiện tại
$stmt = $pdo->prepare('SELECT h.*, u.ho_ten AS nguoi_ghi FROM contact_history h JOIN users u ON u.id = h.id_nguoi_ghi
    WHERE h.id_khach_hang = ? ORDER BY h.ngay_lien_he DESC, h.id DESC');
$stmt->execute([$id]);
$contacts = $stmt->fetchAll();
$canManage = can_manage($customer['id_nhom']);

$info = [
    'Số điện thoại' => $customer['so_dien_thoai'],
    'Email'         => $customer['email'],
    'Ngày sinh'     => fmt_date($customer['ngay_sinh']),
    'Giới tính'     => $customer['gioi_tinh'],
    'Địa chỉ'       => $customer['dia_chi'],
    'Nguồn'         => $customer['nguon_khach_hang'],
    'Ngày tạo'      => fmt_date($customer['ngay_tao']),
    'Ghi chú'       => $customer['ghi_chu'],
];

$title = $customer['ho_ten'];
$nav = 'customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
    <div>
        <h1><?= e($customer['ho_ten']) ?></h1>
        <div class="mt-2"><?= group_badge($customer['ten_nhom'], $customer['mau_nhan']) ?></div>
    </div>
    <?php if ($canManage): ?>
    <div class="d-flex flex-wrap gap-2">
        <a href="contact_form.php?customer_id=<?= $id ?>" class="btn btn-primary">+ Ghi nhận liên hệ</a>
        <a href="customer_form.php?id=<?= $id ?>" class="btn btn-outline-secondary">Sửa</a>
        <form method="post" action="customers.php" data-confirm="Xóa khách hàng &quot;<?= e($customer['ho_ten']) ?>&quot;? <?= count($contacts) ?> bản ghi liên hệ cũng sẽ bị xóa.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn btn-outline-primary">Xóa</button>
        </form>
    </div>
    <?php else: ?>
    <span class="small text-secondary">Chỉ xem — khách thuộc nhóm do nhân viên khác phụ trách</span>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">Lịch chăm sóc</div>
            <div class="card-body">
                <?= followup_badge($contacts[0]['ngay_hen_tiep_theo'] ?? null) ?>
                <p class="small text-secondary mt-2 mb-0">Lấy theo ngày hẹn của lần liên hệ gần nhất. Ghi nhận liên hệ mới để cập nhật.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Thông tin khách hàng</div>
            <div class="card-body">
                <dl class="info-list mb-0">
                    <?php foreach ($info as $label => $value): ?>
                        <dt><?= $label ?></dt>
                        <dd class="text-pre"><?= $value ? e($value) : '<span class="text-secondary">—</span>' ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Lịch sử liên hệ (<?= count($contacts) ?>)</div>
            <div class="card-body">
                <?php foreach ($contacts as $contact): ?>
                    <div class="timeline-item">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <strong><?= e($contact['loai_lien_he']) ?></strong>
                                <span class="text-secondary small">· <?= fmt_date($contact['ngay_lien_he'], 'd/m/Y H:i') ?> · Ghi bởi <?= e($contact['nguoi_ghi']) ?></span>
                            </div>
                            <?php if ($canManage): ?>
                            <div class="text-nowrap">
                                <a href="contact_form.php?id=<?= $contact['id'] ?>" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                <form method="post" action="contacts.php" class="d-inline" data-confirm="Xóa bản ghi liên hệ này?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $contact['id'] ?>">
                                    <input type="hidden" name="customer_id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Xóa</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-pre my-2"><?= e($contact['noi_dung']) ?></p>
                        <?php if ($contact['ngay_hen_tiep_theo']): ?>
                            <div class="small text-secondary">Hẹn liên hệ tiếp: <?= fmt_date($contact['ngay_hen_tiep_theo']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$contacts): ?>
                    <p class="text-secondary text-center py-4 mb-0">Chưa có lần liên hệ nào với khách hàng này.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
