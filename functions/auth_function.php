<?php
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/cookie_function.php';

if (!defined('AUTH_CONFIG_PATH') || !file_exists(AUTH_CONFIG_PATH)) {
    redirect('setting.php');
}
// MFAキーの存在チェックを追加
if (!defined('MFA_SECRET_PATH') || !file_exists(MFA_SECRET_PATH)) {
    redirect('setting.php');
}

function check_authentication() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 1. 永続的なクッキー認証をチェック
    if (validate_auth_cookie()) { // 修正
        return true;
    } 
    
    // 2. 1段階目認証済みセッションをチェック（MFA待機状態）
    if (isset($_SESSION['auth_passed']) && $_SESSION['auth_passed'] === true) {
         // クッキーがないが、セッションに1段階目認証フラグがある場合は、MFA認証画面へ戻す
        redirect('mfa_login.php');
        exit;
    }

    // 3. どちらもなければログイン画面へリダイレクト
    // 念のためセッションもクリア（フラッシュメッセージ用セッションのクリーンアップ）
    $_SESSION = [];
    session_destroy();

    redirect('login.php');
    exit;
}