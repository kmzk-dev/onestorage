<?php
require_once __DIR__ . '/../path.php';
//require_once __DIR__ . '/init_function.php'; // DATA_ROOT, STAR_CONFIG_FILENAME の定義
require_once __DIR__ . '/helper_function.php';
require_once __DIR__ . '/cookie_function.php'; // 認証チェック用
// 認証チェック (必須)
require_once __DIR__ . '/auth_function.php'; // auth_function.php を読み込む

// validate_auth_cookie() は認証に成功すれば true を返します。
if (!validate_auth_cookie()) {
     // リダイレクトさせず、認証失敗をJSONで返す
     http_response_code(401);
     echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
     exit; // ★ここで確実に処理を停止させる
}


// ヘッダーを設定してJSONレスポンスであることを明示
header('Content-Type: application/json');

// --- API処理の開始 ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// 認証チェック
if (!validate_auth_cookie()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
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
        // JSから送信される '1' or '0' を bool に変換
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