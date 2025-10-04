<?php

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';

// --- GETリクエスト処理 ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $file_path_raw = $_GET['path'] ?? '';
    $file_path = realpath(DATA_ROOT . '/' . $file_path_raw);


    if (defined('INBOX_DIR_NAME') && str_starts_with($file_path_raw, 'inbox/')) {
        // 'inbox/filename.ext' を '.inbox/filename.ext' に変換
        $internal_path = INBOX_DIR_NAME . '/' . substr($file_path_raw, 6);
        $file_path = realpath(DATA_ROOT . '/' . $internal_path);
    } else {
        $file_path = realpath(DATA_ROOT . '/' . $file_path_raw);
    }

    // セキュリティチェック:存在性,ディレクトリトラバーサル,ファイル種別（ブラウザの対応）
    if ($file_path && strpos($file_path, DATA_ROOT) === 0 && !is_dir($file_path)) {
        $file_name = basename($file_path);

        if ($action === 'view') {
            $mime_types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'txt' => 'text/plain; charset=utf-8', 'html' => 'text/html; charset=utf-8'];
            $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $content_type = $mime_types[$extension] ?? 'application/octet-stream';
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . rawurlencode($file_name) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } elseif ($action === 'download') {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"'); // ダウンロードを強制
            readfile($file_path);
            exit;
        }
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => '無効なファイルです。'];
        redirect('index.php');
    }
}
