<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once dirname(__DIR__) . "/../admin/lib/auth.php";
admin_start_session();

if (!admin_is_logged_in()) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "auth"], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "method"], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents("php://input");
$sub = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($sub) || trim((string)($sub["endpoint"] ?? "")) === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "bad_subscription"], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . "/inc/mirai_push.php";
mirai_push_save_subscription($sub);

echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
