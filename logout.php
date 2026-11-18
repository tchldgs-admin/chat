<?php
session_start();

// セッション変数をすべて解除
$_SESSION = array();

// セッションクッキーも削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破棄
session_destroy();

// login.php へリダイレクト
if (!headers_sent()) {
    header("Location: login.php");
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=login.php">
    <title>ログアウト中...</title>
</head>
<body>
    <script>window.location.replace("login.php");</script>
    <p>ログアウトしました。<a href="login.php">ログイン画面へ移動します</a></p>
</body>
</html>
<?php exit; ?>