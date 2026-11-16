# Hệ thống quản lý khách hàng – Gia Bao Group

Bài tập lớn môn **Lập trình PHP cơ bản** – Đề tài 44. Sinh viên: Trần Gia Bảo – MSSV 233404050162 – Lớp Đ23HT1.

Ứng dụng web quản lý khách hàng cho cửa hàng điện máy – công nghệ nhỏ: hồ sơ khách hàng, nhóm khách hàng theo sản phẩm/dịch vụ quan tâm, lịch sử liên hệ và lịch hẹn chăm sóc. Có hai vai trò: **Admin** (toàn quyền) và **Nhân viên** (mỗi nhân viên phụ trách một nhóm khách hàng).

Công nghệ: PHP thuần (PDO, prepared statement), MySQL/MariaDB, HTML/CSS/JavaScript, Bootstrap 5.3 (đã tải sẵn trong `assets/vendor`, chạy được khi không có Internet). Không dùng framework, không cần Composer.

## 1. Yêu cầu

- XAMPP (hoặc WAMP) có **PHP 8.0 trở lên** và MySQL 5.7+/MariaDB 10.4+.
- Trình duyệt Chrome/Edge/Firefox bản mới.

## 2. Cài đặt trên XAMPP

1. Chép cả thư mục dự án vào `C:\xampp\htdocs\crm_giabao`.
2. Mở **XAMPP Control Panel**, bấm **Start** cho *Apache* và *MySQL*.
3. Import cơ sở dữ liệu, chọn một trong hai cách:
   - **phpMyAdmin:** vào `http://localhost/phpmyadmin` → tab **Import** → chọn file `sql/crm_giabao.sql` → **Import**. File tự tạo CSDL `crm_giabao`.
   - **Dòng lệnh:** `C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\crm_giabao\sql\crm_giabao.sql`
4. Nếu MySQL của bạn có mật khẩu hoặc user khác `root`, sửa 4 hằng số trong `config/database.php`.
5. Mở `http://localhost/crm_giabao/` và đăng nhập bằng tài khoản demo:

   | Tên đăng nhập | Mật khẩu | Vai trò / nhóm phụ trách |
   |---|---|---|
   | `admin` | `Admin@123` | Admin – toàn quyền |
   | `nvdienthoai` | `NhanVien@123` | Nguyễn Minh An – Điện thoại & máy tính bảng |
   | `nvlaptop` | `NhanVien@123` | Trần Quốc Bình – Laptop & phụ kiện máy tính |
   | `nvgiadung` | `NhanVien@123` | Lê Thu Cúc – Điện gia dụng |
   | `nvbaohanh` | `NhanVien@123` | Phạm Đức Dũng – Sửa chữa & bảo hành |

> Import lại `sql/crm_giabao.sql` bất kỳ lúc nào để khôi phục dữ liệu mẫu. Ngày trong dữ liệu mẫu được tính theo ngày import, nên dashboard luôn có đủ các trạng thái *Quá hạn / Hôm nay / Sắp tới*.

## 3. Hướng dẫn sử dụng

