<?php
require __DIR__ . '/includes/functions.php';

redirect(current_user() ? 'admin/dashboard.php' : 'login.php');
