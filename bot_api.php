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

header("X-Content-Type-Options: nosniff");

require_once 'db.php';

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// --------------------------------------------------
// 1. チャットボット＆ナレッジベース用テーブルの自動作成・スキーマ同期
// --------------------------------------------------
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_settings (
        site_id VARCHAR(64) NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        theme_color VARCHAR(20) NOT NULL DEFAULT '#28a745',
        header_title VARCHAR(100) NOT NULL DEFAULT 'サポートチャット',
        header_subtitle VARCHAR(150) NOT NULL DEFAULT 'ご質問をお選びください',
        bot_icon_type VARCHAR(20) NOT NULL DEFAULT 'operator',
        launcher_type VARCHAR(20) NOT NULL DEFAULT 'bar',
        launcher_text VARCHAR(100) NOT NULL DEFAULT 'お問い合わせはこちらをクリック',
        eyecatcher_enabled TINYINT(1) NOT NULL DEFAULT 1,
        eyecatcher_url VARCHAR(255) NOT NULL DEFAULT '',
        auto_open_seconds INT NOT NULL DEFAULT 0,
        allow_free_input TINYINT(1) NOT NULL DEFAULT 1,
        fallback_message TEXT,
        schedule_enabled TINYINT(1) NOT NULL DEFAULT 0,
        schedule_days VARCHAR(50) NOT NULL DEFAULT '1,2,3,4,5',
        schedule_start VARCHAR(10) NOT NULL DEFAULT '09:00',
        schedule_end VARCHAR(10) NOT NULL DEFAULT '18:00',
        off_hours_message TEXT,
        tree_json LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_logs (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        session_key VARCHAR(64) NOT NULL,
        site_id VARCHAR(64) NOT NULL,
        site_name VARCHAR(100) NOT NULL DEFAULT '',
        visitor_name VARCHAR(100) NOT NULL DEFAULT '',
        visitor_email VARCHAR(190) NOT NULL DEFAULT '',
        messages_json LONGTEXT,
        ticket_id INT NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_session_key (session_key),
        INDEX idx_site_id (site_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_knowledge (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        site_id VARCHAR(64) NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT '',
        question VARCHAR(255) NOT NULL,
        keywords VARCHAR(255) NOT NULL DEFAULT '',
        answer TEXT NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        hit_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_kb_site (site_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Bot DB Init Error: " . $e->getMessage());
}

// --------------------------------------------------
// 2. 設定ファイル（config.json）の読み込み
// --------------------------------------------------
$config_file = __DIR__ . '/config.json';
$default_signature = "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------";
$config = [
    'maintenance_mode' => false,
    'from_email'       => 'no-reply@cc-circle.net',
    'from_name'        => 'サポートデスク',
    'signature'        => $default_signature,
    'sites'            => []
];
if (file_exists($config_file)) {
    $loaded = json_decode(file_get_contents($config_file), true);
    if (is_array($loaded)) {
        $config = array_merge($config, $loaded);
    }
}

function get_site_by_id($config, $site_id) {
    if (!empty($config['sites']) && is_array($config['sites'])) {
        foreach ($config['sites'] as $s) {
            if (($s['id'] ?? '') === $site_id) {
                return $s;
            }
        }
    }
    return null;
}

function get_default_tree($site_name = 'サポート') {
    return [
        [
            'id' => 'node_start',
            'title' => '最初の挨拶（スタート）',
            'message' => "こんにちは！{$site_name} サポートチャットです。\n下のボタンから項目をお選びいただくか、質問を直接入力してください。",
            'action_type' => 'options',
            'options' => [
                ['label' => 'よくあるご質問・ご案内', 'next_node_id' => 'node_faq'],
                ['label' => '営業時間・アクセスについて', 'next_node_id' => 'node_info'],
                ['label' => 'オペレーターへお問い合わせ（メール受付）', 'next_node_id' => 'node_form']
            ],
            'link_label' => '',
            'link_url' => ''
        ],
        [
            'id' => 'node_faq',
            'title' => 'よくあるご質問',
            'message' => "よくいただくご質問についてご案内いたします。\n下の入力欄にキーワード（例：「予約」「営業時間」「料金」など）を入力して検索することもできます。",
            'action_type' => 'options',
            'options' => [
                ['label' => 'お問い合わせフォームへ進む', 'next_node_id' => 'node_form'],
                ['label' => '最初のメニューに戻る', 'next_node_id' => 'node_start']
            ],
            'link_label' => '',
            'link_url' => ''
        ],
        [
            'id' => 'node_info',
            'title' => '営業時間・ご案内',
            'message' => "最新の営業情報や詳細につきましては、公式サイトのご案内ページをご確認ください。",
            'action_type' => 'options',
            'options' => [
                ['label' => '個別にお問い合わせする', 'next_node_id' => 'node_form'],
                ['label' => '最初のメニューに戻る', 'next_node_id' => 'node_start']
            ],
            'link_label' => '',
            'link_url' => ''
        ],
        [
            'id' => 'node_form',
            'title' => 'お問い合わせ受付フォーム',
            'message' => "担当者よりメールにて回答いたします。\n下記にお名前・メールアドレス・お問い合わせ内容をご入力ください。",
            'action_type' => 'form',
            'options' => [],
            'link_label' => '',
            'link_url' => ''
        ]
    ];
}

function get_or_create_bot_setting($pdo, $site_id, $site_name = 'サポート') {
    $stmt = $pdo->prepare("SELECT * FROM bot_settings WHERE site_id = ?");
    $stmt->execute([$site_id]);
    $row = $stmt->fetch();
    if ($row) {
        $row['tree'] = json_decode($row['tree_json'] ?: '[]', true);
        if (!is_array($row['tree']) || empty($row['tree'])) {
            $row['tree'] = get_default_tree($site_name);
        }
        return $row;
    }

    $default_tree = get_default_tree($site_name);
    $default_fallback = "ご質問ありがとうございます。恐れ入りますが、該当するご案内が見つかりませんでした。下のボタンから項目をお選びいただくか、お問い合わせフォームよりご連絡ください。";
    $default_off = "ただいま営業時間外となっております。お問い合わせフォームよりメッセージを送信いただければ、翌営業日に担当者より回答いたします。";

    $ins = $pdo->prepare("INSERT INTO bot_settings (site_id, enabled, theme_color, header_title, header_subtitle, bot_icon_type, launcher_type, launcher_text, eyecatcher_enabled, eyecatcher_url, auto_open_seconds, allow_free_input, fallback_message, schedule_enabled, schedule_days, schedule_start, schedule_end, off_hours_message, tree_json) VALUES (?, 1, '#28a745', 'サポートチャット', 'ご質問をお選びください', 'operator', 'bar', 'お問い合わせはこちらをクリック', 1, '', 0, 1, ?, 0, '1,2,3,4,5', '09:00', '18:00', ?, ?)");
    $ins->execute([
        $site_id,
        $default_fallback,
        $default_off,
        json_encode($default_tree, JSON_UNESCAPED_UNICODE)
    ]);

    $stmt->execute([$site_id]);
    $row = $stmt->fetch();
    $row['tree'] = $default_tree;
    return $row;
}

// --------------------------------------------------
// ナレッジベース（Q&A）日本語マッチングアルゴリズム（外部AI不使用）
// --------------------------------------------------
function normalize_jp_text($str) {
    $str = mb_convert_kana((string)$str, 'asKV', 'UTF-8');
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[\s 、。！？!?\.,・\/／\(\)（）「」『』【】\-\r\n]+/u', ' ', $str);
    return trim($str);
}

function make_bigrams($str) {
    $clean = str_replace(' ', '', $str);
    $len = mb_strlen($clean, 'UTF-8');
    if ($len < 2) return $len === 1 ? [$clean] : [];
    $grams = [];
    for ($i = 0; $i < $len - 1; $i++) {
        $grams[] = mb_substr($clean, $i, 2, 'UTF-8');
    }
    return array_unique($grams);
}

function search_knowledge_base($pdo, $site_id, $query_text) {
    $norm_query = normalize_jp_text($query_text);
    if ($norm_query === '') return [];

    $stmt = $pdo->prepare("SELECT id, category, question, keywords, answer FROM bot_knowledge WHERE site_id = ? AND enabled = 1");
    $stmt->execute([$site_id]);
    $items = $stmt->fetchAll();
    if (empty($items)) return [];

    $query_tokens = array_values(array_filter(explode(' ', $norm_query), function($t) {
        return mb_strlen($t, 'UTF-8') >= 1;
    }));
    $query_bigrams = make_bigrams($norm_query);

    $scored = [];
    foreach ($items as $item) {
        $score = 0;
        $norm_q = normalize_jp_text($item['question']);
        $norm_a = normalize_jp_text($item['answer']);
        $raw_kws = preg_split('/[,、，\s ]+/u', (string)$item['keywords'], -1, PREG_SPLIT_NO_EMPTY);

        // 1. 質問文との完全一致・部分一致
        if ($norm_q === $norm_query) {
            $score += 100;
        } elseif (mb_strlen($norm_query, 'UTF-8') >= 2 && mb_strpos($norm_q, $norm_query) !== false) {
            $score += 55;
        } elseif (mb_strlen($norm_q, 'UTF-8') >= 2 && mb_strpos($norm_query, $norm_q) !== false) {
            $score += 45;
        }

        // 2. 登録キーワードとの一致判定（最重要）
        foreach ($raw_kws as $kw) {
            $nkw = normalize_jp_text($kw);
            if ($nkw === '') continue;
            if ($norm_query === $nkw) {
                $score += 80;
            } elseif (mb_strpos($norm_query, $nkw) !== false) {
                $score += 40 + min(20, mb_strlen($nkw, 'UTF-8') * 3);
            } elseif (mb_strlen($norm_query, 'UTF-8') >= 2 && mb_strpos($nkw, $norm_query) !== false) {
                $score += 30;
            }
        }

        // 3. スペース区切りトークンの一致
        foreach ($query_tokens as $tok) {
            if (mb_strlen($tok, 'UTF-8') < 2) continue;
            if (mb_strpos($norm_q, $tok) !== false) $score += 18;
            if (mb_strpos($norm_a, $tok) !== false) $score += 8;
        }

        // 4. 日本語2文字N-gram（バイグラム）類似度
        if (!empty($query_bigrams)) {
            $q_bigrams = make_bigrams($norm_q . ' ' . normalize_jp_text($item['keywords']));
            if (!empty($q_bigrams)) {
                $intersect = count(array_intersect($query_bigrams, $q_bigrams));
                $ratio = $intersect / max(1, count($query_bigrams));
                if ($ratio >= 0.35) {
                    $score += (int)round($ratio * 45);
                }
            }
        }

        if ($score >= 22) {
            $item['score'] = $score;
            $scored[] = $item;
        }
    }

    usort($scored, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return array_slice($scored, 0, 3);
}

// --------------------------------------------------
// SMTP対応メール送信ヘルパー
// --------------------------------------------------
function smtp_read_response_bot($fp) {
    $data = '';
    while ($str = fgets($fp, 515)) {
        $data .= $str;
        if (isset($str[3]) && $str[3] === ' ') break;
    }
    return $data;
}

function send_bot_ticket_mail($to, $subject, $body, $from_email, $from_name, $site = []) {
    $to         = trim(str_replace(["\r", "\n"], '', (string)$to));
    $from_email = trim(str_replace(["\r", "\n"], '', (string)$from_email));
    $from_name  = trim(str_replace(["\r", "\n"], ' ', (string)$from_name));
    $subject    = trim(str_replace(["\r", "\n"], ' ', (string)$subject));

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $smtp_host = trim($site['smtp_host'] ?? '');
    $smtp_port = (int)($site['smtp_port'] ?? 465);
    $smtp_user = trim($site['smtp_user'] ?? '');
    $smtp_pass = (string)($site['smtp_pass'] ?? '');

    if ($smtp_host !== '' && $smtp_user !== '' && $smtp_pass !== '') {
        $remote = ($smtp_port === 465) ? "ssl://{$smtp_host}:{$smtp_port}" : "tcp://{$smtp_host}:{$smtp_port}";
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if ($fp) {
            stream_set_timeout($fp, 15);
            smtp_read_response_bot($fp);
            $helo_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            fwrite($fp, "EHLO {$helo_host}\r\n");
            smtp_read_response_bot($fp);

            if ($smtp_port !== 465) {
                fwrite($fp, "STARTTLS\r\n");
                smtp_read_response_bot($fp);
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                @stream_socket_enable_crypto($fp, true, $crypto);
                fwrite($fp, "EHLO {$helo_host}\r\n");
                smtp_read_response_bot($fp);
            }

            fwrite($fp, "AUTH LOGIN\r\n");
            smtp_read_response_bot($fp);
            fwrite($fp, base64_encode($smtp_user) . "\r\n");
            smtp_read_response_bot($fp);
            fwrite($fp, base64_encode($smtp_pass) . "\r\n");
            $auth_res = smtp_read_response_bot($fp);

            if (strpos($auth_res, '235') === 0) {
                $env_from = filter_var($smtp_user, FILTER_VALIDATE_EMAIL) ? $smtp_user : $from_email;
                fwrite($fp, "MAIL FROM:<{$env_from}>\r\n");
                smtp_read_response_bot($fp);
                fwrite($fp, "RCPT TO:<{$to}>\r\n");
                smtp_read_response_bot($fp);
                fwrite($fp, "DATA\r\n");
                smtp_read_response_bot($fp);

                $enc_name    = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
                $enc_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
                $norm_body   = str_replace(["\r\n", "\r"], "\n", (string)$body);
                $enc_body    = chunk_split(base64_encode($norm_body));
                $msg_id      = '<' . bin2hex(random_bytes(12)) . '@' . ($helo_host ?: 'tc-plus') . '>';

                $raw  = "Date: " . date('r') . "\r\n";
                $raw .= "Message-ID: {$msg_id}\r\n";
                $raw .= "From: {$enc_name} <{$from_email}>\r\n";
                $raw .= "Reply-To: {$from_email}\r\n";
                $raw .= "To: <{$to}>\r\n";
                $raw .= "Subject: {$enc_subject}\r\n";
                $raw .= "MIME-Version: 1.0\r\n";
                $raw .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $raw .= $enc_body . "\r\n.\r\n";

                fwrite($fp, $raw);
                $send_res = smtp_read_response_bot($fp);
                fwrite($fp, "QUIT\r\n");
                fclose($fp);

                if (strpos($send_res, '250') === 0) return true;
            } else {
                fwrite($fp, "QUIT\r\n");
                fclose($fp);
            }
        }
    }

    mb_language("Japanese");
    mb_internal_encoding("UTF-8");
    $headers = "From: " . mb_encode_mimeheader($from_name) . " <{$from_email}>";
    return mb_send_mail($to, $subject, $body, $headers);
}

$action = $_REQUEST['action'] ?? '';

// CSVエクスポート（ナレッジベース）
if ($action === 'kb_export_csv') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        exit;
    }
    $site_id = trim($_GET['site_id'] ?? '');
    $stmt = $pdo->prepare("SELECT category, question, keywords, answer, enabled, hit_count FROM bot_knowledge WHERE site_id = ? ORDER BY id ASC");
    $stmt->execute([$site_id]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="knowledge_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $site_id) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['カテゴリ', '質問文', '関連キーワード（カンマ区切り）', '回答文', '有効(1/0)', 'ヒット数']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['category'], $r['question'], $r['keywords'], $r['answer'], $r['enabled'], $r['hit_count']]);
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // ==================================================
    // 公開エンドポイント（訪問者チャットウィジェットから利用）
    // ==================================================

    // 1. 公開ボット設定の取得
    if ($action === 'public_config') {
        $site_id = trim($_GET['site'] ?? '');
        $site = get_site_by_id($config, $site_id);
        if (!$site) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '無効なサイトIDです']);
            exit;
        }

        $setting = get_or_create_bot_setting($pdo, $site_id, $site['name'] ?? 'サポート');
        $is_maint = !empty($config['maintenance_mode']);

        $is_off_hours = false;
        if (!empty($setting['schedule_enabled'])) {
            $dow = (string)date('w');
            $allowed_days = array_map('trim', explode(',', (string)$setting['schedule_days']));
            $now_hm = date('H:i');
            $st_hm = $setting['schedule_start'] ?: '09:00';
            $ed_hm = $setting['schedule_end'] ?: '18:00';
            if (!in_array($dow, $allowed_days, true) || $now_hm < $st_hm || $now_hm > $ed_hm) {
                $is_off_hours = true;
            }
        }

        echo json_encode([
            'success'          => true,
            'maintenance_mode' => $is_maint,
            'is_off_hours'     => $is_off_hours,
            'site_name'        => $site['name'] ?? '',
            'setting'          => [
                'enabled'            => (int)$setting['enabled'],
                'theme_color'        => $setting['theme_color'],
                'header_title'       => $setting['header_title'],
                'header_subtitle'    => $setting['header_subtitle'],
                'bot_icon_type'      => $setting['bot_icon_type'],
                'launcher_type'      => $setting['launcher_type'],
                'launcher_text'      => $setting['launcher_text'],
                'eyecatcher_enabled' => (int)$setting['eyecatcher_enabled'],
                'eyecatcher_url'     => $setting['eyecatcher_url'],
                'auto_open_seconds'  => (int)$setting['auto_open_seconds'],
                'allow_free_input'   => (int)$setting['allow_free_input'],
                'fallback_message'   => $setting['fallback_message'],
                'off_hours_message'  => $setting['off_hours_message'],
                'tree'               => $setting['tree']
            ]
        ]);
        exit;
    }

    // 2. フリーワード質問に対するナレッジベース（Q&A）検索応答
    if ($action === 'ask_knowledge') {
        $site_id = trim($_POST['site_id'] ?? ($_GET['site_id'] ?? ''));
        $query   = trim($_POST['query'] ?? ($_GET['query'] ?? ''));
        $kb_id   = (int)($_POST['kb_id'] ?? ($_GET['kb_id'] ?? 0));

        $site = get_site_by_id($config, $site_id);
        if (!$site) {
            throw new Exception('無効なサイトIDです。');
        }

        // 候補ボタンが直接クリックされた場合
        if ($kb_id > 0) {
            $stmt = $pdo->prepare("SELECT id, category, question, answer FROM bot_knowledge WHERE id = ? AND site_id = ? AND enabled = 1");
            $stmt->execute([$kb_id, $site_id]);
            $item = $stmt->fetch();
            if ($item) {
                $pdo->prepare("UPDATE bot_knowledge SET hit_count = hit_count + 1 WHERE id = ?")->execute([$item['id']]);
                echo json_encode([
                    'success' => true,
                    'mode'    => 'direct',
                    'match'   => $item
                ]);
                exit;
            }
        }

        $matches = search_knowledge_base($pdo, $site_id, $query);
        if (empty($matches)) {
            echo json_encode([
                'success' => true,
                'mode'    => 'none',
                'matches' => []
            ]);
            exit;
        }

        // 1位が圧倒的に高スコア、または1件のみヒットした場合は即回答
        if (count($matches) === 1 || ($matches[0]['score'] >= 55 && ($matches[0]['score'] - $matches[1]['score']) >= 20)) {
            $top = $matches[0];
            $pdo->prepare("UPDATE bot_knowledge SET hit_count = hit_count + 1 WHERE id = ?")->execute([$top['id']]);
            echo json_encode([
                'success' => true,
                'mode'    => 'direct',
                'match'   => [
                    'id'       => $top['id'],
                    'category' => $top['category'],
                    'question' => $top['question'],
                    'answer'   => $top['answer']
                ]
            ]);
            exit;
        }

        // 複数候補がある場合は選択肢として提示
        echo json_encode([
            'success' => true,
            'mode'    => 'candidates',
            'matches' => array_map(function($m) {
                return [
                    'id'       => $m['id'],
                    'category' => $m['category'],
                    'question' => $m['question']
                ];
            }, $matches)
        ]);
        exit;
    }

    // 3. チャット会話ログの保存
    if ($action === 'save_log') {
        $site_id     = trim($_POST['site_id'] ?? '');
        $session_key = trim($_POST['session_key'] ?? '');
        $messages    = $_POST['messages_json'] ?? '[]';

        $site = get_site_by_id($config, $site_id);
        if (!$site || $session_key === '') {
            echo json_encode(['success' => false]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM bot_logs WHERE session_key = ? AND site_id = ?");
        $stmt->execute([$session_key, $site_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE bot_logs SET messages_json = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$messages, $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO bot_logs (session_key, site_id, site_name, messages_json) VALUES (?, ?, ?, ?)");
            $ins->execute([$session_key, $site_id, $site['name'] ?? '', $messages]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 4. チャット内ヒアリングフォーム送信 → 既存チケットシステムへ自動起票
    if ($action === 'submit_ticket') {
        if (!empty($config['maintenance_mode'])) {
            throw new Exception('現在メンテナンス中のため、受付を一時休止しております。');
        }

        $site_id      = trim($_POST['site_id'] ?? '');
        $session_key  = trim($_POST['session_key'] ?? '');
        $name         = trim($_POST['customer_name'] ?? '');
        $email        = trim($_POST['customer_email'] ?? '');
        $subject      = trim($_POST['subject'] ?? 'チャットボットからのお問い合わせ');
        $body         = trim($_POST['body'] ?? '');
        $chat_history = trim($_POST['chat_history'] ?? '');

        $site = get_site_by_id($config, $site_id);
        if (!$site) {
            throw new Exception('無効なサイト指定です。');
        }
        if ($name === '' || $email === '' || $body === '') {
            throw new Exception('お名前・メールアドレス・お問い合わせ内容は必須です。');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('正しいメールアドレスを入力してください。');
        }

        $site_name = $site['name'] ?? 'メインサイト';
        $full_message = $body;
        if ($chat_history !== '') {
            $full_message .= "\n\n--------------------------------------------------\n【チャットボットでの選択・会話履歴】\n" . $chat_history;
        }

        $pdo->beginTransaction();
        $secret_key = bin2hex(random_bytes(16));

        $columns = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('site_name', $columns)) {
            $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, site_name, status, is_read) VALUES (?, ?, ?, ?, ?, 11, 0)");
            $stmt->execute([$secret_key, $name, $email, $subject, $site_name]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, status) VALUES (?, ?, ?, ?, 11)");
            $stmt->execute([$secret_key, $name, $email, $subject]);
        }
        $ticket_id = $pdo->lastInsertId();

        $stmt_msg = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, 'customer', ?)");
        $stmt_msg->execute([$ticket_id, $full_message]);

        if ($session_key !== '') {
            $upd_log = $pdo->prepare("UPDATE bot_logs SET visitor_name = ?, visitor_email = ?, ticket_id = ?, updated_at = NOW() WHERE session_key = ? AND site_id = ?");
            $upd_log->execute([$name, $email, $ticket_id, $session_key, $site_id]);
        }

        $pdo->commit();

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket_id . "&key=" . $secret_key;

        $from_email = !empty($site['from_email']) ? $site['from_email'] : ($config['from_email'] ?? 'no-reply@cc-circle.net');
        $from_name  = !empty($site['from_name'])  ? $site['from_name']  : ($config['from_name'] ?? 'サポートデスク');
        $signature  = isset($site['signature'])     ? $site['signature']  : ($config['signature'] ?? $default_signature);

        $mail_subject = "【受付完了】お問い合わせありがとうございます（チケットID: #{$ticket_id}）";
        $mail_body = "{$name} 様\n\n"
                   . "チャットボットよりお問い合わせを受け付けました。\n"
                   . "担当者より回答いたしますので、今しばらくお待ちください。\n\n"
                   . "■お問い合わせ内容の確認・追加の返信はこちら\n"
                   . "{$ticket_url}\n\n"
                   . "--------------------------------------------------\n"
                   . "件名：{$subject}\n"
                   . "内容：\n{$body}\n"
                   . "--------------------------------------------------\n\n"
                   . $signature;

        send_bot_ticket_mail($email, $mail_subject, $mail_body, $from_email, $from_name, $site);

        echo json_encode([
            'success'    => true,
            'ticket_id'  => $ticket_id,
            'ticket_url' => $ticket_url
        ]);
        exit;
    }

    // ==================================================
    // 管理者専用エンドポイント（ログイン＆CSRF検証必須）
    // ==================================================
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'セキュリティトークンが無効です。画面を再読み込みしてください。']);
            exit;
        }
    }

    $stmt_me = $pdo->prepare("SELECT role FROM admins WHERE email = ?");
    $stmt_me->execute([$_SESSION['admin_email'] ?? '']);
    $me = $stmt_me->fetch();
    $my_role = $me['role'] ?? ($_SESSION['admin_role'] ?? 'admin');

    // 5. 管理画面用：サイト一覧＋選択中サイトのボット設定＋ナレッジ一覧取得
    if ($action === 'admin_get') {
        $sites = $config['sites'] ?? [];
        $site_id = trim($_GET['site_id'] ?? ($sites[0]['id'] ?? 'default'));
        $site = get_site_by_id($config, $site_id) ?: ($sites[0] ?? ['id' => 'default', 'name' => 'メインサイト']);

        $setting = get_or_create_bot_setting($pdo, $site['id'], $site['name']);
        $safe_sites = array_map(function($s) {
            return ['id' => $s['id'], 'name' => $s['name']];
        }, $sites);

        $stmt_kb = $pdo->prepare("SELECT * FROM bot_knowledge WHERE site_id = ? ORDER BY id DESC");
        $stmt_kb->execute([$site['id']]);
        $kb_list = $stmt_kb->fetchAll();

        echo json_encode([
            'success'   => true,
            'sites'     => $safe_sites,
            'setting'   => $setting,
            'knowledge' => $kb_list
        ]);
        exit;
    }

    // 6. 管理画面用：ボット設定・デザイン・ツリーシナリオ保存（管理者権限のみ）
    if ($action === 'admin_save') {
        if ($my_role !== 'admin') {
            http_response_code(403);
            throw new Exception('この操作を行う権限がありません。');
        }

        $site_id = trim($_POST['site_id'] ?? '');
        if ($site_id === '') {
            throw new Exception('サイトIDが指定されていません。');
        }

        $enabled            = !empty($_POST['enabled']) ? 1 : 0;
        $theme_color        = trim($_POST['theme_color'] ?? '#28a745');
        $header_title       = trim($_POST['header_title'] ?? 'サポートチャット');
        $header_subtitle    = trim($_POST['header_subtitle'] ?? '');
        $bot_icon_type      = trim($_POST['bot_icon_type'] ?? 'operator');
        $launcher_type      = trim($_POST['launcher_type'] ?? 'bar');
        $launcher_text      = trim($_POST['launcher_text'] ?? 'お問い合わせはこちらをクリック');
        $eyecatcher_enabled = !empty($_POST['eyecatcher_enabled']) ? 1 : 0;
        $eyecatcher_url     = trim($_POST['eyecatcher_url'] ?? '');
        $auto_open_seconds  = max(0, (int)($_POST['auto_open_seconds'] ?? 0));
        $allow_free_input   = !empty($_POST['allow_free_input']) ? 1 : 0;
        $fallback_message   = trim($_POST['fallback_message'] ?? '');
        $schedule_enabled   = !empty($_POST['schedule_enabled']) ? 1 : 0;
        $schedule_days      = trim($_POST['schedule_days'] ?? '1,2,3,4,5');
        $schedule_start     = trim($_POST['schedule_start'] ?? '09:00');
        $schedule_end       = trim($_POST['schedule_end'] ?? '18:00');
        $off_hours_message  = trim($_POST['off_hours_message'] ?? '');
        $tree_json          = $_POST['tree_json'] ?? '[]';

        $decoded_tree = json_decode($tree_json, true);
        if (!is_array($decoded_tree) || empty($decoded_tree)) {
            throw new Exception('シナリオツリーのデータ形式が正しくありません。');
        }

        $stmt = $pdo->prepare("UPDATE bot_settings SET
            enabled = ?, theme_color = ?, header_title = ?, header_subtitle = ?,
            bot_icon_type = ?, launcher_type = ?, launcher_text = ?,
            eyecatcher_enabled = ?, eyecatcher_url = ?, auto_open_seconds = ?,
            allow_free_input = ?, fallback_message = ?,
            schedule_enabled = ?, schedule_days = ?, schedule_start = ?, schedule_end = ?,
            off_hours_message = ?, tree_json = ?
            WHERE site_id = ?");
        $stmt->execute([
            $enabled, $theme_color, $header_title, $header_subtitle,
            $bot_icon_type, $launcher_type, $launcher_text,
            $eyecatcher_enabled, $eyecatcher_url, $auto_open_seconds,
            $allow_free_input, $fallback_message,
            $schedule_enabled, $schedule_days, $schedule_start, $schedule_end,
            $off_hours_message, json_encode($decoded_tree, JSON_UNESCAPED_UNICODE),
            $site_id
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    // 7. ナレッジベース（Q&A）の追加・編集
    if ($action === 'kb_save') {
        if ($my_role !== 'admin') {
            http_response_code(403);
            throw new Exception('この操作を行う権限がありません。');
        }

        $id       = (int)($_POST['id'] ?? 0);
        $site_id  = trim($_POST['site_id'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $answer   = trim($_POST['answer'] ?? '');
        $enabled  = !empty($_POST['enabled']) ? 1 : 0;

        if ($site_id === '' || $question === '' || $answer === '') {
            throw new Exception('質問文と回答文は必須です。');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE bot_knowledge SET category = ?, question = ?, keywords = ?, answer = ?, enabled = ? WHERE id = ? AND site_id = ?");
            $stmt->execute([$category, $question, $keywords, $answer, $enabled, $id, $site_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO bot_knowledge (site_id, category, question, keywords, answer, enabled) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$site_id, $category, $question, $keywords, $answer, $enabled]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // 8. ナレッジベース（Q&A）の削除
    if ($action === 'kb_delete') {
        if ($my_role !== 'admin') {
            http_response_code(403);
            throw new Exception('この操作を行う権限がありません。');
        }

        $id      = (int)($_POST['id'] ?? 0);
        $site_id = trim($_POST['site_id'] ?? '');
        $pdo->prepare("DELETE FROM bot_knowledge WHERE id = ? AND site_id = ?")->execute([$id, $site_id]);

        echo json_encode(['success' => true]);
        exit;
    }

    // 9. ナレッジベース（Q&A）の初期サンプル一括登録
    if ($action === 'kb_seed_sample') {
        if ($my_role !== 'admin') {
            http_response_code(403);
            throw new Exception('この操作を行う権限がありません。');
        }

        $site_id = trim($_POST['site_id'] ?? '');
        $site = get_site_by_id($config, $site_id);
        $sname = $site['name'] ?? '';

        if (mb_strpos($sname, '遊裕') !== false || mb_strpos($sname, '焼肉') !== false) {
            $samples = [
                ['営業時間・定休日', '営業時間と定休日を教えてください', '営業時間,何時まで,定休日,休み,深夜', "営業時間は 21:00 ～ 翌4:00（ラストオーダー 3:30）です。\n定休日は不定休となっております。"],
                ['アクセス・場所', 'お店の場所・住所はどこですか？', '住所,場所,アクセス,地図,どこ,駐車場', "〒305-0005 茨城県つくば市天久保1-7-7 シグマビル103 にございます。\nお電話でのお問い合わせは 029-895-7463 までお気軽にどうぞ。"],
                ['メニュー・コース', 'コース料理や貸切の相談はできますか？', 'コース,予算,貸切,宴会,飲み放題,ママのおまかせ', "ご予算に合わせてご用意する「ママのおまかせコース」がございます。\n貸切やご宴会のご相談も承っておりますので、人数とご希望日時をお知らせください。"],
                ['設備・カラオケ', '一人でも入れますか？カラオケはありますか？', '一人,お一人様,カラオケ,歌える', "お一人様も大歓迎のアットホームな雰囲気です！\n店内にはカラオケも完備しており、お食事しながら楽しくお過ごしいただけます。"]
            ];
        } else {
            $samples = [
                ['ライブ・イベント', '次のライブやイベントの予定はどこで確認できますか？', 'ライブ,イベント,スケジュール,予定,日程,チケット', "最新のライブ・イベント出演予定は、公式サイトの「SCHEDULE」ページにて随時更新しております。"],
                ['リリース・楽曲', '楽曲やミュージックビデオはどこで視聴できますか？', '曲,楽曲,動画,MV,リリース,視聴,聴く', "最新楽曲やリリックビデオは、公式サイトの「VIDEO」「DISCOGRAPHY」ページよりご視聴いただけます。"],
                ['ファンクラブ', 'ファンクラブ（Bitfan）の登録・ログイン方法を教えてください', 'ファンクラブ,Bitfan,登録,ログイン,会員,フォロー', "サイト上部の「フォロー」または「ログイン」ボタンよりBitfanアカウントにてご登録・ログインいただけます。"],
                ['お仕事のご依頼', '出演依頼や制作のご相談はどこから送れますか？', '出演,依頼,仕事,コラボ,取材,案件', "お仕事のご依頼・ご相談は、チャット内の「お問い合わせフォームへ進む」より詳細をお送りください。担当者より折り返しご連絡いたします。"]
            ];
        }

        $ins = $pdo->prepare("INSERT INTO bot_knowledge (site_id, category, question, keywords, answer, enabled) VALUES (?, ?, ?, ?, ?, 1)");
        foreach ($samples as $sp) {
            $ins->execute([$site_id, $sp[0], $sp[1], $sp[2], $sp[3]]);
        }

        echo json_encode(['success' => true, 'added' => count($samples)]);
        exit;
    }

    // 10. ナレッジベース CSV一括インポート
    if ($action === 'kb_import_csv') {
        if ($my_role !== 'admin') {
            http_response_code(403);
            throw new Exception('この操作を行う権限がありません。');
        }

        $site_id = trim($_POST['site_id'] ?? '');
        if ($site_id === '' || empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('CSVファイルを選択してください。');
        }

        $raw = file_get_contents($_FILES['csv_file']['tmp_name']);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'SJIS-win', 'CP932', 'EUC-JP'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }

        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $raw);
        rewind($fp);

        $ins = $pdo->prepare("INSERT INTO bot_knowledge (site_id, category, question, keywords, answer, enabled) VALUES (?, ?, ?, ?, ?, 1)");
        $count = 0;
        $row_idx = 0;
        while (($cols = fgetcsv($fp)) !== false) {
            $row_idx++;
            if (count($cols) < 4) continue;
            $cat = trim($cols[0]);
            $q   = trim($cols[1]);
            $kw  = trim($cols[2]);
            $ans = trim($cols[3]);
            if ($row_idx === 1 && ($q === '質問文' || $cat === 'カテゴリ')) continue;
            if ($q === '' || $ans === '') continue;

            $ins->execute([$site_id, $cat, $q, $kw, $ans]);
            $count++;
        }
        fclose($fp);

        echo json_encode(['success' => true, 'imported' => $count]);
        exit;
    }

    // 11. アイキャッチャー画像のアップロード（管理者権限のみ）
    if ($action === 'upload_eyecatcher') {
        if ($my_role !== 'admin') {
            http_response_code(403);
            throw new Exception('この操作を行う権限がありません。');
        }

        if (empty($_FILES['eyecatcher_file']) || $_FILES['eyecatcher_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('画像ファイルのアップロードに失敗しました。');
        }

        $file = $_FILES['eyecatcher_file'];
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('ファイルサイズは2MB以内にしてください。');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        if (!in_array($ext, $allowed_ext, true)) {
            throw new Exception('PNG / JPG / GIF / WebP 形式の画像のみアップロードできます。');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (strpos((string)$mime, 'image/') !== 0) {
            throw new Exception('有効な画像ファイルではありません。');
        }

        $upload_dir = __DIR__ . '/uploads';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = 'eyecatcher_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest_path = $upload_dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            throw new Exception('ファイルの保存に失敗しました。');
        }

        echo json_encode([
            'success' => true,
            'url'     => 'uploads/' . $filename
        ]);
        exit;
    }

    throw new Exception('不正なアクション指定です。');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>