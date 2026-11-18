<?php
// エラーを表示する設定（開発中のみ。本番では消します）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// データベースの接続情報（ロリポップでメモした内容に書き換えてください）
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
    echo "データベース接続失敗: " . $e->getMessage();
    exit;
}
?>