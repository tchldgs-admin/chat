<?php
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_https,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");

if (empty($_SESSION['view_csrf_token'])) {
    $_SESSION['view_csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'db.php';

$ticket_id  = $_GET['id'] ?? '';
$secret_key = $_GET['key'] ?? '';

// チケットIDと合言葉が一致するデータを取得
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND secret_key = ?");
$stmt->execute([$ticket_id, $secret_key]);
$ticket = $stmt->fetch();

if (!$ticket) {
    exit('無効なURLか、チケットが見つかりません。');
}

// メンテナンスモードの確認
$config_file = __DIR__ . '/config.json';
$is_maintenance = false;
if (file_exists($config_file)) {
    $loaded_cfg = json_decode(file_get_contents($config_file), true);
    if (is_array($loaded_cfg)) {
        $is_maintenance = !empty($loaded_cfg['maintenance_mode']);
    }
}

$info_message  = '';
$error_message = '';

// お客様が追加返信を送信したときの処理（メンテナンス中は受け付けない）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['view_csrf_token'], $posted_token)) {
        $error_message = '不正なリクエストが検出されました。画面を再読み込みしてもう一度お試しください。';
    } elseif ($is_maintenance) {
        $error_message = '現在メンテナンス中のため、送信を受け付けることができません。';
    } else {
        $reply_body = trim($_POST['reply_body'] ?? '');
        if ($reply_body !== '') {
            $pdo->beginTransaction();
            
            // 1. メッセージを追加
            $stmt_msg = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, 'customer', ?)");
            $stmt_msg->execute([$ticket_id, $reply_body]);

            // 2. お客様から返信があったので、ステータスを「11（未対応）」に戻し、未読フラグ(is_read=0)と最終更新日時を更新
            $columns = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('is_read', $columns)) {
                $stmt_upd = $pdo->prepare("UPDATE tickets SET status = 11, is_read = 0, updated_at = NOW() WHERE id = ?");
            } else {
                $stmt_upd = $pdo->prepare("UPDATE tickets SET status = 11, updated_at = NOW() WHERE id = ?");
            }
            $stmt_upd->execute([$ticket_id]);

            $pdo->commit();
            
            header("Location: view.php?id=" . urlencode($ticket_id) . "&key=" . urlencode($secret_key) . "&sent=1");
            exit;
        }
    }
}

if (isset($_GET['sent'])) {
    $info_message = '追加の返信を送信しました。担当者からの回答をお待ちください。';
}

// メッセージ履歴を取得（社内メモ 'memo' はお客様に見せないように除外。sender_name もお客様側には表示しない）
$stmt_msgs = $pdo->prepare("SELECT id, ticket_id, sender_type, body, created_at FROM messages WHERE ticket_id = ? AND sender_type != 'memo' ORDER BY created_at ASC");
$stmt_msgs->execute([$ticket_id]);
$messages = $stmt_msgs->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ詳細 (#<?php echo htmlspecialchars($ticket['id'], ENT_QUOTES, 'UTF-8'); ?>)</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", sans-serif; background: #f7fafc; color: #2d3748; margin: 0; padding: 20px; }
        .container { max-width: 750px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); padding: 24px; }
        .header { border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; }
        .ticket-meta { font-size: 13px; color: #718096; margin-bottom: 6px; }
        h1 { font-size: 20px; margin: 8px 0 0; }
        .message-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
        .msg-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; }
        .msg-card.customer { background: #fff; border-left: 4px solid #a0aec0; }
        .msg-card.support { background: #ebf8ff; border-left: 4px solid #3182ce; }
        .msg-header { display: flex; justify-content: space-between; font-size: 13px; color: #4a5568; margin-bottom: 10px; font-weight: bold; }
        .msg-body { white-space: pre-wrap; line-height: 1.6; font-size: 15px; }
        .reply-box { border-top: 2px solid #e2e8f0; padding-top: 20px; }
        textarea { width: 100%; height: 120px; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; box-sizing: border-box; margin-bottom: 12px; }
        .btn-reply { background: #3182ce; color: #fff; border: none; padding: 10px 24px; font-size: 15px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        .btn-reply:hover { background: #2b6cb0; }
        .alert-info { background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; font-weight: bold; }
        .maintenance-notice {
            background: #fffaf0; border: 1px solid #fbd38d; color: #9c4221;
            padding: 18px 20px; border-radius: 6px; font-size: 14px; line-height: 1.7; text-align: center;
        }
        .maintenance-notice strong { display: block; font-size: 16px; margin-bottom: 6px; color: #7b341e; }
    </style>
</head>
<body>
<div class="container">
    <?php if ($info_message): ?>
        <div class="alert-info"><?php echo htmlspecialchars($info_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="header">
        <div class="ticket-meta">
            チケットID: #<?php echo htmlspecialchars($ticket['id'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <h1><?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <div class="message-list">
        <?php foreach ($messages as $msg): ?>
            <div class="msg-card <?php echo htmlspecialchars($msg['sender_type'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="msg-header">
                    <span><?php echo $msg['sender_type'] === 'support' ? 'サポート担当' : htmlspecialchars($ticket['customer_name'], ENT_QUOTES, 'UTF-8') . ' 様'; ?></span>
                    <span style="font-weight: normal; color: #718096;"><?php echo htmlspecialchars($msg['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="msg-body"><?php echo htmlspecialchars($msg['body'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="reply-box">
        <?php if ($is_maintenance): ?>
            <div class="maintenance-notice">
                <strong>現在メンテナンス中です</strong>
                ただいまシステムメンテナンスを実施しているため、回答および追加のご連絡の受付を一時休止しております。<br>
                ご不便をおかけいたしますが、メンテナンス終了後に改めてご利用くださいますようお願いいたします。
            </div>
        <?php else: ?>
            <h3 style="margin-top:0; font-size:16px;">このお問い合わせ（#<?php echo htmlspecialchars($ticket['id'], ENT_QUOTES, 'UTF-8'); ?>）に追加のメッセージを送る</h3>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['view_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <textarea name="reply_body" required placeholder="追加の返信内容をご入力ください"></textarea>
                <button type="submit" class="btn-reply">返信を送信する</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>