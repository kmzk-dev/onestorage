<?php
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}
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
        if (defined('INBOX_DIR_NAME') && $item == INBOX_DIR_NAME) continue;
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
            if (defined('INBOX_DIR_NAME') && $item == INBOX_DIR_NAME) continue;
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

/**
 * 現在の接続がHTTPSかどうかを判定する
 * @return bool HTTPSならtrue
 */
function is_https(): bool {
    // サーバー変数に基づく標準的なチェック
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    // ロードバランサやプロキシ経由の場合のチェック
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}


/**
 * スター設定ファイルパスを取得する
 * DATA_ROOTが定義されていることが前提
 * @return string スター設定ファイルパス
 */
function get_star_config_path(): string {
    if (!defined('DATA_ROOT') || !defined('STAR_CONFIG_FILENAME')) {
        error_log('DATA_ROOT or STAR_CONFIG_FILENAME is not defined.');
        return '';
    }
    return DATA_ROOT . DIRECTORY_SEPARATOR . STAR_CONFIG_FILENAME;
}

/**
 * スター設定ファイルを読み込む
 * @return array スター登録されたアイテムのリスト
 */
function load_star_config(): array {
    $path = get_star_config_path();
    if (empty($path) || !file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * スター設定ファイルを保存する
 * @param array $data スター登録されたアイテムのリスト
 * @return bool 成功ならtrue
 */
function save_star_config(array $data): bool {
    $path = get_star_config_path();
    if (empty($path)) return false;
    
    // itemsをソートする
    usort($data, fn($a, $b) => strcmp($a['path'] . $a['name'], $b['path'] . $b['name']));
    
    $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $content) !== false;
}

/**
 * スターアイテムのパス（web_path + item_name）のハッシュを取得
 * @param string $web_path アイテムがあるディレクトリのウェブパス
 * @param string $item_name アイテム名
 * @return string ハッシュキー
 */
function get_item_hash(string $web_path, string $item_name): string {
    $full_path = ltrim($web_path . '/' . $item_name, '/');
    return md5($full_path);
}

/**
 * スターアイテムの登録/解除を行う
 * @param string $web_path アイテムがあるディレクトリのウェブパス
 * @param string $item_name アイテム名
 * @param bool $is_dir ディレクトリかファイルか
 * @return array 成功フラグ、アクション（added/removed）、メッセージ
 */
function toggle_star_item(string $web_path, string $item_name, bool $is_dir): array {
    global $DATA_ROOT; // DATA_ROOTが定義されていることを期待
    
    $current_stars = load_star_config();
    $item_hash = get_item_hash($web_path, $item_name);
    $is_starred = false;
    
    // アイテムの存在チェックと容量取得
    $is_inbox_item = (defined('INBOX_DIR_NAME') && $web_path === 'inbox');
    if ($is_inbox_item) {
        // INBOXアイテムの場合、物理的な隠しディレクトリパスを使用
        $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $item_name);
    } else {
        // 通常のアイテムの場合
        $full_path = realpath(DATA_ROOT . '/' . ltrim($web_path . '/' . $item_name, '/'));
    }
    if ($full_path === false || strpos($full_path, DATA_ROOT) !== 0 || str_starts_with($item_name, '.')) {
        return ['success' => false, 'message' => '無効なアイテムです。'];
    }
    
    $size = $is_dir ? get_directory_size($full_path) : filesize($full_path);

    // ハッシュに基づいてアイテムを検索し、存在する場合は削除
    $new_stars = [];
    foreach ($current_stars as $star) {
        if (get_item_hash($star['path'], $star['name']) !== $item_hash) {
            $new_stars[] = $star;
        } else {
            $is_starred = true;
        }
    }

    if ($is_starred) {
        // 解除
        if (save_star_config($new_stars)) {
            return ['success' => true, 'action' => 'removed', 'message' => htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . ' のスターを解除しました。'];
        }
    } else {
        // 登録
        $new_item = [
            'hash' => $item_hash,
            'path' => $web_path,
            'name' => $item_name,
            'is_dir' => $is_dir,
            'size' => $size,
        ];
        $new_stars[] = $new_item;
        if (save_star_config($new_stars)) {
            return ['success' => true, 'action' => 'added', 'message' => htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . ' をスターに登録しました。'];
        }
    }
    
    return ['success' => false, 'message' => 'スター設定の保存に失敗しました。'];
}

