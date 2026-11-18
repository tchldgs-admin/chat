<?php
require_once 'db.php';

// 言語と文字コードの設定（メールの文字化け防止）
mb_language("Japanese");
mb_internal_encoding("UTF-8");

$is_submitted = false;
$error_message = '';
$ticket_url = '';

// フォームが送信されたときの処理
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
            // トランザクション開始（チケットとメッセージの保存をセットで行う）
            $pdo->beginTransaction();

            // お客様専用の合言葉（ランダムな32文字の英数字）を生成
            $secret_key = bin2hex(random_bytes(16));

            // 1. ticketsテーブルに保存（ステータス 11 = 未対応）
            $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, status) VALUES (?, ?, ?, ?, 11)");
            $stmt->execute([$secret_key, $name, $email, $subject]);
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

            // 3. お客様へ受付完了メールを送信
            $from_email = "no-reply@cc-circle.net";
            $from_name  = "サポートデスク";
            $mail_subject = "【受付完了】お問い合わせありがとうございます（チケットID: #{$ticket_id}）";
            $mail_body = "{$name} 様\n\n"
                       . "お問い合わせを受け付けました。\n"
                       . "担当者より回答いたしますので、今しばらくお待ちください。\n\n"
                       . "■お問い合わせ内容の確認・追加の返信はこちら\n"
                       . "{$ticket_url}\n\n"
                       . "※このメールは送信専用アドレスから配信されています。\n"
                       . "ご返信いただいてもお答えできませんので、上記URLよりご連絡ください。\n"
                       . "--------------------------------------------------\n"
                       . "件名：{$subject}\n"
                       . "内容：\n{$body}\n"
                       . "--------------------------------------------------";

            $headers = "From: " . mb_encode_mimeheader($from_name) . " <{$from_email}>";
            mb_send_mail($email, $mail_subject, $mail_body, $headers);

            $is_submitted = true;

        } catch (Exception $e) {
            $pdo->rollBack();
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
        </div>
    <?php else: ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="">
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