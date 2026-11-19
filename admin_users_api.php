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

// adminsテーブルにname（担当者名）とrole（権限）カラムがなければ自動追加
try {
    $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('name', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT '' AFTER id");
        $pdo->exec("UPDATE admins SET name = SUBSTRING_INDEX(email, '@', 1) WHERE name = ''");
    }
    if (!in_array('role', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER password_hash");
    }
} catch (Exception $e) {
    error_log("Admins Table Migration Error: " . $e->getMessage());
}

// 最新の自分の権限を確認（一般権限はアクセス不可）
$current_email = $_SESSION['admin_email'] ?? '';
$stmt_me = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
$stmt_me->execute([$current_email]);
$me = $stmt_me->fetch();
$my_role = $me['role'] ?? ($_SESSION['admin_role'] ?? 'admin');

if ($my_role !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'この操作を行う権限がありません。']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM admins ORDER BY id ASC");
        $users = $stmt->fetchAll();
        foreach ($users as &$u) {
            if (empty($u['name'])) {
                $u['name'] = explode('@', $u['email'])[0];
            }
            if (empty($u['role'])) {
                $u['role'] = 'admin';
            }
        }
        unset($u);

        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }

    if ($action === 'save') {
        $id       = trim($_POST['id'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = ($_POST['role'] ?? 'admin') === 'general' ? 'general' : 'admin';
        $password = $_POST['password'] ?? '';

        if ($name === '') {
            throw new Exception('担当者名は必須です。');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('正しいメールアドレスを入力してください。');
        }

        if ($id === '') {
            // 新規追加
            if ($password === '' || strlen($password) < 6) {
                throw new Exception('新規追加時は6文字以上のパスワードが必須です。');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('このメールアドレスは既に登録されています。');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $role]);

        } else {
            // 編集（更新）
            $stmt_old = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_user = $stmt_old->fetch();
            if (!$old_user) {
                throw new Exception('対象の担当者が見つかりません。');
            }

            // 自分以外の重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('このメールアドレスは他の担当者が使用しています。');
            }

            // 管理者から一般へ変更する場合、他に管理者が最低1人残るかチェック
            if (($old_user['role'] ?? 'admin') === 'admin' && $role === 'general') {
                $admin_cnt = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn();
                if ($admin_cnt <= 1) {
                    throw new Exception('管理者は最低1名必要なため、この担当者を「一般」に変更できません。');
                }
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new Exception('パスワードは6文字以上で入力してください。');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hash, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $id]);
            }

            // ログイン中の自分自身を編集した場合はセッション情報も即時更新
            if ($me && (int)$me['id'] === (int)$id) {
                $_SESSION['admin_name']  = $name;
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_role']  = $role;
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = trim($_POST['id'] ?? '');

        if ($me && (int)$me['id'] === (int)$id) {
            throw new Exception('現在ログイン中の自分自身は削除できません。');
        }

        $stmt_target = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt_target->execute([$id]);
        $target = $stmt_target->fetch();

        if ($target && ($target['role'] ?? 'admin') === 'admin') {
            $admin_cnt = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn();
            if ($admin_cnt <= 1) {
                throw new Exception('最後の管理者は削除できません。');
            }
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
?>