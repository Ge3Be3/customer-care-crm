-- Hệ thống quản lý khách hàng – Gia Bao Group (Đề tài 44)
-- MySQL 5.7+/MariaDB 10.4+ · utf8mb4
-- Dữ liệu mẫu dùng ngày tương đối (CURDATE/NOW) để lịch chăm sóc luôn có
-- đủ trạng thái Quá hạn / Hôm nay / Sắp tới bất kể ngày import.

CREATE DATABASE IF NOT EXISTS crm_giabao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_giabao;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS contact_history;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS customer_groups;

-- Nhóm khách hàng theo sản phẩm/dịch vụ quan tâm
CREATE TABLE customer_groups (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ten_nhom  VARCHAR(100) NOT NULL,
    mo_ta     VARCHAR(255) NULL,
    mau_nhan  ENUM('gold','red','slate','green','navy') NOT NULL DEFAULT 'slate',
    UNIQUE KEY uq_ten_nhom (ten_nhom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tài khoản: 1 Admin (toàn quyền) và các Nhân viên, mỗi nhân viên phụ trách đúng 1 nhóm khách hàng
CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL,
    ho_ten     VARCHAR(100) NOT NULL,
    mat_khau   VARCHAR(255) NOT NULL COMMENT 'password_hash()',
    vai_tro    ENUM('admin','nhan_vien') NOT NULL DEFAULT 'nhan_vien',
    id_nhom    INT UNSIGNED NULL COMMENT 'Nhóm phụ trách (chỉ nhân viên)',
    hoat_dong  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = bị khóa',
    ngay_tao   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    CONSTRAINT fk_users_group FOREIGN KEY (id_nhom) REFERENCES customer_groups (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_vai_tro_nhom CHECK ((vai_tro = 'admin' AND id_nhom IS NULL) OR (vai_tro = 'nhan_vien' AND id_nhom IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ho_ten           VARCHAR(100) NOT NULL,
    so_dien_thoai    VARCHAR(10)  NOT NULL,
    email            VARCHAR(100) NULL,
    ngay_sinh        DATE         NULL,
    gioi_tinh        ENUM('Nam','Nữ','Khác') NULL,
    dia_chi          VARCHAR(255) NULL,
    id_nhom          INT UNSIGNED NOT NULL,
    nguon_khach_hang ENUM('Facebook','Zalo','Giới thiệu','Vãng lai','Website','Khác') NULL,
    ghi_chu          TEXT         NULL,
    ngay_tao         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_so_dien_thoai (so_dien_thoai),
    UNIQUE KEY uq_email (email),
    KEY idx_ho_ten (ho_ten),
    CONSTRAINT fk_customers_group FOREIGN KEY (id_nhom) REFERENCES customer_groups (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_sdt CHECK (so_dien_thoai REGEXP '^0[0-9]{9}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_history (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_khach_hang      INT UNSIGNED NOT NULL,
    id_nguoi_ghi       INT UNSIGNED NOT NULL COMMENT 'Tài khoản ghi nhận lần liên hệ',
    loai_lien_he       ENUM('Gọi điện','Gặp mặt','Email','Tin nhắn','Ghi chú chăm sóc','Khác') NOT NULL,
    noi_dung           TEXT     NOT NULL,
    ngay_lien_he       DATETIME NOT NULL,
    ngay_hen_tiep_theo DATE     NULL,
    ngay_tao           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kh_ngay (id_khach_hang, ngay_lien_he),
    KEY idx_ngay_lien_he (ngay_lien_he),
    KEY idx_ngay_hen (ngay_hen_tiep_theo),
    CONSTRAINT fk_contact_customer FOREIGN KEY (id_khach_hang) REFERENCES customers (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_contact_user FOREIGN KEY (id_nguoi_ghi) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_ngay_hen CHECK (ngay_hen_tiep_theo IS NULL OR ngay_hen_tiep_theo >= DATE(ngay_lien_he))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nhật ký đăng nhập sai để chống dò mật khẩu (không khóa ngoại: tên đăng nhập có thể không tồn tại)
CREATE TABLE login_attempts (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(50) NOT NULL,
    ip        VARCHAR(45) NOT NULL COMMENT 'IPv4/IPv6',
    thoi_gian DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip_thoi_gian (ip, thoi_gian)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO customer_groups (id, ten_nhom, mo_ta, mau_nhan) VALUES
(1, 'Điện thoại & máy tính bảng', 'Quan tâm điện thoại, máy tính bảng, đồng hồ thông minh',       'red'),
(2, 'Laptop & phụ kiện máy tính', 'Laptop, màn hình, bàn phím, chuột, linh kiện nâng cấp',         'navy'),
(3, 'Điện gia dụng',              'Tivi, tủ lạnh, máy giặt, điều hòa, đồ gia dụng nhà bếp',       'green'),
(4, 'Sửa chữa & bảo hành',        'Khách dùng dịch vụ sửa chữa, bảo hành, vệ sinh thiết bị',       'gold');

-- Tài khoản demo: admin / Admin@123 · 4 nhân viên / NhanVien@123
INSERT INTO users (id, username, ho_ten, mat_khau, vai_tro, id_nhom) VALUES
(1, 'admin',       'Quản trị viên',   '$2y$10$dXE8JztJnHyIKrczGiqxYuxd7YxETpLDX4PdnoEDIOHgoF70OAsHm', 'admin',     NULL),
(2, 'nvdienthoai', 'Nguyễn Minh An',  '$2y$10$EkhhJXTct2lvt8PwLOJFPetHlYx4CTKDsc4rB11ZKfI5wtkltOQvO', 'nhan_vien', 1),
(3, 'nvlaptop',    'Trần Quốc Bình',  '$2y$10$EkhhJXTct2lvt8PwLOJFPetHlYx4CTKDsc4rB11ZKfI5wtkltOQvO', 'nhan_vien', 2),
(4, 'nvgiadung',   'Lê Thu Cúc',      '$2y$10$EkhhJXTct2lvt8PwLOJFPetHlYx4CTKDsc4rB11ZKfI5wtkltOQvO', 'nhan_vien', 3),
(5, 'nvbaohanh',   'Phạm Đức Dũng',   '$2y$10$EkhhJXTct2lvt8PwLOJFPetHlYx4CTKDsc4rB11ZKfI5wtkltOQvO', 'nhan_vien', 4);

INSERT INTO customers (id, ho_ten, so_dien_thoai, email, ngay_sinh, gioi_tinh, dia_chi, id_nhom, nguon_khach_hang, ghi_chu, ngay_tao) VALUES
(1,  'Nguyễn Thị Mai',      '0901234561', 'mai.nguyen@gmail.com',   '1990-05-12', 'Nữ',  '12 Lê Lợi, Q.1, TP.HCM',               1, 'Giới thiệu', 'Đang dùng iPhone 12, quan tâm lên đời máy mới', NOW() - INTERVAL 300 DAY),
(2,  'Trần Văn Hùng',       '0912345672', 'hung.tran@yahoo.com',    '1985-11-03', 'Nam', '45 Nguyễn Huệ, Q.1, TP.HCM',           2, 'Facebook',   'Lập trình viên, hay mua linh kiện nâng cấp. Chỉ liên hệ sau 18h', NOW() - INTERVAL 260 DAY),
(3,  'Lê Thị Hồng',         '0987654323', 'hong.le@gmail.com',      '1995-02-20', 'Nữ',  '89 Võ Văn Tần, Q.3, TP.HCM',           3, 'Zalo',       'Mới chuyển nhà, cần trọn bộ đồ gia dụng',     NOW() - INTERVAL 200 DAY),
(4,  'Phạm Minh Tuấn',      '0934567894', NULL,                     '1988-07-15', 'Nam', '23 Cách Mạng Tháng 8, Q.10, TP.HCM',   4, 'Vãng lai',   'Laptop đang bảo hành, hay hỏi tiến độ',       NOW() - INTERVAL 180 DAY),
(5,  'Hoàng Thị Lan',       '0978123455', 'lan.hoang@outlook.com',  '1992-09-09', 'Nữ',  '7 Phan Xích Long, Phú Nhuận, TP.HCM',  1, 'Website',    'Mua máy tính bảng cho con học online',        NOW() - INTERVAL 400 DAY),
(6,  'Vũ Đức Anh',          '0965432106', 'anh.vu@gmail.com',       '1999-12-01', 'Nam', '101 Điện Biên Phủ, Bình Thạnh, TP.HCM',2, 'Facebook',   NULL,                                          NOW() - INTERVAL 150 DAY),
(7,  'Đặng Thu Trang',      '0923456787', 'trang.dang@gmail.com',   '1997-04-18', 'Nữ',  '56 Hai Bà Trưng, Q.1, TP.HCM',         3, 'Giới thiệu', 'Quan tâm tủ lạnh inverter cao cấp',           NOW() - INTERVAL 120 DAY),
(8,  'Bùi Quốc Bảo',        '0945678918', NULL,                     '1983-03-27', 'Nam', '14 Nguyễn Trãi, Q.5, TP.HCM',          4, 'Vãng lai',   NULL,                                          NOW() - INTERVAL 100 DAY),
(9,  'Đỗ Ngọc Hân',         '0956789019', 'han.do@gmail.com',       '2000-08-30', 'Nữ',  '33 Lý Thường Kiệt, Q.10, TP.HCM',      4, 'Zalo',       'Máy giặt hỏng, đã đặt lịch sửa tại nhà',      NOW() - INTERVAL 90 DAY),
(10, 'Ngô Thanh Sơn',       '0967890120', 'son.ngo@gmail.com',      '1979-01-14', 'Nam', '78 Trần Hưng Đạo, Q.5, TP.HCM',        2, 'Website',    NULL,                                          NOW() - INTERVAL 80 DAY),
(11, 'Dương Mỹ Linh',       '0978901231', 'linh.duong@gmail.com',   '1994-06-06', 'Nữ',  '5 Pasteur, Q.3, TP.HCM',               3, 'Facebook',   NULL,                                          NOW() - INTERVAL 70 DAY),
(12, 'Lý Gia Huy',          '0989012342', NULL,                     '1991-10-22', 'Nam', '66 Nguyễn Thị Minh Khai, Q.3, TP.HCM', 1, 'Giới thiệu', 'Mua điện thoại số lượng cho nhân viên công ty', NOW() - INTERVAL 60 DAY),
(13, 'Trịnh Bảo Ngọc',      '0901122333', 'ngoc.trinh@gmail.com',   '1996-12-25', 'Nữ',  '19 Lê Văn Sỹ, Q.3, TP.HCM',            1, 'Vãng lai',   NULL,                                          NOW() - INTERVAL 55 DAY),
(14, 'Mai Xuân Phúc',       '0912233444', 'phuc.mai@gmail.com',     '1987-05-05', 'Nam', '8 Hoàng Văn Thụ, Tân Bình, TP.HCM',    2, 'Zalo',       'Chờ laptop gaming đời mới',                   NOW() - INTERVAL 40 DAY),
(15, 'Cao Thị Yến',         '0923344555', NULL,                     NULL,         'Nữ',  NULL,                                   3, 'Facebook',   'Hỏi giá nồi chiên không dầu qua fanpage',     NOW() - INTERVAL 20 DAY),
(16, 'Hồ Minh Khang',       '0934455666', 'khang.ho@gmail.com',     '2001-03-03', 'Nam', '120 Âu Cơ, Tân Phú, TP.HCM',           4, 'Website',    NULL,                                          NOW() - INTERVAL 15 DAY),
(17, 'Tạ Thùy Dung',        '0945566777', 'dung.ta@gmail.com',      '1993-07-19', 'Nữ',  '42 Nguyễn Đình Chiểu, Q.1, TP.HCM',    1, 'Giới thiệu', NULL,                                          NOW() - INTERVAL 12 DAY),
(18, 'Phan Đức Thắng',      '0956677888', NULL,                     '1990-02-11', 'Nam', '9 Cộng Hòa, Tân Bình, TP.HCM',         2, 'Vãng lai',   NULL,                                          NOW() - INTERVAL 10 DAY),
(19, 'Lâm Khánh Vy',        '0967788999', 'vy.lam@gmail.com',       '1998-09-28', 'Nữ',  '27 Sư Vạn Hạnh, Q.10, TP.HCM',         3, 'Zalo',       NULL,                                          NOW() - INTERVAL 8 DAY),
(20, 'Kiều Văn Nam',        '0978899000', NULL,                     '1982-04-04', 'Nam', NULL,                                   4, 'Khác',       NULL,                                          NOW() - INTERVAL 6 DAY),
(21, 'Tôn Nữ Quỳnh Anh',    '0989900111', 'quynhanh.ton@gmail.com', '1995-11-11', 'Nữ',  '3 Tú Xương, Q.3, TP.HCM',              1, 'Facebook',   'Thích điện thoại màu pastel',                 NOW() - INTERVAL 4 DAY),
(22, 'Châu Tấn Lộc',        '0909011222', 'loc.chau@gmail.com',     '1989-08-08', 'Nam', '250 Lạc Long Quân, Q.11, TP.HCM',      2, 'Website',    NULL,                                          NOW() - INTERVAL 3 DAY),
(23, 'Quách Tuệ Nhi',       '0910122333', NULL,                     '2002-01-30', 'Nữ',  NULL,                                   3, 'Vãng lai',   NULL,                                          NOW() - INTERVAL 2 DAY),
(24, 'Văn Hữu Tài',         '0921233444', 'tai.van@gmail.com',      '1986-06-16', 'Nam', '61 Phạm Văn Đồng, Thủ Đức, TP.HCM',    4, 'Giới thiệu', NULL,                                          NOW() - INTERVAL 1 DAY);

-- Lịch hẹn hiện tại của KH = ngay_hen_tiep_theo của bản ghi liên hệ MỚI NHẤT.
-- KH 1, 2, 5, 12: có bản ghi cũ đã được "xử lý" bởi lần liên hệ sau.
INSERT INTO contact_history (id_khach_hang, id_nguoi_ghi, loai_lien_he, noi_dung, ngay_lien_he, ngay_hen_tiep_theo) VALUES
(1,  2, 'Gặp mặt',          'Khách ghé xem iPhone đời mới, hẹn gọi báo khi có hàng.',                 NOW() - INTERVAL 30 DAY, CURDATE() - INTERVAL 20 DAY),
(1,  2, 'Gọi điện',         'Báo hàng đã về, khách hẹn cuối tuần ghé xem máy.',                        NOW() - INTERVAL 10 DAY, CURDATE() - INTERVAL 3 DAY),
(2,  3, 'Email',            'Gửi báo giá RAM và SSD nâng cấp.',                                        NOW() - INTERVAL 20 DAY, CURDATE() - INTERVAL 12 DAY),
(2,  3, 'Gọi điện',         'Khách đồng ý báo giá, muốn đặt trước 2 thanh RAM 16GB.',                  NOW() - INTERVAL 5 DAY,  CURDATE()),
(3,  4, 'Tin nhắn',         'Nhắn Zalo gửi combo tủ lạnh + máy giặt giá ưu đãi.',                      NOW() - INTERVAL 2 DAY,  CURDATE() + INTERVAL 2 DAY),
(4,  5, 'Gặp mặt',          'Nhận laptop bảo hành lỗi bàn phím, hẹn ngày trả máy.',                    NOW() - INTERVAL 7 DAY,  CURDATE() + INTERVAL 15 DAY),
(5,  2, 'Ghi chú chăm sóc', 'Khách mua máy tính bảng tháng trước, hẹn hỏi thăm mức độ hài lòng.',      NOW() - INTERVAL 8 DAY,  CURDATE() - INTERVAL 1 DAY),
(5,  1, 'Gọi điện',         'Tư vấn chọn máy tính bảng cho trẻ học online.',                           NOW() - INTERVAL 40 DAY, CURDATE() - INTERVAL 30 DAY),
(6,  3, 'Tin nhắn',         'Trả lời câu hỏi về chính sách đổi trả chuột không dây.',                  NOW() - INTERVAL 9 DAY,  NULL),
(7,  4, 'Gọi điện',         'Tư vấn tủ lạnh inverter, khách hẹn gọi lại hôm nay để chốt đơn.',         NOW() - INTERVAL 3 DAY,  CURDATE()),
(8,  5, 'Gặp mặt',          'Vệ sinh máy lạnh tại nhà, khách hài lòng.',                               NOW() - INTERVAL 25 DAY, NULL),
(9,  5, 'Tin nhắn',         'Xác nhận lịch kỹ thuật đến sửa máy giặt.',                                NOW() - INTERVAL 14 DAY, CURDATE() - INTERVAL 6 DAY),
(10, 3, 'Email',            'Xác nhận đơn đặt màn hình 27 inch online.',                               NOW() - INTERVAL 4 DAY,  CURDATE() + INTERVAL 4 DAY),
(11, 4, 'Khác',             'Khách để lại đánh giá 5 sao cho nồi cơm điện.',                           NOW() - INTERVAL 11 DAY, NULL),
(12, 1, 'Gặp mặt',          'Trao đổi báo giá 20 điện thoại cho nhân viên công ty khách.',             NOW() - INTERVAL 6 DAY,  CURDATE() + INTERVAL 30 DAY),
(12, 2, 'Email',            'Gửi báo giá sơ bộ.',                                                      NOW() - INTERVAL 12 DAY, CURDATE() - INTERVAL 6 DAY),
(14, 3, 'Gọi điện',         'Khách hỏi lịch về laptop gaming, hẹn báo lại.',                           NOW() - INTERVAL 1 DAY,  CURDATE() + INTERVAL 6 DAY),
(15, 4, 'Tin nhắn',         'Trả lời báo giá nồi chiên không dầu qua fanpage.',                        NOW() - INTERVAL 18 DAY, NULL),
(16, 5, 'Email',            'Gửi phiếu bảo hành điện tử cho điện thoại đã sửa.',                       NOW() - INTERVAL 14 DAY, NULL),
(18, 3, 'Gặp mặt',          'Khách mua bàn phím cơ, nhờ báo khi có switch mới.',                       NOW() - INTERVAL 2 HOUR, CURDATE() + INTERVAL 1 DAY),
(19, 4, 'Tin nhắn',         'Khách hỏi về trả góp máy lọc không khí.',                                 NOW() - INTERVAL 5 DAY,  NULL),
(21, 2, 'Ghi chú chăm sóc', 'Khách thích điện thoại màu pastel, báo khi có màu mới.',                  NOW() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 10 DAY),
(22, 3, 'Gọi điện',         'Gọi xác nhận địa chỉ giao laptop.',                                       NOW() - INTERVAL 2 DAY,  NULL),
(24, 5, 'Gặp mặt',          'Khách mang điện thoại vỡ màn hình đến thay, hẹn ngày trả máy.',           NOW() - INTERVAL 1 DAY,  CURDATE() + INTERVAL 3 DAY);

-- (Tùy chọn – least privilege) Tạo user MySQL riêng chỉ có quyền CRUD cho ứng dụng,
-- rồi sửa DB_USER/DB_PASS trong config/database.php:
-- CREATE USER 'crm_app'@'localhost' IDENTIFIED BY 'doi_mat_khau_nay';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON crm_giabao.* TO 'crm_app'@'localhost';
