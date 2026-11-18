<?php
require_once 'db.php';

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// 1. 既存のticketsテーブルに必要なカラムがなければ自動追加
try {
    $columns = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('assignee', $columns)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN assignee VARCHAR(100) DEFAULT '' AFTER subject");
    }
    if (!in_array('site_name', $columns)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN site_name VARCHAR(100) DEFAULT 'Webサイト' AFTER assignee");
    }
    if (!in_array('category', $columns)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN category VARCHAR(100) DEFAULT '' AFTER site_name");
    }
    if (!in_array('is_read', $columns)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER status");
    }
} catch (Exception $e) {
    // すでに存在する場合は無視
}

// 2. 設定ファイル（config.json）の読み込み・初期作成
$config_file = __DIR__ . '/config.json';
$default_config = [
    'from_email' => 'no-reply@cc-circle.net',
    'from_name'  => 'サポートデスク',
    'signature'  => "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------",
    'agents'     => ['担当者A', '担当者B', 'サポート窓口'],
    'categories' => ['一般問い合わせ', '操作方法', '不具合・エラー', '契約・お支払い', 'その他'],
    'site_name'  => 'メインサイト'
];

if (!file_exists($config_file)) {
    file_put_contents($config_file, json_encode($default_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
$config = array_merge($default_config, json_decode(file_get_contents($config_file), true) ?: []);

// 経過時間のフォーマット関数（ChatPlus風）
function format_elapsed($datetime_str) {
    $ts = strtotime($datetime_str);
    $now = time();
    $diff = max(0, $now - $ts);

    $date_part = (date('Y-m-d', $ts) === date('Y-m-d', $now))
        ? date('H:i', $ts)
        : date('n月j日 H:i', $ts);

    if ($diff < 3600) {
        $mins = max(1, floor($diff / 60));
        $ago = "{$mins}分前";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $ago = "{$hours}時間前";
    } else {
        $days = floor($diff / 86400);
        $ago = "{$days}日前";
    }
    return "{$date_part} ({$ago})";
}

$action = $_REQUEST['action'] ?? '';

// --------------------------------------------------
// CSVダウンロード処理
// --------------------------------------------------
if ($action === 'export_csv') {
    $stmt = $pdo->query("SELECT * FROM tickets ORDER BY updated_at DESC");
    $rows = $stmt->fetchAll();

    $status_map = [11 => '未対応', 12 => '対応中', 13 => '保留中', 21 => '解決済', 31 => 'ゴミ箱'];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tickets_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // Excel文字化け防止BOM

    fputcsv($out, ['チケットID', 'ステータス', '件名', '訪問者名', 'メールアドレス', '担当者', 'カテゴリ', 'サイト名', '作成日時', '最終更新日時']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $status_map[$r['status']] ?? $r['status'],
            $r['subject'],
            $r['customer_name'],
            $r['customer_email'],
            $r['assignee'] ?: '(未割り当て)',
            $r['category'],
            $r['site_name'],
            $r['created_at'],
            $r['updated_at']
        ]);
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // --------------------------------------------------
    // 1. チケット一覧取得・検索
    // --------------------------------------------------
    if ($action === 'list') {
        $where = [];
        $params = [];

        $status_filter = $_GET['status_group'] ?? 'active';
        if ($status_filter === 'active') {
            $where[] = "status IN (11, 12, 13)";
        } elseif ($status_filter !== 'all' && is_numeric($status_filter)) {
            $where[] = "status = ?";
            $params[] = (int)$status_filter;
        }

        if (!empty($_GET['start_date'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $_GET['start_date'];
        }
        if (!empty($_GET['end_date'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $_GET['end_date'];
        }
        if (!empty($_GET['assignee'])) {
            if ($_GET['assignee'] === 'unassigned') {
                $where[] = "(assignee = '' OR assignee IS NULL)";
            } else {
                $where[] = "assignee = ?";
                $params[] = $_GET['assignee'];
            }
        }
        if (!empty($_GET['category'])) {
            $where[] = "category = ?";
            $params[] = $_GET['category'];
        }
        if (!empty($_GET['ticket_id'])) {
            $where[] = "id = ?";
            $params[] = (int)$_GET['ticket_id'];
        }
        if (!empty($_GET['unread_only'])) {
            $where[] = "is_read = 0";
        }
        if (!empty($_GET['word'])) {
            $word = '%' . $_GET['word'] . '%';
            $where[] = "(subject LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR id IN (SELECT ticket_id FROM messages WHERE body LIKE ?))";
            $params[] = $word;
            $params[] = $word;
            $params[] = $word;
            $params[] = $word;
        }

        $sql = "SELECT * FROM tickets";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ソート順
        $sort_map = [
            'subject'   => 'subject',
            'requester' => 'customer_name',
            'assignee'  => 'assignee',
            'elapsed'   => 'updated_at',
            'ticket-id' => 'id'
        ];
        $sort_col = $sort_map[$_GET['sort_by'] ?? ''] ?? 'updated_at';
        $sort_dir = (($_GET['sort_order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$sort_col} {$sort_dir}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll();

        $now = time();
        foreach ($tickets as &$t) {
            $t['elapsed_text'] = format_elapsed($t['updated_at']);
            $diff_hours = ($now - strtotime($t['updated_at'])) / 3600;
            $t['alert_level'] = '';
            if (in_array((int)$t['status'], [11, 12])) {
                if ($diff_hours >= 24) {
                    $t['alert_level'] = 'danger';
                } elseif ($diff_hours >= 18) {
                    $t['alert_level'] = 'warning';
                }
            }
        }

        // 未対応（status=11）の総件数（ヘッダーの赤丸バッジ用）
        $unanswered_count = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 11")->fetchColumn();

        echo json_encode([
            'success' => true,
            'tickets' => $tickets,
            'unanswered_count' => $unanswered_count,
            'config' => $config
        ]);
        exit;
    }

    // --------------------------------------------------
    // 2. チケット詳細・やり取り履歴の取得
    // --------------------------------------------------
    if ($action === 'events') {
        $ticket_id = (int)($_GET['ticket_id'] ?? 0);

        // 開いたら自動的に既読にする
        $pdo->prepare("UPDATE tickets SET is_read = 1 WHERE id = ?")->execute([$ticket_id]);

        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();

        $stmt_m = $pdo->prepare("SELECT * FROM messages WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt_m->execute([$ticket_id]);
        $messages = $stmt_m->fetchAll();

        foreach ($messages as &$m) {
            $m['elapsed_text'] = format_elapsed($m['created_at']);
            $m['short_body'] = mb_strimwidth(str_replace(["\r", "\n"], ' ', $m['body']), 0, 90, '...');
        }

        echo json_encode([
            'success' => true,
            'ticket' => $ticket,
            'messages' => $messages
        ]);
        exit;
    }

    // --------------------------------------------------
    // 3. 返信送信・社内メモ追加・ステータス更新
    // --------------------------------------------------
    if ($action === 'reply') {
        $ticket_id  = (int)($_POST['ticket_id'] ?? 0);
        $reply_type = $_POST['reply_type'] ?? 'support'; // 'support' or 'memo'
        $body       = trim($_POST['body'] ?? '');
        $status     = (int)($_POST['status'] ?? 12);

        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            throw new Exception('チケットが見つかりません。');
        }

        $pdo->beginTransaction();

        if ($body !== '') {
            $sender_type = ($reply_type === 'memo') ? 'memo' : 'support';
            $stmt_m = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, ?, ?)");
            $stmt_m->execute([$ticket_id, $sender_type, $body]);
        }

        $stmt_u = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW(), is_read = 1 WHERE id = ?");
        $stmt_u->execute([$status, $ticket_id]);

        $pdo->commit();

        // 「お客様へ返信」で本文がある場合は、お客様へメール通知を送信
        if ($reply_type === 'support' && $body !== '') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket['id'] . "&key=" . $ticket['secret_key'];

            $mail_subject = "Re: {$ticket['subject']}（チケットID: #{$ticket['id']}）";
            $mail_body = "{$ticket['customer_name']} 様\n\n"
                       . "{$body}\n\n"
                       . "■これまでのやり取りの確認・追加のご返信はこちら\n"
                       . "{$ticket_url}\n\n"
                       . $config['signature'];

            $headers = "From: " . mb_encode_mimeheader($config['from_name']) . " <{$config['from_email']}>";
            mb_send_mail($ticket['customer_email'], $mail_subject, $mail_body, $headers);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // --------------------------------------------------
    // 4. チケット属性の更新（単体・一括）
    // --------------------------------------------------
    if ($action === 'update_ticket') {
        $ids = $_POST['ticket_ids'] ?? [];
        if (!is_array($ids)) $ids = [$ids];
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            throw new Exception('対象チケットが選択されていません。');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        if ($field === 'status') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([(int)$value], $ids));
        } elseif ($field === 'assignee') {
            $stmt = $pdo->prepare("UPDATE tickets SET assignee = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$value], $ids));
        } elseif ($field === 'category') {
            $stmt = $pdo->prepare("UPDATE tickets SET category = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$value], $ids));
        } elseif ($field === 'is_read') {
            $stmt = $pdo->prepare("UPDATE tickets SET is_read = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([(int)$value], $ids));
        } elseif ($field === 'customer_info') {
            $stmt = $pdo->prepare("UPDATE tickets SET customer_name = ?, customer_email = ?, subject = ? WHERE id = ?");
            $stmt->execute([$_POST['customer_name'], $_POST['customer_email'], $_POST['subject'], $ids[0]]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // --------------------------------------------------
    // 5. 「+ チケットを追加」からの新規作成
    // --------------------------------------------------
    if ($action === 'create_ticket') {
        $name     = trim($_POST['customer_name'] ?? '');
        $email    = trim($_POST['customer_email'] ?? '');
        $subject  = trim($_POST['subject'] ?? '');
        $body     = trim($_POST['body'] ?? '');
        $assignee = trim($_POST['assignee'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $send_mail = !empty($_POST['send_mail']);

        if ($name === '' || $email === '' || $subject === '' || $body === '') {
            throw new Exception('必須項目をすべて入力してください。');
        }

        $pdo->beginTransaction();
        $secret_key = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, assignee, site_name, category, status, is_read) VALUES (?, ?, ?, ?, ?, ?, ?, 12, 1)");
        $stmt->execute([$secret_key, $name, $email, $subject, $assignee, $config['site_name'], $category]);
        $ticket_id = $pdo->lastInsertId();

        $stmt_m = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, 'support', ?)");
        $stmt_m->execute([$ticket_id, $body]);
        $pdo->commit();

        if ($send_mail) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket_id . "&key=" . $secret_key;

            $mail_subject = "{$subject}（チケットID: #{$ticket_id}）";
            $mail_body = "{$name} 様\n\n"
                       . "{$body}\n\n"
                       . "■内容の確認・ご返信はこちら\n"
                       . "{$ticket_url}\n\n"
                       . $config['signature'];

            $headers = "From: " . mb_encode_mimeheader($config['from_name']) . " <{$config['from_email']}>";
            mb_send_mail($email, $mail_subject, $mail_body, $headers);
        }

        echo json_encode(['success' => true, 'ticket_id' => $ticket_id]);
        exit;
    }

    // --------------------------------------------------
    // 6. 「⚙ チケット設定」の保存
    // --------------------------------------------------
    if ($action === 'save_settings') {
        $new_config = [
            'from_email' => trim($_POST['from_email'] ?? 'no-reply@cc-circle.net'),
            'from_name'  => trim($_POST['from_name'] ?? 'サポートデスク'),
            'signature'  => $_POST['signature'] ?? '',
            'site_name'  => trim($_POST['site_name'] ?? 'メインサイト'),
            'agents'     => array_values(array_filter(array_map('trim', explode("\n", $_POST['agents'] ?? '')))),
            'categories' => array_values(array_filter(array_map('trim', explode("\n", $_POST['categories'] ?? '')))),
        ];
        file_put_contents($config_file, json_encode($new_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>