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

require_once 'db.php';

// adminsテーブルにname・roleカラムがなければ自動追加し、現在のユーザー情報を最新化
try {
    $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('name', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT '' AFTER id");
        $pdo->exec("UPDATE admins SET name = SUBSTRING_INDEX(email, '@', 1) WHERE name = ''");
    }
    if (!in_array('role', $cols)) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER password_hash");
    }

    $stmt_me = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt_me->execute([$_SESSION['admin_email'] ?? '']);
    $me = $stmt_me->fetch();
    if ($me) {
        $_SESSION['admin_name'] = !empty($me['name']) ? $me['name'] : explode('@', $me['email'])[0];
        $_SESSION['admin_role'] = !empty($me['role']) ? $me['role'] : 'admin';
    }
} catch (Exception $e) {
    error_log("Admin Sync Error: " . $e->getMessage());
}

$admin_name = $_SESSION['admin_name'] ?? '管理者';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$is_admin   = ($admin_role === 'admin');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チケット - TC Plus</title>
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
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; color: var(--text-main); background: #ffffff; font-size: 13px; }
        svg { vertical-align: middle; flex-shrink: 0; }

        /* 上部ヘッダー */
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
        .top-nav li.active a { color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); }
        .top-nav li.disabled a { color: #a0aec0; cursor: default; }
        .ticket-badge { background: #e53e3e; color: #fff; font-size: 11px; font-weight: bold; padding: 1px 6px; border-radius: 10px; margin-left: 5px; }
        
        .header-right { display: flex; align-items: center; gap: 16px; color: #666; }
        .form-link-btn { font-size: 12px; color: var(--primary-blue); text-decoration: none; border: 1px solid var(--primary-blue); padding: 4px 10px; border-radius: 4px; }
        .form-link-btn:hover { background: #ebf8ff; }
        .logout-link { color: #666; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e0; }
        .logout-link:hover { background: #f7fafc; color: #e53e3e; border-color: #feb2b2; }

        .layout-wrapper { display: flex; padding-top: 54px; min-height: 100vh; }

        /* 左サイドバー */
        .sidebar { width: 185px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding: 16px 0; position: fixed; top: 54px; bottom: 0; left: 0; overflow-y: auto; }
        .side-menu { list-style: none; }
        .side-menu li a { display: flex; align-items: center; gap: 10px; padding: 10px 18px; color: #4a5568; text-decoration: none; font-size: 13px; transition: background 0.15s; }
        .side-menu li a:hover { background: #e8edf3; }
        .side-menu li.active a { background: #e2ecf7; color: #1a365d; font-weight: bold; border-left: 3px solid var(--primary-blue); }
        .side-menu svg { color: #718096; }
        .side-menu li.active svg { color: #29a3e5; }
        .side-divider { border: none; border-top: 1px solid #dce3ec; margin: 12px 14px; }
        .side-buttons { padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
        .btn-navy { background: var(--navy-btn); color: #fff; border: none; padding: 9px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; }
        .btn-navy:hover { background: var(--navy-btn-hover); }
        .side-group-title { font-size: 11px; color: #a0aec0; font-weight: bold; padding: 0 18px; margin-bottom: 4px; margin-top: 16px; }

        /* メインコンテンツ */
        .main-content { flex: 1; margin-left: 185px; padding: 20px 28px; background: #fff; }
        .breadcrumb { font-size: 11px; color: var(--text-sub); margin-bottom: 4px; }
        .page-title { font-size: 20px; font-weight: bold; margin-bottom: 16px; color: #2d3748; }

        /* 検索ボックス（本家デザイン風） */
        .search-box { border: 1px solid var(--border-color); border-radius: 4px; margin-bottom: 14px; }
        .search-box .content-basic { background: #fff; padding: 12px 14px; }
        .search-box .content-detail { background: #f8fafc; padding: 12px 14px; border-top: 1px dashed #cbd5e0; display: none; }
        .search-box.detail_shown .content-detail { display: block; }
        
        .search-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .form-control { height: 32px; padding: 4px 10px; border: 1px solid #cbd5e0; border-radius: 3px; font-size: 12px; background: #fff; }
        .form-control:focus { outline: none; border-color: var(--primary-blue); }
        
        .status-filters { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; font-size: 12px; }
        .status-filters label { display: flex; align-items: center; gap: 4px; cursor: pointer; }
        
        .search-footer { display: flex; justify-content: flex-end; align-items: center; padding: 10px 14px; background: #f8fafc; border-top: 1px solid #e2e8f0; gap: 8px; }
        .detail-toggle { color: #4a5568; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 2px; user-select: none; text-decoration: none; }
        .detail-toggle:hover { text-decoration: underline; }
        .btn-search { background: var(--navy-btn); color: #fff; border: none; height: 32px; padding: 0 14px; border-radius: 3px; font-size: 12px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
        .btn-clear { background: #fff; color: #4a5568; border: 1px solid #cbd5e0; height: 32px; width: 32px; border-radius: 3px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }

        /* ツールバー */
        .list-toolbar { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 12px; }

        /* チケット一覧テーブル */
        .ticket-table-wrap { border-top: 1px solid var(--border-color); }
        
        .ticket-header-row { 
            display: grid; grid-template-columns: 34px 28px 1fr 150px 120px 100px 120px 90px; 
            align-items: center; background: #f8fafc; border-bottom: 1px solid var(--border-color); 
            padding: 9px 8px; font-weight: bold; color: #4a5568; font-size: 12px; 
        }
        .sort-col { cursor: pointer; display: flex; align-items: center; gap: 2px; user-select: none; }
        .sort-col:hover { color: var(--primary-blue); }

        .bulk-bar { display: none; background: #ebf8ff; grid-column: 1 / -1; align-items: center; gap: 10px; }
        .bulk-bar.active { display: flex; }

        .ticket-item { border-bottom: 1px solid var(--border-color); transition: background 0.1s; }
        .ticket-item.alert-warning { background: #fefcbf; }
        .ticket-item.alert-danger { background: #fed7d7; }
        .ticket-row { 
            display: grid; grid-template-columns: 34px 28px 1fr 150px 120px 100px 120px 90px; 
            align-items: center; padding: 10px 8px; cursor: pointer; 
        }
        .ticket-row:hover { background: rgba(41, 163, 229, 0.08); }
        .ticket-item.expanded .ticket-row { background: #edf2f7; font-weight: bold; }
        
        .ticket-item.unread .col-subject { font-weight: bold; }
        .unread-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: transparent; cursor: pointer; }
        .ticket-item.unread .unread-dot { background: var(--primary-blue); }
        
        .col-subject { display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 12px; color: #2b6cb0; }
        .col-subject:hover .subject-text { text-decoration: underline; }
        
        .mail-icon { color: #718096; }
        .subject-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .col-visitor { display: flex; align-items: center; gap: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px; }
        .col-assignee, .col-elapsed, .col-site { color: #4a5568; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .col-id { color: #4a5568; font-size: 12px; text-align: right; padding-right: 8px; }
        
        .status-pill { font-size: 10px; padding: 1px 6px; border-radius: 3px; font-weight: bold; color: #fff; flex-shrink: 0; }
        .st-11 { background: #e53e3e; }
        .st-12 { background: #3182ce; }
        .st-13 { background: #d69e2e; }
        .st-21 { background: #38a169; }
        .st-31 { background: #718096; }

        /* スレッド詳細（本家風インライン展開） */
        .ticket-detail-panel { display: none; background: #f9fbfd; border-top: 1px solid #cbd5e0; padding: 18px 24px 24px 42px; }
        .ticket-item.expanded .ticket-detail-panel { display: block; }
        
        .detail-meta-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 10px; }
        .meta-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        
        .thread-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
        .event-card { background: #fff; border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden; }
        .event-card.memo { background: #fefcbf; border-color: #f6e05e; }
        .event-card.support { border-left: 4px solid var(--primary-blue); }
        .event-card.customer { border-left: 4px solid #a0aec0; }
        .event-header { padding: 9px 14px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: rgba(0,0,0,0.015); }
        .event-header:hover { background: rgba(0,0,0,0.03); }
        .event-sender { font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .event-preview { color: #718096; font-weight: normal; font-size: 12px; margin-left: 8px; }
        .event-body { padding: 14px; border-top: 1px solid #edf2f7; white-space: pre-wrap; line-height: 1.65; font-size: 13px; }
        .event-card.collapsed .event-body { display: none; }
        .event-card:not(.collapsed) .event-preview { display: none; }

        /* 返信エディタボックス */
        .reply-editor-box { background: #fff; border: 1px solid #cbd5e0; border-radius: 4px; padding: 14px; }
        .reply-tabs { display: flex; gap: 8px; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
        .reply-tab { padding: 5px 12px; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 12px; border: 1px solid transparent; background: #edf2f7; color: #4a5568; }
        .reply-tab.active[data-type="support"] { background: var(--primary-blue); color: #fff; }
        .reply-tab.active[data-type="memo"] { background: #ecc94b; color: #744210; }
        .reply-to-info { font-size: 12px; color: #4a5568; margin-bottom: 8px; }
        .reply-textarea { width: 100%; height: 130px; padding: 10px; border: 1px solid #cbd5e0; border-radius: 3px; font-size: 13px; font-family: inherit; resize: vertical; }
        .reply-textarea.memo-mode { background: #fffff0; border-color: #ecc94b; }
        .reply-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        
        .btn-send { background: var(--primary-blue); color: #fff; border: none; padding: 7px 20px; border-radius: 3px; font-weight: bold; cursor: pointer; }
        .btn-send:hover { background: #2188c4; }
        .btn-update-only { background: #edf2f7; color: #2d3748; border: 1px solid #cbd5e0; padding: 7px 14px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 6px; display: inline-flex; align-items: center; gap: 4px; }

        /* モーダル */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-dialog { background: #fff; width: 540px; max-width: 94vw; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 15px; }
        .modal-body { padding: 18px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 12px 18px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 8px; }
        .m-group { margin-bottom: 12px; }
        .m-group label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 12px; }
        .m-group input, .m-group select, .m-group textarea { width: 100%; }
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
            <li class="disabled"><a href="#" onclick="return false;">チャット</a></li>
            <li class="disabled"><a href="#" onclick="return false;">訪問者</a></li>
            <li class="disabled"><a href="#" onclick="return false;">履歴</a></li>
            <li class="active"><a href="admin.php">チケット<span id="header-badge" class="ticket-badge" style="display:none;">0</span></a></li>
            <li class="disabled"><a href="#" onclick="return false;">レポート</a></li>
        </ul>
    </div>
    <div class="header-right">
        <?php if ($is_admin): ?>
            <a href="admin_sites.php" class="form-link-btn">フォーム埋め込み・サイト管理 ↗</a>
        <?php endif; ?>
        <span style="font-size:12px; display:inline-flex; align-items:center; gap:4px; font-weight:bold; color:#333;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#718096"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <a href="logout.php" class="logout-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            ログアウト
        </a>
    </div>
</header>

<div class="layout-wrapper">
    <!-- 左サイドバー -->
    <aside class="sidebar">
        <div class="side-group-title">メインメニュー</div>
        <ul class="side-menu" id="side-status-menu">
            <li class="active" data-status="active" data-title="一覧">
                <a href="#">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                    <span>一覧</span>
                </a>
            </li>
            <li class="status-11" data-status="11" data-title="未対応">
                <a href="#">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2l.01 6L10 12l-3.99 4.01L6 22h12v-6l-4-4 4-3.99V2H6zm10 14.5V20H8v-3.5l4-4 4 4z"/></svg>
                    <span>未対応</span>
                </a>
            </li>
            <li data-status="12" data-title="対応中">
                <a href="#">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                    <span>対応中</span>
                </a>
            </li>
            <li data-status="13" data-title="保留中">
                <a href="#">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                    <span>保留中</span>
                </a>
            </li>
            <hr class="side-divider">
            <li data-status="21" data-title="解決済">
                <a href="#">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    <span>解決済</span>
                </a>
            </li>
            <li data-status="31" data-title="ゴミ箱">
                <a href="#">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    <span>ゴミ箱</span>
                </a>
            </li>
        </ul>
        <div class="side-buttons">
            <button type="button" class="btn-navy" id="btn-open-create">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                <span>チケットを追加</span>
            </button>
            <?php if ($is_admin): ?>
            <button type="button" class="btn-navy" id="btn-open-settings">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span>チケット設定</span>
            </button>
            <?php endif; ?>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="side-group-title">設定</div>
        <ul class="side-menu">
            <li>
                <a href="admin_sites.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    <span>サイト管理</span>
                </a>
            </li>
            <li>
                <a href="admin_users.php">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    <span>担当者管理</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </aside>

    <!-- メインエリア -->
    <main class="main-content">
        <div class="breadcrumb">チケット</div>
        <h1 class="page-title" id="current-view-title">一覧</h1>

        <!-- 検索フォーム -->
        <form id="search-form" class="search-box">
            <div class="content-basic">
                <div class="search-row">
                    <div style="display:flex; align-items:center; gap:4px;">
                        <input type="date" name="start_date" class="form-control" title="作成日 始点">
                        <span>〜</span>
                        <input type="date" name="end_date" class="form-control" title="作成日 終点">
                    </div>

                    <select name="site_name" id="filter-site" class="form-control" style="width:140px;">
                        <option value="">サイト</option>
                    </select>

                    <select name="assignee" id="filter-assignee" class="form-control" style="width:140px;">
                        <option value="">担当者</option>
                        <option value="unassigned">(未割り当て)</option>
                    </select>

                    <select name="category" id="filter-category" class="form-control" style="width:140px;">
                        <option value="">カテゴリ</option>
                    </select>

                    <input type="text" name="word" class="form-control" style="width:200px;" placeholder="フリーワード検索">
                </div>
            </div>
            
            <div class="content-detail" id="search-detail-area">
                <div class="status-filters">
                    <label><input type="checkbox" name="search-status[]" value="11" class="status-cb" checked> 未対応</label>
                    <label><input type="checkbox" name="search-status[]" value="12" class="status-cb" checked> 対応中</label>
                    <label><input type="checkbox" name="search-status[]" value="13" class="status-cb" checked> 保留中</label>
                    <label><input type="checkbox" name="search-status[]" value="21" class="status-cb"> 解決済</label>
                    <label><input type="checkbox" name="search-status[]" value="31" class="status-cb"> ゴミ箱</label>
                </div>
                <div class="search-row" style="margin-bottom:12px;">
                    <label style="display:flex; align-items:center; gap:4px; cursor:pointer;">
                        <input type="checkbox" name="unread_only" value="1"> 未読のみ
                    </label>
                    <select name="search-rate" class="form-control" style="width:120px; margin-left:12px;" disabled>
                        <option value="">評価</option>
                        <option value="0">未評価</option>
                        <option value="1">よかった</option>
                        <option value="2">悪かった</option>
                    </select>
                </div>
                <div class="search-row">
                    <input type="number" name="ticket_id" class="form-control" style="width:120px;" placeholder="チケットID">
                </div>
            </div>

            <div class="search-footer">
                <a id="toggle-search-detail" class="detail-toggle">詳細 <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></a>
                <button type="submit" class="btn-search">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <span>検索</span>
                </button>
                <button type="button" class="btn-clear" id="btn-reset-search" title="検索クリア">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
        </form>

        <!-- 件数表示 & CSV -->
        <div class="list-toolbar">
            <span id="result-count-text">0 件</span>
            <a href="admin_api.php?action=export_csv" class="btn-navy" style="padding:5px 12px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                <span>CSVダウンロード</span>
            </a>
        </div>

        <!-- チケット一覧テーブル -->
        <div class="ticket-table-wrap">
            <div class="ticket-header-row" id="ticket-header-normal">
                <div><input type="checkbox" id="check-all"></div>
                <div></div>
                <div class="sort-col" data-sort="subject">件名 <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></div>
                <div class="sort-col" data-sort="requester">訪問者名 <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></div>
                <div class="sort-col" data-sort="assignee">担当者 <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></div>
                <div class="sort-col" data-sort="elapsed">経過時間 <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></div>
                <div>サイト名</div>
                <div class="sort-col" data-sort="ticket-id" style="justify-content:flex-end;">チケットID <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg></div>
            </div>
            
            <div class="ticket-header-row bulk-bar" id="bulk-action-bar">
                <div><input type="checkbox" id="check-all-bulk" checked></div>
                <span id="bulk-selected-count" style="font-weight:bold; color:#2b6cb0; font-size:13px; margin-left:10px;">0件選択中</span>
                <select id="bulk-status-select" class="form-control" style="width:150px; margin-left:14px;">
                    <option value="">ステータスを変更...</option>
                    <option value="11">未対応</option>
                    <option value="12">対応中</option>
                    <option value="13">保留中</option>
                    <option value="21">解決済</option>
                    <option value="31">ゴミ箱</option>
                </select>
                <select id="bulk-assignee-select" class="form-control" style="width:150px;">
                    <option value="">担当者を変更...</option>
                </select>
                <select id="bulk-read-select" class="form-control" style="width:180px;">
                    <option value="">未読/既読状態を変更...</option>
                    <option value="1">既読にする</option>
                    <option value="0">未読にする</option>
                </select>
                <select id="bulk-category-select" class="form-control" style="width:150px;">
                    <option value="">カテゴリを変更...</option>
                </select>
            </div>

            <div id="ticket-list-container"></div>
        </div>
    </main>
</div>

<!-- モーダル1：+ チケットを追加 -->
<div class="modal-overlay" id="modal-create">
    <div class="modal-dialog">
        <div class="modal-header">
            <span>新規チケットを追加</span>
            <span class="modal-close" style="cursor:pointer; display:inline-flex;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </span>
        </div>
        <form id="form-create-ticket">
            <div class="modal-body">
                <div class="m-group">
                    <label>サイト *</label>
                    <select name="site_name" id="create-site" class="form-control" required></select>
                </div>
                <div class="m-group">
                    <label>訪問者名（お客様名）*</label>
                    <input type="text" name="customer_name" class="form-control" required placeholder="例：山田 太郎">
                </div>
                <div class="m-group">
                    <label>メールアドレス *</label>
                    <input type="email" name="customer_email" class="form-control" required placeholder="例：taro@example.com">
                </div>
                <div class="m-group">
                    <label>件名 *</label>
                    <input type="text" name="subject" class="form-control" required placeholder="例：お電話でのお問い合わせの件">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="m-group">
                        <label>担当者</label>
                        <select name="assignee" id="create-assignee" class="form-control"></select>
                    </div>
                    <div class="m-group">
                        <label>カテゴリ</label>
                        <select name="category" id="create-category" class="form-control"></select>
                    </div>
                </div>
                <div class="m-group">
                    <label>本文 *</label>
                    <textarea name="body" class="form-control" style="height:120px;" required placeholder="お客様へのメッセージまたは対応内容"></textarea>
                </div>
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="send_mail" value="1" checked> 作成と同時にこの内容をお客様へメール送信する
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-update-only modal-close">キャンセル</button>
                <button type="submit" class="btn-send">チケットを作成</button>
            </div>
        </form>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- モーダル2：⚙ チケット設定（管理者のみ表示） -->
<div class="modal-overlay" id="modal-settings">
    <div class="modal-dialog">
        <div class="modal-header">
            <span>チケット設定</span>
            <span class="modal-close" style="cursor:pointer; display:inline-flex;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </span>
        </div>
        <form id="form-settings">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="m-group">
                        <label>担当者リスト（1行に1名）</label>
                        <textarea name="agents" id="set-agents" class="form-control" style="height:160px;"></textarea>
                    </div>
                    <div class="m-group">
                        <label>カテゴリリスト（1行に1項目）</label>
                        <textarea name="categories" id="set-categories" class="form-control" style="height:160px;"></textarea>
                    </div>
                </div>
                <div style="font-size:11px; color:#718096; margin-top:4px;">
                    ※差出人メールアドレス・差出人名・メール署名は左メニューの「サイト管理」からサイトごとに設定できます。
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-update-only modal-close">閉じる</button>
                <button type="submit" class="btn-send">設定を保存</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStatusGroup = 'active';
    let sortBy = 'elapsed';
    let sortOrder = 'desc';
    let appConfig = {};

    const statusLabels = { 11: '未対応', 12: '対応中', 13: '保留中', 21: '解決済', 31: 'ゴミ箱' };

    const svgIcons = {
        arrowRight: '<svg class="expand-arrow" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>',
        mail: '<svg class="mail-icon" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>',
        globe: '<svg width="15" height="15" viewBox="0 0 24 24" fill="#a0aec0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>',
        person: '<svg width="16" height="16" viewBox="0 0 24 24" fill="#718096"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
        support: '<svg width="16" height="16" viewBox="0 0 24 24" fill="#29a3e5"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>',
        memo: '<svg width="16" height="16" viewBox="0 0 24 24" fill="#b7791f"><path d="M19 3H4.99c-1.11 0-1.98.9-1.98 2L3 19c0 1.1.88 2 1.99 2H15l6-6V5c0-1.1-.9-2-2-2zm-7 11H7v-2h5v2zm5-4H7V8h10v2z"/></svg>',
        trash: '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>',
        attr_tags: '<svg width="16" height="16" viewBox="0 0 24 24" fill="#a0aec0" style="margin-left:4px;" title="タグ/カテゴリ"><path d="M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z"/></svg>',
        attr_memo: '<svg width="16" height="16" viewBox="0 0 24 24" fill="#a0aec0" style="margin-left:4px;" title="メモ"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }

    async function apiGet(paramsObj) {
        const params = new URLSearchParams(paramsObj);
        const res = await fetch('admin_api.php?' + params.toString(), { cache: 'no-store' });
        if (res.status === 401) {
            window.location.replace('login.php');
            throw new Error('Unauthorized');
        }
        return await res.json();
    }

    async function apiPost(formDataObj) {
        const formData = new FormData();
        for (const [k, v] of Object.entries(formDataObj)) {
            if (Array.isArray(v)) {
                v.forEach(item => formData.append(k + '[]', item));
            } else {
                formData.append(k, v);
            }
        }
        const res = await fetch('admin_api.php', { method: 'POST', body: formData });
        if (res.status === 401) {
            window.location.replace('login.php');
            throw new Error('Unauthorized');
        }
        return await res.json();
    }

    async function loadTickets() {
        const form = document.getElementById('search-form');
        const fd = new FormData(form);
        const params = {};
        fd.forEach((value, key) => { 
            if (key === 'search-status[]') {
                if (!params[key]) params[key] = [];
                params[key].push(value);
            } else {
                params[key] = value; 
            }
        });
        
        // 検索パラメータの送信準備
        params.action = 'list';
        params.status_group = currentStatusGroup;
        params.sort_by = sortBy;
        params.sort_order = sortOrder;

        try {
            const res = await apiGet(params);
            if (!res.success) return;

            appConfig = res.config || {};
            populateSelects();

            const badge = document.getElementById('header-badge');
            if (res.unanswered_count > 0) {
                badge.textContent = res.unanswered_count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }

            const total = res.tickets.length;
            document.getElementById('result-count-text').textContent = total > 0 ? `${total} 件` : '0 件';

            const container = document.getElementById('ticket-list-container');
            if (total === 0) {
                container.innerHTML = '<div style="padding:40px; text-align:center; color:#718096;">該当するチケットはありません</div>';
                updateBulkBar();
                return;
            }

            container.innerHTML = res.tickets.map(t => {
                const alertClass = t.alert_level ? `alert-${t.alert_level}` : '';
                const unreadClass = (t.is_read == 0) ? 'unread' : '';
                const assigneeText = t.assignee ? esc(t.assignee) : '<span style="color:#a0aec0;">(未割り当て)</span>';
                const statusBadge = `<span class="status-pill st-${t.status}">${statusLabels[t.status] || ''}</span>`;

                let attrIcons = '';
                if (t.category) {
                    attrIcons += svgIcons.attr_tags;
                }
                
                return `
                <div class="ticket-item ${alertClass} ${unreadClass}" data-id="${t.id}">
                    <div class="ticket-row">
                        <div class="no-expand"><input type="checkbox" class="row-check" value="${t.id}"></div>
                        <div class="no-expand"><span class="unread-dot" title="未読/既読切替" data-id="${t.id}" data-read="${t.is_read}"></span></div>
                        <div class="col-subject">
                            ${svgIcons.arrowRight}
                            ${svgIcons.mail}
                            ${statusBadge}
                            <span class="subject-text" title="${esc(t.subject)}">${esc(t.subject)}</span>
                            ${attrIcons}
                        </div>
                        <div class="col-visitor">
                            ${svgIcons.globe}
                            <span>${esc(t.customer_name)}</span>
                        </div>
                        <div class="col-assignee">${assigneeText}</div>
                        <div class="col-elapsed">${esc(t.elapsed_text)}</div>
                        <div class="col-site">${esc(t.site_name)}</div>
                        <div class="col-id">${t.id}</div>
                    </div>
                    <div class="ticket-detail-panel" id="detail-panel-${t.id}">
                        <div style="color:#718096;">読み込み中...</div>
                    </div>
                </div>`;
            }).join('');

            updateBulkBar();
        } catch (err) {
            console.error(err);
        }
    }

    function populateSelects() {
        const agents = appConfig.agents || [];
        const cats = appConfig.categories || [];
        const sites = appConfig.sites || [];

        const filterSite = document.getElementById('filter-site');
        const curSite = filterSite.value;
        filterSite.innerHTML = '<option value="">サイト</option>' +
            sites.map(s => `<option value="${esc(s.name)}">${esc(s.name)}</option>`).join('');
        filterSite.value = curSite;

        const filterAss = document.getElementById('filter-assignee');
        const curAss = filterAss.value;
        filterAss.innerHTML = '<option value="">担当者</option><option value="unassigned">(未割り当て)</option>' +
            agents.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join('');
        filterAss.value = curAss;

        const filterCat = document.getElementById('filter-category');
        const curCat = filterCat.value;
        filterCat.innerHTML = '<option value="">カテゴリ</option>' +
            cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
        filterCat.value = curCat;

        document.getElementById('bulk-assignee-select').innerHTML = '<option value="">担当者を変更...</option>' +
            agents.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join('');

        document.getElementById('bulk-category-select').innerHTML = '<option value="">カテゴリを変更...</option><option value="">(カテゴリなし)</option>' +
            cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');

        document.getElementById('create-site').innerHTML =
            sites.map(s => `<option value="${esc(s.name)}">${esc(s.name)}</option>`).join('');

        document.getElementById('create-assignee').innerHTML = '<option value="">(未割り当て)</option>' +
            agents.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join('');

        document.getElementById('create-category').innerHTML = '<option value="">カテゴリなし</option>' +
            cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
    }

    async function loadTicketDetail(ticketId) {
        const panel = document.getElementById(`detail-panel-${ticketId}`);
        if (!panel) return;

        try {
            const res = await apiGet({ action: 'events', ticket_id: ticketId });
            if (!res.success) {
                panel.innerHTML = `<div style="color:#e53e3e;">読み込みエラー: ${esc(res.error || '不明なエラー')}</div>`;
                return;
            }
            const t = res.ticket;
            const msgs = res.messages || [];

            const agentOpts = '<option value="">(未割り当て)</option>' +
                (appConfig.agents || []).map(a => `<option value="${esc(a)}" ${t.assignee === a ? 'selected' : ''}>${esc(a)}</option>`).join('');

            const catOpts = '<option value="">カテゴリなし</option>' +
                (appConfig.categories || []).map(c => `<option value="${esc(c)}" ${t.category === c ? 'selected' : ''}>${esc(c)}</option>`).join('');

            const eventsHtml = msgs.map((m, idx) => {
                const isLast = (idx === msgs.length - 1);
                const collapsedClass = isLast ? '' : 'collapsed';
                let senderLabel = '';
                if (m.sender_type === 'customer') {
                    senderLabel = `${svgIcons.person} ${esc(t.customer_name)} &lt;${esc(t.customer_email)}&gt;`;
                } else if (m.sender_type === 'memo') {
                    senderLabel = `${svgIcons.memo} 社内メモ（お客様には非表示）`;
                } else {
                    senderLabel = `${svgIcons.support} サポート担当`;
                }

                return `
                <div class="event-card ${esc(m.sender_type)} ${collapsedClass}">
                    <div class="event-header">
                        <div class="event-sender">
                            ${senderLabel}
                            <span class="event-preview">${esc(m.short_body)}</span>
                        </div>
                        <div style="font-size:11px; color:#718096;">${esc(m.elapsed_text)}</div>
                    </div>
                    <div class="event-body">${esc(m.body)}</div>
                </div>`;
            }).join('');

            panel.innerHTML = `
                <div class="detail-meta-bar">
                    <div class="meta-group">
                        <span class="meta-label">サイト:</span>
                        <span style="font-weight:bold; color:#2d3748;">${esc(t.site_name)}</span>
                        <span class="meta-label" style="margin-left:10px;">担当者:</span>
                        <select class="form-control inline-update" data-id="${t.id}" data-field="assignee">${agentOpts}</select>
                        <span class="meta-label" style="margin-left:10px;">カテゴリ:</span>
                        <select class="form-control inline-update" data-id="${t.id}" data-field="category">${catOpts}</select>
                        <a href="view.php?id=${t.id}&key=${esc(t.secret_key)}" target="_blank" style="font-size:12px; color:#2b6cb0; margin-left:10px;">お客様用確認ページ ↗</a>
                    </div>
                    <div>
                        <button type="button" class="btn-update-only btn-trash-ticket" data-id="${t.id}" style="color:#e53e3e;">
                            ${svgIcons.trash} ゴミ箱へ
                        </button>
                    </div>
                </div>

                <div class="thread-list">${eventsHtml}</div>

                <div class="reply-editor-box" data-ticket-id="${t.id}">
                    <div class="reply-tabs">
                        <button type="button" class="reply-tab active" data-type="support">お客様へ返信（メール送信）</button>
                        <button type="button" class="reply-tab" data-type="memo">社内メモを残す</button>
                    </div>
                    <div class="reply-to-info">To: <strong>${esc(t.customer_name)}</strong> &lt;${esc(t.customer_email)}&gt;</div>
                    <textarea class="reply-textarea" placeholder="返信内容を入力してください..."></textarea>
                    <div class="reply-footer">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span>送信後のステータス:</span>
                            <select class="form-control reply-status-select">
                                <option value="11" ${t.status == 11 ? 'selected' : ''}>未対応</option>
                                <option value="12" ${t.status == 12 ? 'selected' : ''}>対応中</option>
                                <option value="13" ${t.status == 13 ? 'selected' : ''}>保留中</option>
                                <option value="21" ${t.status == 21 ? 'selected' : ''}>解決済</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="btn-update-only btn-status-only" data-id="${t.id}">ステータスのみ更新</button>
                            <button type="button" class="btn-send btn-submit-reply" data-id="${t.id}">送信</button>
                        </div>
                    </div>
                </div>
            `;
        } catch (err) {
            panel.innerHTML = `<div style="color:#e53e3e;">通信エラーが発生しました。</div>`;
        }
    }

    document.addEventListener('click', async function(e) {
        const row = e.target.closest('.ticket-row');
        if (row && !e.target.closest('.no-expand')) {
            const item = row.closest('.ticket-item');
            const ticketId = item.dataset.id;

            if (item.classList.contains('expanded')) {
                item.classList.remove('expanded');
            } else {
                document.querySelectorAll('.ticket-item.expanded').forEach(el => el.classList.remove('expanded'));
                item.classList.add('expanded');
                item.classList.remove('unread');
                loadTicketDetail(ticketId);
            }
            return;
        }

        const eventHeader = e.target.closest('.event-header');
        if (eventHeader) {
            eventHeader.closest('.event-card').classList.toggle('collapsed');
            return;
        }

        const replyTab = e.target.closest('.reply-tab');
        if (replyTab) {
            const box = replyTab.closest('.reply-editor-box');
            const type = replyTab.dataset.type;
            box.querySelectorAll('.reply-tab').forEach(t => t.classList.remove('active'));
            replyTab.classList.add('active');

            const ta = box.querySelector('.reply-textarea');
            const toInfo = box.querySelector('.reply-to-info');
            const submitBtn = box.querySelector('.btn-submit-reply');

            if (type === 'memo') {
                ta.classList.add('memo-mode');
                ta.placeholder = '社内スタッフ向けのメモを入力（お客様には送信されません）';
                toInfo.style.display = 'none';
                submitBtn.textContent = 'メモを保存';
            } else {
                ta.classList.remove('memo-mode');
                ta.placeholder = '返信内容を入力してください...';
                toInfo.style.display = 'block';
                submitBtn.textContent = '送信';
            }
            return;
        }

        const submitReplyBtn = e.target.closest('.btn-submit-reply');
        if (submitReplyBtn) {
            const ticketId = submitReplyBtn.dataset.id;
            const box = submitReplyBtn.closest('.reply-editor-box');
            const replyType = box.querySelector('.reply-tab.active').dataset.type;
            const body = box.querySelector('.reply-textarea').value.trim();
            const status = box.querySelector('.reply-status-select').value;

            if (!body) {
                alert('本文を入力してください。');
                return;
            }
            submitReplyBtn.disabled = true;
            submitReplyBtn.textContent = '送信中...';

            const res = await apiPost({
                action: 'reply',
                ticket_id: ticketId,
                reply_type: replyType,
                body: body,
                status: status
            });

            if (res.success) {
                loadTickets();
            } else {
                alert(res.error || 'エラーが発生しました');
                submitReplyBtn.disabled = false;
                submitReplyBtn.textContent = '送信';
            }
            return;
        }

        const statusOnlyBtn = e.target.closest('.btn-status-only');
        if (statusOnlyBtn) {
            const ticketId = statusOnlyBtn.dataset.id;
            const status = statusOnlyBtn.closest('.reply-editor-box').querySelector('.reply-status-select').value;
            await apiPost({ action: 'update_ticket', ticket_ids: [ticketId], field: 'status', value: status });
            loadTickets();
            return;
        }

        const trashBtn = e.target.closest('.btn-trash-ticket');
        if (trashBtn) {
            if (!confirm('このチケットをゴミ箱へ移動しますか？')) return;
            await apiPost({ action: 'update_ticket', ticket_ids: [trashBtn.dataset.id], field: 'status', value: 31 });
            loadTickets();
            return;
        }

        const unreadDot = e.target.closest('.unread-dot');
        if (unreadDot) {
            const nextVal = unreadDot.dataset.read == 1 ? 0 : 1;
            await apiPost({ action: 'update_ticket', ticket_ids: [unreadDot.dataset.id], field: 'is_read', value: nextVal });
            loadTickets();
            return;
        }

        const sideItem = e.target.closest('#side-status-menu li');
        if (sideItem) {
            e.preventDefault();
            document.querySelectorAll('#side-status-menu li').forEach(li => li.classList.remove('active'));
            sideItem.classList.add('active');
            currentStatusGroup = sideItem.dataset.status;
            const spanText = sideItem.querySelector('span');
            document.getElementById('current-view-title').textContent = sideItem.dataset.title || (spanText ? spanText.textContent.trim() : '');
            
            // ステータス絞り込みチェックボックスの同期
            document.querySelectorAll('.status-cb').forEach(cb => {
                if (currentStatusGroup === 'active') {
                    cb.checked = ['11', '12', '13'].includes(cb.value);
                } else if (currentStatusGroup === 'all') {
                    cb.checked = true;
                } else {
                    cb.checked = (cb.value === currentStatusGroup);
                }
            });
            
            loadTickets();
            return;
        }

        const sortCol = e.target.closest('.sort-col');
        if (sortCol) {
            const col = sortCol.dataset.sort;
            if (sortBy === col) {
                sortOrder = (sortOrder === 'asc') ? 'desc' : 'asc';
            } else {
                sortBy = col;
                sortOrder = 'asc';
            }
            loadTickets();
            return;
        }

        const closeBtn = e.target.closest('.modal-close');
        if (closeBtn) {
            closeBtn.closest('.modal-overlay').classList.remove('open');
            return;
        }
    });

    document.addEventListener('change', async function(e) {
        if (e.target.classList.contains('inline-update')) {
            await apiPost({
                action: 'update_ticket',
                ticket_ids: [e.target.dataset.id],
                field: e.target.dataset.field,
                value: e.target.value
            });
            loadTickets();
        } else if (e.target.classList.contains('row-check')) {
            updateBulkBar();
        } else if (e.target.id === 'check-all') {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = e.target.checked);
            updateBulkBar();
        } else if (e.target.id === 'check-all-bulk') {
            // 一括操作バー側のチェックを外すと全解除して元のヘッダーに戻す
            if (!e.target.checked) {
                document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
                document.getElementById('check-all').checked = false;
                updateBulkBar();
            }
        } else if (e.target.id === 'bulk-status-select') {
            const val = e.target.value;
            if (!val) return;
            const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
            await apiPost({ action: 'update_ticket', ticket_ids: ids, field: 'status', value: val });
            e.target.value = '';
            document.getElementById('check-all').checked = false;
            loadTickets();
        } else if (e.target.id === 'bulk-assignee-select') {
            const val = e.target.value;
            if (!val) return;
            const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
            await apiPost({ action: 'update_ticket', ticket_ids: ids, field: 'assignee', value: val });
            e.target.value = '';
            document.getElementById('check-all').checked = false;
            loadTickets();
        } else if (e.target.id === 'bulk-read-select') {
            const val = e.target.value;
            if (!val) return;
            const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
            await apiPost({ action: 'update_ticket', ticket_ids: ids, field: 'is_read', value: val });
            e.target.value = '';
            document.getElementById('check-all').checked = false;
            loadTickets();
        } else if (e.target.id === 'bulk-category-select') {
            // valueが空文字列の場合は「未設定」として更新する（先頭の案内用option以外）
            if (e.target.selectedIndex === 0) return;
            const val = e.target.value;
            const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
            await apiPost({ action: 'update_ticket', ticket_ids: ids, field: 'category', value: val });
            e.target.selectedIndex = 0;
            document.getElementById('check-all').checked = false;
            loadTickets();
        }
    });

    function updateBulkBar() {
        const count = document.querySelectorAll('.row-check:checked').length;
        const bulkBar = document.getElementById('bulk-action-bar');
        const normalHeader = document.getElementById('ticket-header-normal');
        
        if (count > 0) {
            document.getElementById('bulk-selected-count').textContent = `${count}件選択中`;
            bulkBar.classList.add('active');
            normalHeader.style.display = 'none';
            document.getElementById('check-all-bulk').checked = true;
        } else {
            bulkBar.classList.remove('active');
            normalHeader.style.display = 'grid';
            document.getElementById('check-all').checked = false;
        }
    }

    document.getElementById('search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadTickets();
    });
    
    document.getElementById('btn-reset-search').addEventListener('click', function() {
        document.getElementById('search-form').reset();
        
        // サイドバーと連動しているステータスをリセット
        document.querySelectorAll('.status-cb').forEach(cb => {
            if (currentStatusGroup === 'active') {
                cb.checked = ['11', '12', '13'].includes(cb.value);
            } else if (currentStatusGroup === 'all') {
                cb.checked = true;
            } else {
                cb.checked = (cb.value === currentStatusGroup);
            }
        });
        
        loadTickets();
    });
    
    document.getElementById('toggle-search-detail').addEventListener('click', function(e) {
        e.preventDefault();
        const searchBox = document.querySelector('.search-box');
        searchBox.classList.toggle('detail_shown');
        const icon = searchBox.classList.contains('detail_shown') ? 
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14l5-5 5 5z"/></svg>' : 
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>';
        this.innerHTML = `詳細 ${icon}`;
    });

    document.getElementById('btn-open-create').addEventListener('click', function() {
        document.getElementById('form-create-ticket').reset();
        document.getElementById('modal-create').classList.add('open');
    });
    
    document.getElementById('form-create-ticket').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const obj = { action: 'create_ticket' };
        fd.forEach((v, k) => obj[k] = v);
        const res = await apiPost(obj);
        if (res.success) {
            document.getElementById('modal-create').classList.remove('open');
            loadTickets();
        } else {
            alert(res.error || 'エラーが発生しました');
        }
    });

    const btnOpenSettings = document.getElementById('btn-open-settings');
    if (btnOpenSettings) {
        btnOpenSettings.addEventListener('click', function() {
            document.getElementById('set-agents').value = (appConfig.agents || []).join("\n");
            document.getElementById('set-categories').value = (appConfig.categories || []).join("\n");
            document.getElementById('modal-settings').classList.add('open');
        });
    }

    const formSettings = document.getElementById('form-settings');
    if (formSettings) {
        formSettings.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const obj = { action: 'save_settings' };
            fd.forEach((v, k) => obj[k] = v);
            const res = await apiPost(obj);
            if (res.success) {
                document.getElementById('modal-settings').classList.remove('open');
                alert('設定を保存しました。');
                loadTickets();
            } else {
                alert(res.error || 'エラーが発生しました');
            }
        });
    }

    loadTickets();
});
</script>
</body>
</html>