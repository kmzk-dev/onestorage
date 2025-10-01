<?php
// custom_functions.php
// ヘルパー関数群
require_once __DIR__ . '/init_function.php';


// ランダムな15桁 -data-[15桁]でルートフォルダ名を作成
function generate_random_string(int $length = 15): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $char_length = strlen($characters);
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[random_int(0, $char_length - 1)];
    }
    return $random_string;
}

// リダイレクト
function redirect(string $path): void {
    header("Location: {$path}");
    exit;
}
// フォルダとその中身を再帰的に削除する
function delete_directory($dir) { 
    if (!file_exists($dir)) return true; 
    if (!is_dir($dir)) return unlink($dir); 
    foreach (scandir($dir) as $item) { 
        if ($item == '.' || $item == '..') continue; 
        if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) return false; 
    } 
    return rmdir($dir); 
}

// DATA_ROOT以下の全てのディレクトリのウェブパスを再帰的に取得する（移動モーダル用）
function get_all_directories_recursive($dir, &$results = []) { 
    $items = scandir($dir); 
    foreach ($items as $item) { 
        if ($item == '.' || $item == '..') continue; 
        $path = $dir . DIRECTORY_SEPARATOR . $item; 
        if (is_dir($path)) { 
            if (str_starts_with($item, '.')) continue; 
            $web_path = ltrim(str_replace('\\', '/', substr($path, strlen(DATA_ROOT))), '/'); 
            $results[] = $web_path; 
            get_all_directories_recursive($path, $results); 
        } 
    } 
    return $results; 
}

// ディレクトリ構造をツリー形式で取得する（サイドバー表示用）
function get_directory_tree($base_path) {
    $get_web_path = fn($path) => ltrim(str_replace('\\', '/', substr($path, strlen(DATA_ROOT))), '/');
    $build_tree = function($current_path) use (&$build_tree, $get_web_path) {
        $dirs = [];
        $items = array_diff(scandir($current_path), ['.', '..']);
        natsort($items);
        foreach ($items as $item) {
            if (str_starts_with($item, '.')) continue;
            $path = $current_path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $dirs[] = [
                    'name' => $item,
                    'path' => $get_web_path($path),
                    'children' => $build_tree($path)
                ];
            }
        }
        return $dirs;
    };
    // DATA_ROOTはinit.phpで定義されている前提
    return $build_tree(DATA_ROOT);
}

// バイト数を読みやすいMB形式にフォーマットする
function format_bytes($bytes, $precision = 2) {
    if ($bytes === null || !is_numeric($bytes) || $bytes < 0) return '-';
    if ($bytes == 0) return '0 B';
    $megabytes = $bytes / (1024 * 1024);
    return number_format($megabytes, 2) . ' MB';
}