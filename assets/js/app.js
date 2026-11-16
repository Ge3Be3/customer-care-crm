// Form có data-confirm="..." sẽ hỏi xác nhận trước khi gửi (dùng cho các nút xóa).
document.addEventListener('submit', function (event) {
    var message = event.target.dataset.confirm;
    if (message && !confirm(message)) {
        event.preventDefault();
    }
});

// Ô tìm khách hàng (form ghi nhận liên hệ): lấy gợi ý từ customer_search.php,
// khi chọn đúng một gợi ý thì ghi id khách vào ô ẩn id_khach_hang.
var customerSearch = document.getElementById('customer_search');
if (customerSearch) {
    var options = document.getElementById('customer_options');
    var customerId = document.getElementById('id_khach_hang');
    var idByLabel = {};
    idByLabel[customerSearch.value] = customerId.value; // khách được chọn sẵn khi mở form
    var timer;

    customerSearch.addEventListener('input', function () {
        var text = customerSearch.value;
        customerId.value = idByLabel[text] || '';
        clearTimeout(timer);
        if (text.length < 2 || customerId.value) {
            return;
        }
        timer = setTimeout(function () {
            fetch('customer_search.php?q=' + encodeURIComponent(text))
                .then(function (response) { return response.json(); })
                .then(function (customers) {
                    options.replaceChildren();
                    customers.forEach(function (customer) {
                        var option = document.createElement('option');
                        option.value = customer.label; // gán thuộc tính, không dùng innerHTML → không bị XSS
                        options.appendChild(option);
                        idByLabel[customer.label] = customer.id;
                    });
                })
                .catch(function () {}); // mất phiên đăng nhập hoặc mạng lỗi: server vẫn kiểm tra khi gửi form
        }, 250);
    });
}
