<?php
require_once 'db.php';

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// データベースの自動連番（AUTO_INCREMENT）設定のチェック・修復
try {
    $id_col = $pdo->query("SHOW COLUMNS FROM tickets WHERE Field = 'id'")->fetch();
    if ($id_col && stripos($id_col['Extra'] ?? '', 'auto_increment') === false) {
        $pdo->exec("SET @t_seq = 0");
        $pdo->exec("UPDATE tickets SET id = (@t_seq := @t_seq + 1) ORDER BY created_at ASC");
        $has_pk = !empty($pdo->query("SHOW KEYS FROM tickets WHERE Key_name = 'PRIMARY'")->fetch());
        if ($has_pk) {
            $pdo->exec("ALTER TABLE tickets MODIFY id INT NOT NULL AUTO_INCREMENT");
        } else {
            $pdo->exec("ALTER TABLE tickets MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }
    }

    $msg_id_col = $pdo->query("SHOW COLUMNS FROM messages WHERE Field = 'id'")->fetch();
    if ($msg_id_col && stripos($msg_id_col['Extra'] ?? '', 'auto_increment') === false) {
        $pdo->exec("SET @m_seq = 0");
        $pdo->exec("UPDATE messages SET id = (@m_seq := @m_seq + 1) ORDER BY created_at ASC");
        $has_msg_pk = !empty($pdo->query("SHOW KEYS FROM messages WHERE Key_name = 'PRIMARY'")->fetch());
        if ($has_msg_pk) {
            $pdo->exec("ALTER TABLE messages MODIFY id INT NOT NULL AUTO_INCREMENT");
        } else {
            $pdo->exec("ALTER TABLE messages MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }
    }
} catch (Exception $e) {
    error_log("DB Structure Check Error: " . $e->getMessage());
}

// 設定ファイル（config.json）の読み込み
$config_file = __DIR__ . '/config.json';
$default_config = [
    'from_email' => 'no-reply@cc-circle.net',
    'from_name'  => 'サポートデスク',
    'signature'  => "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------",
    'site_name'  => 'メインサイト'
];
$config = $default_config;
if (file_exists($config_file)) {
    $loaded = json_decode(file_get_contents($config_file), true);
    if (is_array($loaded)) {
        $config = array_merge($default_config, $loaded);
    }
}

// 共通メール送信関数（差出人名・-fオプション対応）
function send_tc_mail($to, $subject, $body, $from_email, $from_name) {
    $encoded_name = mb_encode_mimeheader($from_name, "UTF-8", "B", "\n");
    $headers  = "From: {$encoded_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit";

    $additional_params = '';
    if (filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $additional_params = "-f" . $from_email;
    }
    return mb_send_mail($to, $subject, $body, $headers, $additional_params);
}

$is_submitted = false;
$error_message = '';
$ticket_url = '';

// フォームが送信されたときの処理（毎回必ず新規チケットを発行）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['customer_name'] ?? '');
    $email   = trim($_POST['customer_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');

    if ($name === '' || $email === '' || $subject === '' || $body === '') {
        $error_message = 'すべての項目を入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = '正しいメールアドレスを入力してください。';
    } else {
        try {
            $pdo->beginTransaction();

            // お客様専用の合言葉（ランダムな32文字の英数字）を生成
            $secret_key = bin2hex(random_bytes(16));
            $site_name  = $config['site_name'] ?? 'メインサイト';

            // 1. ticketsテーブルに新規レコードを挿入（必ず新しいチケットIDを発行）
            $columns = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('site_name', $columns)) {
                $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, site_name, status, is_read) VALUES (?, ?, ?, ?, ?, 11, 0)");
                $stmt->execute([$secret_key, $name, $email, $subject, $site_name]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, status) VALUES (?, ?, ?, ?, 11)");
                $stmt->execute([$secret_key, $name, $email, $subject]);
            }
            $ticket_id = $pdo->lastInsertId();

            // 2. messagesテーブルに1通目の内容を保存
            $stmt_msg = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, 'customer', ?)");
            $stmt_msg->execute([$ticket_id, $body]);

            $pdo->commit();

            // お客様専用の確認・返信URLを組み立てる
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket_id . "&key=" . $secret_key;

            // 3. お客様へ受付完了メールを送信（設定画面の差出人名・メール・署名を反映）
            $from_email = $config['from_email'];
            $from_name  = $config['from_name'];
            $signature  = $config['signature'];

            $mail_subject = "【受付完了】お問い合わせありがとうございます（チケットID: #{$ticket_id}）";
            $mail_body = "{$name} 様\n\n"
                       . "お問い合わせを受け付けました。\n"
                       . "担当者より回答いたしますので、今しばらくお待ちください。\n\n"
                       . "■お問い合わせ内容の確認・追加の返信はこちら\n"
                       . "{$ticket_url}\n\n"
                       . "--------------------------------------------------\n"
                       . "件名：{$subject}\n"
                       . "内容：\n{$body}\n"
                       . "--------------------------------------------------\n\n"
                       . $signature;

            send_tc_mail($email, $mail_subject, $mail_body, $from_email, $from_name);

            $is_submitted = true;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = '送信中にエラーが発生しました：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせフォーム</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", sans-serif; background: #fff; color: #333; margin: 0; padding: 20px; }
        .form-container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 14px; }
        .badge-req { background: #e53e3e; color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 3px; margin-left: 6px; }
        input[type="text"], input[type="email"], textarea {
            width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; box-sizing: border-box;
        }
        input:focus, textarea:focus { outline: none; border-color: #2b6cb0; box-shadow: 0 0 0 2px rgba(43,108,176,0.2); }
        textarea { height: 140px; resize: vertical; }
        .btn-submit {
            background: #2b6cb0; color: #fff; border: none; padding: 12px 24px; font-size: 16px; font-weight: bold;
            border-radius: 4px; cursor: pointer; width: 100%; transition: background 0.2s;
        }
        .btn-submit:hover { background: #2c5282; }
        .alert-error { background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; }
        .complete-box { background: #ebf8ff; border: 1px solid #90cdf4; padding: 24px; border-radius: 6px; text-align: center; }
        .complete-box h2 { color: #2b6cb0; margin-top: 0; font-size: 20px; }
        .url-box { background: #fff; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; word-break: break-all; margin: 16px 0; font-size: 14px; }
        .btn-another { display: inline-block; margin-top: 12px; font-size: 13px; color: #2b6cb0; text-decoration: underline; }
    </style>
</head>
<body>
<div class="form-container">
    <?php if ($is_submitted): ?>
        <div class="complete-box">
            <h2>お問い合わせを受け付けました</h2>
            <p>ご入力いただいたメールアドレス宛に受付完了メールをお送りしました。</p>
            <p style="font-size: 14px; color: #4a5568;">サポートからの返信の確認や、追加のご連絡は以下の専用ページから行えます。</p>
            <div class="url-box">
                <a href="<?php echo htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                    <?php echo htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
            <a href="form.php" class="btn-another">別のお問い合わせを送る</a>
        </div>
    <?php else: ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="form.php">
            <div class="form-group">
                <label>お名前<span class="badge-req">必須</span></label>
                <input type="text" name="customer_name" required placeholder="例：山田 太郎" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>メールアドレス<span class="badge-req">必須</span></label>
                <input type="email" name="customer_email" required placeholder="例：taro@example.com" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>件名<span class="badge-req">必須</span></label>
                <input type="text" name="subject" required placeholder="例：ログイン方法について" value="<?php echo htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>お問い合わせ内容<span class="badge-req">必須</span></label>
                <textarea name="body" required placeholder="お問い合わせ内容をご記入ください"><?php echo htmlspecialchars($_POST['body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="btn-submit">この内容で送信する</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>