<?php
require __DIR__ . '/includes/functions.php'; // POST đã được kiểm tra CSRF ở đây

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$_SESSION = [];
session_regenerate_id(true);
flash('success', 'Bạn đã đăng xuất.');
redirect('login.php');
