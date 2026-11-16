<?php
// Nạp ở đầu MỌI trang: session, CSDL, CSRF, hàm tiện ích dùng chung.
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Lỗi không lường trước: ghi log, không lộ chi tiết (đường dẫn, câu SQL) ra trình duyệt.
set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    exit('Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.');
});

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

// Tự đăng xuất sau 30 phút không thao tác
if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_active'] = time();

require __DIR__ . '/../config/database.php';

const PER_PAGE = 10;
const GIOI_TINH = ['Nam', 'Nữ', 'Khác'];
const NGUON_KH = ['Facebook', 'Zalo', 'Giới thiệu', 'Vãng lai', 'Website', 'Khác'];
const LOAI_LH = ['Gọi điện', 'Gặp mặt', 'Email', 'Tin nhắn', 'Ghi chú chăm sóc', 'Khác'];
const MAU_NHAN = ['gold' => 'Vàng', 'red' => 'Đỏ đậm', 'slate' => 'Xám', 'green' => 'Xanh lá', 'navy' => 'Xanh navy'];

// Lịch hẹn hiện tại của KH = ngày hẹn của bản ghi liên hệ MỚI NHẤT (alias f, cần alias c = customers).
const FOLLOWUP_JOIN = 'LEFT JOIN contact_history f ON f.id = (SELECT h.id FROM contact_history h
    WHERE h.id_khach_hang = c.id ORDER BY h.ngay_lien_he DESC, h.id DESC LIMIT 1)';
const FOLLOWUP_LABEL = ['qua_han' => 'Quá hạn', 'hom_nay' => 'Hôm nay', 'sap_toi' => 'Sắp tới', 'da_hen' => 'Đã hẹn', 'khong' => 'Không có lịch'];
const FOLLOWUP_SQL = [
    'qua_han' => 'f.ngay_hen_tiep_theo < CURDATE()',
    'hom_nay' => 'f.ngay_hen_tiep_theo = CURDATE()',
    'sap_toi' => 'f.ngay_hen_tiep_theo BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY',
    'khong'   => 'f.ngay_hen_tiep_theo IS NULL',
];

// Mọi request POST đều phải mang CSRF token hợp lệ — kiểm tra một lần ở đây cho tất cả các trang.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

// Người dùng đang đăng nhập, đọc lại từ CSDL mỗi request để việc khóa tài khoản / đổi nhóm có hiệu lực ngay.
function current_user(): ?array
{
    global $pdo;
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = $pdo->prepare('SELECT u.id, u.username, u.ho_ten, u.vai_tro, u.id_nhom, g.ten_nhom
                FROM users u LEFT JOIN customer_groups g ON g.id = u.id_nhom WHERE u.id = ? AND u.hoat_dong = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['vai_tro'] ?? '') === 'admin';
}

// Admin quản lý mọi nhóm; nhân viên chỉ quản lý khách hàng thuộc nhóm mình phụ trách.
function can_manage(int $groupId): bool
{
    return is_admin() || (int)(current_user()['id_nhom'] ?? 0) === $groupId;
}

function require_login(): void
{
    if (!current_user()) {
        unset($_SESSION['user_id']);
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('../login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Bạn không có quyền truy cập chức năng này.');
        redirect('dashboard.php');
    }
}

function deny(string $back): void
{
    flash('danger', 'Bạn chỉ được thao tác với khách hàng thuộc nhóm mình phụ trách.');
    redirect($back);
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
    }
}

// Lấy chuỗi đã trim từ $_POST/$_GET; giá trị không phải chuỗi UTF-8 hợp lệ (mảng, byte lỗi...) coi như rỗng.
function input(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) && mb_check_encoding($value, 'UTF-8') ? trim($value) : '';
}

// Quy tắc mật khẩu dùng chung (đổi mật khẩu, tạo/đặt lại mật khẩu nhân viên). Trả về '' nếu hợp lệ.
function password_error(string $password): string
{
    return mb_strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)
        ? 'Mật khẩu tối thiểu 8 ký tự, gồm cả chữ và số.' : '';
}

function valid_date(string $value, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat('!' . $format, $value);
    return $d && $d->format($format) === $value;
}

function fmt_date(?string $value, string $format = 'd/m/Y'): string
{
    return $value ? date($format, strtotime($value)) : '';
}

// Trạng thái lịch hẹn — cùng mốc với FOLLOWUP_SQL. null = không có lịch; 'da_hen' = hẹn xa hơn 7 ngày.
function followup_status(?string $date, ?string $today = null): ?string
{
    if (!$date) {
        return null;
    }
    $today ??= date('Y-m-d');
    if ($date < $today) return 'qua_han';
    if ($date === $today) return 'hom_nay';
    if ($date <= date('Y-m-d', strtotime($today . ' +7 days'))) return 'sap_toi';
    return 'da_hen';
}

function followup_badge(?string $date): string
{
    $status = followup_status($date);
    if (!$status) {
        return '<span class="text-muted">—</span>';
    }
    return '<span class="badge fu-' . $status . '">' . FOLLOWUP_LABEL[$status] . ' · ' . fmt_date($date) . '</span>';
}

function group_badge(string $name, string $color): string
{
    return '<span class="badge grp-' . e($color) . '">' . e($name) . '</span>';
}

// Trả về [trang hiện tại, tổng số trang, offset] từ ?page=
function paginate(int $total): array
{
    $pages = max(1, (int)ceil($total / PER_PAGE));
    $page = min($pages, max(1, (int)input($_GET, 'page')));
    return [$page, $pages, ($page - 1) * PER_PAGE];
}

// Phân trang dạng cửa sổ: ‹ 1 … 4 5 [6] 7 8 … 20 › — trang đầu, trang cuối và 2 trang mỗi bên trang hiện tại.
function pagination(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = fn(int $i, string $text, string $label) => '<li class="page-item"><a class="page-link" href="?'
        . e(http_build_query(['page' => $i] + $_GET)) . '" aria-label="' . $label . '">' . $text . '</a></li>';

    $html = '<nav aria-label="Phân trang"><ul class="pagination pagination-sm mb-0">';
    if ($page > 1) {
        $html .= $link($page - 1, '‹', 'Trang trước');
    }
    $prev = 0;
    foreach (array_unique([1, ...range(max(1, $page - 2), min($pages, $page + 2)), $pages]) as $i) {
        if ($i - $prev === 2) {
            $html .= $link($i - 1, (string)($i - 1), 'Trang ' . ($i - 1)); // chỉ thiếu 1 trang thì hiện luôn số trang
        } elseif ($i - $prev > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= $i === $page
            ? '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>'
            : $link($i, (string)$i, "Trang $i");
        $prev = $i;
    }
    if ($page < $pages) {
        $html .= $link($page + 1, '›', 'Trang sau');
    }
    return $html . '</ul></nav>';
}

// Bootstrap validation: class is-invalid + thông báo lỗi dưới ô nhập
function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function feedback(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
}

function is_duplicate(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? 0) === 1062;
}
