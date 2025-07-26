<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/config.php';
startSecureSession();

$username_error = '';

// ログイン済みでない場合はログインページへリダイレクト
// ただし、パスワード認証は通っているがユーザー名が未設定の場合は、ユーザー名入力フォームを表示する
if (!isLoggedIn()) {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && !isset($_SESSION['username'])) {
        // ユーザー名入力フォームを表示するために、このページに留まる
    } else {
        header('Location: login.php');
        exit;
    }
}

// ユーザー名が設定されていない場合の処理
if (!isset($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
        // CSRFトークンの検証
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $username_error = '不正なリクエストです。';
            error_log('CSRF token mismatch on index.php username input for IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
        } else {
            $username = trim($_POST['username']);
            // ユーザー名のバリデーション
            if (!empty($username)) {
                // ユーザー名をセッションに保存する前にXSS対策
                $_SESSION['username'] = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
                // ログイン中のユーザーリストに追加
                addLoggedInUser($_SESSION['username']); 
                header('Location: index.php'); // 再度リダイレクトして、通常の掲示板表示へ
                exit;
            } else {
                $username_error = 'ユーザー名を入力してください。';
            }
        }
    }
    // ユーザー名入力フォームのCSRFトークンを生成
    $csrf_token_for_username_form = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー名入力</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container auth-container">
        <h2>ユーザー名を入力してください</h2>
        <?php if ($username_error): ?>
            <p class="error"><?php echo htmlspecialchars($username_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form action="index.php" method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token_for_username_form, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" name="username" placeholder="あなたのユーザー名" required maxlength="50">
            <button type="submit">入室</button>
        </form>
    </div>
</body>
</html>
<?php
    exit; // ユーザー名入力フォームを表示したら処理を終了
}

// ユーザー情報が設定されている場合
$username = getSessionUsername(); // XSS対策済みのユーザー名を取得
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>簡易リアルタイム掲示板</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="sidebar">
        <h2>こんにちは、<?php echo $username; ?>さん！</h2>
        <h3>現在のログイン人数: <span id="user-count">0</span>人</h3>
        <div id="user-list">
            <h4>ユーザー一覧</h4>
            <ul>
                </ul>
        </div>
        <button class="logout-button" onclick="location.href='logout.php'">ログアウト</button>
    </div>

    <div class="main-content">
        <h1>簡易リアルタイム掲示板</h1>
        <div id="chat-board">
            </div>
        <form id="message-form" class="message-form">
            <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" id="message-input" placeholder="メッセージを入力..." autocomplete="off" required maxlength="500">
            <input type="password" id="trip-password-input" placeholder="トリップパスワード (任意)" autocomplete="new-password" maxlength="32">
            <button type="submit">送信</button>
        </form>
    </div>

    <script src="script.js"></script>
</body>
</html>
