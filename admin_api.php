<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

require_once 'db.php';

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// 1. データベースの自動連番（AUTO_INCREMENT）設定とカラムの自動チェック・修復
try {
    $id_col = $pdo->query("SHOW COLUMNS FROM tickets WHERE Field = 'id'")->fetch();
    if ($id_col && stripos($id_col['Extra'] ?? '', 'auto_increment') === false) {
        $pdo->exec("SET @t_seq = 0");
        $pdo->exec("UPDATE tickets SET id = (@t_seq := @t_seq + 1) ORDER BY created_at ASC");
        $has_pk = !empty($pdo->query("SHOW KEYS FROM tickets WHERE Key_name = 'PRIMARY'")->fetch());
        if ($has_pk) {
            $pdo->exec("ALTER TABLE tickets MODIFY id INT NOT NULL AUTO_INCREMENT");
        } else {
            $pdo->exec("ALTER TABLE tickets MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }
    }

    $msg_id_col = $pdo->query("SHOW COLUMNS FROM messages WHERE Field = 'id'")->fetch();
    if ($msg_id_col && stripos($msg_id_col['Extra'] ?? '', 'auto_increment') === false) {
        $pdo->exec("SET @m_seq = 0");
        $pdo->exec("UPDATE messages SET id = (@m_seq := @m_seq + 1) ORDER BY created_at ASC");
        $has_msg_pk = !empty($pdo->query("SHOW KEYS FROM messages WHERE Key_name = 'PRIMARY'")->fetch());
        if ($has_msg_pk) {
            $pdo->exec("ALTER TABLE messages MODIFY id INT NOT NULL AUTO_INCREMENT");
        } else {
            $pdo->exec("ALTER TABLE messages MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }
    }

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
    error_log("DB Structure Check Error: " . $e->getMessage());
}

// 2. 設定ファイル（config.json）の読み込み・初期作成
$config_file = __DIR__ . '/config.json';
$default_signature = "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------";
$default_config = [
    'from_email' => 'no-reply@cc-circle.net',
    'from_name'  => 'サポートデスク',
    'signature'  => $default_signature,
    'agents'     => ['担当者A', '担当者B', 'サポート窓口'],
    'categories' => ['一般問い合わせ', '操作方法', '不具合・エラー', '契約・お支払い', 'その他'],
    'site_name'  => 'メインサイト',
    'sites'      => [
        [
            'id'         => 'default',
            'name'       => 'メインサイト',
            'url'        => '',
            'from_email' => 'no-reply@cc-circle.net',
            'from_name'  => 'サポートデスク',
            'signature'  => $default_signature
        ]
    ]
];

if (!file_exists($config_file)) {
    file_put_contents($config_file, json_encode($default_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
$loaded_config = json_decode(file_get_contents($config_file), true);
$config = array_merge($default_config, is_array($loaded_config) ? $loaded_config : []);

if (empty($config['sites']) || !is_array($config['sites'])) {
    $initial_name = !empty($config['site_name']) ? $config['site_name'] : 'メインサイト';
    $config['sites'] = [
        [
            'id'         => 'default',
            'name'       => $initial_name,
            'url'        => '',
            'from_email' => $config['from_email'] ?? 'no-reply@cc-circle.net',
            'from_name'  => $config['from_name'] ?? 'サポートデスク',
            'signature'  => $config['signature'] ?? $default_signature
        ]
    ];
    file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// サイト名から該当サイトのメール設定を取得する関数
function get_site_mail_settings($config, $site_name) {
    $fallback = [
        'from_email' => $config['sites'][0]['from_email'] ?? ($config['from_email'] ?? 'no-reply@cc-circle.net'),
        'from_name'  => $config['sites'][0]['from_name'] ?? ($config['from_name'] ?? 'サポートデスク'),
        'signature'  => $config['sites'][0]['signature'] ?? ($config['signature'] ?? '')
    ];
    if (!empty($config['sites']) && is_array($config['sites'])) {
        foreach ($config['sites'] as $s) {
            if (($s['name'] ?? '') === $site_name) {
                return [
                    'from_email' => !empty($s['from_email']) ? $s['from_email'] : $fallback['from_email'],
                    'from_name'  => !empty($s['from_name'])  ? $s['from_name']  : $fallback['from_name'],
                    'signature'  => isset($s['signature'])     ? $s['signature']  : $fallback['signature']
                ];
            }
        }
    }
    return $fallback;
}

// 共通メール送信関数
function send_tc_mail($to, $subject, $body, $from_email, $from_name) {
    $encoded_name = mb_encode_mimeheader($from_name, "UTF-8", "B", "\n");
    $headers  = "From: {$encoded_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit";

    $additional_params = '';
    if (filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $additional_params = "-f" . $from_email;
    }
    return mb_send_mail($to, $subject, $body, $headers, $additional_params);
}

// 経過時間のフォーマット関数
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
    fwrite($out, "\xEF\xBB\xBF");

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
        if (!empty($_GET['site_name'])) {
            $where[] = "site_name = ?";
            $params[] = $_GET['site_name'];
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
        $reply_type = $_POST['reply_type'] ?? 'support';
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

        if ($reply_type === 'support' && $body !== '') {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket['id'] . "&key=" . $ticket['secret_key'];

            $site_mail = get_site_mail_settings($config, $ticket['site_name'] ?? '');
            $mail_subject = "Re: {$ticket['subject']}（チケットID: #{$ticket['id']}）";
            $mail_body = "{$ticket['customer_name']} 様\n\n"
                       . "{$body}\n\n"
                       . "■これまでのやり取りの確認・追加のご返信はこちら\n"
                       . "{$ticket_url}\n\n"
                       . $site_mail['signature'];

            $mail_ok = send_tc_mail($ticket['customer_email'], $mail_subject, $mail_body, $site_mail['from_email'], $site_mail['from_name']);
            if (!$mail_ok) {
                throw new Exception('メッセージは保存されましたが、メール送信に失敗しました。サーバーのメール設定を確認してください。');
            }
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
        $name      = trim($_POST['customer_name'] ?? '');
        $email     = trim($_POST['customer_email'] ?? '');
        $subject   = trim($_POST['subject'] ?? '');
        $body      = trim($_POST['body'] ?? '');
        $assignee  = trim($_POST['assignee'] ?? '');
        $category  = trim($_POST['category'] ?? '');
        $site_name = trim($_POST['site_name'] ?? ($config['sites'][0]['name'] ?? $config['site_name']));
        $send_mail = !empty($_POST['send_mail']);

        if ($name === '' || $email === '' || $subject === '' || $body === '') {
            throw new Exception('必須項目をすべて入力してください。');
        }

        $pdo->beginTransaction();
        $secret_key = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, assignee, site_name, category, status, is_read) VALUES (?, ?, ?, ?, ?, ?, ?, 12, 1)");
        $stmt->execute([$secret_key, $name, $email, $subject, $assignee, $site_name, $category]);
        $ticket_id = $pdo->lastInsertId();

        $stmt_m = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, 'support', ?)");
        $stmt_m->execute([$ticket_id, $body]);
        $pdo->commit();

        if ($send_mail) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket_id . "&key=" . $secret_key;

            $site_mail = get_site_mail_settings($config, $site_name);
            $mail_subject = "{$subject}（チケットID: #{$ticket_id}）";
            $mail_body = "{$name} 様\n\n"
                       . "{$body}\n\n"
                       . "■内容の確認・ご返信はこちら\n"
                       . "{$ticket_url}\n\n"
                       . $site_mail['signature'];

            $mail_ok = send_tc_mail($email, $mail_subject, $mail_body, $site_mail['from_email'], $site_mail['from_name']);
            if (!$mail_ok) {
                throw new Exception('チケットは作成されましたが、メール送信に失敗しました。');
            }
        }

        echo json_encode(['success' => true, 'ticket_id' => $ticket_id]);
        exit;
    }

    // --------------------------------------------------
    // 6. 「⚙ チケット設定」の保存（管理者権限のみ許可）
    // --------------------------------------------------
    if ($action === 'save_settings') {
        $stmt_me = $pdo->prepare("SELECT role FROM admins WHERE email = ?");
        $stmt_me->execute([$_SESSION['admin_email'] ?? '']);
        $me = $stmt_me->fetch();
        $my_role = $me['role'] ?? ($_SESSION['admin_role'] ?? 'admin');

        if ($my_role !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            throw new Exception('この操作を行う権限がありません。');
        }

        $config['agents']     = array_values(array_filter(array_map('trim', explode("\n", $_POST['agents'] ?? ''))));
        $config['categories'] = array_values(array_filter(array_map('trim', explode("\n", $_POST['categories'] ?? ''))));

        if (file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            throw new Exception('設定ファイル（config.json）の保存に失敗しました。ファイルの書き込み権限を確認してください。');
        }
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