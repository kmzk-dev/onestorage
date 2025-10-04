<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/init.php'; // $file_config を利用するために読み込む

function handle_chunk_upload(string $target_dir_path): void {
    global $file_config;

    cleanup_stale_chunks();

    $chunk = $_FILES['chunk'] ?? null;
    $original_name = $_POST['original_name'] ?? '';
    $chunk_index = (int)($_POST['chunk_index'] ?? -1);
    $total_chunks = (int)($_POST['total_chunks'] ?? -1);
    $total_size = (int)($_POST['total_size'] ?? 0);

    $response = ['type' => 'danger', 'text' => '不明なエラーが発生しました。'];

    if (!$chunk || $chunk['error'] !== UPLOAD_ERR_OK || empty($original_name) || $chunk_index < 0 || $total_chunks <= 0) {
        $response['text'] = '不正なリクエストです。';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 一時フォルダの準備
    $temp_dir = DATA_ROOT . DIRECTORY_SEPARATOR . '.temp_chunks';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
        $htaccess_content = "Order allow,deny\nDeny from all";
        file_put_contents($temp_dir . DIRECTORY_SEPARATOR . '.htaccess', $htaccess_content);
    }

    // 一時ファイル名の生成 (セッションIDとファイル名でユニークにする)
    $temp_file_name = session_id() . '_' . md5($original_name) . '.part';
    $temp_file_path = $temp_dir . DIRECTORY_SEPARATOR . $temp_file_name;

    // チャンクを追記
    if (file_put_contents($temp_file_path, file_get_contents($chunk['tmp_name']), FILE_APPEND) === false) {
        $response['text'] = '一時ファイルへの書き込みに失敗しました。';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 最終チャンクの処理
    if ($chunk_index === $total_chunks - 1) {
        // サイズ検証
        $max_size_bytes = $file_config['max_file_size_mb'] * 1024 * 1024;
        if (filesize($temp_file_path) !== $total_size || $total_size > $max_size_bytes) {
            unlink($temp_file_path);
            $response['text'] = 'ファイルサイズが不正か、上限を超えています。(' . $file_config['max_file_size_mb'] . 'MB)';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $final_path = $target_dir_path . DIRECTORY_SEPARATOR . $original_name;

        if (file_exists($final_path)) {
            unlink($temp_file_path);
            $response = ['type' => 'warning', 'text' => '同名のファイルが既に存在します: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        } elseif (rename($temp_file_path, $final_path)) {
            $response = ['type' => 'success', 'text' => 'ファイルをアップロードしました: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        } else {
            unlink($temp_file_path);
            $response['text'] = 'ファイルの移動に失敗しました。';
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['type' => 'processing', 'text' => 'Chunk ' . ($chunk_index + 1) . '/' . $total_chunks . ' processed.']);
    exit;
}