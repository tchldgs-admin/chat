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

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'セキュリティトークンが無効です。画面を再読み込みしてください。']);
        exit;
    }
}

require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

$stmt_me = $pdo->prepare("SELECT role FROM admins WHERE email = ?");
$stmt_me->execute([$_SESSION['admin_email'] ?? '']);
$me = $stmt_me->fetch();
$my_role = $me['role'] ?? ($_SESSION['admin_role'] ?? 'admin');

if ($my_role !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'この操作を行う権限がありません。']);
    exit;
}

$config_file = __DIR__ . '/config.json';
$default_signature = "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------";
$default_config = [
    'maintenance_mode' => false,
    'from_email'       => 'no-reply@cc-circle.net',
    'from_name'        => 'サポートデスク',
    'signature'        => $default_signature,
    'agents'           => ['担当者A', '担当者B', 'サポート窓口'],
    'categories'       => ['一般問い合わせ', '操作方法', '不具合・エラー', '契約・お支払い', 'その他'],
    'site_name'        => 'メインサイト',
    'sites'            => [
        [
            'id'         => 'default',
            'name'       => 'メインサイト',
            'url'        => '',
            'from_email' => 'no-reply@cc-circle.net',
            'from_name'  => 'サポートデスク',
            'signature'  => $default_signature,
            'smtp_host'  => '',
            'smtp_port'  => '465',
            'smtp_user'  => '',
            'smtp_pass'  => ''
        ]
    ]
];

