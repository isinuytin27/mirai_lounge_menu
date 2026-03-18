<?php
declare(strict_types=1);

require_once __DIR__ . "/lib/auth.php";

if (admin_is_logged_in()) {
    header("Location: /admin/dashboard.php");
    exit;
}

header("Location: /admin/login.php");
exit;

