<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list') {
        // 全担当者を取得（パスワードハッシュは返さない）
        $stmt = $pdo->query("SELECT id, email, created_at FROM admins ORDER BY id ASC");
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }

    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '') {
            throw new Exception('メールアドレスは必須です。');
        }

        if ($id === '') {
            // 新規追加
            if ($password === '') {
                throw new Exception('新規追加時はパスワードが必須です。');
            }
            
            // メールアドレスの重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('このメールアドレスは既に登録されています。');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$email, $hash]);

        } else {
            // 編集（更新）
            // 自分以外の重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('このメールアドレスは他の担当者が使用しています。');
            }

            if ($password !== '') {
                // パスワードも変更する場合
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET email = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$email, $hash, $id]);
            } else {
                // パスワードは変更しない場合
                $stmt = $pdo->prepare("UPDATE admins SET email = ? WHERE id = ?");
                $stmt->execute([$email, $id]);
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        // 最低1人は残すためのチェック（最後の1人を消すと誰もログインできなくなるため）
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        if ($stmt->fetchColumn() <= 1) {
            throw new Exception('最後の担当者は削除できません。');
        }

        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}