if (!file_exists($config_file)) {
    file_put_contents($config_file, json_encode($default_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
$loaded = json_decode(file_get_contents($config_file), true);
$config = array_merge($default_config, is_array($loaded) ? $loaded : []);

if (empty($config['sites']) || !is_array($config['sites'])) {
    $initial_name = !empty($config['site_name']) ? $config['site_name'] : 'メインサイト';
    $config['sites'] = [
        [
            'id'         => 'default',
            'name'       => $initial_name,
            'url'        => '',
            'from_email' => $config['from_email'] ?? 'no-reply@cc-circle.net',
            'from_name'  => $config['from_name'] ?? 'サポートデスク',
            'signature'  => $config['signature'] ?? $default_signature,
            'smtp_host'  => '',
            'smtp_port'  => '465',
            'smtp_user'  => '',
            'smtp_pass'  => ''
        ]
    ];
}

function smtp_read_response($fp) {
    $data = '';
    while ($str = fgets($fp, 515)) {
        $data .= $str;
        if (isset($str[3]) && $str[3] === ' ') {
            break;
        }
    }
    return $data;
}

function send_via_smtp($to, $subject, $body, $from_email, $from_name, $smtp) {
    $host = trim($smtp['smtp_host'] ?? '');
    $port = (int)($smtp['smtp_port'] ?? 465);
    $user = trim($smtp['smtp_user'] ?? '');
    $pass = (string)($smtp['smtp_pass'] ?? '');

    if ($host === '' || $user === '' || $pass === '') {
        return ['ok' => false, 'error' => 'SMTP設定（サーバー・ユーザー名・パスワード）が不足しています。'];
    }

    $remote = ($port === 465) ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false
        ]
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return ['ok' => false, 'error' => "SMTPサーバーに接続できませんでした ({$errstr})"];
    }
    stream_set_timeout($fp, 15);

    $res = smtp_read_response($fp);
    if (strpos($res, '220') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "SMTP初期応答エラー: {$res}"];
    }

    $helo_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    fwrite($fp, "EHLO {$helo_host}\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '250') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "EHLOエラー: {$res}"];
    }

    if ($port !== 465) {
        fwrite($fp, "STARTTLS\r\n");
        $res = smtp_read_response($fp);
        if (strpos($res, '220') !== 0) {
            fclose($fp);
            return ['ok' => false, 'error' => "STARTTLSエラー: {$res}"];
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!@stream_socket_enable_crypto($fp, true, $crypto_method)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'TLS暗号化通信の確立に失敗しました。'];
        }
        fwrite($fp, "EHLO {$helo_host}\r\n");
        $res = smtp_read_response($fp);
    }

    fwrite($fp, "AUTH LOGIN\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '334') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "AUTH LOGINエラー: {$res}"];
    }

    fwrite($fp, base64_encode($user) . "\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '334') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "SMTPユーザー名エラー: {$res}"];
    }

    fwrite($fp, base64_encode($pass) . "\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '235') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "SMTP認証失敗（ユーザー名またはパスワードを確認してください）: {$res}"];
    }

    $envelope_from = filter_var($user, FILTER_VALIDATE_EMAIL) ? $user : $from_email;
    fwrite($fp, "MAIL FROM:<{$envelope_from}>\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '250') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "MAIL FROMエラー: {$res}"];
    }

    fwrite($fp, "RCPT TO:<{$to}>\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '250') !== 0 && strpos($res, '251') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "RCPT TOエラー: {$res}"];
    }

    fwrite($fp, "DATA\r\n");
    $res = smtp_read_response($fp);
    if (strpos($res, '354') !== 0) {
        fclose($fp);
        return ['ok' => false, 'error' => "DATAエラー: {$res}"];
    }

    $encoded_name    = '=?UTF-8?B?' . base64_encode($from_name) . '?=';
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $normalized_body = str_replace(["\r\n", "\r"], "\n", (string)$body);
    $encoded_body    = chunk_split(base64_encode($normalized_body));
    $msg_id          = '<' . bin2hex(random_bytes(12)) . '@' . ($helo_host ?: 'tc-plus') . '>';

    $raw  = "Date: " . date('r') . "\r\n";
    $raw .= "Message-ID: {$msg_id}\r\n";
    $raw .= "From: {$encoded_name} <{$from_email}>\r\n";
    $raw .= "Reply-To: {$from_email}\r\n";
    $raw .= "To: <{$to}>\r\n";
    $raw .= "Subject: {$encoded_subject}\r\n";
    $raw .= "MIME-Version: 1.0\r\n";
    $raw .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $raw .= $enc_body . "\r\n.\r\n";

    fwrite($fp, $raw);
    $res = smtp_read_response($fp);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    if (strpos($res, '250') !== 0) {
        return ['ok' => false, 'error' => "メールデータ送信エラー: {$res}"];
    }

    return ['ok' => true];
}

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list') {
        $safe_sites = [];
        foreach ($config['sites'] as $s) {
            $safe_sites[] = [
                'id'            => $s['id'] ?? '',
                'name'          => $s['name'] ?? '',
                'url'           => $s['url'] ?? '',
                'from_email'    => $s['from_email'] ?? '',
                'from_name'     => $s['from_name'] ?? '',
                'signature'     => $s['signature'] ?? '',
                'smtp_host'     => $s['smtp_host'] ?? '',
                'smtp_port'     => $s['smtp_port'] ?? '465',
                'smtp_user'     => $s['smtp_user'] ?? '',
                'has_smtp_pass' => !empty($s['smtp_pass'])
            ];
        }
        echo json_encode(['success' => true, 'sites' => $safe_sites]);
        exit;
    }

    if ($action === 'save') {
        $id         = trim($_POST['id'] ?? '');
        $name       = trim($_POST['name'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $from_email = trim($_POST['from_email'] ?? '');
        $from_name  = trim($_POST['from_name'] ?? '');
        $signature  = $_POST['signature'] ?? '';
        $smtp_host  = trim($_POST['smtp_host'] ?? '');
        $smtp_port  = trim($_POST['smtp_port'] ?? '465');
        $smtp_user  = trim($_POST['smtp_user'] ?? '');
        $smtp_pass  = (string)($_POST['smtp_pass'] ?? '');

        if ($name === '') {
            throw new Exception('サイト名を入力してください。');
        }
        if ($from_email === '' || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('正しい差出人メールアドレスを入力してください。');
        }
        if ($from_name === '') {
            throw new Exception('差出人名を入力してください。');
        }

        if ($id === '') {
            $new_id = 's_' . bin2hex(random_bytes(4));
            $config['sites'][] = [
                'id'         => $new_id,
                'name'       => $name,
                'url'        => $url,
                'from_email' => $from_email,
                'from_name'  => $from_name,
                'signature'  => $signature,
                'smtp_host'  => $smtp_host,
                'smtp_port'  => $smtp_port !== '' ? $smtp_port : '465',
                'smtp_user'  => $smtp_user,
                'smtp_pass'  => $smtp_pass
            ];
        } else {
            $found = false;
            foreach ($config['sites'] as &$s) {
                if ($s['id'] === $id) {
                    $s['name']       = $name;
                    $s['url']        = $url;
                    $s['from_email'] = $from_email;
                    $s['from_name']  = $from_name;
                    $s['signature']  = $signature;
                    $s['smtp_host']  = $smtp_host;
                    $s['smtp_port']  = $smtp_port !== '' ? $smtp_port : '465';
                    $s['smtp_user']  = $smtp_user;
                    if ($smtp_host === '') {
                        $s['smtp_pass'] = '';
                    } elseif ($smtp_pass !== '') {
                        $s['smtp_pass'] = $smtp_pass;
                    }
                    $found = true;
                    break;
                }
            }
            unset($s);
            if (!$found) {
                throw new Exception('対象のサイトが見つかりません。');
            }
        }

        if (!empty($config['sites'][0]['name'])) {
            $config['site_name'] = $config['sites'][0]['name'];
        }

        if (file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            throw new Exception('設定ファイル（config.json）の書き込みに失敗しました。');
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'test_mail') {
        $id         = trim($_POST['id'] ?? '');
        $test_to    = trim($_POST['test_to'] ?? '');
        $from_email = trim($_POST['from_email'] ?? '');
        $from_name  = trim($_POST['from_name'] ?? 'サポートデスク');
        $smtp_host  = trim($_POST['smtp_host'] ?? '');
        $smtp_port  = trim($_POST['smtp_port'] ?? '465');
        $smtp_user  = trim($_POST['smtp_user'] ?? '');
        $smtp_pass  = (string)($_POST['smtp_pass'] ?? '');

        if (!filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('テスト送信先のメールアドレスを正しく入力してください。');
        }

        if ($smtp_pass === '' && $id !== '') {
            foreach ($config['sites'] as $s) {
                if (($s['id'] ?? '') === $id) {
                    $smtp_pass = $s['smtp_pass'] ?? '';
                    break;
                }
            }
        }

        $subject = "【TC Plus】SMTPテストメール（{$from_name}）";
        $body    = "このメールは TC Plus のサイト管理画面から送信されたテストメールです。\n\n"
                 . "差出人名: {$from_name}\n"
                 . "差出人メール: {$from_email}\n"
                 . "送信方式: " . ($smtp_host !== '' ? "SMTP認証 ({$smtp_host}:{$smtp_port})" : "サーバー標準送信") . "\n\n"
                 . "このメールが受信トレイに届いていれば、メール設定は正常に完了しています。";

        if ($smtp_host !== '') {
            $res = send_via_smtp($test_to, $subject, $body, $from_email, $from_name, [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_user' => $smtp_user,
                'smtp_pass' => $smtp_pass
            ]);
            if (!$res['ok']) {
                throw new Exception($res['error']);
            }
        } else {
            mb_language("Japanese");
            mb_internal_encoding("UTF-8");
            $headers = "From: " . mb_encode_mimeheader($from_name) . " <{$from_email}>";
            if (!mb_send_mail($test_to, $subject, $body, $headers)) {
                throw new Exception('サーバー標準送信に失敗しました。');
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = trim($_POST['id'] ?? '');
        if (count($config['sites']) <= 1) {
            throw new Exception('最低1つのサイトが必要なため削除できません。');
        }

        $config['sites'] = array_values(array_filter($config['sites'], function($s) use ($id) {
            return $s['id'] !== $id;
        }));

        if (!empty($config['sites'][0]['name'])) {
            $config['site_name'] = $config['sites'][0]['name'];
        }

        file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>