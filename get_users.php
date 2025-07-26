<?php
require_once __DIR__ . '/auth_helper.php';
startSecureSession();

header('Content-Type: application/json');

// ログインチェック
// ユーザーリストの取得もログインユーザーに限定
if (!isLoggedIn()) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

// 期限切れセッションのクリーンアップを実行
cleanupExpiredSessions(SESSION_REGENERATE_INTERVAL * 2);

$loggedInUsersData = getLoggedInUsers();
$activeUsernames = [];

foreach ($loggedInUsersData as $userEntry) {
    // ここでは、usernameのみをクライアントに返す
    $activeUsernames[] = htmlspecialchars($userEntry['username'], ENT_QUOTES, 'UTF-8');
}

// 重複を排除 (念のため)
$activeUsernames = array_values(array_unique($activeUsernames));

echo json_encode(['count' => count($activeUsernames), 'users' => $activeUsernames]);
?>