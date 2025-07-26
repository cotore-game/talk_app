<?php
require_once __DIR__ . '/auth_helper.php';
startSecureSession();

if (isset($_SESSION['username'])) {
    $usernameToLogOut = $_SESSION['username'];
    removeLoggedInUser($usernameToLogOut); // ログインユーザーリストから削除
}

// セッション変数を全て解除
$_SESSION = array();

// クッキーを削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破壊
session_destroy();

// ログインページへリダイレクト
header('Location: login.php');
exit;
?>
