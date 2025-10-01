<?php
// init.php
// アプリケーションの動作に必要な設定をロードし、定数・変数を定義する。
require_once __DIR__ . '/../path.php';

// セッション
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// DATA_ROOT
if (!file_exists(MAIN_CONFIG_PATH)) { 
    die('データフォルダの設定ファイル(config.php)が見つかりません。'); 
}
$main_config = require MAIN_CONFIG_PATH;
if (!isset($main_config['data_root'])) {
    die('設定ファイル(config.php)に必須の\'data_root\'が定義されていません。');
}
define('DATA_ROOT', $main_config['data_root']);

// アップロードするファイル種別
$file_config = [
    'allowed_extensions' => [],
    'max_file_size_mb' => 50
];
if (file_exists(ACCEPT_CONFIG_PATH)) {
    $json_content = file_get_contents(ACCEPT_CONFIG_PATH);
    $loaded_config = json_decode($json_content, true);
    if ($loaded_config) {
        $file_config = array_merge($file_config, $loaded_config);
    }
}
$allowed_ext_list = array_map(fn($ext) => '.' . trim($ext, '.'), $file_config['allowed_extensions']);
$accept_attribute = implode(',', $allowed_ext_list);

//