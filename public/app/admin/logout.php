<?php
defined('PS_APP') || exit;

if (is_post() && admin_csrf_ok()) {
    admin_logout();
}
redirect(admin_url('prihlasenie'));
