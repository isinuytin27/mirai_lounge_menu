<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/../admin/lib/auth.php";
admin_logout();
header("Location: /orders/login.php");
exit;