| Trang | Chức năng |
|---|---|
| **Tổng quan** | Tổng số khách hàng (kèm khách mới trong tháng), số khách *quá hạn* / *cần liên hệ hôm nay* / *sắp tới 7 ngày*, danh sách 10 lịch hẹn gần nhất, số khách theo từng nhóm. Bấm vào ô số liệu để mở danh sách đã lọc sẵn. |
| **Khách hàng** | Danh sách có phân trang (10 dòng/trang). Tìm theo tên, số điện thoại hoặc phần đầu email; lọc theo nhóm và trạng thái chăm sóc. Thêm, sửa, xem chi tiết, xóa (xóa cả lịch sử liên hệ của khách, có hộp thoại xác nhận). **Xuất CSV** xuất đúng danh sách đang lọc, mở được bằng Excel. |
| **Chi tiết khách hàng** | Thông tin hồ sơ, lịch chăm sóc hiện tại và toàn bộ lịch sử liên hệ theo thời gian (mới nhất ở trên). Nút **Ghi nhận liên hệ** để thêm lần liên hệ mới. |
| **Ghi nhận liên hệ** | Ô **Khách hàng** là ô tìm kiếm: gõ ít nhất 2 ký tự của tên hoặc số điện thoại rồi chọn trong danh sách gợi ý (tối đa 20; nhân viên chỉ thấy khách nhóm mình). Mở từ trang chi tiết khách thì khách được chọn sẵn. |
| **Lịch sử liên hệ** | Toàn bộ các lần liên hệ (phân trang) kèm người ghi nhận. Lọc theo khoảng ngày liên hệ và theo nhóm khách hàng / nhân viên phụ trách. Sửa/xóa từng bản ghi, ghi nhận liên hệ mới. |
| **Nhóm khách hàng** *(Admin)* | Thêm/sửa/xóa nhóm, chọn màu nhãn, xem nhân viên phụ trách. Không xóa được nhóm còn khách hàng hoặc nhân viên. |
| **Nhân viên** *(Admin)* | Thêm tài khoản nhân viên, sửa họ tên / tên đăng nhập / nhóm phụ trách, đặt lại mật khẩu, khóa hoặc mở khóa. Không xóa cứng để giữ tên người ghi nhận trong lịch sử liên hệ. |
| **Tên người dùng ▾ → Đổi mật khẩu** | Mọi tài khoản tự đổi mật khẩu của mình (tối thiểu 8 ký tự, có cả chữ và số). |

**Phân quyền:**

| Chức năng | Admin | Nhân viên |
|---|---|---|
| Xem danh sách, chi tiết, lịch sử liên hệ của mọi khách hàng; xuất CSV | ✔ | ✔ |
| Thêm khách hàng | ✔ (chọn nhóm bất kỳ) | ✔ (tự động vào nhóm mình) |
| Sửa / xóa khách hàng; ghi nhận / sửa / xóa liên hệ | ✔ mọi nhóm | ✔ chỉ khách thuộc nhóm mình |
| Chuyển khách hàng sang nhóm khác | ✔ | ✘ |
| Trang Tổng quan | Số liệu toàn cửa hàng | Số liệu nhóm mình |
| Quản lý nhóm khách hàng, quản lý nhân viên | ✔ | ✘ |

**Quy tắc lịch chăm sóc:** lịch hẹn hiện tại của một khách hàng là *ngày hẹn tiếp theo* của **lần liên hệ gần nhất**. Khi ghi nhận một lần liên hệ mới, lịch cũ được coi là đã xử lý và được thay bằng ngày hẹn mới (hoặc hết lịch nếu để trống).

| Nhãn | Ý nghĩa |
|---|---|
| Quá hạn (đỏ đậm) | Ngày hẹn đã qua |
| Hôm nay (vàng cam) | Ngày hẹn là hôm nay |
| Sắp tới (viền xanh navy) | Trong 7 ngày tới |
| Đã hẹn (viền xám) | Xa hơn 7 ngày |

## 4. Cấu trúc thư mục

```
config/database.php     Kết nối PDO (sửa thông tin CSDL tại đây)
includes/functions.php  Session, CSRF, phân quyền, hàm tiện ích, quy tắc lịch hẹn
includes/header.php     Đầu trang + thanh điều hướng + thông báo
includes/footer.php     Chân trang
admin/                  Các trang quản trị (dashboard, customers, contacts, groups, staff...)
assets/                 CSS giao diện, JS, logo, Bootstrap 5 bản local
sql/crm_giabao.sql      5 bảng (khóa chính/khóa ngoại, ràng buộc CHECK) + dữ liệu mẫu
tests/check.php         Tự kiểm tra logic lịch hẹn (chạy bằng dòng lệnh)
index.php, login.php, logout.php
```

