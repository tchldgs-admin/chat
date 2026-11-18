<?php
session_start();
require_once 'db.php';

// すでにログインしている場合は管理画面へリダイレクト
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (!headers_sent()) {
        header("Location: admin.php");
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=admin.php"></head><body><script>window.location.replace("admin.php");</script></body></html>';
    exit;
}

$error_message = '';

// ログインボタンが押されたときの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error_message = 'メールアドレスとパスワードを入力してください。';
    } else {
        // データベースからメールアドレスで管理者を検索
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        // パスワードの照合
        if ($admin && password_verify($password, $admin['password_hash'])) {
            // セッションIDを再生成（セキュリティ対策）
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $admin['email'];
            
            if (!headers_sent()) {
                header("Location: admin.php");
            }
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=admin.php"></head><body><script>window.location.replace("admin.php");</script></body></html>';
            exit;
        } else {
            $error_message = 'メールアドレスまたはパスワードが間違っています。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<title>TC Plus - ログイン</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<meta name="viewport" content="width=device-width,minimum-scale=1,maximum-scale=1">

<link rel="stylesheet" href="https://appimg.chatplus.jp/app/s/lib/bootstrap/3.3.7/css/bootstrap.min.css">
<style>
    body { background-color: #f7f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    #login_container { width: 100%; max-width: 380px; padding: 15px; margin: auto; }
    .form-signin-heading { text-align: center; margin-bottom: 30px; font-weight: bold; color: #29a3e5; font-size: 32px; letter-spacing: -1px; }
    .form-group { margin-bottom: 20px; }
    .form-control { height: 44px; font-size: 15px; }
    .btn-primary { background-color: #29a3e5; border-color: #29a3e5; height: 48px; font-size: 16px; font-weight: bold; margin-top: 10px;}
    .btn-primary:hover, .btn-primary:focus { background-color: #2188c4; border-color: #2188c4; }
    .alert { padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 20px; }
    ul.links { list-style: none; padding: 0; margin-top: 20px; text-align: center; }
    ul.links li a { color: #718096; font-size: 13px; text-decoration: none; }
    ul.links li a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div id="login_container">
    <form id="form-signin" action="" method="post" autocomplete="off">
        <h2 class="form-signin-heading">
            TC Plus
        </h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="control-label">メールアドレス</label>
            <input name="email" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="メールアドレスを入力してください" required autofocus>
        </div>
        <div class="form-group">
            <label class="control-label">パスワード</label>
            <input name="password" type="password" class="form-control" placeholder="パスワードを入力してください" required minlength="6">
        </div>
        
        <button class="btn btn-lg btn-primary btn-block" type="submit">ログイン</button>
        <ul class="links">
            <li><a href="#">パスワードを忘れた方</a></li>
        </ul>
    </form>
</div>
</body>
</html>