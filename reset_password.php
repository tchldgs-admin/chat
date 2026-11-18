<?php
session_start();
date_default_timezone_set('Asia/Tokyo');
require_once 'db.php';

$token = $_GET['token'] ?? '';
$error_message = '';
$is_invalid_token = false;
$is_completed = false;
$admin = null;

// トークンの検証
if ($token === '') {
    $is_invalid_token = true;
} else {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE reset_token = ?");
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if (!$admin || empty($admin['reset_expires']) || strtotime($admin['reset_expires']) < time()) {
        $is_invalid_token = true;
    }
}

// 新しいパスワードが送信されたときの処理
if (!$is_invalid_token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['password_confirm'] ?? '';

    if ($new_password === '' || strlen($new_password) < 6) {
        $error_message = '新しいパスワードは6文字以上で入力してください。';
    } elseif ($new_password !== $confirm_password) {
        $error_message = '確認用パスワードが一致しません。';
    } else {
        // パスワードを暗号化して更新し、トークンを無効化（使い捨てにする）
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE admins SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $upd->execute([$hash, $admin['id']]);

        $is_completed = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<title>TC Plus - 新しいパスワードの設定</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<meta name="viewport" content="width=device-width,minimum-scale=1,maximum-scale=1">
<link rel="stylesheet" href="https://appimg.chatplus.jp/app/s/lib/bootstrap/3.3.7/css/bootstrap.min.css">
<style>
    body { background-color: #f7f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", sans-serif; }
    #login_container { width: 100%; max-width: 440px; padding: 25px; margin: auto; }
    .form-signin-heading { text-align: center; margin-bottom: 20px; font-weight: bold; color: #29a3e5; font-size: 32px; letter-spacing: -1px; }
    h3 { text-align: center; font-size: 20px; font-weight: bold; color: #333; margin: 15px 0 10px; }
    .summary { margin-bottom: 25px; font-size: 14px; text-align: center; color: #555; }
    .required { color: #e53e3e; margin-left: 4px; }
    .form-group { margin-bottom: 20px; }
    .form-control { height: 44px; font-size: 15px; }
    .btn-primary { background-color: #29a3e5; border-color: #29a3e5; height: 48px; font-size: 16px; font-weight: bold; margin-top: 10px; }
    .btn-primary:hover, .btn-primary:focus { background-color: #2188c4; border-color: #2188c4; }
    .alert { padding: 12px; border-radius: 4px; font-size: 13px; margin-bottom: 20px; line-height: 1.6; }
    ul.links { list-style: none; padding: 0; margin-top: 22px; text-align: center; }
    ul.links li a { color: #718096; font-size: 13px; text-decoration: none; }
    ul.links li a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div id="login_container">
    <h2 class="form-signin-heading">TC Plus</h2>

    <?php if ($is_completed): ?>
        <h3>パスワードを変更しました</h3>
        <div class="alert alert-success">
            新しいパスワードの設定が完了しました。<br>
            下のボタンからログイン画面へ進み、新しいパスワードでログインしてください。
        </div>
        <a href="login.php" class="btn btn-lg btn-primary btn-block">ログイン画面へ</a>

    <?php elseif ($is_invalid_token): ?>
        <h3>無効なURLです</h3>
        <div class="alert alert-danger">
            このパスワード再設定URLは有効期限（30分）が切れているか、すでに使用されています。<br>
            お手数ですが、最初からやり直してください。
        </div>
        <ul class="links">
            <li><a href="reminder.php">パスワード再設定メールを再送信する</a></li>
            <li style="margin-top:10px;"><a href="login.php">&laquo;&nbsp;ログインに戻る</a></li>
        </ul>

    <?php else: ?>
        <form action="" method="POST" autocomplete="off">
            <h3>新しいパスワードの設定</h3>
            <p class="summary"><?php echo htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8'); ?> の新しいパスワードを入力してください。</p>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="control-label">新しいパスワード（6文字以上）<span class="required">※</span></label>
                <input name="password" type="password" class="form-control" placeholder="新しいパスワードを入力してください" required minlength="6" autofocus>
            </div>

            <div class="form-group">
                <label class="control-label">新しいパスワード（確認用）<span class="required">※</span></label>
                <input name="password_confirm" type="password" class="form-control" placeholder="もう一度同じパスワードを入力してください" required minlength="6">
            </div>

            <button class="btn btn-lg btn-primary btn-block" type="submit">パスワードを変更する</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>