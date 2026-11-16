<?php
// Gợi ý khách hàng (JSON, tối đa 20) cho ô tìm kiếm ở form ghi nhận liên hệ.
// Nhân viên chỉ nhận khách thuộc nhóm mình, vì chỉ được ghi nhận liên hệ cho nhóm đó.
require __DIR__ . '/../includes/functions.php';
require_login();

$q = input($_GET, 'q');
$result = [];
if (mb_strlen($q) >= 2) {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $sql = "SELECT id, CONCAT(ho_ten, ' – ', so_dien_thoai) AS label FROM customers WHERE (ho_ten LIKE ? OR so_dien_thoai LIKE ?)";
    $params = [$like, $like];
    if (!is_admin()) {
        $sql .= ' AND id_nhom = ?';
        $params[] = current_user()['id_nhom'];
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY ho_ten LIMIT 20');
    $stmt->execute($params);
    $result = $stmt->fetchAll();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
