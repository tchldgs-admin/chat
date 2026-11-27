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
$is_admin   = ($admin_role === 'admin');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訪問者 - TC Plus</title>
    <style>
        :root {
            --primary-blue: #29a3e5;
            --navy-btn: #283e5c;
            --sidebar-bg: #f4f6f9;
            --border-color: #e2e8f0;
            --text-main: #333333;
            --text-sub: #718096;
            --bg-light: #f7fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; color: var(--text-main); font-size: 13px; background: var(--bg-light); }
        svg { vertical-align: middle; flex-shrink: 0; }

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
        .top-nav li.active a { color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); }
        .header-right { display: flex; align-items: center; gap: 14px; color: #666; }
        .logout-link { color: #666; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e0; }

        .layout-wrapper { display: flex; padding-top: 54px; min-height: 100vh; }

        .sidebar { width: 185px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding: 16px 0; position: fixed; top: 54px; bottom: 0; left: 0; overflow-y: auto; }
        .side-group-title { font-size: 11px; color: #a0aec0; font-weight: bold; padding: 0 18px; margin-bottom: 4px; margin-top: 16px; }
        .side-menu { list-style: none; }
        .side-menu li a { display: flex; align-items: center; gap: 10px; padding: 10px 18px; color: #4a5568; text-decoration: none; font-size: 13px; cursor: pointer; }
        .side-menu li a:hover { background: #e8edf3; }
        .side-menu li.active a { background: #e2ecf7; color: #1a365d; font-weight: bold; border-left: 3px solid var(--primary-blue); }

        .main-content { flex: 1; margin-left: 185px; padding: 22px 28px; }
        .page-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 20px; font-weight: bold; color: #2d3748; display: flex; align-items: center; gap: 10px; }
        .live-badge { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; font-size: 12px; padding: 3px 10px; border-radius: 12px; font-weight: bold; }

        .toolbar-controls { display: flex; align-items: center; gap: 10px; }
        .form-control { height: 32px; padding: 4px 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12.5px; background: #fff; }
        .btn-navy { background: var(--navy-btn); color: #fff; border: none; height: 32px; padding: 0 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }

        .table-card { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; }
        .v-header {
            display: grid; grid-template-columns: 110px 160px 150px 75px 1fr 110px 110px;
            background: #f8fafc; border-bottom: 1px solid var(--border-color);
            padding: 10px 12px; font-weight: bold; color: #4a5568; font-size: 12px;
        }
        .v-row {
            display: grid; grid-template-columns: 110px 160px 150px 75px 1fr 110px 110px;
            align-items: center; padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 12.5px;
        }
        .v-row:hover { background: #f8fafc; }
        .status-online { display: inline-flex; align-items: center; gap: 5px; background: #c6f6d5; color: #22543d; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 10px; }
        .status-offline { display: inline-flex; align-items: center; gap: 5px; background: #edf2f7; color: #718096; font-size: 11px; padding: 2px 8px; border-radius: 10px; }
        .dot-g { width: 7px; height: 7px; border-radius: 50%; background: #38a169; }
        .dot-gray { width: 7px; height: 7px; border-radius: 50%; background: #a0aec0; }
        .ticket-link-pill { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-decoration: none; }
        .bot-done-pill { background: #f0fff4; color: #276749; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
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
            <li class="active"><a href="admin_visitors.php">訪問者</a></li>
            <li><a href="admin_logs.php">履歴</a></li>
            <li><a href="admin.php">チケット</a></li>
            <li><a href="admin_reports.php">レポート</a></li>
        </ul>
    </div>
    <div class="header-right">
        <span style="font-size:12px; font-weight:bold; color:#333;"><?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="logout.php" class="logout-link">ログアウト</a>
    </div>
</header>

<div class="layout-wrapper">
    <aside class="sidebar">
        <div class="side-group-title" style="margin-top:2px;">訪問者モニター</div>
        <ul class="side-menu" id="visitor-filter-menu">
            <li class="active" data-filter="all"><a><span>👥 直近24時間の訪問者</span></a></li>
            <li data-filter="online"><a><span>🟢 現在アクティブ中</span></a></li>
        </ul>
        <div class="side-group-title">関連メニュー</div>
        <ul class="side-menu">
            <li><a href="admin_logs.php"><span>💬 チャット履歴一覧</span></a></li>
            <li><a href="admin_reports.php"><span>📊 レポート集計</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header-bar">
            <div class="page-title">
                <span>訪問者モニター</span>
                <span class="live-badge" id="active-count-badge">オンライン: 0名</span>
            </div>
            <div class="toolbar-controls">
                <select id="filter-site" class="form-control" style="min-width:180px;">
                    <option value="">すべてのサイト</option>
                </select>
                <button type="button" class="btn-navy" id="btn-refresh">↻ 最新に更新</button>
            </div>
        </div>

        <div class="table-card">
            <div class="v-header">
                <div>状態</div>
                <div>訪問者名</div>
                <div>サイト名</div>
                <div>端末</div>
                <div>最新の発言・操作内容</div>
                <div>対応結果</div>
                <div>最終アクセス</div>
            </div>
            <div id="visitors-container">
                <div style="padding:36px; text-align:center; color:#718096;">読み込み中...</div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let allVisitors = [];
    let filterMode = 'all';

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function renderVisitors() {
        const list = allVisitors.filter(v => filterMode === 'online' ? v.is_online : true);
        const container = document.getElementById('visitors-container');

        if (list.length === 0) {
            container.innerHTML = '<div style="padding:40px; text-align:center; color:#718096;">該当する訪問者データはありません</div>';
            return;
        }

        container.innerHTML = list.map(v => {
            const stBadge = v.is_online
                ? '<span class="status-online"><span class="dot-g"></span>対応中</span>'
                : '<span class="status-offline"><span class="dot-gray"></span>終了</span>';
            const resBadge = v.ticket_id
                ? `<a href="admin.php" class="ticket-link-pill">チケット #${v.ticket_id}</a>`
                : '<span class="bot-done-pill">ボット案内</span>';
            const subInfo = v.visitor_email ? `<div style="font-size:11px; color:#718096;">${esc(v.visitor_email)}</div>` : '';

            return `
            <div class="v-row">
                <div>${stBadge}</div>
                <div><strong>${esc(v.visitor_name)}</strong>${subInfo}</div>
                <div style="color:#4a5568;">${esc(v.site_name)}</div>
                <div style="color:#718096;">${esc(v.device)}</div>
                <div style="color:#2d3748; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:10px;" title="${esc(v.last_msg)}">${esc(v.last_msg || '(起動のみ)')}</div>
                <div>${resBadge}</div>
                <div style="color:#718096; font-size:12px;">${esc(v.ago_text)}</div>
            </div>`;
        }).join('');
    }

    async function loadVisitors() {
        const siteId = document.getElementById('filter-site').value;
        const res = await fetch('bot_api.php?action=visitors_list&site_id=' + encodeURIComponent(siteId), { cache: 'no-store' });
        if (res.status === 401) {
            window.location.replace('login.php');
            return;
        }
        const data = await res.json();
        if (!data.success) return;

        const sel = document.getElementById('filter-site');
        const cur = sel.value;
        sel.innerHTML = '<option value="">すべてのサイト</option>' +
            (data.sites || []).map(s => `<option value="${esc(s.id)}">${esc(s.name)}</option>`).join('');
        sel.value = cur;

        document.getElementById('active-count-badge').textContent = `オンライン: ${data.active_count || 0}名`;
        allVisitors = data.visitors || [];
        renderVisitors();
    }

    document.querySelectorAll('#visitor-filter-menu li').forEach(li => {
        li.addEventListener('click', function() {
            document.querySelectorAll('#visitor-filter-menu li').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            filterMode = this.dataset.filter;
            renderVisitors();
        });
    });

    document.getElementById('filter-site').addEventListener('change', loadVisitors);
    document.getElementById('btn-refresh').addEventListener('click', loadVisitors);

    loadVisitors();
    setInterval(loadVisitors, 30000);
});
</script>
</body>
</html>