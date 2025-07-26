<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/config.php';
startSecureSession();

header('Content-Type: application/json');

// ログイン状態をチェック
// ユーザーリストの取得はログインユーザーのみに限定
if (!isLoggedIn()) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

// 期限切れセッションのクリーンアップを実行
// SESSION_REGENERATE_INTERVALの2倍の時間をタイムアウトとして設定
cleanupExpiredSessions(SESSION_REGENERATE_INTERVAL * 2);

$loggedInUsersData = getLoggedInUsers();
$activeUsernames = [];

// ログイン中のユーザーデータからユーザー名のみを抽出し、XSS対策を施す
foreach ($loggedInUsersData as $userEntry) {
    $activeUsernames[] = htmlspecialchars($userEntry['username'], ENT_QUOTES, 'UTF-8');
}

// 重複を排除 (念のため)
$activeUsernames = array_values(array_unique($activeUsernames));

// ログイン中のユーザー数とユーザー名のリストをJSON形式で返す
echo json_encode(['count' => count($activeUsernames), 'users' => $activeUsernames]);
