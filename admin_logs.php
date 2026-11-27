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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>履歴 - TC Plus</title>
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
        .page-title { font-size: 20px; font-weight: bold; color: #2d3748; margin-bottom: 16px; }

        .search-card { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; padding: 14px; margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .form-control { height: 32px; padding: 4px 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12.5px; background: #fff; }
        .btn-navy { background: var(--navy-btn); color: #fff; border: none; height: 32px; padding: 0 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }

        .toolbar-row { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 12px; }

        .log-table { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; }
        .log-header {
            display: grid; grid-template-columns: 65px 145px 150px 1fr 90px 110px 140px;
            background: #f8fafc; border-bottom: 1px solid var(--border-color);
            padding: 10px 12px; font-weight: bold; color: #4a5568; font-size: 12px;
        }
        .log-item { border-bottom: 1px solid #edf2f7; }
        .log-row {
            display: grid; grid-template-columns: 65px 145px 150px 1fr 90px 110px 140px;
            align-items: center; padding: 11px 12px; cursor: pointer; font-size: 12.5px;
        }
        .log-row:hover { background: #f0f7ff; }
        .log-detail { display: none; background: #f4f7f9; border-top: 1px dashed #cbd5e0; padding: 16px 24px; }
        .log-item.open .log-detail { display: block; }

        .chat-bubble-list { display: flex; flex-direction: column; gap: 8px; max-width: 720px; }
        .b-row { display: flex; }
        .b-row.bot { justify-content: flex-start; }
        .b-row.user { justify-content: flex-end; }
        .b-box { padding: 8px 12px; border-radius: 10px; font-size: 12.5px; line-height: 1.5; max-width: 80%; white-space: pre-wrap; }
        .b-row.bot .b-box { background: #fff; border: 1px solid #cbd5e0; color: #2d3748; }
        .b-row.user .b-box { background: #29a3e5; color: #fff; }

        .pill-ticket { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-decoration: none; }
        .pill-bot { background: #f0fff4; color: #276749; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .pill-sat-good { background: #ebf8ff; color: #2b6cb0; border: 1px solid #90cdf4; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .pill-sat-bad { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .survey-detail-card {
            background: #fff; border: 1px solid #cbd5e0; border-radius: 6px; padding: 10px 14px;
            margin-bottom: 12px; max-width: 720px; font-size: 12.5px;
        }
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
            <li><a href="admin_visitors.php">訪問者</a></li>
            <li class="active"><a href="admin_logs.php">履歴</a></li>
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
        <div class="side-group-title" style="margin-top:2px;">会話ログ絞り込み</div>
        <ul class="side-menu" id="log-side-filter">
            <li class="active" data-type=""><a><span>📋 すべての会話履歴</span></a></li>
            <li data-type="bot_only"><a><span>🤖 ボット完結のみ</span></a></li>
            <li data-type="ticket"><a><span>📩 チケット起票済のみ</span></a></li>
            <li data-type="satisfied"><a><span>👍 満足評価のみ</span></a></li>
            <li data-type="dissatisfied"><a><span>👎 不満評価のみ</span></a></li>
        </ul>
        <div class="side-group-title">関連メニュー</div>
        <ul class="side-menu">
            <li><a href="admin_visitors.php"><span>👥 訪問者モニター</span></a></li>
            <li><a href="admin_reports.php"><span>📊 レポート集計</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <h1 class="page-title">チャットボット会話履歴</h1>

        <form id="log-search-form" class="search-card">
            <input type="date" id="s-start" class="form-control">
            <span>〜</span>
            <input type="date" id="s-end" class="form-control">
            <select id="s-site" class="form-control" style="min-width:160px;">
                <option value="">すべてのサイト</option>
            </select>
            <input type="text" id="s-word" class="form-control" style="width:220px;" placeholder="会話内容・訪問者名・ご意見で検索">
            <button type="submit" class="btn-navy">検索</button>
        </form>

        <div class="toolbar-row">
            <span id="log-count-label">0 件</span>
            <a href="bot_api.php?action=logs_export_csv" class="btn-navy">⬇ CSVダウンロード</a>
        </div>

        <div class="log-table">
            <div class="log-header">
                <div>ログID</div>
                <div>サイト名</div>
                <div>訪問者</div>
                <div>最初のやり取り・発言内容</div>
                <div>満足度</div>
                <div>結果</div>
                <div>日時</div>
            </div>
            <div id="logs-container">
                <div style="padding:36px; text-align:center; color:#718096;">読み込み中...</div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let resultType = '';

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    async function loadLogs() {
        const params = new URLSearchParams({
            action: 'logs_list',
            site_id: document.getElementById('s-site').value,
            start_date: document.getElementById('s-start').value,
            end_date: document.getElementById('s-end').value,
            result_type: resultType,
            word: document.getElementById('s-word').value
        });

        const res = await fetch('bot_api.php?' + params.toString(), { cache: 'no-store' });
        if (res.status === 401) {
            window.location.replace('login.php');
            return;
        }
        const data = await res.json();
        if (!data.success) return;

        const sel = document.getElementById('s-site');
        const cur = sel.value;
        sel.innerHTML = '<option value="">すべてのサイト</option>' +
            (data.sites || []).map(s => `<option value="${esc(s.id)}">${esc(s.name)}</option>`).join('');
        sel.value = cur;

        const logs = data.logs || [];
        document.getElementById('log-count-label').textContent = `${logs.length} 件`;

        const container = document.getElementById('logs-container');
        if (logs.length === 0) {
            container.innerHTML = '<div style="padding:40px; text-align:center; color:#718096;">該当するチャット履歴はありません</div>';
            return;
        }

        container.innerHTML = logs.map(l => {
            const resTag = l.ticket_id
                ? `<a href="admin.php" class="pill-ticket">チケット #${l.ticket_id}</a>`
                : '<span class="pill-bot">ボット完結</span>';

            let satTag = '<span style="color:#a0aec0; font-size:11px;">未回答</span>';
            if (l.satisfaction === 'satisfied') {
                satTag = '<span class="pill-sat-good">👍 満足</span>';
            } else if (l.satisfaction === 'dissatisfied') {
                satTag = '<span class="pill-sat-bad">👎 不満</span>';
            }

            let surveyBox = '';
            if (l.satisfaction) {
                const satTitle = l.satisfaction === 'satisfied' ? '👍 満足' : '👎 不満';
                const commentTxt = l.satisfaction_comment ? esc(l.satisfaction_comment) : '<span style="color:#a0aec0;">（コメントなし）</span>';
                surveyBox = `
                <div class="survey-detail-card">
                    <strong>チャット終了時アンケート結果：</strong> ${satTitle}
                    <div style="margin-top:4px; color:#4a5568;">ご意見：${commentTxt}</div>
                </div>`;
            }

            const bubbles = (l.messages || []).map(m => {
                const role = (m.sender === 'user') ? 'user' : 'bot';
                return `<div class="b-row ${role}"><div class="b-box">${esc(m.text)}</div></div>`;
            }).join('');

            return `
            <div class="log-item">
                <div class="log-row">
                    <div style="color:#718096;">#${l.id}</div>
                    <div style="color:#4a5568;">${esc(l.site_name)}</div>
                    <div><strong>${esc(l.visitor_name)}</strong></div>
                    <div style="color:#2b6cb0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:10px;">▶ ${esc(l.summary)} <span style="color:#a0aec0; font-size:11px;">(${l.msg_count}発言)</span></div>
                    <div>${satTag}</div>
                    <div>${resTag}</div>
                    <div style="color:#718096; font-size:11.5px;">${esc(l.updated_at)}</div>
                </div>
                <div class="log-detail">
                    <div style="font-size:11.5px; color:#718096; margin-bottom:10px;">セッション開始: ${esc(l.created_at)} ／ 発言数: ${l.msg_count}件</div>
                    ${surveyBox}
                    <div class="chat-bubble-list">${bubbles || '<div style="color:#a0aec0;">メッセージ詳細がありません</div>'}</div>
                </div>
            </div>`;
        }).join('');
    }

    document.addEventListener('click', function(e) {
        const row = e.target.closest('.log-row');
        if (row && !e.target.closest('a')) {
            row.closest('.log-item').classList.toggle('open');
        }
    });

    document.querySelectorAll('#log-side-filter li').forEach(li => {
        li.addEventListener('click', function() {
            document.querySelectorAll('#log-side-filter li').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            resultType = this.dataset.type;
            loadLogs();
        });
    });

    document.getElementById('log-search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadLogs();
    });

    loadLogs();
});
</script>
</body>
</html>