<?php
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helper_function.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 認証情報の検証
if (!file_exists(AUTH_CONFIG_PATH)) { redirect('setting.php'); }
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    redirect('index.php');
}

$error_message = ''; //　エラー返却の格納

// バリデーション
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_input = $_POST['user'] ?? '';
    $password_input = $_POST['password'] ?? '';

    if (empty($user_input) || empty($password_input)) {
        $error_message = 'メールアドレスとパスワードを入力してください。';
    } else {
        $config = require AUTH_CONFIG_PATH;
        if ($user_input === $config['user'] && password_verify($password_input, $config['hash'])) {
            $_SESSION['authenticated'] = true;
            redirect('index.php');
        } else {
            $error_message = 'メールアドレスまたはパスワードが間違っています。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    </style>
</head>
<body class="bg-light">
    
    <div class="container my-5">
    <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7 col-sm-9"> 
    <div class="p-4 border rounded-3 bg-white shadow-sm">
    
        <h2 class="mb-4 text-center">ログイン</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="user" class="form-label">メールアドレス:</label> 
                <input type="text" id="user" name="user" class="form-control" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">パスワード:</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">ログイン</button>
        </form>
        
    </div>
    </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>