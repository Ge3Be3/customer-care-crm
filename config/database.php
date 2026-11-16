<?php
// Kết nối CSDL bằng PDO. Mặc định dùng cấu hình XAMPP (root, không mật khẩu).
// Khi chạy bằng Docker / GitHub Codespaces, các biến môi trường DB_* trong docker-compose.yml sẽ được dùng.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'crm_giabao');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // CURDATE()/NOW() của MySQL khớp giờ Việt Nam như PHP
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    error_log('DB connect failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình trong config/database.php.');
}
