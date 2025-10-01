<?php
require_once __DIR__ . '/../path.php'; 
require_once __DIR__ . '/cookie_function.php';

if (!defined('AUTH_CONFIG_PATH') || !file_exists(AUTH_CONFIG_PATH)) {
    redirect('setting.php');
}

function check_authentication() {
    // クッキー認証に置き換え
    if (validate_auth_cookie()) { // 修正
        return true;
    } else {
        // 念のためセッションもクリア（フラッシュメッセージ用セッションのクリーンアップ）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        session_destroy();

        redirect('login.php');
        exit; 
    }
}