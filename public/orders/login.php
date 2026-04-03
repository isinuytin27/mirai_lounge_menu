<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/../admin/lib/auth.php";
admin_start_session();

if (admin_is_logged_in()) {
    $next = (string)($_GET["next"] ?? "/orders/");
    if ($next === "" || !str_starts_with($next, "/") || str_starts_with($next, "//")) {
        $next = "/orders/";
    }
    header("Location: " . $next);
    exit;
}

$err = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = trim((string)($_POST["username"] ?? ""));
    $pass = (string)($_POST["password"] ?? "");
    if (admin_try_login($user, $pass)) {
        $next = (string)($_POST["next"] ?? "/orders/");
        if ($next === "" || !str_starts_with($next, "/") || str_starts_with($next, "//")) {
            $next = "/orders/";
        }
        header("Location: " . $next);
        exit;
    }
    $err = "Неверный логин или пароль";
}

$next = (string)($_GET["next"] ?? "/orders/");
if ($next === "" || !str_starts_with($next, "/") || str_starts_with($next, "//")) {
    $next = "/orders/";
}

require_once dirname(__DIR__) . "/inc/mirai_asset.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0a0a">
    <title>Заказы — вход</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="<?= htmlspecialchars(mirai_asset("assets/css/orders.css"), ENT_QUOTES, "UTF-8") ?>">
</head>
<body class="orders-page orders-login">
    <main class="orders-shell">
        <h1 class="orders-brand">Заказы зала</h1>
        <p class="orders-lead">Те же логин и пароль, что в админке меню.</p>
        <?php if ($err !== ""): ?>
            <div class="orders-flash orders-flash--err"><?= htmlspecialchars($err, ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>
        <form class="orders-form" method="post" action="">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, "UTF-8") ?>">
            <label class="orders-label">
                <span>Логин</span>
                <input class="orders-input" type="text" name="username" required autocomplete="username">
            </label>
            <label class="orders-label">
                <span>Пароль</span>
                <input class="orders-input" type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="orders-btn orders-btn--primary" type="submit">Войти</button>
        </form>
        <p class="orders-hint">Для уведомлений на iPhone: после входа откройте «Заказы», добавьте сайт на экран «Домой» и включите push.</p>
    </main>
</body>
</html>
