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

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (!headers_sent()) {
        header("Location: login.php");
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=login.php"></head><body><script>window.location.replace("login.php");</script></body></html>';
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'db.php';

$stmt_me = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
$stmt_me->execute([$_SESSION['admin_email'] ?? '']);
$me = $stmt_me->fetch();
if ($me) {
    $_SESSION['admin_name'] = !empty($me['name']) ? $me['name'] : explode('@', $me['email'])[0];
    $_SESSION['admin_role'] = !empty($me['role']) ? $me['role'] : 'admin';
}

$admin_name = $_SESSION['admin_name'] ?? '管理者';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$csrf_token = $_SESSION['csrf_token'];

if ($admin_role !== 'admin') {
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定 | 担当者管理 - TC Plus</title>
    <style>
        :root {
            --primary-blue: #29a3e5;
            --navy-btn: #283e5c;
            --navy-btn-hover: #1e2f47;
            --sidebar-bg: #f4f6f9;
            --border-color: #e2e8f0;
            --text-main: #333333;
            --text-sub: #718096;
            --bg-light: #f7fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; color: var(--text-main); font-size: 13px; background: #fff; }
        svg { vertical-align: middle; flex-shrink: 0; }

        /* ヘッダー周り */
        #top-header {
            height: 54px; background: #ffffff; border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
        }
        .header-left { display: flex; align-items: center; gap: 36px; height: 100%; }
        .brand-logo { display: flex; align-items: center; gap: 8px; font-size: 19px; font-weight: bold; color: var(--primary-blue); text-decoration: none; }
        .brand-logo .icon-box { background: var(--primary-blue); color: #fff; width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; }
        .top-nav { display: flex; height: 100%; list-style: none; gap: 6px; }
        .top-nav li a { display: flex; align-items: center; height: 100%; padding: 0 14px; text-decoration: none; color: #555; font-weight: bold; font-size: 14px; }
        .top-nav li a:hover { color: var(--primary-blue); }
        .top-nav li.disabled a { color: #a0aec0; cursor: default; }
        .header-right { display: flex; align-items: center; gap: 16px; color: #666; }
        .logout-link { color: #666; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e0; }
        .logout-link:hover { background: #f7fafc; color: #e53e3e; border-color: #feb2b2; }
        
        .layout-wrapper { display: flex; padding-top: 54px; min-height: 100vh; }
        
        /* 左サイドバー */
        .sidebar { width: 185px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding: 16px 0; position: fixed; top: 54px; bottom: 0; left: 0; overflow-y: auto; }
        .side-menu { list-style: none; margin-bottom: 20px;}
        .side-menu li a { display: flex; align-items: center; gap: 10px; padding: 10px 18px; color: #4a5568; text-decoration: none; font-size: 13px; }
        .side-menu li a:hover { background: #e8edf3; }
        .side-menu li.active a { background: #e2ecf7; color: #1a365d; font-weight: bold; border-left: 3px solid var(--primary-blue); }
        .side-menu svg { color: #718096; }
        .side-group-title { font-size: 11px; color: #a0aec0; font-weight: bold; padding: 0 18px; margin-bottom: 4px; margin-top: 16px;}

        /* メインエリア */
        .main-content { flex: 1; margin-left: 185px; padding: 24px 32px; background: var(--bg-light); min-height: calc(100vh - 54px);}
        .breadcrumb { font-size: 12px; color: var(--text-sub); margin-bottom: 8px; }
        .page-title-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-title { font-size: 22px; font-weight: bold; color: #2d3748; }
        .btn-primary { background: var(--primary-blue); color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;}
        .btn-primary:hover { background: #2188c4; }

        /* ユーザーリスト */
        .user-list { display: flex; flex-direction: column; gap: 12px; }
        .user-item { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; padding: 16px; display: flex; justify-content: space-between; align-items: center; transition: box-shadow 0.2s; }
        .user-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-color: #cbd5e0; }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-icon { width: 42px; height: 42px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #718096; }
        .user-details { display: flex; flex-direction: column; gap: 4px; }
        .user-name { font-size: 15px; font-weight: bold; color: #2d3748; display: flex; align-items: center; gap: 8px; }
        .user-email { font-size: 12px; color: #718096; display: flex; align-items: center; gap: 4px; }
        .badge-admin { background: #e53e3e; color: #fff; font-size: 10px; padding: 2px 7px; border-radius: 4px; font-weight: bold; }
        .badge-general { background: #718096; color: #fff; font-size: 10px; padding: 2px 7px; border-radius: 4px; font-weight: bold; }
        
        .user-actions { display: flex; gap: 8px; }
        .btn-action { background: #f7fafc; border: 1px solid #e2e8f0; color: #4a5568; width: 34px; height: 34px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;}
        .btn-action:hover { background: #e2e8f0; }
        .btn-edit:hover { color: var(--primary-blue); border-color: #bee3f8; background: #ebf8ff;}
        .btn-delete:hover { color: #e53e3e; border-color: #fed7d7; background: #fff5f5;}

        /* モーダル */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-dialog { background: #fff; width: 480px; max-width: 90vw; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 16px; color: #2d3748; }
        .modal-close { cursor: pointer; color: #a0aec0; display: inline-flex; }
        .modal-close:hover { color: #4a5568; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 13px; color: #4a5568; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 14px; background: #fff; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 2px rgba(41, 163, 229, 0.2); }
        .form-note { font-size: 11px; color: #718096; margin-top: 4px; line-height: 1.5; }
        .btn-cancel { background: #fff; border: 1px solid #cbd5e0; color: #4a5568; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-cancel:hover { background: #f7fafc; }
    </style>
</head>
<body>

<header id="top-header">
    <div class="header-left">
        <a href="admin.php" class="brand-logo">
            <span class="icon-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            </span>
            <span>TC Plus</span>
        </a>
        <ul class="top-nav">
            <li><a href="admin_bot.php">チャット</a></li>
            <li class="disabled"><a href="#" onclick="return false;">訪問者</a></li>
            <li class="disabled"><a href="#" onclick="return false;">履歴</a></li>
            <li><a href="admin.php">チケット</a></li>
            <li class="disabled"><a href="#" onclick="return false;">レポート</a></li>
        </ul>
    </div>
    <div class="header-right">
        <span style="font-size:12px; display:inline-flex; align-items:center; gap:4px; font-weight:bold; color:#333;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#718096"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            <span id="header-admin-name"><?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></span>
        </span>
        <a href="logout.php" class="logout-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            ログアウト
        </a>
    </div>
</header>

<div class="layout-wrapper">
    <aside class="sidebar">
        <div class="side-group-title">メインメニュー</div>
        <ul class="side-menu">
            <li>
                <a href="admin.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                    <span>チケット管理</span>
                </a>
            </li>
            <li>
                <a href="admin_bot.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                    <span>チャットボット設定</span>
                </a>
            </li>
        </ul>
        <div class="side-group-title">設定</div>
        <ul class="side-menu">
            <li>
                <a href="admin_sites.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    <span>サイト管理</span>
                </a>
            </li>
            <li class="active">
                <a href="admin_users.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    <span>担当者管理</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="breadcrumb">設定 | 担当者管理</div>
        <div class="page-title-area">
            <h1 class="page-title">担当者管理</h1>
            <button type="button" class="btn-primary" id="btn-add-user">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span>担当者を追加</span>
            </button>
        </div>

        <div class="user-list" id="user-list-container">
            <div style="padding: 20px; text-align: center; color: #718096;">読み込み中...</div>
        </div>
    </main>
</div>

<!-- ユーザー追加/編集モーダル -->
<div class="modal-overlay" id="modal-user">
    <div class="modal-dialog">
        <div class="modal-header">
            <span id="modal-title">担当者を追加</span>
            <span class="modal-close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </span>
        </div>
        <form id="form-user">
            <div class="modal-body">
                <input type="hidden" name="id" id="user-id" value="">
                
                <div class="form-group">
                    <label>担当者名（表示名）<span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="name" id="user-name" class="form-control" required placeholder="例: 山田 太郎">
                    <div class="form-note">※画面右上のアカウント表示や返信履歴に表示される名前です。</div>
                </div>

                <div class="form-group">
                    <label>メールアドレス（ログインID）<span style="color:#e53e3e;">*</span></label>
                    <input type="email" name="email" id="user-email" class="form-control" required placeholder="例: yamada@example.com">
                </div>

                <div class="form-group">
                    <label>権限<span style="color:#e53e3e;">*</span></label>
                    <select name="role" id="user-role" class="form-control">
                        <option value="admin">管理者（すべての機能・設定メニューが利用可能）</option>
                        <option value="general">一般（チケット管理のみ利用可能・設定メニューは非表示）</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>パスワード <span id="pass-req-mark" style="color:#e53e3e;">*</span></label>
                    <input type="password" name="password" id="user-password" class="form-control" minlength="6" placeholder="6文字以上の英数字">
                    <div class="form-note" id="pass-note"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel modal-close">キャンセル</button>
                <button type="submit" class="btn-primary">保存する</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = <?php echo json_encode($csrf_token); ?>;
    let usersData = [];

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }

    async function loadUsers() {
        try {
            const res = await fetch('admin_users_api.php?action=list', { cache: 'no-store' });
            if (res.status === 401) {
                window.location.replace('login.php');
                return;
            }
            if (res.status === 403) {
                window.location.replace('admin.php');
                return;
            }
            const data = await res.json();
            if (!data.success) {
                alert('エラー: ' + data.error);
                return;
            }

            usersData = data.users || [];
            const container = document.getElementById('user-list-container');
            container.innerHTML = usersData.map(u => {
                const roleBadge = (u.role === 'general')
                    ? '<span class="badge-general">一般</span>'
                    : '<span class="badge-admin">管理者</span>';

                return `
                <div class="user-item">
                    <div class="user-info">
                        <div class="user-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <div class="user-details">
                            <div class="user-name">${esc(u.name)} ${roleBadge}</div>
                            <div class="user-email">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                ${esc(u.email)}
                            </div>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button type="button" class="btn-action btn-edit" title="編集" data-id="${u.id}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        </button>
                        <button type="button" class="btn-action btn-delete" title="削除" data-id="${u.id}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                        </button>
                    </div>
                </div>`;
            }).join('');
        } catch (err) {
            console.error(err);
        }
    }

    document.getElementById('btn-add-user').addEventListener('click', function() {
        document.getElementById('form-user').reset();
        document.getElementById('user-id').value = '';
        document.getElementById('user-role').value = 'admin';
        document.getElementById('modal-title').textContent = '担当者を追加';
        document.getElementById('user-password').required = true;
        document.getElementById('pass-req-mark').style.display = 'inline';
        document.getElementById('pass-note').textContent = '';
        document.getElementById('modal-user').classList.add('open');
    });

    document.addEventListener('click', async function(e) {
        const editBtn = e.target.closest('.btn-edit');
        if (editBtn) {
            const targetId = String(editBtn.dataset.id);
            const user = usersData.find(u => String(u.id) === targetId);
            if (!user) return;

            document.getElementById('form-user').reset();
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-name').value = user.name || '';
            document.getElementById('user-email').value = user.email || '';
            document.getElementById('user-role').value = user.role || 'admin';
            document.getElementById('modal-title').textContent = '担当者を編集';
            document.getElementById('user-password').required = false;
            document.getElementById('pass-req-mark').style.display = 'none';
            document.getElementById('pass-note').textContent = '※変更する場合のみ入力してください。空欄の場合は現在のパスワードが維持されます。';
            document.getElementById('modal-user').classList.add('open');
            return;
        }

        const delBtn = e.target.closest('.btn-delete');
        if (delBtn) {
            if (!confirm('この担当者を削除してもよろしいですか？\n※この操作は取り消せません。')) return;
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'delete');
            fd.append('id', delBtn.dataset.id);
            const res = await fetch('admin_users_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                loadUsers();
            } else {
                alert(data.error);
            }
            return;
        }

        const closeBtn = e.target.closest('.modal-close');
        if (closeBtn) {
            closeBtn.closest('.modal-overlay').classList.remove('open');
        }
    });

    document.getElementById('form-user').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'save');
        const res = await fetch('admin_users_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || '保存に失敗しました');
        }
    });

    loadUsers();
});
</script>
</body>
</html>