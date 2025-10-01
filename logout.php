<?php
require_once __DIR__ . '/functions/helper_function.php';
require_once __DIR__ . '/functions/cookie_function.php'; // 追加

// 認証クッキーを削除
clear_auth_cookie(); // 修正

// フラッシュメッセージのためセッションの開始と破棄は維持
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();

redirect('index.php');