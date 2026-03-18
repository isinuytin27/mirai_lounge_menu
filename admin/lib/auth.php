<?php
declare(strict_types=1);

function admin_config(): array
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    return $cfg;
}

function admin_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function admin_is_logged_in(): bool
{
    admin_start_session();
    return !empty($_SESSION["admin_logged_in"]);
}

function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header("Location: /admin/login.php");
        exit;
    }
}

function admin_try_login(string $username, string $password): bool
{
    $cfg = admin_config();
    $users = $cfg["admin"]["users"] ?? [];
    $expectedPass = is_array($users) ? (string)($users[$username] ?? "") : "";

    if ($expectedPass !== "" && hash_equals($expectedPass, $password)) {
        admin_start_session();
        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_username"] = $username;
        return true;
    }

    return false;
}

function admin_logout(): void
{
    admin_start_session();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

