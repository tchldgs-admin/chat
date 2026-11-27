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
$default_start = date('Y-m-d', strtotime('-13 days'));
$default_end   = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レポート - TC Plus</title>
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
        .side-menu li.active a { background: #e2ecf7; color: #1a365d; font-weight: bold; border-left: 3px solid var(--primary-blue); }

        .main-content { flex: 1; margin-left: 185px; padding: 22px 28px; }
        .page-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 20px; font-weight: bold; color: #2d3748; }

        .filter-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .form-control { height: 32px; padding: 4px 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12.5px; background: #fff; }
        .btn-navy { background: var(--navy-btn); color: #fff; border: none; height: 32px; padding: 0 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }

        /* KPIサマリーカード */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
        .kpi-card { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; padding: 16px 18px; }
        .kpi-label { font-size: 12px; color: #718096; font-weight: bold; margin-bottom: 6px; }
        .kpi-val { font-size: 26px; font-weight: bold; color: #2d3748; }
        .kpi-sub { font-size: 11px; color: #718096; margin-top: 4px; }

        .card-box { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; padding: 18px 20px; margin-bottom: 18px; }
        .card-title { font-size: 15px; font-weight: bold; color: #2d3748; margin-bottom: 14px; border-bottom: 1px solid #edf2f7; padding-bottom: 8px; }

        /* 日別バーグラフ（CSSベースで軽量・高速描画） */
        .chart-wrap { display: flex; align-items: flex-end; gap: 8px; height: 190px; padding: 16px 8px 6px; border-bottom: 1px solid #cbd5e0; overflow-x: auto; }
        .chart-col { flex: 1; min-width: 36px; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
        .bar-stack { width: 24px; display: flex; flex-direction: column-reverse; border-radius: 3px 3px 0 0; overflow: hidden; }
        .bar-solved { background: #38a169; width: 100%; }
        .bar-ticket { background: #29a3e5; width: 100%; }
        .bar-num { font-size: 10px; font-weight: bold; color: #4a5568; margin-bottom: 3px; }
        .chart-labels { display: flex; gap: 8px; padding: 6px 8px 0; overflow-x: auto; }
        .chart-lbl { flex: 1; min-width: 36px; text-align: center; font-size: 10px; color: #718096; }

        .rank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .rank-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #edf2f7; font-size: 12.5px; }
        .rank-num { width: 22px; height: 22px; border-radius: 50%; background: #edf2f7; color: #4a5568; font-weight: bold; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; flex-shrink: 0; }
        .rank-row:nth-child(1) .rank-num { background: #ecc94b; color: #744210; }
        .rank-count { font-weight: bold; color: #2b6cb0; background: #ebf8ff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
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
            <li><a href="admin_logs.php">履歴</a></li>
            <li><a href="admin.php">チケット</a></li>
            <li class="active"><a href="admin_reports.php">レポート</a></li>
        </ul>
    </div>
    <div class="header-right">
        <span style="font-size:12px; font-weight:bold; color:#333;"><?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="logout.php" class="logout-link">ログアウト</a>
    </div>
</header>

<div class="layout-wrapper">
    <aside class="sidebar">
        <div class="side-group-title" style="margin-top:2px;">レポート分析</div>
        <ul class="side-menu">
            <li class="active"><a href="admin_reports.php"><span>📊 チャットボット稼働分析</span></a></li>
        </ul>
        <div class="side-group-title">関連メニュー</div>
        <ul class="side-menu">
            <li><a href="admin_logs.php"><span>💬 会話履歴一覧</span></a></li>
            <li><a href="admin_bot.php"><span>🤖 チャットボット設定</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header-bar">
            <h1 class="page-title">チャットボット稼働レポート</h1>
            <form id="report-filter-form" class="filter-bar">
                <input type="date" id="r-start" class="form-control" value="<?php echo $default_start; ?>">
                <span>〜</span>
                <input type="date" id="r-end" class="form-control" value="<?php echo $default_end; ?>">
                <select id="r-site" class="form-control" style="min-width:170px;">
                    <option value="">すべてのサイト</option>
                </select>
                <button type="submit" class="btn-navy">集計する</button>
            </form>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">チャットボット起動数</div>
                <div class="kpi-val" id="kpi-total">0</div>
                <div class="kpi-sub">期間内の総セッション数</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">ボット完結数（自己解決）</div>
                <div class="kpi-val" id="kpi-solved" style="color:#38a169;">0</div>
                <div class="kpi-sub">問い合わせ化せず案内完了</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">チケット自動起票数</div>
                <div class="kpi-val" id="kpi-ticket" style="color:#29a3e5;">0</div>
                <div class="kpi-sub">フォーム送信・メール受付数</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">ボット完結率</div>
                <div class="kpi-val" id="kpi-rate">0%</div>
                <div class="kpi-sub">自動案内のみで解決した割合</div>
            </div>
        </div>

        <div class="card-box">
            <div style="display:flex; justify-content:space-between; align-items:center;" class="card-title">
                <span>日別チャットボット稼働推移</span>
                <span style="font-size:11px; font-weight:normal; color:#718096;">
                    <span style="display:inline-block; width:10px; height:10px; background:#38a169; margin-right:4px;"></span>ボット完結
                    <span style="display:inline-block; width:10px; height:10px; background:#29a3e5; margin:0 4px 0 12px;"></span>チケット起票
                </span>
            </div>
            <div class="chart-wrap" id="daily-chart-bars"></div>
            <div class="chart-labels" id="daily-chart-labels"></div>
        </div>

        <div class="rank-grid">
            <div class="card-box">
                <div class="card-title">よく選択されたボタン・発言ランキング</div>
                <div id="top-choices-list"></div>
            </div>
            <div class="card-box">
                <div class="card-title">ナレッジベース（Q&A）ヒット数ランキング</div>
                <div id="top-kb-list"></div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    async function loadReport() {
        const params = new URLSearchParams({
            action: 'report_stats',
            site_id: document.getElementById('r-site').value,
            start_date: document.getElementById('r-start').value,
            end_date: document.getElementById('r-end').value
        });

        const res = await fetch('bot_api.php?' + params.toString(), { cache: 'no-store' });
        if (res.status === 401) {
            window.location.replace('login.php');
            return;
        }
        const data = await res.json();
        if (!data.success) return;

        const sel = document.getElementById('r-site');
        const cur = sel.value;
        sel.innerHTML = '<option value="">すべてのサイト</option>' +
            (data.sites || []).map(s => `<option value="${esc(s.id)}">${esc(s.name)}</option>`).join('');
        sel.value = cur;

        const kpi = data.kpi || {};
        document.getElementById('kpi-total').textContent  = `${kpi.total_sessions || 0} 件`;
        document.getElementById('kpi-solved').textContent = `${kpi.solved_sessions || 0} 件`;
        document.getElementById('kpi-ticket').textContent = `${kpi.ticket_sessions || 0} 件`;
        document.getElementById('kpi-rate').textContent   = `${kpi.resolution_rate || 0}%`;

        // 日別棒グラフ描画
        const daily = data.daily || [];
        const maxVal = Math.max(1, ...daily.map(d => d.total));
        document.getElementById('daily-chart-bars').innerHTML = daily.map(d => {
            const solvedH = Math.round((d.solved / maxVal) * 140);
            const ticketH = Math.round((d.ticket / maxVal) * 140);
            return `
            <div class="chart-col" title="${d.date}：合計${d.total}件（完結${d.solved} / チケット${d.ticket}）">
                <div class="bar-num">${d.total > 0 ? d.total : ''}</div>
                <div class="bar-stack">
                    <div class="bar-solved" style="height:${solvedH}px;"></div>
                    <div class="bar-ticket" style="height:${ticketH}px;"></div>
                </div>
            </div>`;
        }).join('');

        document.getElementById('daily-chart-labels').innerHTML = daily.map(d => {
            const md = d.date.slice(5).replace('-', '/');
            return `<div class="chart-lbl">${md}</div>`;
        }).join('');

        // 選択肢ランキング
        const choices = data.top_choices || [];
        document.getElementById('top-choices-list').innerHTML = choices.length
            ? choices.map((c, idx) => `
                <div class="rank-row">
                    <div style="display:flex; align-items:center; overflow:hidden; padding-right:8px;">
                        <span class="rank-num">${idx + 1}</span>
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${esc(c.label)}</span>
                    </div>
                    <span class="rank-count">${c.count} 回</span>
                </div>`).join('')
            : '<div style="padding:20px; text-align:center; color:#a0aec0;">データがありません</div>';

        // Q&Aヒットランキング
        const kbs = data.top_kb || [];
        document.getElementById('top-kb-list').innerHTML = kbs.length
            ? kbs.map((k, idx) => `
                <div class="rank-row">
                    <div style="display:flex; align-items:center; overflow:hidden; padding-right:8px;">
                        <span class="rank-num">${idx + 1}</span>
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${esc(k.question)}</span>
                    </div>
                    <span class="rank-count">${k.hit_count} ヒット</span>
                </div>`).join('')
            : '<div style="padding:20px; text-align:center; color:#a0aec0;">まだヒット履歴がありません</div>';
    }

    document.getElementById('report-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport();
    });

    loadReport();
});
</script>
</body>
</html>