/**
 * INBOXの絶対パスを取得する
 * DATA_ROOTが定義されていることが前提
 * @return string INBOXの絶対パス
 */
function get_inbox_path(): string {
    if (!defined('DATA_ROOT') || !defined('INBOX_DIR_NAME')) {
        error_log('DATA_ROOT or INBOX_DIR_NAME is not defined.');
        return '';
    }
    $inbox_path = DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME;
    // 存在しない場合は作成を試みる
    if (!is_dir($inbox_path)) {
         // 権限は777は推奨されないが、既存のコードに合わせて0777
        if (!mkdir($inbox_path, 0777, true)) {
            error_log('Failed to create INBOX directory: ' . $inbox_path);
        }
    }
    return $inbox_path;
}

/**
 * ウェブパスがINBOXビューのパスであるかを判定する
 * @param string $web_path 現在のウェブパス（index.phpで判定される 'inbox' など）
 * @return bool
 */
function is_inbox_view(string $web_path): bool {
    return $web_path === 'inbox';
}

/**
 * ウェブパスがルートディレクトリを示すかを判定する
 * @param string $web_path 現在のウェブパス
 * @return bool
 */
function is_root_view(string $web_path): bool {
    // web_pathが空文字列（?path=なしまたは?path=）の場合
    return empty($web_path);
}


/**
 * スターアイテムの情報を更新する（移動、リネーム時）
 * @param string $old_web_path 変更前のディレクトリのウェブパス
 * @param string $old_item_name 変更前のアイテム名
 * @param string $new_web_path 変更後のディレクトリのウェブパス
 * @param string $new_item_name 変更後のアイテム名
 * @return bool 成功ならtrue
 */
function update_star_item(string $old_web_path, string $old_item_name, string $new_web_path, string $new_item_name): bool {
    global $DATA_ROOT;
    
    $old_hash = get_item_hash($old_web_path, $old_item_name);
    $current_stars = load_star_config();
    $updated = false;

    foreach ($current_stars as &$star) {
        if ($star['hash'] === $old_hash) {
            $star['path'] = $new_web_path;
            $star['name'] = $new_item_name;
            // ハッシュを再計算
            $star['hash'] = get_item_hash($new_web_path, $new_item_name);
            
            // サイズを再取得
            $is_inbox_item = (defined('INBOX_DIR_NAME') && $new_web_path === 'inbox');
            if ($is_inbox_item) {
                // INBOXアイテムの場合、物理的な隠しディレクトリパスを使用
                $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $new_item_name);
            } else {
                // 通常のアイテムの場合
                $item_path_from_root = ltrim($new_web_path . '/' . $new_item_name, '/');
                $full_path = realpath(DATA_ROOT . '/' . $item_path_from_root);
            }

            if ($full_path !== false) {
                // is_dirの情報は変更されない前提だが、念のため再チェック
                $star['size'] = is_dir($full_path) ? get_directory_size($full_path) : filesize($full_path);
            }
            
            $updated = true;
            break;
        }
    }
    unset($star); // 参照を解除

    if ($updated) {
        return save_star_config($current_stars);
    }

    return false; // スター登録されていなかった
}

/**
 * スターアイテムの登録を解除する（アイテム削除時）
 * @param string $web_path アイテムがあるディレクトリのウェブパス
 * @param string $item_name アイテム名
 * @return bool 成功ならtrue（アイテムが存在しなかった場合もtrueとする）
 */
function remove_star_item(string $web_path, string $item_name): bool {
    $item_hash = get_item_hash($web_path, $item_name);
    $current_stars = load_star_config();
    $removed = false;

    $new_stars = [];
    foreach ($current_stars as $star) {
        if ($star['hash'] !== $item_hash) {
            $new_stars[] = $star;
        } else {
            $removed = true;
        }
    }

    // 削除対象が存在しなかった、またはリストに変更がなかった場合はtrue
    if (!$removed) {
        return true;
    }

    return save_star_config($new_stars);
}