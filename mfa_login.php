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
        .otp-inputs {
            gap: 5px;
            /* マスとマスの間のスペース */
            max-width: 300px;
            margin: 0 auto;
        }

        .otp-input {
            width: 45px;
            /* マスのサイズ */
            height: 55px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid #ced4da;
            border-radius: 6px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .otp-input:focus {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .otp-separator {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 300;
            color: #6c757d;
            padding: 0 5px;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-9">
                <div class="p-4 border rounded-3 bg-white shadow-sm">

                    <p class="h3 py-5 text-center">二段階認証コードの入力</p>
                    <p class="text-center text-muted">認証アプリに表示されている6桁のコードを入力してください。</p>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form action="mfa_login.php" method="POST" id="mfaForm">
                        <div class="mb-5">
                            <div class="d-flex justify-content-center otp-inputs">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required autofocus data-index="0">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="1">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="2">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="3">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="4">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="5">
                                <input type="hidden" name="mfa_code" id="mfa_code_combined">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">認証</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputs = document.querySelectorAll('.otp-input');
        const combinedInput = document.getElementById('mfa_code_combined');
        const form = document.getElementById('mfaForm');
        const submitBtn = document.getElementById('submitBtn');

        // フォームの自動送信処理
        function tryAutoSubmit() {
            const code = Array.from(inputs).map(input => input.value).join('');
            // 6桁の数字が揃っているかチェック
            if (code.length === 6 && /^\d{6}$/.test(code)) {
                combinedInput.value = code;
                form.submit();
            }
        }
        
        // 入力時のフォーカス移動と自動送信ロジック
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                // 入力が数字1桁であるかを確認
                if (e.data && /^\d$/.test(e.data) && input.value.length === 1) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus(); // 次のマスへ移動
                    } else {
                        // 最後のマスに入力完了 -> 自動送信を試みる
                        tryAutoSubmit();
                    }
                }
            });

            // Backspaceで前のマスに戻るロジック
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value.length === 0 && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            // ペースト時の処理 (6桁の数字をペーストした場合)
            input.addEventListener('paste', (e) => {
                const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                if (/^\d{6}$/.test(pasteData)) {
                    e.preventDefault();
                    pasteData.split('').forEach((char, i) => {
                        if (inputs[i]) {
                            inputs[i].value = char;
                        }
                    });
                    // ペースト完了 -> 自動送信を試みる
                    tryAutoSubmit();
                }
            });
        });

        // ボタンは隠さないが、自動送信を優先するため、ボタンは手動クリック時にのみ処理を実行するようにする
        form.addEventListener('submit', (e) => {
             // 自動送信に失敗した場合や、Enterキーなどで送信された場合のフォールバック処理
             const code = Array.from(inputs).map(input => input.value).join('');
             if (code.length !== 6 || !/^\d{6}$/.test(code)) {
                 e.preventDefault();
                 alert('認証コードを6桁すべて入力してください。');
             } else {
                 combinedInput.value = code;
             }
        });
    });
</script>
</body>

</html>
</body>

</html>