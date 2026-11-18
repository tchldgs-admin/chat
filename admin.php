<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
$admin_email = $_SESSION['admin_email'] ?? '管理者';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チケット - TC Plus</title>
    <!-- Google Material Icons & jQuery -->
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
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif;
            color: var(--text-main);
            background: #ffffff;
            font-size: 13px;
        }

        /* 上部ヘッダー（TC Plus風） */
        #top-header {
            height: 54px;
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
        }
        .header-left { display: flex; align-items: center; gap: 36px; height: 100%; }
        .brand-logo {
            display: flex; align-items: center; gap: 8px;
            font-size: 19px; font-weight: bold; color: var(--primary-blue);
            text-decoration: none;
        }
        .brand-logo .icon-box {
            background: var(--primary-blue); color: #fff;
            width: 30px; height: 30px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
        }
        .top-nav { display: flex; height: 100%; list-style: none; gap: 6px; }
        .top-nav li a {
            display: flex; align-items: center; height: 100%; padding: 0 14px;
            text-decoration: none; color: #555; font-weight: bold; font-size: 14px;
            position: relative;
        }
        .top-nav li.active a {
            color: var(--primary-blue);
            border-bottom: 3px solid var(--primary-blue);
        }
        .top-nav li.disabled a { color: #a0aec0; cursor: default; }
        .ticket-badge {
            background: #e53e3e; color: #fff; font-size: 11px; font-weight: bold;
            padding: 1px 6px; border-radius: 10px; margin-left: 5px; line-height: 1.4;
        }
        .header-right { display: flex; align-items: center; gap: 16px; color: #666; }
        .form-link-btn {
            font-size: 12px; color: var(--primary-blue); text-decoration: none;
            border: 1px solid var(--primary-blue); padding: 4px 10px; border-radius: 4px;
        }
        .form-link-btn:hover { background: #ebf8ff; }
        .logout-link {
            color: #666; text-decoration: none; font-size: 12px;
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e0;
        }
        .logout-link:hover { background: #f7fafc; color: #e53e3e; border-color: #feb2b2; }

        /* レイアウト全体 */
        .layout-wrapper { display: flex; padding-top: 54px; min-height: 100vh; }

        /* 左サイドバー */
        .sidebar {
            width: 185px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            padding: 16px 0;
            position: fixed;
            top: 54px; bottom: 0; left: 0;
            overflow-y: auto;
        }
        .side-menu { list-style: none; }
        .side-menu li a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 18px; color: #4a5568; text-decoration: none; font-size: 13px;
            transition: background 0.15s;
        }
        .side-menu li a:hover { background: #e8edf3; }
        .side-menu li.active a {
            background: #e2ecf7; color: #1a365d; font-weight: bold;
            border-left: 3px solid var(--primary-blue);
        }
        .side-menu .material-icons-round { font-size: 18px; color: #718096; }
        .side-menu li.status-11 .material-icons-round { color: #29a3e5; }
        .side-divider { border: none; border-top: 1px solid #dce3ec; margin: 12px 14px; }
        .side-buttons { padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
        .btn-navy {
            background: var(--navy-btn); color: #fff; border: none;
            padding: 9px 12px; border-radius: 4px; font-size: 12px; font-weight: bold;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
            text-decoration: none; transition: background 0.15s;
        }
        .btn-navy:hover { background: var(--navy-btn-hover); }
        .btn-navy .material-icons-round { font-size: 16px; }

        /* メインコンテンツ */
        .main-content { flex: 1; margin-left: 185px; padding: 20px 28px; }
        .breadcrumb { font-size: 11px; color: var(--text-sub); margin-bottom: 4px; }
        .page-title { font-size: 20px; font-weight: bold; margin-bottom: 16px; color: #2d3748; }

        /* 検索ボックス */
        .search-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .search-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .form-control {
            height: 32px; padding: 4px 10px; border: 1px solid #cbd5e0;
            border-radius: 3px; font-size: 12px; background: #fff; color: #333;
        }
        .form-control:focus { outline: none; border-color: var(--primary-blue); }
        .search-detail-area {
            display: none; margin-top: 10px; padding-top: 10px;
            border-top: 1px dashed #cbd5e0; gap: 12px; align-items: center;
        }
        .search-detail-area.open { display: flex; flex-wrap: wrap; }
        .detail-toggle {
            color: #4a5568; cursor: pointer; font-size: 12px;
            display: inline-flex; align-items: center; gap: 2px; user-select: none;
        }
        .btn-search {
            background: var(--navy-btn); color: #fff; border: none; height: 32px;
            padding: 0 14px; border-radius: 3px; font-size: 12px; font-weight: bold;
            cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-clear {
            background: #fff; color: #4a5568; border: 1px solid #cbd5e0; height: 32px;
            width: 32px; border-radius: 3px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }

        /* ツールバー（件数・CSV） */
        .list-toolbar {
            display: flex; justify-content: flex-end; align-items: center;
            gap: 12px; margin-bottom: 10px; font-size: 12px;
        }

        /* チケット一覧テーブル */
        .ticket-table-wrap { border-top: 1px solid var(--border-color); }
        .ticket-header-row {
            display: grid;
            grid-template-columns: 34px 28px 1fr 170px 140px 150px 110px 80px;
            align-items: center;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            padding: 9px 8px;
            font-weight: bold;
            color: #4a5568;
            font-size: 12px;
        }
        .sort-col { cursor: pointer; display: flex; align-items: center; gap: 2px; user-select: none; }
        .sort-col:hover { color: var(--primary-blue); }

        /* 一括操作バー */
        .bulk-bar {
            display: none; background: #ebf8ff; border-bottom: 1px solid #bee3f8;
            padding: 7px 12px; align-items: center; gap: 10px;
        }
        .bulk-bar.active { display: flex; }

        /* 各チケット行 */
        .ticket-item { border-bottom: 1px solid var(--border-color); transition: background 0.1s; }
        .ticket-item.alert-warning { background: #fefcbf; } /* 18時間以上経過 */
        .ticket-item.alert-danger { background: #fed7d7; }  /* 24時間以上経過 */
        .ticket-row {
            display: grid;
            grid-template-columns: 34px 28px 1fr 170px 140px 150px 110px 80px;
            align-items: center;
            padding: 10px 8px;
            cursor: pointer;
        }
        .ticket-row:hover { background: rgba(41, 163, 229, 0.06); }
        .ticket-item.unread .col-subject { font-weight: bold; }
        .unread-dot {
            width: 8px; height: 8px; border-radius: 50%; display: inline-block;
            background: transparent; cursor: pointer;
        }
        .ticket-item.unread .unread-dot { background: var(--primary-blue); }
        .col-subject {
            display: flex; align-items: center; gap: 6px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 12px;
            color: #2b6cb0;
        }
        .col-subject .mail-icon { font-size: 15px; color: #718096; }
        .col-visitor {
            display: flex; align-items: center; gap: 5px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 8px;
        }
        .col-visitor .material-icons-round { font-size: 15px; color: #a0aec0; }
        .col-assignee { color: #4a5568; font-size: 12px; }
        .col-elapsed { color: #4a5568; font-size: 12px; }
        .col-site { color: #4a5568; font-size: 12px; }
        .col-id { color: #4a5568; font-size: 12px; text-align: right; padding-right: 8px; }
        .status-pill {
            font-size: 10px; padding: 1px 6px; border-radius: 3px; font-weight: bold; color: #fff;
        }
        .st-11 { background: #e53e3e; }
        .st-12 { background: #3182ce; }
        .st-13 { background: #d69e2e; }
        .st-21 { background: #38a169; }
        .st-31 { background: #718096; }

        /* 展開されたチケット詳細（スレッド＆返信フォーム） */
        .ticket-detail-panel {
            display: none;
            background: #f9fbfd;
            border-top: 1px solid #cbd5e0;
            padding: 18px 24px 24px 42px;
        }
        .ticket-item.expanded .ticket-detail-panel { display: block; }
        .ticket-item.expanded .ticket-row { background: #edf2f7; font-weight: bold; }

        .detail-meta-bar {
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
            background: #fff; border: 1px solid var(--border-color); border-radius: 4px;
            padding: 10px 14px; margin-bottom: 14px; gap: 10px;
        }
        .meta-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .meta-label { font-size: 11px; color: var(--text-sub); font-weight: bold; }

        /* スレッド履歴 */
        .thread-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
        .event-card {
            background: #fff; border: 1px solid var(--border-color); border-radius: 4px;
            overflow: hidden;
        }
        .event-card.memo { background: #fefcbf; border-color: #f6e05e; }
        .event-card.support { border-left: 4px solid var(--primary-blue); }
        .event-card.customer { border-left: 4px solid #a0aec0; }
        .event-header {
            padding: 9px 14px; display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; background: rgba(0,0,0,0.015);
        }
        .event-header:hover { background: rgba(0,0,0,0.03); }
        .event-sender { font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .event-preview { color: #718096; font-weight: normal; font-size: 12px; margin-left: 8px; }
        .event-body {
            padding: 14px; border-top: 1px solid #edf2f7;
            white-space: pre-wrap; line-height: 1.65; font-size: 13px;
        }
        .event-card.collapsed .event-body { display: none; }
        .event-card:not(.collapsed) .event-preview { display: none; }

        /* 返信エディタボックス */
        .reply-editor-box {
            background: #fff; border: 1px solid #cbd5e0; border-radius: 4px; padding: 14px;
        }
        .reply-tabs { display: flex; gap: 8px; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
        .reply-tab {
            padding: 5px 12px; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 12px;
            border: 1px solid transparent; background: #edf2f7; color: #4a5568;
        }
        .reply-tab.active[data-type="support"] { background: var(--primary-blue); color: #fff; }
        .reply-tab.active[data-type="memo"] { background: #ecc94b; color: #744210; }
        .reply-to-info { font-size: 12px; color: #4a5568; margin-bottom: 8px; }
        .reply-textarea {
            width: 100%; height: 130px; padding: 10px; border: 1px solid #cbd5e0;
            border-radius: 3px; font-size: 13px; font-family: inherit; resize: vertical;
        }
        .reply-textarea.memo-mode { background: #fffff0; border-color: #ecc94b; }
        .reply-footer {
            display: flex; justify-content: space-between; align-items: center; margin-top: 10px;
        }
        .btn-send {
            background: var(--primary-blue); color: #fff; border: none;
            padding: 7px 20px; border-radius: 3px; font-weight: bold; cursor: pointer;
        }
        .btn-send:hover { background: #2188c4; }
        .btn-update-only {
            background: #edf2f7; color: #2d3748; border: 1px solid #cbd5e0;
            padding: 7px 14px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 6px;
        }

        /* モーダル */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45);
            z-index: 200; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-dialog {
            background: #fff; width: 540px; max-width: 94vw; border-radius: 6px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;
        }
        .modal-header {
            padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 15px;
        }
        .modal-body { padding: 18px; max-height: 75vh; overflow-y: auto; }
        .modal-footer {
            padding: 12px 18px; background: #f8fafc; border-top: 1px solid var(--border-color);
            display: flex; justify-content: flex-end; gap: 8px;
        }
        .m-group { margin-bottom: 12px; }
        .m-group label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 12px; }
        .m-group input, .m-group select, .m-group textarea { width: 100%; }
    </style>
</head>
<body>

<!-- 上部ヘッダー -->
<header id="top-header">
    <div class="header-left">
        <a href="admin.php" class="brand-logo">
            <span class="icon-box"><span class="material-icons-round">forum</span></span>
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
        <a href="form.php" target="_blank" class="form-link-btn">お客様用フォームを開く ↗</a>
        <span style="font-size:12px;"><span class="material-icons-round" style="font-size:18px; vertical-align:middle;">account_circle</span> <?php echo htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="logout.php" class="logout-link"><span class="material-icons-round" style="font-size:15px;">logout</span>ログアウト</a>
    </div>
</header>

<div class="layout-wrapper">
    <!-- 左サイドバー -->
    <aside class="sidebar">
        <ul class="side-menu" id="side-status-menu">
            <li class="active" data-status="active"><a href="#"><span class="material-icons-round">list</span>一覧</a></li>
            <li class="status-11" data-status="11"><a href="#"><span class="material-icons-round">hourglass_top</span>未対応</a></li>
            <li data-status="12"><a href="#"><span class="material-icons-round">update</span>対応中</a></li>
            <li data-status="13"><a href="#"><span class="material-icons-round">pending</span>保留中</a></li>
            <hr class="side-divider">
            <li data-status="21"><a href="#"><span class="material-icons-round">check_circle</span>解決済</a></li>
            <li data-status="31"><a href="#"><span class="material-icons-round">delete</span>ゴミ箱</a></li>
        </ul>
        <div class="side-buttons">
            <button type="button" class="btn-navy" id="btn-open-create"><span class="material-icons-round">add</span>チケットを追加</button>
            <button type="button" class="btn-navy" id="btn-open-settings"><span class="material-icons-round">settings</span>チケット設定</button>
        </div>
    </aside>

    <!-- メインエリア -->
    <main class="main-content">
        <div class="breadcrumb">チケット</div>
        <h1 class="page-title" id="current-view-title">チケット</h1>

        <!-- 検索フォーム -->
        <form id="search-form" class="search-box">
            <div class="search-row">
                <input type="date" name="start_date" class="form-control" title="開始日">
                <span>〜</span>
                <input type="date" name="end_date" class="form-control" title="終了日">

                <select name="assignee" id="filter-assignee" class="form-control">
                    <option value="">担当者</option>
                    <option value="unassigned">(未割り当て)</option>
                </select>

                <select name="category" id="filter-category" class="form-control">
                    <option value="">カテゴリ</option>
                </select>

                <input type="text" name="word" class="form-control" style="width:210px;" placeholder="フリーワード検索">

                <span class="detail-toggle" id="toggle-search-detail">
                    詳細 <span class="material-icons-round" style="font-size:16px;">arrow_drop_down</span>
                </span>

                <button type="submit" class="btn-search"><span class="material-icons-round" style="font-size:16px;">search</span>検索</button>
                <button type="button" class="btn-clear" id="btn-reset-search" title="検索クリア"><span class="material-icons-round" style="font-size:16px;">close</span></button>
            </div>

            <div class="search-detail-area" id="search-detail-area">
                <label style="display:flex; align-items:center; gap:4px; cursor:pointer;">
                    <input type="checkbox" name="unread_only" value="1"> 未読のみ
                </label>
                <div>
                    <input type="number" name="ticket_id" class="form-control" style="width:120px;" placeholder="チケットID">
                </div>
            </div>
        </form>

        <!-- 件数表示 & CSV -->
        <div class="list-toolbar">
            <span id="result-count-text">0 件中 0 〜 0</span>
            <a href="admin_api.php?action=export_csv" class="btn-navy" style="padding:5px 12px;">
                <span class="material-icons-round" style="font-size:15px;">file_download</span>CSVダウンロード
            </a>
        </div>

        <!-- チケット一覧 -->
        <div class="ticket-table-wrap">
            <!-- 一括操作バー -->
            <div class="bulk-bar" id="bulk-action-bar">
                <span id="bulk-selected-count" style="font-weight:bold; color:#2b6cb0;">0件選択中</span>
                <select id="bulk-status-select" class="form-control">
                    <option value="">ステータスを変更...</option>
                    <option value="11">未対応</option>
                    <option value="12">対応中</option>
                    <option value="13">保留中</option>
                    <option value="21">解決済</option>
                    <option value="31">ゴミ箱</option>
                </select>
                <select id="bulk-assignee-select" class="form-control">
                    <option value="">担当者を変更...</option>
                </select>
            </div>

            <div class="ticket-header-row">
                <div><input type="checkbox" id="check-all"></div>
                <div></div>
                <div class="sort-col" data-sort="subject">件名 <span class="material-icons-round" style="font-size:14px;">arrow_drop_down</span></div>
                <div class="sort-col" data-sort="requester">訪問者名 <span class="material-icons-round" style="font-size:14px;">arrow_drop_down</span></div>
                <div class="sort-col" data-sort="assignee">担当者 <span class="material-icons-round" style="font-size:14px;">arrow_drop_down</span></div>
                <div class="sort-col" data-sort="elapsed">経過時間 <span class="material-icons-round" style="font-size:14px;">arrow_drop_down</span></div>
                <div>サイト名</div>
                <div class="sort-col" data-sort="ticket-id" style="justify-content:flex-end;">チケットID <span class="material-icons-round" style="font-size:14px;">arrow_drop_down</span></div>
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
            <span class="material-icons-round modal-close" style="cursor:pointer;">close</span>
        </div>
        <form id="form-create-ticket">
            <div class="modal-body">
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

<!-- モーダル2：⚙ チケット設定 -->
<div class="modal-overlay" id="modal-settings">
    <div class="modal-dialog">
        <div class="modal-header">
            <span>チケット設定</span>
            <span class="material-icons-round modal-close" style="cursor:pointer;">close</span>
        </div>
        <form id="form-settings">
            <div class="modal-body">
                <div class="m-group">
                    <label>差出人メールアドレス（From）</label>
                    <input type="email" name="from_email" id="set-from-email" class="form-control" required>
                </div>
                <div class="m-group">
                    <label>差出人名</label>
                    <input type="text" name="from_name" id="set-from-name" class="form-control" required>
                </div>
                <div class="m-group">
                    <label>デフォルトのサイト名</label>
                    <input type="text" name="site_name" id="set-site-name" class="form-control">
                </div>
                <div class="m-group">
                    <label>メール署名（返信メールの末尾に自動挿入されます）</label>
                    <textarea name="signature" id="set-signature" class="form-control" style="height:90px;"></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="m-group">
                        <label>担当者リスト（1行に1名）</label>
                        <textarea name="agents" id="set-agents" class="form-control" style="height:90px;"></textarea>
                    </div>
                    <div class="m-group">
                        <label>カテゴリリスト（1行に1項目）</label>
                        <textarea name="categories" id="set-categories" class="form-control" style="height:90px;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-update-only modal-close">閉じる</button>
                <button type="submit" class="btn-send">設定を保存</button>
            </div>
        </form>
    </div>
</div>

<script>
$(function() {
    let currentStatusGroup = 'active';
    let sortBy = 'elapsed';
    let sortOrder = 'desc';
    let appConfig = {};

    const statusLabels = {
        11: '未対応',
        12: '対応中',
        13: '保留中',
        21: '解決済',
        31: 'ゴミ箱'
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }

    // チケット一覧の読み込み
    function loadTickets() {
        const params = $('#search-form').serializeArray();
        params.push({name: 'action', value: 'list'});
        params.push({name: 'status_group', value: currentStatusGroup});
        params.push({name: 'sort_by', value: sortBy});
        params.push({name: 'sort_order', value: sortOrder});

        $.getJSON('admin_api.php', params, function(res) {
            if (!res.success) return;

            appConfig = res.config;
            populateSelects();

            // 未対応バッジ更新
            if (res.unanswered_count > 0) {
                $('#header-badge').text(res.unanswered_count).show();
            } else {
                $('#header-badge').hide();
            }

            const total = res.tickets.length;
            $('#result-count-text').text(total > 0 ? `${total} 件中 1 〜 ${total}` : '0 件');

            const $container = $('#ticket-list-container').empty();
            if (total === 0) {
                $container.html('<div style="padding:40px; text-align:center; color:#718096;">該当するチケットはありません</div>');
                return;
            }

            res.tickets.forEach(function(t) {
                const alertClass = t.alert_level ? `alert-${t.alert_level}` : '';
                const unreadClass = (t.is_read == 0) ? 'unread' : '';
                const assigneeText = t.assignee ? esc(t.assignee) : '<span style="color:#a0aec0;">(未割り当て)</span>';
                const statusBadge = `<span class="status-pill st-${t.status}">${statusLabels[t.status] || ''}</span>`;

                const html = `
                <div class="ticket-item ${alertClass} ${unreadClass}" data-id="${t.id}">
                    <div class="ticket-row">
                        <div onclick="event.stopPropagation();"><input type="checkbox" class="row-check" value="${t.id}"></div>
                        <div onclick="event.stopPropagation();"><span class="unread-dot" title="未読/既読切替" data-id="${t.id}" data-read="${t.is_read}"></span></div>
                        <div class="col-subject">
                            <span class="material-icons-round mail-icon">mail_outline</span>
                            ${statusBadge}
                            <span>${esc(t.subject)}</span>
                        </div>
                        <div class="col-visitor">
                            <span class="material-icons-round">language</span>
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
                $container.append(html);
            });
            updateBulkBar();
        }).fail(function(xhr) {
            if (xhr.status === 401) {
                window.location.href = 'login.php';
            }
        });
    }

    // プルダウン選択肢の反映
    function populateSelects() {
        const agents = appConfig.agents || [];
        const cats = appConfig.categories || [];

        const curAss = $('#filter-assignee').val();
        $('#filter-assignee').html('<option value="">担当者</option><option value="unassigned">(未割り当て)</option>' +
            agents.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join('')).val(curAss);

        const curCat = $('#filter-category').val();
        $('#filter-category').html('<option value="">カテゴリ</option>' +
            cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('')).val(curCat);

        $('#bulk-assignee-select').html('<option value="">担当者を変更...</option>' +
            agents.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join(''));

        $('#create-assignee').html('<option value="">(未割り当て)</option>' +
            agents.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join(''));

        $('#create-category').html('<option value="">カテゴリなし</option>' +
            cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join(''));
    }

    // チケット詳細の展開（スレッド読み込み）
    function loadTicketDetail(ticketId) {
        const $panel = $(`#detail-panel-${ticketId}`);
        $.getJSON('admin_api.php', {action: 'events', ticket_id: ticketId}, function(res) {
            if (!res.success) return;
            const t = res.ticket;
            const msgs = res.messages;

            const agentOpts = '<option value="">(未割り当て)</option>' +
                (appConfig.agents || []).map(a => `<option value="${esc(a)}" ${t.assignee === a ? 'selected' : ''}>${esc(a)}</option>`).join('');

            const catOpts = '<option value="">カテゴリなし</option>' +
                (appConfig.categories || []).map(c => `<option value="${esc(c)}" ${t.category === c ? 'selected' : ''}>${esc(c)}</option>`).join('');

            let eventsHtml = '';
            msgs.forEach(function(m, idx) {
                const isLast = (idx === msgs.length - 1);
                const collapsedClass = isLast ? '' : 'collapsed';
                let senderLabel = '';
                if (m.sender_type === 'customer') {
                    senderLabel = `<span class="material-icons-round" style="font-size:16px; color:#718096;">person</span> ${esc(t.customer_name)} &lt;${esc(t.customer_email)}&gt;`;
                } else if (m.sender_type === 'memo') {
                    senderLabel = `<span class="material-icons-round" style="font-size:16px; color:#b7791f;">sticky_note_2</span> 社内メモ（お客様には非表示）`;
                } else {
                    senderLabel = `<span class="material-icons-round" style="font-size:16px; color:#29a3e5;">support_agent</span> サポート担当`;
                }

                eventsHtml += `
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
            });

            const detailHtml = `
                <div class="detail-meta-bar">
                    <div class="meta-group">
                        <span class="meta-label">担当者:</span>
                        <select class="form-control inline-update" data-id="${t.id}" data-field="assignee">${agentOpts}</select>
                        <span class="meta-label">カテゴリ:</span>
                        <select class="form-control inline-update" data-id="${t.id}" data-field="category">${catOpts}</select>
                        <a href="view.php?id=${t.id}&key=${esc(t.secret_key)}" target="_blank" style="font-size:12px; color:#2b6cb0;">お客様用確認ページ ↗</a>
                    </div>
                    <div>
                        <button type="button" class="btn-update-only btn-trash-ticket" data-id="${t.id}" style="color:#e53e3e;">
                            <span class="material-icons-round" style="font-size:14px; vertical-align:middle;">delete</span> ゴミ箱へ
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
            $panel.html(detailHtml);
        });
    }

    // チケット行のクリックで展開・折りたたみ
    $(document).on('click', '.ticket-row', function() {
        const $item = $(this).closest('.ticket-item');
        const ticketId = $item.data('id');

        if ($item.hasClass('expanded')) {
            $item.removeClass('expanded');
        } else {
            $('.ticket-item.expanded').removeClass('expanded');
            $item.addClass('expanded').removeClass('unread');
            loadTicketDetail(ticketId);
        }
    });

    // スレッド内のメッセージ開閉
    $(document).on('click', '.event-header', function() {
        $(this).closest('.event-card').toggleClass('collapsed');
    });

    // 返信タブ切り替え（お客様返信 / 社内メモ）
    $(document).on('click', '.reply-tab', function() {
        const $box = $(this).closest('.reply-editor-box');
        const type = $(this).data('type');
        $box.find('.reply-tab').removeClass('active');
        $(this).addClass('active');

        const $ta = $box.find('.reply-textarea');
        if (type === 'memo') {
            $ta.addClass('memo-mode').attr('placeholder', '社内スタッフ向けのメモを入力（お客様には送信されません）');
            $box.find('.reply-to-info').hide();
            $box.find('.btn-submit-reply').text('メモを保存');
        } else {
            $ta.removeClass('memo-mode').attr('placeholder', '返信内容を入力してください...');
            $box.find('.reply-to-info').show();
            $box.find('.btn-submit-reply').text('送信');
        }
    });

    // 返信・メモの送信
    $(document).on('click', '.btn-submit-reply', function() {
        const $btn = $(this);
        const ticketId = $btn.data('id');
        const $box = $btn.closest('.reply-editor-box');
        const replyType = $box.find('.reply-tab.active').data('type');
        const body = $box.find('.reply-textarea').val().trim();
        const status = $box.find('.reply-status-select').val();

        if (!body) {
            alert('本文を入力してください。');
            return;
        }
        $btn.prop('disabled', true).text('送信中...');

        $.post('admin_api.php', {
            action: 'reply',
            ticket_id: ticketId,
            reply_type: replyType,
            body: body,
            status: status
        }, function(res) {
            if (res.success) {
                loadTickets();
            } else {
                alert(res.error || 'エラーが発生しました');
                $btn.prop('disabled', false).text('送信');
            }
        }, 'json');
    });

    // ステータスのみ更新
    $(document).on('click', '.btn-status-only', function() {
        const ticketId = $(this).data('id');
        const status = $(this).closest('.reply-editor-box').find('.reply-status-select').val();
        $.post('admin_api.php', {
            action: 'update_ticket',
            ticket_ids: [ticketId],
            field: 'status',
            value: status
        }, function() {
            loadTickets();
        }, 'json');
    });

    // 担当者・カテゴリのインライン変更
    $(document).on('change', '.inline-update', function() {
        $.post('admin_api.php', {
            action: 'update_ticket',
            ticket_ids: [$(this).data('id')],
            field: $(this).data('field'),
            value: $(this).val()
        }, function() {
            loadTickets();
        }, 'json');
    });

    // ゴミ箱へ移動
    $(document).on('click', '.btn-trash-ticket', function() {
        if (!confirm('このチケットをゴミ箱へ移動しますか？')) return;
        $.post('admin_api.php', {
            action: 'update_ticket',
            ticket_ids: [$(this).data('id')],
            field: 'status',
            value: 31
        }, function() {
            loadTickets();
        }, 'json');
    });

    // 未読ドットクリックで既読/未読切替
    $(document).on('click', '.unread-dot', function() {
        const nextVal = $(this).data('read') == 1 ? 0 : 1;
        $.post('admin_api.php', {
            action: 'update_ticket',
            ticket_ids: [$(this).data('id')],
            field: 'is_read',
            value: nextVal
        }, function() {
            loadTickets();
        }, 'json');
    });

    // 左サイドバーのステータス絞り込み
    $('#side-status-menu li').on('click', function(e) {
        e.preventDefault();
        $('#side-status-menu li').removeClass('active');
        $(this).addClass('active');
        currentStatusGroup = $(this).data('status');
        $('#current-view-title').text($(this).text().trim());
        loadTickets();
    });

    // 検索フォーム送信＆クリア
    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        loadTickets();
    });
    $('#btn-reset-search').on('click', function() {
        $('#search-form')[0].reset();
        loadTickets();
    });
    $('#toggle-search-detail').on('click', function() {
        $('#search-detail-area').toggleClass('open');
    });

    // ソート機能
    $('.sort-col').on('click', function() {
        const col = $(this).data('sort');
        if (sortBy === col) {
            sortOrder = (sortOrder === 'asc') ? 'desc' : 'asc';
        } else {
            sortBy = col;
            sortOrder = 'asc';
        }
        loadTickets();
    });

    // 一括選択・一括操作
    function updateBulkBar() {
        const count = $('.row-check:checked').length;
        if (count > 0) {
            $('#bulk-selected-count').text(`${count}件選択中`);
            $('#bulk-action-bar').addClass('active');
        } else {
            $('#bulk-action-bar').removeClass('active');
        }
    }
    $('#check-all').on('change', function() {
        $('.row-check').prop('checked', $(this).prop('checked'));
        updateBulkBar();
    });
    $(document).on('change', '.row-check', updateBulkBar);

    $('#bulk-status-select').on('change', function() {
        const val = $(this).val();
        if (!val) return;
        const ids = $('.row-check:checked').map(function() { return $(this).val(); }).get();
        $.post('admin_api.php', {action: 'update_ticket', ticket_ids: ids, field: 'status', value: val}, function() {
            $('#bulk-status-select').val('');
            $('#check-all').prop('checked', false);
            loadTickets();
        }, 'json');
    });

    $('#bulk-assignee-select').on('change', function() {
        const val = $(this).val();
        if (!val) return;
        const ids = $('.row-check:checked').map(function() { return $(this).val(); }).get();
        $.post('admin_api.php', {action: 'update_ticket', ticket_ids: ids, field: 'assignee', value: val}, function() {
            $('#bulk-assignee-select').val('');
            $('#check-all').prop('checked', false);
            loadTickets();
        }, 'json');
    });

    // モーダル操作（チケット追加 / チケット設定）
    $('#btn-open-create').on('click', function() {
        $('#form-create-ticket')[0].reset();
        $('#modal-create').addClass('open');
    });
    $('#form-create-ticket').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serializeArray();
        data.push({name: 'action', value: 'create_ticket'});
        $.post('admin_api.php', data, function(res) {
            if (res.success) {
                $('#modal-create').removeClass('open');
                loadTickets();
            } else {
                alert(res.error);
            }
        }, 'json');
    });

    $('#btn-open-settings').on('click', function() {
        $('#set-from-email').val(appConfig.from_email || '');
        $('#set-from-name').val(appConfig.from_name || '');
        $('#set-site-name').val(appConfig.site_name || '');
        $('#set-signature').val(appConfig.signature || '');
        $('#set-agents').val((appConfig.agents || []).join("\n"));
        $('#set-categories').val((appConfig.categories || []).join("\n"));
        $('#modal-settings').addClass('open');
    });
    $('#form-settings').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serializeArray();
        data.push({name: 'action', value: 'save_settings'});
        $.post('admin_api.php', data, function(res) {
            if (res.success) {
                $('#modal-settings').removeClass('open');
                alert('設定を保存しました。');
                loadTickets();
            }
        }, 'json');
    });

    $('.modal-close').on('click', function() {
        $(this).closest('.modal-overlay').removeClass('open');
    });

    // 初回読み込み
    loadTickets();
});
</script>
</body>
</html>