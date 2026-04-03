<?php
declare(strict_types=1);

require_once __DIR__ . "/lib/auth.php";

admin_start_session();

$error = "";

$next = trim((string)($_GET["next"] ?? ""));
if ($next !== "" && (!str_starts_with($next, "/") || str_starts_with($next, "//"))) {
    $next = "";
}

if (admin_is_logged_in()) {
    header("Location: " . ($next !== "" ? $next : "/admin/dashboard.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim((string)($_POST["username"] ?? ""));
    $password = (string)($_POST["password"] ?? "");
    $postNext = trim((string)($_POST["next"] ?? ""));
    if ($postNext !== "" && str_starts_with($postNext, "/") && !str_starts_with($postNext, "//")) {
        $next = $postNext;
    }

    if (admin_try_login($username, $password)) {
        header("Location: " . ($next !== "" ? $next : "/admin/dashboard.php"));
        exit;
    }

    $error = "Неверный логин или пароль";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirai Admin — Вход</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <h1 class="title">Админ‑панель</h1>
            <span class="muted">Mirai Lounge</span>
        </div>

        <div class="card">
            <h2>Вход</h2>

            <?php if ($error !== ""): ?>
                <div class="err"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?php if ($next !== ""): ?>
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, "UTF-8") ?>">
                <?php endif; ?>
                <label for="username">Логин</label>
                <input id="username" name="username" type="text" placeholder="admin" required>

                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" placeholder="••••••••" required>

                <div class="spacer-h-12"></div>
                <button class="btn" type="submit">Войти</button>

                <div class="spacer-h-12"></div>
                <div class="hint">
                    Пароль сейчас хранится в <code>config/config.php</code> (временно). После первого входа — поменяйте его.
                </div>
            </form>
        </div>
    </div>
</body>
</html>

