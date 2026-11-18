<?php
require_once 'db.php';

try {
    // 3. 管理者（admins）テーブルを作成
    $sql_admins = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql_admins);
    echo "管理者テーブル (admins) を作成しました。<br>";

    // デフォルトの管理者アカウントを作成（すでに存在する場合は無視）
    // メールアドレス: admin@example.com
    // パスワード: password123
    $default_email = 'admin@example.com';
    $default_password = password_hash('password123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
    $stmt->execute([$default_email]);
    if ($stmt->fetchColumn() == 0) {
        $stmt_insert = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
        $stmt_insert->execute([$default_email, $default_password]);
        echo "デフォルト管理者アカウントを作成しました。<br>（ID: admin@example.com / PASS: password123）<br>";
    }

    echo "<b>セットアップが完了しました！このファイル(setup.php)はサーバーから削除してください。</b>";

} catch (PDOException $e) {
    echo "テーブル作成エラー: " . $e->getMessage();
}
?>