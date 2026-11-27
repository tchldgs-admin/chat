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

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'] ?? 'cc-circle.net';
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_url = $protocol . $domain . $dir;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定 | サイト管理 - TC Plus</title>
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
        .header-right { display: flex; align-items: center; gap: 16px; color: #666; }
        .logout-link { color: #666; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e0; }
        .logout-link:hover { background: #f7fafc; color: #e53e3e; border-color: #feb2b2; }
        
        .layout-wrapper { display: flex; padding-top: 54px; min-height: 100vh; }
        
        .sidebar { width: 185px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding: 16px 0; position: fixed; top: 54px; bottom: 0; left: 0; overflow-y: auto; }
        .side-menu { list-style: none; margin-bottom: 20px; }
        .side-menu li a { display: flex; align-items: center; gap: 10px; padding: 10px 18px; color: #4a5568; text-decoration: none; font-size: 13px; }
        .side-menu li a:hover { background: #e8edf3; }
        .side-menu li.active a { background: #e2ecf7; color: #1a365d; font-weight: bold; border-left: 3px solid var(--primary-blue); }
        .side-menu svg { color: #718096; }
        .side-group-title { font-size: 11px; color: #a0aec0; font-weight: bold; padding: 0 18px; margin-bottom: 4px; margin-top: 16px; }

        .main-content { flex: 1; margin-left: 185px; padding: 24px 32px; background: var(--bg-light); min-height: calc(100vh - 54px); }
        .breadcrumb { font-size: 12px; color: var(--text-sub); margin-bottom: 8px; }
        .page-title-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-title { font-size: 22px; font-weight: bold; color: #2d3748; }
        .btn-primary { background: var(--primary-blue); color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
        .btn-primary:hover { background: #2188c4; }

        .site-list { display: flex; flex-direction: column; gap: 16px; }
        .site-card { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; padding: 18px 20px; transition: box-shadow 0.2s; }
        .site-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-color: #cbd5e0; }
        .site-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 14px; }
        .site-info { display: flex; align-items: center; gap: 14px; }
        .site-icon { width: 42px; height: 42px; background: #ebf8ff; color: var(--primary-blue); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .site-name { font-size: 16px; font-weight: bold; color: #2d3748; margin-bottom: 4px; }
        .site-meta { font-size: 12px; color: #718096; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .site-meta a { color: #2b6cb0; text-decoration: none; }
        .site-meta a:hover { text-decoration: underline; }
        .site-id-badge { background: #edf2f7; color: #4a5568; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 11px; }
        .mail-setting-badge { background: #f0fff4; border: 1px solid #c6f6d5; color: #276749; padding: 2px 8px; border-radius: 4px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; }
        .smtp-badge-on { background: #ebf8ff; border: 1px solid #bee3f8; color: #2b6cb0; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .smtp-badge-off { background: #fffaf0; border: 1px solid #fbd38d; color: #c05621; padding: 2px 8px; border-radius: 4px; font-size: 11px; }

        .site-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-action { background: #f7fafc; border: 1px solid #e2e8f0; color: #4a5568; height: 34px; padding: 0 10px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; font-size: 12px; font-weight: bold; text-decoration: none; transition: all 0.2s; }
        .btn-action:hover { background: #e2e8f0; }
        .btn-edit:hover { color: var(--primary-blue); border-color: #bee3f8; background: #ebf8ff; }
        .btn-delete:hover { color: #e53e3e; border-color: #fed7d7; background: #fff5f5; }

        .embed-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        @media (max-width: 1180px) {
            .embed-grid { grid-template-columns: 1fr; }
        }
        .embed-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 12px 14px; }
        .embed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 6px; }
        .embed-label { font-size: 12px; font-weight: bold; color: #4a5568; display: flex; align-items: center; gap: 5px; }
        .btn-copy { background: var(--navy-btn); color: #fff; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.2s; white-space: nowrap; }
        .btn-copy:hover { background: var(--navy-btn-hover); }
        .btn-copy.copied { background: #38a169 !important; }
        .embed-code-input {
            width: 100%; font-family: Consolas, Monaco, "Courier New", monospace; font-size: 11px;
            background: #fff; border: 1px solid #cbd5e0; border-radius: 4px; padding: 8px 10px;
            color: #2d3748; resize: none; height: 74px; line-height: 1.4;
        }
        .embed-code-input:focus { outline: none; border-color: var(--primary-blue); }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-dialog { background: #fff; width: 580px; max-width: 94vw; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 16px; color: #2d3748; }
        .modal-close { cursor: pointer; color: #a0aec0; display: inline-flex; }
        .modal-close:hover { color: #4a5568; }
        .modal-body { padding: 20px; max-height: 78vh; overflow-y: auto; }
        .modal-footer { padding: 16px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
        
        .section-divider { font-size: 12px; font-weight: bold; color: #2b6cb0; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin: 20px 0 12px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #4a5568; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 13px; font-family: inherit; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 2px rgba(41, 163, 229, 0.2); }
        .form-note { font-size: 11px; color: #718096; margin-top: 4px; line-height: 1.5; }
        .smtp-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; }
        .test-mail-row { display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #cbd5e0; }
        .btn-test { background: #283e5c; color: #fff; border: none; padding: 0 14px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; white-space: nowrap; }
        .btn-test:hover { background: #1e2f47; }
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
            <li><a href="admin_visitors.php">訪問者</a></li>
            <li><a href="admin_logs.php">履歴</a></li>
            <li><a href="admin.php">チケット</a></li>
            <li><a href="admin_reports.php">レポート</a></li>
        </ul>
    </div>
    <div class="header-right">
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
            <li class="active">
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
    </aside>

    <main class="main-content">
        <div class="breadcrumb">設定 | サイト管理</div>
        <div class="page-title-area">
            <h1 class="page-title">サイト管理・フォーム＆チャットボット発行</h1>
            <button type="button" class="btn-primary" id="btn-add-site">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                <span>サイトを追加</span>
            </button>
        </div>

        <div class="site-list" id="site-list-container">
            <div style="padding: 20px; text-align: center; color: #718096;">読み込み中...</div>
        </div>
    </main>
</div>

<!-- サイト追加/編集モーダル -->
<div class="modal-overlay" id="modal-site">
    <div class="modal-dialog">
        <div class="modal-header">
            <span id="modal-title">サイトを追加</span>
            <span class="modal-close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </span>
        </div>
        <form id="form-site">
            <div class="modal-body">
                <input type="hidden" name="id" id="site-id" value="">
                
                <div class="form-group">
                    <label>サイト名（管理画面に表示される名前）<span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="name" id="site-name" class="form-control" required placeholder="例: コーポレートサイト、LP① など">
                </div>
                
                <div class="form-group">
                    <label>設置先サイトURL（任意・メモ用）</label>
                    <input type="url" name="url" id="site-url" class="form-control" placeholder="例: https://example.com/contact">
                </div>

                <div class="section-divider">基本のメール表示設定</div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>差出人メールアドレス（From）<span style="color:#e53e3e;">*</span></label>
                        <input type="email" name="from_email" id="site-from-email" class="form-control" required placeholder="例: no-reply@cc-circle.net">
                    </div>
                    <div class="form-group">
                        <label>差出人名<span style="color:#e53e3e;">*</span></label>
                        <input type="text" name="from_name" id="site-from-name" class="form-control" required placeholder="例: サポートデスク">
                    </div>
                </div>

                <div class="form-group">
                    <label>メール署名（返信メールの末尾に自動挿入されます）</label>
                    <textarea name="signature" id="site-signature" class="form-control" style="height: 85px; resize: vertical;"></textarea>
                </div>

                <div class="section-divider">SMTP認証送信設定（Google Workspace / 迷惑メール防止）</div>
                <div class="smtp-box">
                    <div class="form-note" style="margin-top:0; margin-bottom:10px;">
                        ※Google Workspace（<code>smtp.gmail.com</code>）やロリポップ（<code>smtp.lolipop.jp</code>）のメールサーバー経由で直接送信する設定です。迷惑メール入りや不達を確実に防げます。
                    </div>
                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>SMTPサーバー（ホスト名）</label>
                            <input type="text" name="smtp_host" id="site-smtp-host" class="form-control" placeholder="例: smtp.gmail.com">
                        </div>
                        <div class="form-group">
                            <label>ポート番号</label>
                            <select name="smtp_port" id="site-smtp-port" class="form-control">
                                <option value="465">465 (SSL)</option>
                                <option value="587">587 (TLS)</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>SMTPユーザー名（メールアドレス）</label>
                            <input type="text" name="smtp_user" id="site-smtp-user" class="form-control" placeholder="例: no-reply@cc-circle.net">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>SMTPパスワード / アプリパスワード</label>
                            <input type="password" name="smtp_pass" id="site-smtp-pass" class="form-control" placeholder="パスワードを入力">
                            <div class="form-note" id="smtp-pass-note"></div>
                        </div>
                    </div>

                    <div class="test-mail-row">
                        <input type="email" id="test-mail-to" class="form-control" placeholder="テスト送信先メールアドレス（例: 自分のGmail）">
                        <button type="button" class="btn-test" id="btn-send-test-mail">テストメール送信</button>
                    </div>
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
    const baseUrl = <?php echo json_encode($base_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    let sitesData = [];

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }

    function buildGtmBotCode(siteId) {
        const safeId = String(siteId).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        return `<script>\n(function() {\n  var siteId = '${safeId}';\n  if (document.getElementById('tc-plus-bot-iframe')) return;\n  var iframe = document.createElement('iframe');\n  iframe.id = 'tc-plus-bot-iframe';\n  iframe.src = '${baseUrl}/bot.php?site=' + encodeURIComponent(siteId);\n  iframe.style.position = 'fixed';\n  iframe.style.bottom = '0px';\n  iframe.style.right = '16px';\n  iframe.style.width = '390px';\n  iframe.style.height = '580px';\n  iframe.style.maxWidth = '95vw';\n  iframe.style.maxHeight = '88vh';\n  iframe.style.border = 'none';\n  iframe.style.zIndex = '99999';\n  iframe.style.background = 'transparent';\n  iframe.setAttribute('allowtransparency', 'true');\n  document.body.appendChild(iframe);\n})();\n<\/script>`;
    }

    async function loadSites() {
        try {
            const res = await fetch('admin_sites_api.php?action=list', { cache: 'no-store' });
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

            sitesData = data.sites || [];
            const container = document.getElementById('site-list-container');
            container.innerHTML = sitesData.map(s => {
                const formUrl = `${baseUrl}/form.php?site=${encodeURIComponent(s.id)}`;
                const botUrl  = `${baseUrl}/bot.php?site=${encodeURIComponent(s.id)}`;
                const iframeFormCode = `<iframe src="${formUrl}" width="100%" height="620" style="border:none;"></iframe>`;
                const iframeBotCode  = `<iframe src="${botUrl}" style="position:fixed; bottom:0; right:16px; width:390px; height:580px; max-width:95vw; max-height:88vh; border:none; z-index:99999; background:transparent;" allowtransparency="true"></iframe>`;
                const gtmBotCode     = buildGtmBotCode(s.id);

                const urlLink = s.url
                    ? `<a href="${esc(s.url)}" target="_blank" rel="noopener">${esc(s.url)} ↗</a>`
                    : `<span style="color:#a0aec0;">設置URL未設定</span>`;
                const smtpBadge = (s.smtp_host && s.has_smtp_pass)
                    ? `<span class="smtp-badge-on">SMTP認証: 有効 (${esc(s.smtp_host)})</span>`
                    : `<span class="smtp-badge-off">SMTP未設定（標準送信）</span>`;

                return `
                <div class="site-card">
                    <div class="site-card-top">
                        <div class="site-info">
                            <div class="site-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                            </div>
                            <div>
                                <div class="site-name">${esc(s.name)}</div>
                                <div class="site-meta">
                                    <span class="site-id-badge">ID: ${esc(s.id)}</span>
                                    <span class="mail-setting-badge">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                        ${esc(s.from_name)} &lt;${esc(s.from_email)}&gt;
                                    </span>
                                    ${smtpBadge}
                                    ${urlLink}
                                </div>
                            </div>
                        </div>
                        <div class="site-actions">
                            <a href="admin_bot.php" class="btn-action" title="チャットボットデザイン・シナリオ編集">
                                <span>ボット設定 ↗</span>
                            </a>
                            <a href="${esc(formUrl)}" target="_blank" class="btn-action" title="フォームをプレビュー">
                                <span>フォーム確認 ↗</span>
                            </a>
                            <button type="button" class="btn-action btn-edit" title="編集" data-id="${esc(s.id)}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                <span>編集</span>
                            </button>
                            <button type="button" class="btn-action btn-delete" title="削除" data-id="${esc(s.id)}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="embed-grid">
                        <div class="embed-box">
                            <div class="embed-header">
                                <span class="embed-label">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
                                    <span>お問い合わせフォーム埋め込み用 iframe</span>
                                </span>
                                <button type="button" class="btn-copy">
                                    <span>コピー</span>
                                </button>
                            </div>
                            <textarea class="embed-code-input" readonly onclick="this.select();">${esc(iframeFormCode)}</textarea>
                        </div>

                        <div class="embed-box" style="background:#f0fff4; border-color:#c6f6d5;">
                            <div class="embed-header">
                                <span class="embed-label" style="color:#276749;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                    <span>右下チャットボット埋め込み用 iframe</span>
                                </span>
                                <button type="button" class="btn-copy" style="background:#28a745;">
                                    <span>コピー</span>
                                </button>
                            </div>
                            <textarea class="embed-code-input" readonly onclick="this.select();">${esc(iframeBotCode)}</textarea>
                        </div>

                        <div class="embed-box" style="background:#ebf8ff; border-color:#bee3f8;">
                            <div class="embed-header">
                                <span class="embed-label" style="color:#2b6cb0;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
                                    <span>GTM用 チャットボット（カスタムHTML）</span>
                                </span>
                                <button type="button" class="btn-copy" style="background:#29a3e5;">
                                    <span>コピー</span>
                                </button>
                            </div>
                            <textarea class="embed-code-input" readonly onclick="this.select();">${esc(gtmBotCode)}</textarea>
                        </div>
                    </div>
                </div>`;
            }).join('');
        } catch (err) {
            console.error(err);
        }
    }

    document.getElementById('btn-add-site').addEventListener('click', function() {
        document.getElementById('form-site').reset();
        document.getElementById('site-id').value = '';
        const baseSite = sitesData[0] || {};
        document.getElementById('site-from-email').value = '';
        document.getElementById('site-from-name').value = '';
        document.getElementById('site-signature').value = baseSite.signature || "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------";
        document.getElementById('site-smtp-port').value = '465';
        document.getElementById('smtp-pass-note').textContent = '';
        document.getElementById('modal-title').textContent = 'サイトを追加';
        document.getElementById('modal-site').classList.add('open');
    });

    document.addEventListener('click', async function(e) {
        const editBtn = e.target.closest('.btn-edit');
        if (editBtn) {
            const targetId = editBtn.dataset.id;
            const site = sitesData.find(s => s.id === targetId);
            if (!site) return;

            document.getElementById('form-site').reset();
            document.getElementById('site-id').value = site.id;
            document.getElementById('site-name').value = site.name || '';
            document.getElementById('site-url').value = site.url || '';
            document.getElementById('site-from-email').value = site.from_email || '';
            document.getElementById('site-from-name').value = site.from_name || '';
            document.getElementById('site-signature').value = site.signature || '';
            document.getElementById('site-smtp-host').value = site.smtp_host || '';
            document.getElementById('site-smtp-port').value = site.smtp_port || '465';
            document.getElementById('site-smtp-user').value = site.smtp_user || '';
            document.getElementById('smtp-pass-note').textContent = site.has_smtp_pass
                ? '※設定済み（変更する場合のみ入力）'
                : '';
            document.getElementById('modal-title').textContent = 'サイトを編集';
            document.getElementById('modal-site').classList.add('open');
            return;
        }

        const delBtn = e.target.closest('.btn-delete');
        if (delBtn) {
            if (!confirm('このサイトを削除してもよろしいですか？\n※すでにこのサイトから受信した過去のチケット履歴は消えません。')) return;
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'delete');
            fd.append('id', delBtn.dataset.id);
            const res = await fetch('admin_sites_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                loadSites();
            } else {
                alert(data.error);
            }
            return;
        }

        const copyBtn = e.target.closest('.btn-copy');
        if (copyBtn) {
            const ta = copyBtn.closest('.embed-box').querySelector('textarea');
            const code = ta.value;
            const span = copyBtn.querySelector('span');
            const origText = span.textContent;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(code);
                } else {
                    ta.select();
                    document.execCommand('copy');
                }
                copyBtn.classList.add('copied');
                span.textContent = 'コピー完了！';
                setTimeout(() => {
                    copyBtn.classList.remove('copied');
                    span.textContent = origText;
                }, 2000);
            } catch (err) {
                alert('コピーに失敗しました。枠内のテキストを手動でコピーしてください。');
            }
            return;
        }

        const closeBtn = e.target.closest('.modal-close');
        if (closeBtn) {
            closeBtn.closest('.modal-overlay').classList.remove('open');
        }
    });

    document.getElementById('btn-send-test-mail').addEventListener('click', async function() {
        const testTo = document.getElementById('test-mail-to').value.trim();
        if (!testTo) {
            alert('テスト送信先のメールアドレスを入力してください。');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.textContent = '送信中...';

        try {
            const fd = new FormData(document.getElementById('form-site'));
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'test_mail');
            fd.append('test_to', testTo);

            const res = await fetch('admin_sites_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert('テストメールを送信しました！\n受信トレイをご確認ください。');
            } else {
                alert('送信失敗：\n' + (data.error || '不明なエラー'));
            }
        } catch (err) {
            alert('通信エラーが発生しました。');
        } finally {
            btn.disabled = false;
            btn.textContent = 'テストメール送信';
        }
    });

    document.getElementById('form-site').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'save');
        const res = await fetch('admin_sites_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('modal-site').classList.remove('open');
            loadSites();
        } else {
            alert(data.error || '保存に失敗しました');
        }
    });

    loadSites();
});
</script>
</body>
</html>