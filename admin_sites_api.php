<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$config_file = __DIR__ . '/config.json';
$default_config = [
    'from_email' => 'no-reply@cc-circle.net',
    'from_name'  => 'サポートデスク',
    'signature'  => "--------------------------------------------------\nサポートデスク\n※このメールは送信専用アドレスから配信されています。\n--------------------------------------------------",
    'agents'     => ['担当者A', '担当者B', 'サポート窓口'],
    'categories' => ['一般問い合わせ', '操作方法', '不具合・エラー', '契約・お支払い', 'その他'],
    'site_name'  => 'メインサイト',
    'sites'      => [
        ['id' => 'default', 'name' => 'メインサイト', 'url' => '']
    ]
];

if (!file_exists($config_file)) {
    file_put_contents($config_file, json_encode($default_config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
$loaded = json_decode(file_get_contents($config_file), true);
$config = array_merge($default_config, is_array($loaded) ? $loaded : []);

// 既存のconfig.jsonにsitesがない場合は初期サイトを自動生成
if (empty($config['sites']) || !is_array($config['sites'])) {
    $initial_name = !empty($config['site_name']) ? $config['site_name'] : 'メインサイト';
    $config['sites'] = [
        ['id' => 'default', 'name' => $initial_name, 'url' => '']
    ];
    file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list') {
        echo json_encode(['success' => true, 'sites' => $config['sites']]);
        exit;
    }

    if ($action === 'save') {
        $id   = trim($_POST['id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $url  = trim($_POST['url'] ?? '');

        if ($name === '') {
            throw new Exception('サイト名を入力してください。');
        }

        if ($id === '') {
            // 新規追加（ランダムな短いサイト識別IDを発行）
            $new_id = 's_' . bin2hex(random_bytes(4));
            $config['sites'][] = [
                'id'   => $new_id,
                'name' => $name,
                'url'  => $url
            ];
        } else {
            // 既存サイトの編集
            $found = false;
            foreach ($config['sites'] as &$s) {
                if ($s['id'] === $id) {
                    $s['name'] = $name;
                    $s['url']  = $url;
                    $found = true;
                    break;
                }
            }
            unset($s);
            if (!$found) {
                throw new Exception('対象のサイトが見つかりません。');
            }
        }

        // 先頭のサイト名をデフォルトsite_nameにも同期
        if (!empty($config['sites'][0]['name'])) {
            $config['site_name'] = $config['sites'][0]['name'];
        }

        if (file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            throw new Exception('設定ファイル（config.json）の書き込みに失敗しました。');
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