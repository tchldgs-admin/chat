<?php
require_once 'db.php'; // さっき作ったdb.phpを読み込む

try {
    // 1. チケット（親）のテーブルを作成
    $sql_tickets = "CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,          -- チケットID
        secret_key VARCHAR(64) NOT NULL,            -- お客様用の合言葉URL
        customer_name VARCHAR(255) NOT NULL,        -- お客様名
        customer_email VARCHAR(255) NOT NULL,       -- メールアドレス
        subject VARCHAR(255) NOT NULL,              -- 件名
        status INT DEFAULT 11,                      -- ステータス (11:未対応, 12:対応中, 21:解決済)
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 作成日時
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- 最終更新日時
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql_tickets);
    echo "チケットテーブル (tickets) を作成しました。<br>";

    // 2. メッセージ（子）のテーブルを作成
    $sql_messages = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,          -- メッセージID
        ticket_id INT NOT NULL,                     -- どのチケットIDへの返信か
        sender_type VARCHAR(50) NOT NULL,           -- 'customer' か 'support' か 'memo'
        body TEXT NOT NULL,                         -- メッセージ本文
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 送信日時
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql_messages);
    echo "メッセージテーブル (messages) を作成しました。<br>";

    echo "<b>セットアップが完了しました！このファイル(setup.php)はサーバーから削除してください。</b>";

} catch (PDOException $e) {
    echo "テーブル作成エラー: " . $e->getMessage();
}
?>