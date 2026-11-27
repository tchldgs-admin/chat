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
$is_admin   = ($admin_role === 'admin');
$csrf_token = $_SESSION['csrf_token'];

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
    <title>チャットボット設定 - TC Plus</title>
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
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; color: var(--text-main); font-size: 13px; background: var(--bg-light); }
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
        .top-nav li a:hover { color: var(--primary-blue); }
        .top-nav li.active a { color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); }
        .header-right { display: flex; align-items: center; gap: 14px; color: #666; }
        .logout-link { color: #666; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e0; }

        .layout-wrapper { display: flex; padding-top: 54px; min-height: 100vh; }

        /* 左サイドバー */
        .sidebar { width: 195px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); padding: 16px 0; position: fixed; top: 54px; bottom: 0; left: 0; overflow-y: auto; }
        .side-group-title { font-size: 11px; color: #a0aec0; font-weight: bold; padding: 0 18px; margin-bottom: 4px; margin-top: 16px; }
        .side-menu { list-style: none; }
        .side-menu li a { display: flex; align-items: center; gap: 10px; padding: 10px 18px; color: #4a5568; text-decoration: none; font-size: 13px; cursor: pointer; }
        .side-menu li a:hover { background: #e8edf3; }
        .side-menu li.active a { background: #e2ecf7; color: #1a365d; font-weight: bold; border-left: 3px solid var(--primary-blue); }

        /* メインエリア */
        .main-content { flex: 1; margin-left: 195px; padding: 22px 28px; }
        .top-bar-controls {
            background: #fff; border: 1px solid var(--border-color); border-radius: 6px;
            padding: 14px 18px; margin-bottom: 18px;
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;
        }
        .site-selector-group { display: flex; align-items: center; gap: 10px; }
        .form-control { padding: 7px 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 13px; background: #fff; }
        .form-control:focus { outline: none; border-color: var(--primary-blue); }
        .btn-primary { background: var(--primary-blue); color: #fff; border: none; padding: 8px 18px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-primary:hover { background: #2188c4; }
        .btn-navy { background: var(--navy-btn); color: #fff; border: none; padding: 7px 14px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-navy:hover { background: var(--navy-btn-hover); }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* デザイン設定2カラム */
        .design-grid { display: grid; grid-template-columns: 1fr 410px; gap: 20px; align-items: start; }
        .card-box { background: #fff; border: 1px solid var(--border-color); border-radius: 6px; padding: 20px; margin-bottom: 16px; }
        .card-title { font-size: 15px; font-weight: bold; color: #2d3748; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 16px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12.5px; color: #4a5568; }
        .form-note { font-size: 11px; color: #718096; margin-top: 4px; line-height: 1.5; }

        .preview-sticky { position: sticky; top: 74px; }
        .preview-frame-box {
            background: linear-gradient(135deg, #e2e8f0 0%, #edf2f7 100%);
            border: 1px solid #cbd5e0; border-radius: 8px;
            height: 600px; display: flex; flex-direction: column; overflow: hidden;
        }
        .preview-bar {
            background: #2d3748; color: #fff; padding: 8px 12px; font-size: 11px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .preview-iframe { width: 100%; flex: 1; border: none; background: transparent; }

        /* ツリーエディタ＆ドラッグ並び替え */
        .tree-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .tree-nodes-list { display: flex; flex-direction: column; gap: 16px; }
        .tree-node-card {
            background: #fff; border: 1px solid #cbd5e0; border-left: 5px solid var(--primary-blue);
            border-radius: 6px; padding: 16px 18px; box-shadow: 0 2px 5px rgba(0,0,0,0.04);
        }
        .tree-node-card.is-start { border-left-color: #38a169; }
        .tree-node-card.is-form { border-left-color: #d69e2e; }
        .node-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px; }
        .node-badge { background: #ebf8ff; color: #2b6cb0; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 4px; }
        .tree-node-card.is-start .node-badge { background: #f0fff4; color: #276749; }
        .tree-node-card.is-form .node-badge { background: #fefcbf; color: #744210; }
        .option-edit-row {
            display: grid; grid-template-columns: 28px 1fr 240px 36px; gap: 8px; align-items: center;
            background: #f8fafc; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 6px;
            transition: background 0.15s, border-color 0.15s, opacity 0.15s;
        }
        .option-edit-row.dragging {
            opacity: 0.45;
            background: #ebf8ff;
            border-color: var(--primary-blue);
        }
        .option-edit-row.drag-over {
            border-top: 3px solid var(--primary-blue);
        }
        .drag-handle {
            cursor: grab;
            color: #a0aec0;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            user-select: none;
            line-height: 1;
        }
        .drag-handle:hover { color: #4a5568; }
        .drag-handle:active { cursor: grabbing; }
        .btn-del-sm { background: #fff5f5; border: 1px solid #fed7d7; color: #e53e3e; height: 30px; border-radius: 4px; cursor: pointer; font-weight: bold; }

        /* ナレッジベース（Q&A）テーブル・カード */
        .kb-toolbar {
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px;
            margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);
        }
        .kb-item {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px;
            margin-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-start; gap: 14px;
        }
        .kb-item:hover { border-color: #cbd5e0; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
        .kb-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 6px; }
        .kb-cat-pill { background: #ebf8ff; color: #2b6cb0; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 4px; }
        .kb-kw-pill { background: #edf2f7; color: #4a5568; font-size: 11px; padding: 2px 7px; border-radius: 4px; }
        .kb-hit-pill { background: #f0fff4; color: #276749; font-size: 11px; padding: 2px 7px; border-radius: 4px; border: 1px solid #c6f6d5; }
        .kb-q { font-size: 14px; font-weight: bold; color: #2d3748; margin-bottom: 6px; }
        .kb-a { font-size: 12.5px; color: #4a5568; white-space: pre-wrap; line-height: 1.55; background: #f8fafc; padding: 8px 10px; border-radius: 4px; }

        /* モーダル */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-dialog { background: #fff; width: 540px; max-width: 94vw; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 15px; }
        .modal-body { padding: 18px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 12px 18px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 8px; }
        .btn-cancel { background: #fff; border: 1px solid #cbd5e0; color: #4a5568; padding: 7px 14px; border-radius: 4px; font-weight: bold; cursor: pointer; }

        .embed-code-box {
            width: 100%; font-family: monospace; font-size: 12px;
            background: #f8fafc; border: 1px solid #cbd5e0; border-radius: 4px;
            padding: 10px; height: 78px; resize: none;
        }
        .btn-copy-code {
            background: var(--navy-btn); color: #fff; border: none; padding: 5px 12px;
            border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer;
        }
        .btn-copy-code:hover { background: var(--navy-btn-hover); }
        .btn-copy-code.copied { background: #38a169 !important; }
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
            <li class="active"><a href="admin_bot.php">チャット</a></li>
            <li><a href="admin_visitors.php">訪問者</a></li>
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
        <div class="side-group-title" style="margin-top:2px;">チャット設定</div>
        <ul class="side-menu" id="bot-side-tabs">
            <li class="active" data-tab="tab-design"><a><span>🎨 デザイン・起動ボタン</span></a></li>
            <li data-tab="tab-tree"><a><span>🌲 ツリーエディタ（シナリオ）</span></a></li>
            <li data-tab="tab-kb"><a><span>📚 ナレッジベース（Q&A）</span></a></li>
            <li data-tab="tab-func"><a><span>⚙️ 機能・営業時間・埋め込み</span></a></li>
        </ul>
        <?php if ($is_admin): ?>
        <div class="side-group-title">共通設定</div>
        <ul class="side-menu">
            <li><a href="admin_sites.php"><span>🌐 サイト管理</span></a></li>
            <li><a href="admin_users.php"><span>👥 担当者管理</span></a></li>
        </ul>
        <?php endif; ?>
    </aside>

    <main class="main-content">
        <div class="top-bar-controls">
            <div class="site-selector-group">
                <span style="font-weight:bold; color:#2d3748;">設定対象サイト：</span>
                <select id="select-target-site" class="form-control" style="min-width:220px; font-weight:bold;"></select>
                <label style="display:inline-flex; align-items:center; gap:6px; margin-left:12px; cursor:pointer; font-weight:bold;">
                    <input type="checkbox" id="bot-enabled" checked> チャットボットを有効にする
                </label>
            </div>
            <?php if ($is_admin): ?>
            <button type="button" class="btn-primary" id="btn-save-bot">設定を保存する</button>
            <?php else: ?>
            <span style="font-size:12px; color:#718096;">※閲覧専用モード（設定変更は管理者権限が必要です）</span>
            <?php endif; ?>
        </div>

        <!-- タブ1：デザイン・起動ボタン設定 -->
        <div class="tab-panel active" id="tab-design">
            <div class="design-grid">
                <div>
                    <div class="card-box">
                        <div class="card-title">基本デザイン・ヘッダー設定</div>
                        <div style="display:grid; grid-template-columns:140px 1fr; gap:12px;">
                            <div class="form-group">
                                <label>テーマカラー</label>
                                <input type="color" id="bot-theme-color" value="#28a745" style="width:100%; height:36px; border:1px solid #cbd5e0; border-radius:4px; cursor:pointer;">
                            </div>
                            <div class="form-group">
                                <label>ヘッダータイトル</label>
                                <input type="text" id="bot-header-title" class="form-control" style="width:100%;" value="サポートチャット">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group">
                                <label>サブタイトル</label>
                                <input type="text" id="bot-header-subtitle" class="form-control" style="width:100%;" value="ご質問をお選びください">
                            </div>
                            <div class="form-group">
                                <label>ボットアイコン</label>
                                <select id="bot-icon-type" class="form-control" style="width:100%;">
                                    <option value="operator">オペレーター（ヘッドセット）</option>
                                    <option value="robot">チャットボット（ロボット）</option>
                                    <option value="chat">吹き出しアイコン</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-box">
                        <div class="card-title">折りたたみ時の起動ボタン＆アイキャッチャー設定</div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group">
                                <label>起動ボタンの形状</label>
                                <select id="bot-launcher-type" class="form-control" style="width:100%;">
                                    <option value="bar">バー型（テキスト表示・推奨）</option>
                                    <option value="circle">丸型アイコンのみ</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>起動バーの表示テキスト</label>
                                <input type="text" id="bot-launcher-text" class="form-control" style="width:100%;" value="お問い合わせはこちらをクリック">
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" id="bot-eyecatcher-enabled" checked>
                                起動ボタンの上に「アイキャッチャー画像」を表示する
                            </label>
                        </div>

                        <div class="form-group">
                            <label>アイキャッチャー画像の指定（空欄の場合は標準のスマホ案内イラストを使用）</label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" id="bot-eyecatcher-url" class="form-control" style="flex:1;" placeholder="画像URL または 右のボタンからアップロード">
                                <input type="file" id="eyecatcher-file-input" accept="image/*" style="display:none;">
                                <button type="button" class="btn-navy" id="btn-upload-eyecatcher">画像アップロード</button>
                                <button type="button" class="btn-navy" style="background:#718096;" id="btn-clear-eyecatcher">標準に戻す</button>
                            </div>
                            <div class="form-note">※PNG / JPG / GIF / WebP（2MB以内）をアップロードできます。</div>
                        </div>
                    </div>
                </div>

                <div class="preview-sticky">
                    <div class="preview-frame-box">
                        <div class="preview-bar">
                            <span>📱 テストプレビュー（履歴・チケット・評価には記録されません）</span>
                            <button type="button" id="btn-reload-preview" style="background:#4a5568; color:#fff; border:none; padding:3px 8px; border-radius:3px; font-size:11px; cursor:pointer;">再読込</button>
                        </div>
                        <iframe id="bot-preview-iframe" class="preview-iframe" src="about:blank"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <!-- タブ2：ツリーエディタ（シナリオ分岐） -->
        <div class="tab-panel" id="tab-tree">
            <div class="card-box">
                <div class="tree-toolbar">
                    <div>
                        <div style="font-size:16px; font-weight:bold; color:#2d3748;">ツリーエディタ（会話シナリオ分岐設定）</div>
                        <div style="font-size:12px; color:#718096;">左端の「⋮⋮」をドラッグして選択肢の順序を入れ替えられます。分岐先に「チャット終了（満足度アンケート）」を選ぶと終了時に評価画面へ切り替わります。</div>
                    </div>
                    <button type="button" class="btn-primary" id="btn-add-node">＋ 新しい分岐ステップを追加</button>
                </div>

                <div class="tree-nodes-list" id="tree-nodes-container"></div>
            </div>
        </div>

        <!-- タブ3：ナレッジベース（Q&A自動応答管理・外部AI不使用） -->
        <div class="tab-panel" id="tab-kb">
            <div class="card-box">
                <div class="kb-toolbar">
                    <div>
                        <div style="font-size:16px; font-weight:bold; color:#2d3748;">ナレッジベース（Q&A自動マッチング応答）</div>
                        <div style="font-size:12px; color:#718096;">訪問者がチャット欄にフリーワードを入力した際、登録した質問文・関連キーワードをもとに自動回答します（外部AI API不使用）。</div>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn-navy" id="btn-kb-sample">サンプルQ&A自動登録</button>
                        <button type="button" class="btn-navy" id="btn-kb-import">CSVインポート</button>
                        <input type="file" id="kb-csv-input" accept=".csv" style="display:none;">
                        <a href="#" id="btn-kb-export" class="btn-navy">CSV出力</a>
                        <button type="button" class="btn-primary" id="btn-kb-add">＋ Q&Aを追加</button>
                    </div>
                </div>

                <div style="margin-bottom:14px; display:flex; gap:10px; align-items:center;">
                    <input type="text" id="kb-search-input" class="form-control" style="width:320px;" placeholder="質問文・キーワード・回答文で絞り込み...">
                    <span id="kb-count-label" style="font-size:12px; color:#718096;">0 件</span>
                </div>

                <div id="kb-list-container"></div>
            </div>
        </div>

        <!-- タブ4：機能・営業時間・iframe＆GTM埋め込みコード -->
        <div class="tab-panel" id="tab-func">
            <div class="card-box">
                <div class="card-title">HTML埋め込み用コード（右下フローティング表示）</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label style="margin-bottom:0;">① 直接埋め込み用 iframe コード（Bitfan等のHTML欄向け）</label>
                            <button type="button" class="btn-copy-code" data-target="bot-iframe-code" style="background:#28a745;">コピー</button>
                        </div>
                        <textarea id="bot-iframe-code" class="embed-code-box" readonly onclick="this.select();"></textarea>
                        <div class="form-note">※scriptタグが使えないサイトのHTML編集欄へそのまま貼り付けられます。</div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label style="margin-bottom:0;">② GTM用 カスタムHTMLコード（Googleタグマネージャー向け）</label>
                            <button type="button" class="btn-copy-code" data-target="bot-gtm-code" style="background:#29a3e5;">コピー</button>
                        </div>
                        <textarea id="bot-gtm-code" class="embed-code-box" readonly onclick="this.select();"></textarea>
                        <div class="form-note">※GTMの「カスタムHTML」タグへそのまま貼り付けて配信できます。</div>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <div class="card-title">入力・自動展開・営業時間外メッセージ設定</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div class="form-group">
                        <label>初回アクセス時の自動オープン（秒数指定）</label>
                        <input type="number" id="bot-auto-open" class="form-control" style="width:100%;" min="0" max="120" value="0">
                        <div class="form-note">※「0」の場合は自動展開せず、折りたたみ状態（アイキャッチャー＋起動バー）で待機します。</div>
                    </div>
                    <div class="form-group">
                        <label>フリーワード入力欄の表示（ナレッジベース検索）</label>
                        <select id="bot-allow-free-input" class="form-control" style="width:100%;">
                            <option value="1">表示する（フリーワード入力でナレッジベースQ&A検索）</option>
                            <option value="0">非表示にする（選択肢ボタンのみで進行）</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>ナレッジベースに該当がない時の定型応答（フォールバックメッセージ）</label>
                    <textarea id="bot-fallback-message" class="form-control" style="width:100%; height:64px;"></textarea>
                </div>

                <div class="form-group" style="border-top:1px solid #e2e8f0; padding-top:14px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" id="bot-schedule-enabled"> 営業時間外メッセージを有効にする
                    </label>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>営業開始時刻</label>
                        <input type="time" id="bot-schedule-start" class="form-control" style="width:100%;" value="09:00">
                    </div>
                    <div class="form-group">
                        <label>営業終了時刻</label>
                        <input type="time" id="bot-schedule-end" class="form-control" style="width:100%;" value="18:00">
                    </div>
                </div>
                <div class="form-group">
                    <label>営業時間外の冒頭案内メッセージ</label>
                    <textarea id="bot-off-hours-message" class="form-control" style="width:100%; height:64px;"></textarea>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- モーダル：ナレッジベース（Q&A）追加・編集 -->
<div class="modal-overlay" id="modal-kb">
    <div class="modal-dialog">
        <div class="modal-header">
            <span id="modal-kb-title">Q&Aを追加</span>
            <span class="modal-close" style="cursor:pointer;">×</span>
        </div>
        <form id="form-kb">
            <div class="modal-body">
                <input type="hidden" id="kb-id" value="0">
                <div class="form-group">
                    <label>カテゴリ（任意）</label>
                    <input type="text" id="kb-category" class="form-control" style="width:100%;" placeholder="例：営業時間・アクセス、ご予約について">
                </div>
                <div class="form-group">
                    <label>質問文 *</label>
                    <input type="text" id="kb-question" class="form-control" style="width:100%;" required placeholder="例：営業時間を教えてください">
                </div>
                <div class="form-group">
                    <label>関連キーワード（カンマ区切り・複数入力推奨）</label>
                    <input type="text" id="kb-keywords" class="form-control" style="width:100%;" placeholder="例：営業時間,何時まで,定休日,休み,オープン">
                    <div class="form-note">※お客様が入力しそうな単語や言い換え表現をカンマ区切りで入れておくとヒット率が大幅に上がります。</div>
                </div>
                <div class="form-group">
                    <label>回答文 *</label>
                    <textarea id="kb-answer" class="form-control" style="width:100%; height:130px;" required placeholder="ボットが自動返信する回答テキストを入力してください"></textarea>
                </div>
                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" id="kb-enabled" checked> このQ&Aを有効にする
                </label>
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
    const baseUrl   = <?php echo json_encode($base_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    let currentSiteId = '';
    let treeNodes = [];
    let kbList = [];
    let dragSource = null;

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function updateEmbedCode() {
        const iframeCode = `<iframe src="${baseUrl}/bot.php?site=${encodeURIComponent(currentSiteId)}" style="position:fixed; bottom:0; right:16px; width:390px; height:580px; max-width:95vw; max-height:88vh; border:none; z-index:99999; background:transparent;" allowtransparency="true"></iframe>`;
        const safeId = String(currentSiteId).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        const gtmCode = `<script>\n(function() {\n  var siteId = '${safeId}';\n  if (document.getElementById('tc-plus-bot-iframe')) return;\n  var iframe = document.createElement('iframe');\n  iframe.id = 'tc-plus-bot-iframe';\n  iframe.src = '${baseUrl}/bot.php?site=' + encodeURIComponent(siteId);\n  iframe.style.position = 'fixed';\n  iframe.style.bottom = '0px';\n  iframe.style.right = '16px';\n  iframe.style.width = '390px';\n  iframe.style.height = '580px';\n  iframe.style.maxWidth = '95vw';\n  iframe.style.maxHeight = '88vh';\n  iframe.style.border = 'none';\n  iframe.style.zIndex = '99999';\n  iframe.style.background = 'transparent';\n  iframe.setAttribute('allowtransparency', 'true');\n  document.body.appendChild(iframe);\n})();\n<\/script>`;

        document.getElementById('bot-iframe-code').value = iframeCode;
        document.getElementById('bot-gtm-code').value = gtmCode;
        document.getElementById('btn-kb-export').href = `bot_api.php?action=kb_export_csv&site_id=${encodeURIComponent(currentSiteId)}`;
    }

    function reloadPreview() {
        const iframe = document.getElementById('bot-preview-iframe');
        iframe.src = `bot.php?site=${encodeURIComponent(currentSiteId)}&preview=1&_t=${Date.now()}`;
    }

    function renderKnowledgeList() {
        const filter = (document.getElementById('kb-search-input').value || '').trim().toLowerCase();
        const filtered = kbList.filter(item => {
            if (!filter) return true;
            return (item.question || '').toLowerCase().includes(filter)
                || (item.keywords || '').toLowerCase().includes(filter)
                || (item.answer || '').toLowerCase().includes(filter)
                || (item.category || '').toLowerCase().includes(filter);
        });

        document.getElementById('kb-count-label').textContent = `${filtered.length} 件`;
        const container = document.getElementById('kb-list-container');

        if (filtered.length === 0) {
            container.innerHTML = '<div style="padding:36px; text-align:center; color:#718096; background:#f8fafc; border-radius:6px;">登録されているQ&Aがありません。「＋ Q&Aを追加」または「サンプルQ&A自動登録」から登録してください。</div>';
            return;
        }

        container.innerHTML = filtered.map(item => {
            const catBadge = item.category ? `<span class="kb-cat-pill">${esc(item.category)}</span>` : '';
            const kwBadge  = item.keywords ? `<span class="kb-kw-pill">キーワード: ${esc(item.keywords)}</span>` : '';
            const hitBadge = `<span class="kb-hit-pill">ヒット数: ${item.hit_count || 0}回</span>`;
            const offBadge = Number(item.enabled) === 0 ? `<span style="background:#fed7d7; color:#9b2c2c; font-size:11px; padding:2px 6px; border-radius:4px;">無効</span>` : '';

            return `
            <div class="kb-item">
                <div style="flex:1;">
                    <div class="kb-meta">${catBadge}${kwBadge}${hitBadge}${offBadge}</div>
                    <div class="kb-q">Q. ${esc(item.question)}</div>
                    <div class="kb-a">${esc(item.answer)}</div>
                </div>
                <div style="display:flex; gap:6px; flex-shrink:0;">
                    <button type="button" class="btn-navy btn-kb-edit" data-id="${item.id}">編集</button>
                    <button type="button" class="btn-del-sm btn-kb-del" data-id="${item.id}" style="padding:0 10px;">削除</button>
                </div>
            </div>`;
        }).join('');
    }

    function renderTreeEditor() {
        const container = document.getElementById('tree-nodes-container');
        const nodeOptionsHtml = (selectedId) => {
            return '<option value="">（分岐先なし・終了）</option>'
                + `<option value="__end_chat__" ${selectedId === '__end_chat__' ? 'selected' : ''}>🏁 チャット終了（満足度アンケート）</option>`
                + treeNodes.map(n => `<option value="${esc(n.id)}" ${n.id === selectedId ? 'selected' : ''}>${esc(n.title)} (${esc(n.id)})</option>`).join('');
        };

        container.innerHTML = treeNodes.map((node, idx) => {
            const isStart = (node.id === 'node_start');
            const isForm  = (node.action_type === 'form');
            const cardCls = isStart ? 'is-start' : (isForm ? 'is-form' : '');
            const badgeText = isStart ? 'スタート地点' : (isForm ? 'チケット自動起票フォーム' : `ステップ #${idx + 1}`);

            const optionsRows = (node.options || []).map((opt, oIdx) => `
                <div class="option-edit-row" draggable="true" data-nidx="${idx}" data-oidx="${oIdx}">
                    <span class="drag-handle" title="ドラッグして順序を変更">⋮⋮</span>
                    <input type="text" class="form-control opt-label" data-nidx="${idx}" data-oidx="${oIdx}" value="${esc(opt.label)}" placeholder="選択肢ボタンの文言">
                    <select class="form-control opt-next" data-nidx="${idx}" data-oidx="${oIdx}">${nodeOptionsHtml(opt.next_node_id)}</select>
                    <button type="button" class="btn-del-sm btn-del-opt" data-nidx="${idx}" data-oidx="${oIdx}" title="選択肢を削除">×</button>
                </div>
            `).join('');

            return `
            <div class="tree-node-card ${cardCls}">
                <div class="node-header">
                    <div style="display:flex; align-items:center; gap:10px; flex:1;">
                        <span class="node-badge">${badgeText}</span>
                        <input type="text" class="form-control node-title" data-nidx="${idx}" value="${esc(node.title)}" style="font-weight:bold; width:260px;" placeholder="ステップ管理名">
                        <select class="form-control node-action-type" data-nidx="${idx}" style="width:220px;">
                            <option value="options" ${node.action_type === 'options' ? 'selected' : ''}>通常メッセージ＋選択肢分岐</option>
                            <option value="form" ${node.action_type === 'form' ? 'selected' : ''}>お問い合わせ受付フォーム表示</option>
                        </select>
                    </div>
                    ${!isStart ? `<button type="button" class="btn-del-sm btn-del-node" data-nidx="${idx}" style="padding:0 10px;">削除</button>` : ''}
                </div>

                <div class="form-group">
                    <label>ボットの発言メッセージ</label>
                    <textarea class="form-control node-message" data-nidx="${idx}" style="width:100%; height:68px;">${esc(node.message)}</textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>外部リンクボタン文言（任意）</label>
                        <input type="text" class="form-control node-link-label" data-nidx="${idx}" value="${esc(node.link_label || '')}" placeholder="例：公式サイトを開く">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>外部リンク先URL（任意）</label>
                        <input type="url" class="form-control node-link-url" data-nidx="${idx}" value="${esc(node.link_url || '')}" placeholder="https://...">
                    </div>
                </div>

                ${node.action_type !== 'form' ? `
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="font-weight:bold; font-size:12px; color:#4a5568;">選択肢ボタン（ドラッグで並び替え・クリック時の分岐先）</label>
                        <button type="button" class="btn-navy btn-add-opt" data-nidx="${idx}" style="padding:4px 10px; font-size:11px;">＋ 選択肢を追加</button>
                    </div>
                    ${optionsRows || '<div style="font-size:11px; color:#a0aec0;">選択肢はありません</div>'}
                </div>` : '<div style="font-size:12px; color:#9c4221; background:#fffaf0; padding:8px 12px; border-radius:4px;">※このステップに到達すると、チャット内に「お名前・メール・件名・内容」入力フォームが表示され、送信時に既存チケット管理へ自動登録されます。</div>'}
            </div>`;
        }).join('');

        bindOptionDragEvents();
    }

    function bindOptionDragEvents() {
        const rows = document.querySelectorAll('.option-edit-row');
        rows.forEach(row => {
            row.addEventListener('dragstart', function(e) {
                if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT')) {
                    e.preventDefault();
                    return;
                }
                dragSource = {
                    nidx: Number(this.dataset.nidx),
                    oidx: Number(this.dataset.oidx)
                };
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            row.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                document.querySelectorAll('.option-edit-row').forEach(r => r.classList.remove('drag-over'));
                dragSource = null;
            });

            row.addEventListener('dragover', function(e) {
                if (!dragSource) return;
                const targetNidx = Number(this.dataset.nidx);
                if (dragSource.nidx !== targetNidx) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });

            row.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });

            row.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                if (!dragSource) return;
                const targetNidx = Number(this.dataset.nidx);
                const targetOidx = Number(this.dataset.oidx);
                if (dragSource.nidx !== targetNidx || dragSource.oidx === targetOidx) return;

                const opts = treeNodes[targetNidx].options;
                const [moved] = opts.splice(dragSource.oidx, 1);
                opts.splice(targetOidx, 0, moved);
                dragSource = null;
                renderTreeEditor();
            });
        });
    }

    async function loadBotSetting(siteId = '') {
        const url = 'bot_api.php?action=admin_get' + (siteId ? '&site_id=' + encodeURIComponent(siteId) : '');
        const res = await fetch(url, { cache: 'no-store' });
        if (res.status === 401) {
            window.location.replace('login.php');
            return;
        }
        const data = await res.json();
        if (!data.success) {
            alert(data.error || '読み込みエラー');
            return;
        }

        const sel = document.getElementById('select-target-site');
        sel.innerHTML = (data.sites || []).map(s => `<option value="${esc(s.id)}">${esc(s.name)}</option>`).join('');

        const st = data.setting;
        currentSiteId = st.site_id;
        sel.value = currentSiteId;

        document.getElementById('bot-enabled').checked            = (Number(st.enabled) === 1);
        document.getElementById('bot-theme-color').value          = st.theme_color || '#28a745';
        document.getElementById('bot-header-title').value         = st.header_title || '';
        document.getElementById('bot-header-subtitle').value      = st.header_subtitle || '';
        document.getElementById('bot-icon-type').value            = st.bot_icon_type || 'operator';
        document.getElementById('bot-launcher-type').value        = st.launcher_type || 'bar';
        document.getElementById('bot-launcher-text').value        = st.launcher_text || '';
        document.getElementById('bot-eyecatcher-enabled').checked = (Number(st.eyecatcher_enabled) === 1);
        document.getElementById('bot-eyecatcher-url').value       = st.eyecatcher_url || '';
        document.getElementById('bot-auto-open').value            = st.auto_open_seconds || 0;
        document.getElementById('bot-allow-free-input').value     = String(st.allow_free_input ?? 1);
        document.getElementById('bot-fallback-message').value     = st.fallback_message || '';
        document.getElementById('bot-schedule-enabled').checked   = (Number(st.schedule_enabled) === 1);
        document.getElementById('bot-schedule-start').value       = st.schedule_start || '09:00';
        document.getElementById('bot-schedule-end').value         = st.schedule_end || '18:00';
        document.getElementById('bot-off-hours-message').value    = st.off_hours_message || '';

        treeNodes = Array.isArray(st.tree) ? st.tree : [];
        kbList = Array.isArray(data.knowledge) ? data.knowledge : [];

        renderTreeEditor();
        renderKnowledgeList();
        updateEmbedCode();
        reloadPreview();
    }

    // サイドタブ切替
    document.querySelectorAll('#bot-side-tabs li').forEach(li => {
        li.addEventListener('click', function() {
            document.querySelectorAll('#bot-side-tabs li').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            const target = this.dataset.tab;
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.getElementById(target).classList.add('active');
        });
    });

    document.getElementById('select-target-site').addEventListener('change', function() {
        loadBotSetting(this.value);
    });

    document.getElementById('btn-reload-preview').addEventListener('click', reloadPreview);
    document.getElementById('kb-search-input').addEventListener('input', renderKnowledgeList);

    // ナレッジベース操作
    document.getElementById('btn-kb-add').addEventListener('click', function() {
        document.getElementById('form-kb').reset();
        document.getElementById('kb-id').value = '0';
        document.getElementById('kb-enabled').checked = true;
        document.getElementById('modal-kb-title').textContent = 'Q&Aを追加';
        document.getElementById('modal-kb').classList.add('open');
    });

    document.getElementById('btn-kb-sample').addEventListener('click', async function() {
        if (!confirm('現在のサイト内容に合わせた初期サンプルQ&A（4件）を自動登録しますか？')) return;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'kb_seed_sample');
        fd.append('site_id', currentSiteId);
        const res = await fetch('bot_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            loadBotSetting(currentSiteId);
        } else {
            alert(data.error || '登録に失敗しました');
        }
    });

    const csvInput = document.getElementById('kb-csv-input');
    document.getElementById('btn-kb-import').addEventListener('click', () => csvInput.click());
    csvInput.addEventListener('change', async function() {
        if (!this.files || !this.files[0]) return;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'kb_import_csv');
        fd.append('site_id', currentSiteId);
        fd.append('csv_file', this.files[0]);
        const res = await fetch('bot_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        this.value = '';
        if (data.success) {
            alert(`${data.imported} 件のQ&Aをインポートしました！`);
            loadBotSetting(currentSiteId);
        } else {
            alert(data.error || 'インポートに失敗しました');
        }
    });

    document.getElementById('form-kb').addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'kb_save');
        fd.append('site_id', currentSiteId);
        fd.append('id', document.getElementById('kb-id').value);
        fd.append('category', document.getElementById('kb-category').value);
        fd.append('question', document.getElementById('kb-question').value);
        fd.append('keywords', document.getElementById('kb-keywords').value);
        fd.append('answer', document.getElementById('kb-answer').value);
        if (document.getElementById('kb-enabled').checked) fd.append('enabled', '1');

        const res = await fetch('bot_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('modal-kb').classList.remove('open');
            loadBotSetting(currentSiteId);
        } else {
            alert(data.error || '保存に失敗しました');
        }
    });

    // ツリーエディタ操作
    document.getElementById('btn-add-node').addEventListener('click', function() {
        const newId = 'node_' + Math.random().toString(36).slice(2, 8);
        treeNodes.push({
            id: newId,
            title: '新しい案内ステップ',
            message: '案内メッセージを入力してください。',
            action_type: 'options',
            options: [
                { label: '最初のメニューに戻る', next_node_id: 'node_start' },
                { label: 'チャット終了', next_node_id: '__end_chat__' }
            ],
            link_label: '',
            link_url: ''
        });
        renderTreeEditor();
    });

    document.addEventListener('input', function(e) {
        const idx = e.target.dataset.nidx;
        if (idx === undefined) return;
        const node = treeNodes[Number(idx)];
        if (!node) return;

        if (e.target.classList.contains('node-title')) node.title = e.target.value;
        if (e.target.classList.contains('node-message')) node.message = e.target.value;
        if (e.target.classList.contains('node-link-label')) node.link_label = e.target.value;
        if (e.target.classList.contains('node-link-url')) node.link_url = e.target.value;
        if (e.target.classList.contains('opt-label')) {
            const oIdx = Number(e.target.dataset.oidx);
            if (node.options[oIdx]) node.options[oIdx].label = e.target.value;
        }
    });

    document.addEventListener('change', function(e) {
        const idx = e.target.dataset.nidx;
        if (idx === undefined) return;
        const node = treeNodes[Number(idx)];
        if (!node) return;

        if (e.target.classList.contains('node-action-type')) {
            node.action_type = e.target.value;
            renderTreeEditor();
        }
        if (e.target.classList.contains('opt-next')) {
            const oIdx = Number(e.target.dataset.oidx);
            if (node.options[oIdx]) node.options[oIdx].next_node_id = e.target.value;
        }
    });

    document.addEventListener('click', async function(e) {
        const copyCodeBtn = e.target.closest('.btn-copy-code');
        if (copyCodeBtn) {
            const ta = document.getElementById(copyCodeBtn.dataset.target);
            const origText = copyCodeBtn.textContent;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(ta.value);
                } else {
                    ta.select();
                    document.execCommand('copy');
                }
                copyCodeBtn.classList.add('copied');
                copyCodeBtn.textContent = 'コピー完了！';
                setTimeout(() => {
                    copyCodeBtn.classList.remove('copied');
                    copyCodeBtn.textContent = origText;
                }, 2000);
            } catch (err) {
                alert('コピーに失敗しました。');
            }
            return;
        }

        if (e.target.classList.contains('btn-add-opt')) {
            const idx = Number(e.target.dataset.nidx);
            treeNodes[idx].options = treeNodes[idx].options || [];
            treeNodes[idx].options.push({ label: '新しい選択肢', next_node_id: 'node_start' });
            renderTreeEditor();
        }
        if (e.target.classList.contains('btn-del-opt')) {
            const idx = Number(e.target.dataset.nidx);
            const oIdx = Number(e.target.dataset.oidx);
            treeNodes[idx].options.splice(oIdx, 1);
            renderTreeEditor();
        }
        if (e.target.classList.contains('btn-del-node')) {
            const idx = Number(e.target.dataset.nidx);
            if (!confirm('このステップを削除しますか？')) return;
            treeNodes.splice(idx, 1);
            renderTreeEditor();
        }
        if (e.target.classList.contains('btn-kb-edit')) {
            const id = Number(e.target.dataset.id);
            const item = kbList.find(x => Number(x.id) === id);
            if (!item) return;
            document.getElementById('kb-id').value = item.id;
            document.getElementById('kb-category').value = item.category || '';
            document.getElementById('kb-question').value = item.question || '';
            document.getElementById('kb-keywords').value = item.keywords || '';
            document.getElementById('kb-answer').value = item.answer || '';
            document.getElementById('kb-enabled').checked = (Number(item.enabled) === 1);
            document.getElementById('modal-kb-title').textContent = 'Q&Aを編集';
            document.getElementById('modal-kb').classList.add('open');
        }
        if (e.target.classList.contains('btn-kb-del')) {
            const id = Number(e.target.dataset.id);
            if (!confirm('このQ&Aを削除してもよろしいですか？')) return;
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'kb_delete');
            fd.append('site_id', currentSiteId);
            fd.append('id', String(id));
            const res = await fetch('bot_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) loadBotSetting(currentSiteId);
        }
        const closeBtn = e.target.closest('.modal-close');
        if (closeBtn) {
            closeBtn.closest('.modal-overlay').classList.remove('open');
        }
    });

    // アイキャッチャー画像アップロード
    const fileInput = document.getElementById('eyecatcher-file-input');
    document.getElementById('btn-upload-eyecatcher').addEventListener('click', () => fileInput.click());
    document.getElementById('btn-clear-eyecatcher').addEventListener('click', () => {
        document.getElementById('bot-eyecatcher-url').value = '';
    });
    fileInput.addEventListener('change', async function() {
        if (!this.files || !this.files[0]) return;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('action', 'upload_eyecatcher');
        fd.append('eyecatcher_file', this.files[0]);

        const res = await fetch('bot_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('bot-eyecatcher-url').value = data.url;
            alert('画像をアップロードしました。「設定を保存する」を押すとプレビューに反映されます。');
        } else {
            alert(data.error || 'アップロードに失敗しました');
        }
    });

    // 保存処理
    const btnSave = document.getElementById('btn-save-bot');
    if (btnSave) {
        btnSave.addEventListener('click', async function() {
            btnSave.disabled = true;
            btnSave.textContent = '保存中...';

            try {
                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('action', 'admin_save');
                fd.append('site_id', currentSiteId);
                if (document.getElementById('bot-enabled').checked) fd.append('enabled', '1');
                fd.append('theme_color', document.getElementById('bot-theme-color').value);
                fd.append('header_title', document.getElementById('bot-header-title').value);
                fd.append('header_subtitle', document.getElementById('bot-header-subtitle').value);
                fd.append('bot_icon_type', document.getElementById('bot-icon-type').value);
                fd.append('launcher_type', document.getElementById('bot-launcher-type').value);
                fd.append('launcher_text', document.getElementById('bot-launcher-text').value);
                if (document.getElementById('bot-eyecatcher-enabled').checked) fd.append('eyecatcher_enabled', '1');
                fd.append('eyecatcher_url', document.getElementById('bot-eyecatcher-url').value);
                fd.append('auto_open_seconds', document.getElementById('bot-auto-open').value);
                fd.append('allow_free_input', document.getElementById('bot-allow-free-input').value);
                fd.append('fallback_message', document.getElementById('bot-fallback-message').value);
                if (document.getElementById('bot-schedule-enabled').checked) fd.append('schedule_enabled', '1');
                fd.append('schedule_start', document.getElementById('bot-schedule-start').value);
                fd.append('schedule_end', document.getElementById('bot-schedule-end').value);
                fd.append('off_hours_message', document.getElementById('bot-off-hours-message').value);
                fd.append('tree_json', JSON.stringify(treeNodes));

                const res = await fetch('bot_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    reloadPreview();
                    alert('チャットボット設定を保存しました！');
                } else {
                    alert(data.error || '保存に失敗しました');
                }
            } finally {
                btnSave.disabled = false;
                btnSave.textContent = '設定を保存する';
            }
        });
    }

    loadBotSetting();
});
</script>
</body>
</html>