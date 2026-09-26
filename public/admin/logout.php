<?php
require dirname(__DIR__) . '/app/admin_bootstrap.php';

if (is_post() && admin_csrf_ok()) {
    admin_logout();
}
redirect('/admin/login.php');
