<?php
// 本番環境用設定：画面へのエラー詳細表示をオフにする
ini_set('display_errors', 0);
error_reporting(0);

// データベースの接続情報（ロリポップの接続情報を入力してください）
$dsn = 'mysql:dbname=LAA1420323-chat;host=mysql403.phy.lolipop.lan;charset=utf8mb4';
$user = 'LAA1420323';
$password = 'Cuyv9532';

try {
    // データベースに接続
    $pdo = new PDO($dsn, $user, $password);
    
    // エラーが起きたら例外を投げる設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // データを連想配列（名前付きの箱）として取り出す設定
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 本番環境では詳細なエラー内容を表示せず、一般的なメッセージのみ出力
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    exit("現在システムメンテナンス中です。しばらく経ってから再度お試しください。");
}
?>