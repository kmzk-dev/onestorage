<?php
// custom_functions.php
// ヘルパー関数群
//require_once __DIR__ . '/init_function.php';


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

/**
 * ディレクトリ構造のキャッシュを再構築する
 * @return array 再構築されたキャッシュデータ
 */
function rebuild_dir_cache(): array {
    $tree = get_directory_tree(DATA_ROOT);
    $list = get_all_directories_recursive(DATA_ROOT);
    sort($list);

    $cache_data = [
        'tree' => $tree,
        'list' => $list
    ];

    $cache_file_path = DATA_ROOT . DIRECTORY_SEPARATOR . DIR_CACHE_PATH;
    file_put_contents($cache_file_path, json_encode($cache_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $cache_data;
}

/**
 * ディレクトリ構造のキャッシュを読み込む
 * @return array キャッシュデータ
 */
function load_dir_cache(): array {
    $cache_file_path = DATA_ROOT . DIRECTORY_SEPARATOR . DIR_CACHE_PATH;

    if (file_exists($cache_file_path)) {
        $cache_content = file_get_contents($cache_file_path);
        $cache_data = json_decode($cache_content, true);
        if (is_array($cache_data) && isset($cache_data['tree']) && isset($cache_data['list'])) {
            return $cache_data;
        }
    }
    // ファイルが存在しない、または内容が不正な場合は再構築
    return rebuild_dir_cache();
}

// バイト数を読みやすいMB形式にフォーマットする
function format_bytes($bytes, $precision = 2) {
    if ($bytes === null || !is_numeric($bytes) || $bytes < 0) return '-';
    if ($bytes == 0) return '0 B';
    $megabytes = $bytes / (1024 * 1024);
    return number_format($megabytes, 2) . ' MB';
}

/* ディレクトリの合計サイズを再帰的に取得する
 * @param string $dir ディレクトリのパス
 * @return int 合計サイズ（バイト）
 */
function get_directory_size(string $dir): int {
    // ディレクトリが読み取り可能かチェック
    if (!is_readable($dir)) {
        return 0;
    }

    $size = 0;
    $items = scandir($dir);

    // scandirが失敗した場合（パーミッションエラーなど）
    if ($items === false) {
        return 0;
    }

    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;

        // アイテムが読み取り不可の場合はスキップ
        if (!is_readable($path)) continue;

        if (is_dir($path)) {
            $size += get_directory_size($path); //TODO: サブフォルダも再帰的に計算:オーバースペックの可能性があるので要考慮
        } else {
            $size += filesize($path);
        }
    }
    return $size;
}

/**
 * 古いチャンクファイルをクリーンアップする (24時間以上経過)
 * @return int 削除したファイル数
 */
function cleanup_stale_chunks(): int {
    $temp_dir = DATA_ROOT . DIRECTORY_SEPARATOR . '.temp_chunks';
    if (!is_dir($temp_dir)) {
        return 0;
    }

    $deleted_count = 0;
    $one_day_ago = time() - (24 * 60 * 60);

    foreach (scandir($temp_dir) as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess') {
            continue;
        }

        $file_path = $temp_dir . DIRECTORY_SEPARATOR . $file;
        if (filemtime($file_path) < $one_day_ago) {
            if (unlink($file_path)) {
                $deleted_count++;
            }
        }
    }
    return $deleted_count;
}