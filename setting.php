<?php
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helper_function.php';

if (file_exists(AUTH_CONFIG_PATH) && file_exists(MAIN_CONFIG_PATH)) {
    redirect('login.php');
}

$error_messages = []; // 返却するエラー表示の格納・エラー状態の検証

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $password = $_POST['password'] ?? '';

    // --バリデーション処理
    if (empty($user)) {
        $error_messages[] = 'メールアドレスを入力してください。';
    } elseif (!filter_var($user, FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = 'メールアドレスの形式が正しくありません。';
    }

    if (empty($password)) {
        $error_messages[] = 'パスワードを入力してください。';
    } else {
        if (strlen($password) < 15) {
            $error_messages[] = 'パスワードは15桁以上で設定してください。';
        }
        if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).*$/', $password)) {
            $error_messages[] = 'パスワードには大文字英字、小文字英字、数字をすべて含めてください。';
        }
    }

    // 設定ファイルを自動作成
    if (empty($error_messages)) {
        $htaccess_content = "Order allow,deny\nDeny from all";
        // configの構築
        $config_dir = dirname(AUTH_CONFIG_PATH);
        if (!is_dir($config_dir)) {
            mkdir($config_dir, 0755, true);
        }
        if (is_dir($config_dir) && !file_put_contents($config_dir . DIRECTORY_SEPARATOR . '.htaccess', $htaccess_content)) {
            $error_messages[] = 'configディレクトリ内の.htaccess作成に失敗しました。';
        }
        // データフォルダの構築
        if (!file_exists(MAIN_CONFIG_PATH)) {
            $random_part = generate_random_string(15);
            $data_dir_name = 'data-' . $random_part;
            $data_dir_path = __DIR__ . '/' . $data_dir_name;
            if (!mkdir($data_dir_path, 0777, true)) {
                $error_messages[] = 'データフォルダの作成に失敗しました。ディレクトリの書き込み権限を確認してください。';
            } else {
                if (!file_put_contents($data_dir_path . DIRECTORY_SEPARATOR . '.htaccess', $htaccess_content)) {
                    $error_messages[] = 'データフォルダ内の.htaccess作成に失敗しました。';
                }
                // config.phpを登録
                $main_config_content = "<?php\n\n";
                $main_config_content .= "// 自動生成されたデータフォルダのパス\n";
                $main_config_content .= "return ['data_root' => '" . addslashes($data_dir_path) . "'];\n";
                if (!file_put_contents(MAIN_CONFIG_PATH, $main_config_content)) {
                    $error_messages[] = 'メイン設定ファイル(config.php)の作成に失敗しました。';
                }
            }

        }
        // 認証情報の構築 - 上記処理でエラーが出ていない場合のみ実行
        if (empty($error_messages) && !file_exists(AUTH_CONFIG_PATH)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $auth_config_content = "<?php\n\n";
            $auth_config_content .= "return [\n";
            $auth_config_content .= "    'user' => '" . addslashes($user) . "',\n";
            $auth_config_content .= "    'hash' => '" . addslashes($hash) . "',\n";
            $auth_config_content .= "];\n";
            if (!file_put_contents(AUTH_CONFIG_PATH, $auth_config_content)) {
                $error_messages[] = '認証設定ファイル(auth.php)の作成に失敗しました。';
            }
        }
        // 全ての処理が成功 > セッションを保持してリダイレクト
        if (empty($error_messages)) {
            $_SESSION['authenticated'] = true;
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初期設定</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .label-container { display: flex; align-items: center; margin-bottom: 4px; }
        .info-icon { color: #777; margin-left: 8px; }
    </style>
</head>
<body class="bg-light">
    
    <div class="container my-5">
    <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
    <div class="p-4 border rounded-3 bg-white shadow-sm">
    
        <h2 class="mb-4">管理者 初期設定</h2>
        <p>システムを初めて利用する場合は新たに管理者登録が必要です。データストアを削除した場合は設定済みの管理者情報を入力するか、更新してください。</p>

        <?php if (!empty($error_messages)): ?>
            <div class="alert alert-danger" role="alert">
                <ul>
                    <?php foreach ($error_messages as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="setting.php" method="POST" novalidate>
            <div class="mb-3">
                <div class="label-container">
                    <label for="user" class="form-label">メールアドレス:</label>
                    <i class="fa-solid fa-circle-info info-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="管理者としてログインするためのメールアドレスを設定します。"></i>
                </div>
                <input type="text" id="user" name="user" class="form-control" value="<?php echo htmlspecialchars($_POST['user'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-4">
                <div class="label-container">
                    <label for="password" class="form-label">パスワード:</label>
                    <i class="fa-solid fa-circle-info info-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="英大文字・英小文字・数字を含む15桁以上で設定してください"></i>
                </div>
                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">設定を保存</button>
        </form>
    
    </div>
    </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    </script>
</body>
</html>