<?php
ob_start();
require_once 'db.php';

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// --------------------------------------------------
// スパム対策用：暗号化・画像認証ヘルパー関数（iframe埋め込み対応・Cookie不要）
// --------------------------------------------------
$captcha_secret = hash('sha256', ($dsn ?? '') . ($user ?? '') . ($password ?? 'tc_plus_salt_2026'));

function generate_captcha_data($secret) {
    // 読み間違えやすい文字（0, O, 1, I, l）を除いた英数字
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 5; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $issued_at = time();
    $payload = $code . '|' . $issued_at;

    // 暗号化（XOR + Base64URL）と改ざん防止署名（HMAC）
    $key_bytes = hash('sha256', $secret . '_enc', true);
    $xored = '';
    for ($i = 0; $i < strlen($payload); $i++) {
        $xored .= $payload[$i] ^ $key_bytes[$i % strlen($key_bytes)];
    }
    $enc = rtrim(strtr(base64_encode($xored), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $enc, $secret);
    $token = $enc . '.' . $sig;

    return ['code' => $code, 'token' => $token];
}

function decode_captcha_token($token, $secret) {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 2) return null;
    [$enc, $sig] = $parts;

    if (!hash_equals(hash_hmac('sha256', $enc, $secret), $sig)) {
        return null;
    }

    $b64 = strtr($enc, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) $b64 .= str_repeat('=', 4 - $pad);
    $xored = base64_decode($b64, true);
    if ($xored === false) return null;

    $key_bytes = hash('sha256', $secret . '_enc', true);
    $payload = '';
    for ($i = 0; $i < strlen($xored); $i++) {
        $payload .= $xored[$i] ^ $key_bytes[$i % strlen($key_bytes)];
    }

    $items = explode('|', $payload);
    if (count($items) !== 2) return null;
    return ['code' => $items[0], 'issued_at' => (int)$items[1]];
}

// 1. 「別の文字にする」ボタン用のJSON APIエンドポイント
if (isset($_GET['captcha_new'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $cap = generate_captcha_data($captcha_secret);
    echo json_encode(['token' => $cap['token']]);
    exit;
}

// 2. 認証画像（PNG）の出力エンドポイント
if (isset($_GET['captcha_img'])) {
    ob_end_clean();
    $decoded = decode_captcha_token($_GET['captcha_img'], $captcha_secret);
    $code = ($decoded && (time() - $decoded['issued_at']) <= 3600) ? $decoded['code'] : 'ERROR';

    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if (function_exists('imagecreatetruecolor')) {
        $width = 200;
        $height = 54;
        $img = imagecreatetruecolor($width, $height);

        // 背景色（淡いブルーグレー）
        $bg = imagecolorallocate($img, 241, 245, 249);
        imagefill($img, 0, 0, $bg);

        // 干渉線（ボットのOCR読み取り防止）
        for ($i = 0; $i < 6; $i++) {
            $line_color = imagecolorallocate($img, random_int(150, 210), random_int(165, 215), random_int(180, 225));
            imageline($img, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $line_color);
        }

        // ノイズドット
        for ($i = 0; $i < 120; $i++) {
            $dot_color = imagecolorallocate($img, random_int(140, 200), random_int(150, 210), random_int(160, 220));
            imagesetpixel($img, random_int(0, $width - 1), random_int(0, $height - 1), $dot_color);
        }

        // 1文字ずつ少し角度・高さをずらして大きく描画
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $char_tmp = imagecreatetruecolor(22, 26);
            imagefill($char_tmp, 0, 0, $bg);
            $text_color = imagecolorallocate($char_tmp, random_int(20, 60), random_int(45, 95), random_int(110, 165));
            // 疑似太字化のため1pxずらして2回描画
            imagestring($char_tmp, 5, 6, 5, $code[$i], $text_color);
            imagestring($char_tmp, 5, 7, 5, $code[$i], $text_color);

            $dst_x = 18 + ($i * 34) + random_int(-3, 3);
            $dst_y = 6 + random_int(-4, 5);
            imagecopyresampled($img, $char_tmp, $dst_x, $dst_y, 0, 0, 34, 42, 22, 26);
            imagedestroy($char_tmp);
        }

        // 枠線
        $border = imagecolorallocate($img, 203, 213, 224);
        imagerectangle($img, 0, 0, $width - 1, $height - 1, $border);

        imagepng($img);
        imagedestroy($img);
    }
    exit;
}

// --------------------------------------------------
// データベースの自動連番（AUTO_INCREMENT）設定のチェック・修復
// --------------------------------------------------
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
} catch (Exception $e) {
    error_log("DB Structure Check Error: " . $e->getMessage());
}

