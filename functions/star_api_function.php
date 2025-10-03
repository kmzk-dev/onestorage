<?php
require_once __DIR__ . '/../path.php';
// require_once __DIR__ . '/init_function.php'; // ★リダイレクトの原因となるため、このAPIでは読み込みを削除

require_once __DIR__ . '/helper_function.php';
require_once __DIR__ . '/cookie_function.php';
require_once __DIR__ . '/auth_function.php';

// --- DATA_ROOTの定義とシステム設定の簡易チェック (init_function.phpのリダイレクトを避ける) ---
if (!file_exists(MAIN_CONFIG_PATH)) { 
    // config.phpがない場合はAPIとしてエラーを返す
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'システム設定ファイル(config.php)がありません。']);
    exit;
}
$main_config = require MAIN_CONFIG_PATH;
if (!isset($main_config['data_root'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'config.phpにDATA_ROOTが定義されていません。']);
    exit;
}
// DATA_ROOTを定義
define('DATA_ROOT', $main_config['data_root']);
// -----------------------------------------------------------------------------------------


// --- 認証チェック ---
// 冗長な認証チェックを削除し、こちら一つに集約します
if (!validate_auth_cookie()) {
     // 認証失敗の場合はJSONエラーを返し、確実に終了
     http_response_code(401);
     echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
     exit; 
}

// ヘッダーを設定してJSONレスポンスであることを明示
header('Content-Type: application/json');

// --- API処理の開始 ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$data = $input['data'] ?? [];
$response = ['success' => false, 'message' => 'Unknown action.'];

switch ($action) {
    case 'toggle_star':
        $web_path = $data['web_path'] ?? '';
        $item_name = $data['item_name'] ?? '';
        $is_dir = filter_var($data['is_dir'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($item_name)) {
            $response['message'] = 'アイテム名が指定されていません。';
        } else {
            $result = toggle_star_item($web_path, $item_name, $is_dir);
            $response = ['success' => $result['success'], 'message' => $result['message'], 'action' => $result['action'] ?? ''];
        }
        break;
}

echo json_encode($response);
exit;