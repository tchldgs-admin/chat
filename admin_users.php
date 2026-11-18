<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (!headers_sent()) {
        header("Location: login.php");
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=login.php"></head><body><script>window.location.replace("login.php");</script></body></html>';
    exit;
}
$admin_email = $_SESSION['admin_email'] ?? '管理者';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定 | 担当者管理 - TC Plus</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
        body { font-family: "Helvetica Neue", Arial, sans-serif; color: var(--text-main); font-size: 13px; background: #fff; }

        /* ヘッダー周り */
        #top-header {
            height: 54px; background: #ffffff; border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
        }
        .brand-logo { display: flex; align-items: center; gap: 8px; font-size: 19px; font-weight: bold; color: var(--primary-blue); text-decoration: none; }
        .brand-logo .icon-box { background: var(--primary-blue); color: #fff; width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; }
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
        .side-menu .material-icons-round { font-size: 18px; color: #718096; }
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
        .user-icon { width: 42px; height: 42px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 24px;}
        .user-details { display: flex; flex-direction: column; gap: 4px; }
        .user-name { font-size: 15px; font-weight: bold; color: #2d3748; }
        .user-email { font-size: 12px; color: #718096; display: flex; align-items: center; gap: 4px; }
        .badge-admin { background: #e53e3e; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 8px; font-weight: normal; vertical-align: middle;}
        
        .user-actions { display: flex; gap: 8px; }
        .btn-action { background: #f7fafc; border: 1px solid #e2e8f0; color: #4a5568; width: 34px; height: 34px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;}
        .btn-action:hover { background: #e2e8f0; }
        .btn-edit:hover { color: var(--primary-blue); border-color: #bee3f8; background: #ebf8ff;}
        .btn-delete:hover { color: #e53e3e; border-color: #fed7d7; background: #fff5f5;}
        .btn-action .material-icons-round { font-size: 18px; }

        /* モーダル */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-dialog { background: #fff; width: 480px; max-width: 90vw; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 16px; color: #2d3748; }
        .modal-close { cursor: pointer; color: #a0aec0; }
        .modal-close:hover { color: #4a5568; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 13px; color: #4a5568; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 14px; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 2px rgba(41, 163, 229, 0.2); }
        .form-note { font-size: 11px; color: #718096; margin-top: 4px; }
        .btn-cancel { background: #fff; border: 1px solid #cbd5e0; color: #4a5568; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-cancel:hover { background: #f7fafc; }
    </style>
</head>
<body>

<header id="top-header">
    <div class="header-left">
        <a href="admin.php" class="brand-logo">
            <span class="icon-box"><span class="material-icons-round">forum</span></span>
            <span>TC Plus</span>
        </a>
    </div>
    <div class="header-right">
        <span style="font-size:12px;"><span class="material-icons-round" style="font-size:18px; vertical-align:middle;">account_circle</span> <?php echo htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="logout.php" class="logout-link"><span class="material-icons-round" style="font-size:15px;">logout</span>ログアウト</a>
    </div>
</header>

<div class="layout-wrapper">
    <aside class="sidebar">
        <div class="side-group-title">メインメニュー</div>
        <ul class="side-menu">
            <li><a href="admin.php"><span class="material-icons-round">list_alt</span>チケット管理</a></li>
        </ul>
        <div class="side-group-title">設定</div>
        <ul class="side-menu">
            <li class="active"><a href="admin_users.php"><span class="material-icons-round">manage_accounts</span>担当者管理</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="breadcrumb">設定 | 担当者管理</div>
        <div class="page-title-area">
            <h1 class="page-title">担当者管理</h1>
            <button type="button" class="btn-primary" id="btn-add-user">
                <span class="material-icons-round" style="font-size:18px;">person_add</span>担当者を追加
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
            <span class="material-icons-round modal-close">close</span>
        </div>
        <form id="form-user">
            <div class="modal-body">
                <input type="hidden" name="id" id="user-id" value="">
                
                <div class="form-group">
                    <label>メールアドレス（ログインID）<span style="color:#e53e3e;">*</span></label>
                    <input type="email" name="email" id="user-email" class="form-control" required placeholder="例: yamada@example.com">
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
$(function() {
    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }

    function loadUsers() {
        $.getJSON('admin_users_api.php', { action: 'list' }, function(res) {
            if (!res.success) {
                alert('エラー: ' + res.error);
                return;
            }
            
            const $container = $('#user-list-container').empty();
            res.users.forEach(function(u) {
                const html = `
                <div class="user-item">
                    <div class="user-info">
                        <div class="user-icon"><span class="material-icons-round">person</span></div>
                        <div class="user-details">
                            <div class="user-name">${esc(u.email.split('@')[0])} <span class="badge-admin">管理者</span></div>
                            <div class="user-email">
                                <span class="material-icons-round" style="font-size:14px;">mail</span> ${esc(u.email)}
                            </div>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button type="button" class="btn-action btn-edit" title="編集" data-id="${u.id}" data-email="${esc(u.email)}">
                            <span class="material-icons-round">edit</span>
                        </button>
                        <button type="button" class="btn-action btn-delete" title="削除" data-id="${u.id}">
                            <span class="material-icons-round">delete</span>
                        </button>
                    </div>
                </div>`;
                $container.append(html);
            });
        }).fail(function(xhr) {
            if (xhr.status === 401) {
                window.location.href = 'login.php';
            }
        });
    }

    $('#btn-add-user').on('click', function() {
        $('#form-user')[0].reset();
        $('#user-id').val('');
        $('#modal-title').text('担当者を追加');
        $('#user-password').prop('required', true);
        $('#pass-req-mark').show();
        $('#pass-note').text('');
        $('#modal-user').addClass('open');
    });

    $(document).on('click', '.btn-edit', function() {
        $('#form-user')[0].reset();
        $('#user-id').val($(this).data('id'));
        $('#user-email').val($(this).data('email'));
        $('#modal-title').text('担当者を編集');
        $('#user-password').prop('required', false);
        $('#pass-req-mark').hide();
        $('#pass-note').text('※変更する場合のみ入力してください。空欄の場合は現在のパスワードが維持されます。');
        $('#modal-user').addClass('open');
    });

    $(document).on('click', '.btn-delete', function() {
        if (!confirm('この担当者を削除してもよろしいですか？\n※この操作は取り消せません。')) return;
        
        $.post('admin_users_api.php', { action: 'delete', id: $(this).data('id') }, function(res) {
            if (res.success) {
                loadUsers();
            } else {
                alert(res.error);
            }
        }, 'json');
    });

    $('#form-user').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serializeArray();
        data.push({name: 'action', value: 'save'});
        
        $.post('admin_users_api.php', data, function(res) {
            if (res.success) {
                $('#modal-user').removeClass('open');
                loadUsers();
            } else {
                alert(res.error);
            }
        }, 'json');
    });

    $('.modal-close').on('click', function() {
        $(this).closest('.modal-overlay').removeClass('open');
    });

    loadUsers();
});
</script>
</body>
</html>