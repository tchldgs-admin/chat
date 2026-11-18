<?php
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

$info_message = '';

// お客様が追加返信を送信したときの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

if (isset($_GET['sent'])) {
    $info_message = '追加の返信を送信しました。担当者からの回答をお待ちください。';
}

// メッセージ履歴を取得（社内メモ 'memo' はお客様に見せないように除外）
$stmt_msgs = $pdo->prepare("SELECT * FROM messages WHERE ticket_id = ? AND sender_type != 'memo' ORDER BY created_at ASC");
$stmt_msgs->execute([$ticket_id]);
$messages = $stmt_msgs->fetchAll();

// ステータスの表示名
$status_labels = [
    11 => ['label' => '受付済（対応待ち）', 'color' => '#e53e3e'],
    12 => ['label' => '対応中', 'color' => '#3182ce'],
    13 => ['label' => '保留中', 'color' => '#d69e2e'],
    21 => ['label' => '解決済', 'color' => '#38a169'],
    31 => ['label' => 'クローズ', 'color' => '#718096'],
];
$st = $status_labels[$ticket['status']] ?? ['label' => '確認中', 'color' => '#718096'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせ詳細 (#<?php echo htmlspecialchars($ticket['id']); ?>)</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", sans-serif; background: #f7fafc; color: #2d3748; margin: 0; padding: 20px; }
        .container { max-width: 750px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); padding: 24px; }
        .header { border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; }
        .ticket-meta { font-size: 13px; color: #718096; margin-bottom: 6px; }
        .status-badge { display: inline-block; color: #fff; font-size: 12px; font-weight: bold; padding: 3px 10px; border-radius: 12px; margin-left: 8px; }
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
        .new-ticket-note { margin-top: 16px; font-size: 12px; color: #718096; }
        .new-ticket-note a { color: #3182ce; }
    </style>
</head>
<body>
<div class="container">
    <?php if ($info_message): ?>
        <div class="alert-info"><?php echo htmlspecialchars($info_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="header">
        <div class="ticket-meta">
            チケットID: #<?php echo htmlspecialchars($ticket['id']); ?>
            <span class="status-badge" style="background: <?php echo $st['color']; ?>;"><?php echo $st['label']; ?></span>
        </div>
        <h1><?php echo htmlspecialchars($ticket['subject']); ?></h1>
    </div>

    <div class="message-list">
        <?php foreach ($messages as $msg): ?>
            <div class="msg-card <?php echo htmlspecialchars($msg['sender_type']); ?>">
                <div class="msg-header">
                    <span><?php echo $msg['sender_type'] === 'support' ? 'サポート担当' : htmlspecialchars($ticket['customer_name']) . ' 様'; ?></span>
                    <span style="font-weight: normal; color: #718096;"><?php echo htmlspecialchars($msg['created_at']); ?></span>
                </div>
                <div class="msg-body"><?php echo htmlspecialchars($msg['body']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="reply-box">
        <h3 style="margin-top:0; font-size:16px;">このお問い合わせ（#<?php echo htmlspecialchars($ticket['id']); ?>）に追加のメッセージを送る</h3>
        <form method="post" action="">
            <textarea name="reply_body" required placeholder="追加の返信内容をご入力ください"></textarea>
            <button type="submit" class="btn-reply">返信を送信する</button>
        </form>
        <div class="new-ticket-note">
            ※別の新しいお問い合わせをされる場合は、<a href="form.php">新規お問い合わせフォーム</a>よりご連絡ください。
        </div>
    </div>
</div>
</body>
</html>