<?php
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helper_function.php';
require_once __DIR__ . '/functions/cookie_function.php';
require_once __DIR__ . '/functions/mfa_function.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1段階目認証が成功していない場合はログイン画面に戻す
if (!isset($_SESSION['auth_passed']) || $_SESSION['auth_passed'] !== true) {
    redirect('login.php');
}

// MFAシークレットキーをロード
$mfa_secret = get_mfa_secret();
if (empty($mfa_secret)) {
    // 秘密鍵がない場合はMFAをスキップしてログイン完了
    $auth_config = require AUTH_CONFIG_PATH;
    issue_auth_cookie($auth_config['user']);
    unset($_SESSION['auth_passed']);
    redirect('index.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mfa_code = $_POST['mfa_code'] ?? '';

    if (empty($mfa_code)) {
        $error_message = '認証コードを入力してください。';
    } elseif (!preg_match('/^\d{6}$/', $mfa_code)) {
        $error_message = '認証コードは6桁の数字です。';
    } else {
        // MFAコードの検証
        if (verify_mfa_code($mfa_secret, $mfa_code)) {
            // 認証成功: クッキーを発行し、セッションをクリアしてリダイレクト
            $auth_config = require AUTH_CONFIG_PATH;
            issue_auth_cookie($auth_config['user']); 
            unset($_SESSION['auth_passed']);
            redirect('index.php');
        } else {
            $error_message = '認証コードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二段階認証</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    </style>
</head>
<body class="bg-light">
    
    <div class="container my-5">
    <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7 col-sm-9"> 
    <div class="p-4 border rounded-3 bg-white shadow-sm">
    
        <h2 class="mb-4 text-center">二段階認証コードの入力</h2>
        <p class="text-center text-muted">Google Authenticatorアプリで表示されている6桁のコードを入力してください。</p>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form action="mfa_login.php" method="POST">
            <div class="mb-4">
                <label for="mfa_code" class="form-label">認証コード:</label> 
                <input type="text" id="mfa_code" name="mfa_code" class="form-control form-control-lg text-center" maxlength="6" pattern="\d{6}" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">認証</button>
        </form>
        
    </div>
    </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>