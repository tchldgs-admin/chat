<?php
session_start();
date_default_timezone_set('Asia/Tokyo');
require_once 'db.php';

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// すでにログインしている場合は管理画面へリダイレクト
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (!headers_sent()) {
        header("Location: admin.php");
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=admin.php"></head><body><script>window.location.replace("admin.php");</script></body></html>';
    exit;
}

// adminsテーブルにパスワード再設定用のカラムがなければ自動追加
try {
    $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reset_token', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('reset_expires', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN reset_expires DATETIME DEFAULT NULL");
    }
} catch (Exception $e) {
    // すでに存在する場合は無視
}

// 差出人設定をconfig.jsonから読み込み（なければデフォルト値）
$from_email = 'no-reply@cc-circle.net';
$from_name  = 'TC Plus サポート';
$config_file = __DIR__ . '/config.json';
if (file_exists($config_file)) {
    $cfg = json_decode(file_get_contents($config_file), true);
    if (!empty($cfg['from_email'])) $from_email = $cfg['from_email'];
    if (!empty($cfg['from_name']))  $from_name  = $cfg['from_name'];
}

$error_message = '';
$is_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error_message = 'メールアドレスを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'メールアドレスを正しく入力してください。';
    } else {
        // 登録されているメールアドレスか確認
        $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            // 64文字のランダムな合言葉（トークン）と30分後の有効期限を生成
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // データベースに保存
            $upd = $pdo->prepare("UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $upd->execute([$token, $expires, $admin['id']]);

            // 再設定ページのURLを組み立てる
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $reset_url = $protocol . $domain . $dir . "/reset_password.php?token=" . $token;

            // メール送信
            $subject = "【TC Plus】パスワード再設定のご案内";
            $body = "TC Plus をご利用いただきありがとうございます。\n\n"
                  . "パスワードの再設定リクエストを受け付けました。\n"
                  . "以下のURLをクリックして、30分以内に新しいパスワードを設定してください。\n\n"
                  . "■パスワード再設定URL\n"
                  . "{$reset_url}\n\n"
                  . "※このURLの有効期限は30分間です。\n"
                  . "※心当たりがない場合は、このメールを破棄してください（パスワードは変更されません）。\n"
                  . "--------------------------------------------------\n"
                  . "{$from_name}";

            $headers = "From: " . mb_encode_mimeheader($from_name) . " <{$from_email}>";
            mb_send_mail($admin['email'], $subject, $body, $headers);
        }

        // セキュリティ上、未登録アドレスの場合でも同じ完了画面を表示する
        $is_sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<title>TC Plus - パスワード再設定</title>
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
    <form id="form-signin" action="" method="POST" autocomplete="off">
        <h2 class="form-signin-heading">TC Plus</h2>

        <?php if ($is_sent): ?>
            <h3>メールを送信しました</h3>
            <div class="alert alert-info">
                ご入力いただいたメールアドレス宛に、パスワード再設定用のURLをお送りしました。<br>
                メールをご確認のうえ、<strong>30分以内</strong>に新しいパスワードを設定してください。<br><br>
                <span style="font-size:12px; color:#555;">※メールが届かない場合は、入力されたメールアドレスに誤りがあるか、迷惑メールフォルダに振り分けられている可能性があります。</span>
            </div>
            <ul class="links">
                <li><a href="login.php">&laquo;&nbsp;ログインに戻る</a></li>
            </ul>
        <?php else: ?>
            <h3>パスワードをお忘れですか？</h3>
            <p class="summary">パスワード再設定手順をメール送信します。</p>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="inputEmail" class="control-label">メールアドレス<span class="required">※</span></label>
                <input name="email" type="email" id="inputEmail" class="form-control" placeholder="メールアドレスを入力してください" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <button class="btn btn-lg btn-primary btn-block" type="submit">メール送信</button>

            <ul class="links">
                <li><a href="login.php">&laquo;&nbsp;ログインに戻る</a></li>
            </ul>
        <?php endif; ?>
    </form>
</div>
</body>
</html>