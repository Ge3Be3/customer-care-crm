<?php
// Tự kiểm tra logic lõi. Chạy: php tests/check.php  (cần CSDL đã import và cấu hình đúng)
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../includes/functions.php';

function check(bool $ok, string $what): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $what\n");
        exit(1);
    }
}

check(valid_date('2026-02-28') && !valid_date('2026-02-29') && !valid_date('19/09/2026'), 'valid_date');
check(valid_date('2026-09-19T08:30', 'Y-m-d\TH:i') && !valid_date('2026-09-19T25:00', 'Y-m-d\TH:i'), 'valid_date datetime-local');

$today = '2026-09-19';
$expected = [null => null, '2026-09-18' => 'qua_han', '2026-09-19' => 'hom_nay', '2026-09-20' => 'sap_toi',
    '2026-09-26' => 'sap_toi', '2026-09-27' => 'da_hen'];
foreach ($expected as $date => $status) {
    check(followup_status($date ?: null, $today) === $status, "followup_status($date)");
}

// PHP (badge) và SQL (bộ lọc, dashboard) phải phân loại lịch hẹn giống hệt nhau
foreach ([-3, -1, 0, 1, 7, 8, 30] as $offset) {
    $date = date('Y-m-d', strtotime("$offset days"));
    foreach (FOLLOWUP_SQL as $status => $condition) {
        $stmt = $pdo->prepare("SELECT $condition FROM (SELECT CAST(? AS DATE) AS ngay_hen_tiep_theo) f");
        $stmt->execute([$date]);
        check((bool)$stmt->fetchColumn() === (followup_status($date) === $status), "SQL vs PHP: $status @ $offset days");
    }
}

// Phân trang dạng cửa sổ: số trang hiển thị và vị trí dấu "…"
$shown = fn(int $page, int $pages) => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('</li>', ' ', pagination($page, $pages)))));
check(pagination(1, 1) === '', 'pagination: 1 trang thì không hiện');
check($shown(1, 5) === '1 2 3 4 5 ›', 'pagination(1,5): ' . $shown(1, 5));
check($shown(10, 20) === '‹ 1 … 8 9 10 11 12 … 20 ›', 'pagination(10,20): ' . $shown(10, 20));
check($shown(4, 20) === '‹ 1 2 3 4 5 6 … 20 ›', 'pagination(4,20): ' . $shown(4, 20));
check($shown(20, 20) === '‹ 1 … 18 19 20', 'pagination(20,20): ' . $shown(20, 20));

echo "OK\n";