// --------------------------------------------------
// 設定ファイル（config.json）の読み込み
// --------------------------------------------------
$config_file = __DIR__ . '/config.json';
$default_signature = "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------";
$default_config = [
    'from_email' => 'no-reply@cc-circle.net',
    'from_name'  => 'サポートデスク',
    'signature'  => $default_signature,
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
$config = $default_config;
if (file_exists($config_file)) {
    $loaded = json_decode(file_get_contents($config_file), true);
    if (is_array($loaded)) {
        $config = array_merge($default_config, $loaded);
    }
}

// サイトIDの特定とサイト別メール設定の取得
$site_param = trim($_REQUEST['site'] ?? '');
$target_site = $config['sites'][0] ?? [
    'name'       => $config['site_name'] ?? 'メインサイト',
    'from_email' => $config['from_email'] ?? 'no-reply@cc-circle.net',
    'from_name'  => $config['from_name'] ?? 'サポートデスク',
    'signature'  => $config['signature'] ?? $default_signature
];

if ($site_param !== '' && !empty($config['sites']) && is_array($config['sites'])) {
    foreach ($config['sites'] as $s) {
        if (($s['id'] ?? '') === $site_param) {
            $target_site = $s;
            break;
        }
    }
}

$target_site_name  = $target_site['name'] ?? 'メインサイト';
$target_from_email = !empty($target_site['from_email']) ? $target_site['from_email'] : ($config['from_email'] ?? 'no-reply@cc-circle.net');
$target_from_name  = !empty($target_site['from_name'])  ? $target_site['from_name']  : ($config['from_name'] ?? 'サポートデスク');
$target_signature  = isset($target_site['signature'])     ? $target_site['signature']  : ($config['signature'] ?? $default_signature);

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

$is_submitted = false;
$error_message = '';
$ticket_url = '';

// --------------------------------------------------
// フォームが送信されたときの処理（スパム検証 ＋ 新規チケット発行）
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['customer_name'] ?? '');
    $email         = trim($_POST['customer_email'] ?? '');
    $subject       = trim($_POST['subject'] ?? '');
    $body          = trim($_POST['body'] ?? '');
    $honeypot      = trim($_POST['website_url'] ?? '');
    $captcha_token = trim($_POST['captcha_token'] ?? '');
    $captcha_input = strtoupper(trim($_POST['captcha_code'] ?? ''));

    // スパム検証①：ハニーポット（隠しフィールドに値が入っていたらボット判定）
    if ($honeypot !== '') {
        $error_message = '不正な送信が検出されました。';
    } else {
        // スパム検証②：画像認証コード・有効期限・送信速度（2秒未満はボット判定）のチェック
        $decoded = decode_captcha_token($captcha_token, $captcha_secret);
        $now = time();

        if (!$decoded) {
            $error_message = '認証情報の確認に失敗しました。もう一度認証コードを入力してください。';
        } elseif (($now - $decoded['issued_at']) < 2) {
            $error_message = '送信間隔が短すぎます。数秒おいてから再度送信してください。';
        } elseif (($now - $decoded['issued_at']) > 1800) {
            $error_message = '画面の有効期限（30分）が切れました。新しい認証コードを入力してください。';
        } elseif ($captcha_input === '' || !hash_equals($decoded['code'], $captcha_input)) {
            $error_message = '画像に表示されている認証コード（5文字）が一致しません。';
        } elseif ($name === '' || $email === '' || $subject === '' || $body === '') {
            $error_message = 'すべての項目を入力してください。';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = '正しいメールアドレスを入力してください。';
        } else {
            try {
                $pdo->beginTransaction();

                $secret_key = bin2hex(random_bytes(16));

                $columns = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('site_name', $columns)) {
                    $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, site_name, status, is_read) VALUES (?, ?, ?, ?, ?, 11, 0)");
                    $stmt->execute([$secret_key, $name, $email, $subject, $target_site_name]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tickets (secret_key, customer_name, customer_email, subject, status) VALUES (?, ?, ?, ?, 11)");
                    $stmt->execute([$secret_key, $name, $email, $subject]);
                }
                $ticket_id = $pdo->lastInsertId();

                $stmt_msg = $pdo->prepare("INSERT INTO messages (ticket_id, sender_type, body) VALUES (?, 'customer', ?)");
                $stmt_msg->execute([$ticket_id, $body]);

                $pdo->commit();

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $domain = $_SERVER['HTTP_HOST'];
                $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $ticket_url = $protocol . $domain . $dir . "/view.php?id=" . $ticket_id . "&key=" . $secret_key;

                $mail_subject = "【受付完了】お問い合わせありがとうございます（チケットID: #{$ticket_id}）";
                $mail_body = "{$name} 様\n\n"
                           . "お問い合わせを受け付けました。\n"
                           . "担当者より回答いたしますので、今しばらくお待ちください。\n\n"
                           . "■お問い合わせ内容の確認・追加の返信はこちら\n"
                           . "{$ticket_url}\n\n"
                           . "--------------------------------------------------\n"
                           . "件名：{$subject}\n"
                           . "内容：\n{$body}\n"
                           . "--------------------------------------------------\n\n"
                           . $target_signature;

                send_tc_mail($email, $mail_subject, $mail_body, $target_from_email, $target_from_name);

                $is_submitted = true;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = '送信中にエラーが発生しました：' . $e->getMessage();
            }
        }
    }
}

