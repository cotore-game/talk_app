<?php
require_once __DIR__ . '/auth_helper.php';
startSecureSession();

$error_message = '';

// 既にログイン済みであればindex.phpへリダイレクト
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンの検証
    // hash_equalsを使用してタイミング攻撃を防ぐ
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = '不正なリクエストです。';
        error_log('CSRF token mismatch on login.php from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    } else {
        if (isset($_POST['password'])) {
            $input_password = $_POST['password'];

            if (hash_equals(COMMON_PASSWORD, $input_password)) {
                $_SESSION['loggedin'] = true;
                // パスワード認証成功後、ユーザー名入力を促すためにindex.phpへリダイレクト
                // index.phpがセッションにusernameがない場合にユーザー名入力フォームを表示する
                header('Location: index.php');
                exit;
            } else {
                $error_message = 'パスワードが間違っています。';
                error_log('Failed login attempt with incorrect password from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
            }
        } else {
            $error_message = 'パスワードが入力されていません。';
        }
    }
}

// CSRFトークンを生成
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>共通パスワードを入力してください</h2>
        <?php if ($error_message): ?>
            <p class="error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="password" name="password" placeholder="共通パスワード" required>
            <button type="submit">認証</button>
        </form>
    </div>
</body>
</html>