## 5. Bảo mật đã áp dụng

- **SQL Injection:** mọi câu lệnh có dữ liệu người dùng đều dùng PDO prepared statement (`ATTR_EMULATE_PREPARES = false`).
- **XSS:** mọi dữ liệu xuất ra HTML đều đi qua `htmlspecialchars()` (hàm `e()`).
- **CSRF:** mọi form POST (kể cả đăng nhập, đăng xuất) có token ngẫu nhiên và được kiểm tra tập trung trong `includes/functions.php` bằng `hash_equals()`.
- **Mật khẩu:** lưu bằng `password_hash()`, kiểm tra bằng `password_verify()`.
- **Chống dò mật khẩu:** bảng `login_attempts` ghi các lần đăng nhập sai. Trong 15 phút, sai 5 lần cùng tên đăng nhập từ một IP, hoặc 20 lần bất kỳ từ một IP, thì tạm chặn đăng nhập. Khóa theo cặp tên + IP nên người khác không thể cố tình làm khóa tài khoản của bạn từ máy của họ.
- **Phiên đăng nhập:** `session_regenerate_id()` khi đăng nhập/đổi mật khẩu (chống session fixation); cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` khi chạy HTTPS); tự đăng xuất sau 30 phút không thao tác; mọi trang trong `admin/` gọi `require_login()`.
- **Phân quyền phía server:** thông tin tài khoản được đọc lại từ CSDL ở mỗi request, nên khóa tài khoản hoặc đổi nhóm có hiệu lực ngay. Trang Nhóm KH / Nhân viên gọi `require_admin()`. Câu lệnh xóa của nhân viên có sẵn điều kiện `id_nhom` của nhóm mình, form sửa kiểm tra `can_manage()`, và nhóm của khách do nhân viên tạo được gán phía server (không tin giá trị gửi lên). Ẩn nút trên giao diện chỉ để tiện dùng, không phải lớp bảo vệ.
- **Kiểm tra dữ liệu phía server:** độ dài, định dạng SĐT/email/ngày, giá trị thuộc danh sách cho phép, chuỗi UTF-8 hợp lệ. CSDL có thêm khóa ngoại, `UNIQUE` và `CHECK`.
- **Khác:** thư mục `config/`, `includes/`, `sql/`, `tests/` bị chặn truy cập trực tiếp qua `.htaccess`; lỗi hệ thống chỉ ghi log, không hiển thị chi tiết; header `X-Frame-Options: DENY`; CSV xuất ra được chống formula injection.
- **Least privilege (tùy chọn):** cuối file `sql/crm_giabao.sql` có sẵn lệnh tạo user MySQL chỉ có quyền SELECT/INSERT/UPDATE/DELETE.

## 6. Kiểm thử

- Bảng 15 ca kiểm thử và kết quả: xem `KIEM_THU.md`.
- Tự kiểm tra logic lịch hẹn (PHP và SQL phải phân loại giống nhau): `C:\xampp\php\php.exe tests\check.php` → in `OK`.

## 7. Xử lý sự cố

- **"Không thể kết nối cơ sở dữ liệu"**: kiểm tra MySQL đã Start, đã import file `.sql`, và thông tin trong `config/database.php`.
- **MySQL trong XAMPP không khởi động được**, log báo `Can't open and lock privilege tables` hoặc thiếu file `roles_mapping`: bảng hệ thống của MySQL bị hỏng. Tắt MySQL, sao lưu thư mục `C:\xampp\mysql\data`, chép đè thư mục `C:\xampp\mysql\backup\mysql` vào `C:\xampp\mysql\data\mysql`, rồi Start lại.
- **Ô ngày hiển thị dạng mm/dd/yyyy**: định dạng do ngôn ngữ của trình duyệt/Windows quyết định. Dữ liệu vẫn lưu và hiển thị trong bảng theo dạng dd/mm/yyyy.