// フォーム表示用の新しい画像認証データを生成
$captcha_data = generate_captcha_data($captcha_secret);
$has_gd = function_exists('imagecreatetruecolor');
$form_action = 'form.php' . ($site_param !== '' ? '?site=' . urlencode($site_param) : '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせフォーム</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", sans-serif; background: #fff; color: #333; margin: 0; padding: 20px; }
        .form-container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 14px; }
        .badge-req { background: #e53e3e; color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 3px; margin-left: 6px; }
        input[type="text"], input[type="email"], textarea {
            width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 15px; box-sizing: border-box;
        }
        input:focus, textarea:focus { outline: none; border-color: #2b6cb0; box-shadow: 0 0 0 2px rgba(43,108,176,0.2); }
        textarea { height: 140px; resize: vertical; }
        
        /* スパム対策：画像認証エリア */
        .captcha-box {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-bottom: 18px;
        }
        .captcha-row {
            display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 8px;
        }
        .captcha-img-wrap {
            display: inline-flex; align-items: center; gap: 8px;
        }
        .captcha-img {
            height: 54px; width: 200px; border-radius: 4px; border: 1px solid #cbd5e0; display: block;
        }
        .captcha-fallback {
            height: 54px; width: 200px; background: #edf2f7; border: 1px solid #cbd5e0; border-radius: 4px;
            display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;
            letter-spacing: 6px; color: #2b6cb0; user-select: none; font-family: monospace;
        }
        .btn-refresh-captcha {
            background: #fff; border: 1px solid #cbd5e0; color: #4a5568; padding: 6px 10px; border-radius: 4px;
            font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-refresh-captcha:hover { background: #edf2f7; }
        .captcha-input {
            width: 160px !important; text-transform: uppercase; letter-spacing: 3px; font-weight: bold; font-size: 16px !important;
        }
        .captcha-note { font-size: 12px; color: #718096; margin-top: 6px; }

        /* ハニーポット（人間には非表示） */
        .hp-field { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; opacity: 0; }

        .btn-submit {
            background: #2b6cb0; color: #fff; border: none; padding: 12px 24px; font-size: 16px; font-weight: bold;
            border-radius: 4px; cursor: pointer; width: 100%; transition: background 0.2s;
        }
        .btn-submit:hover { background: #2c5282; }
        .alert-error { background: #fed7d7; color: #9b2c2c; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; font-weight: bold; }
        .complete-box { background: #ebf8ff; border: 1px solid #90cdf4; padding: 24px; border-radius: 6px; text-align: center; }
        .complete-box h2 { color: #2b6cb0; margin-top: 0; font-size: 20px; }
        .url-box { background: #fff; padding: 12px; border: 1px solid #cbd5e0; border-radius: 4px; word-break: break-all; margin: 16px 0; font-size: 14px; }
        .btn-another { display: inline-block; margin-top: 12px; font-size: 13px; color: #2b6cb0; text-decoration: underline; }
    </style>
</head>
<body>
<div class="form-container">
    <?php if ($is_submitted): ?>
        <div class="complete-box">
            <h2>お問い合わせを受け付けました</h2>
            <p>ご入力いただいたメールアドレス宛に受付完了メールをお送りしました。</p>
            <p style="font-size: 14px; color: #4a5568;">サポートからの返信の確認や、追加のご連絡は以下の専用ページから行えます。</p>
            <div class="url-box">
                <a href="<?php echo htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                    <?php echo htmlspecialchars($ticket_url, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
            <a href="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>" class="btn-another">別のお問い合わせを送る</a>
        </div>
    <?php else: ?>
        <?php if ($error_message): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <input type="hidden" name="site" value="<?php echo htmlspecialchars($site_param, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="captcha_token" id="captcha-token" value="<?php echo htmlspecialchars($captcha_data['token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- スパムボット対策用ダミー入力欄 -->
            <div class="hp-field" aria-hidden="true">
                <label for="website_url">Website</label>
                <input type="text" name="website_url" id="website_url" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label>お名前<span class="badge-req">必須</span></label>
                <input type="text" name="customer_name" required placeholder="例：山田 太郎" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>メールアドレス<span class="badge-req">必須</span></label>
                <input type="email" name="customer_email" required placeholder="例：taro@example.com" value="<?php echo htmlspecialchars($_POST['customer_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>件名<span class="badge-req">必須</span></label>
                <input type="text" name="subject" required placeholder="例：ログイン方法について" value="<?php echo htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>お問い合わせ内容<span class="badge-req">必須</span></label>
                <textarea name="body" required placeholder="お問い合わせ内容をご記入ください"><?php echo htmlspecialchars($_POST['body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <!-- 画像認証（スパム対策） -->
            <div class="captcha-box">
                <label style="margin-bottom:2px;">スパム防止認証<span class="badge-req">必須</span></label>
                <div class="captcha-row">
                    <div class="captcha-img-wrap">
                        <?php if ($has_gd): ?>
                            <img src="form.php?captcha_img=<?php echo urlencode($captcha_data['token']); ?>" alt="認証コード" class="captcha-img" id="captcha-image">
                            <button type="button" class="btn-refresh-captcha" id="btn-refresh-captcha" title="別の文字に変更">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                                <span>別の文字</span>
                            </button>
                        <?php else: ?>
                            <div class="captcha-fallback"><?php echo htmlspecialchars($captcha_data['code'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                    <input type="text" name="captcha_code" id="captcha-code-input" class="captcha-input" maxlength="5" required placeholder="5文字を入力">
                </div>
                <div class="captcha-note">※画像に表示されている5文字の英数字を入力してください（大文字・小文字は区別しません）。</div>
            </div>

            <button type="submit" class="btn-submit">この内容で送信する</button>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('btn-refresh-captcha');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async function() {
            refreshBtn.disabled = true;
            try {
                const res = await fetch('form.php?captcha_new=1', { cache: 'no-store' });
                const data = await res.json();
                if (data.token) {
                    document.getElementById('captcha-token').value = data.token;
                    document.getElementById('captcha-image').src = 'form.php?captcha_img=' + encodeURIComponent(data.token);
                    const input = document.getElementById('captcha-code-input');
                    input.value = '';
                    input.focus();
                }
            } catch (e) {
                console.error(e);
            } finally {
                refreshBtn.disabled = false;
            }
        });
    }
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>