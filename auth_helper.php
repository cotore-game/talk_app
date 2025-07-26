<?php
require_once __DIR__ . '/config.php';

// セッションを開始または再開
function startSecureSession() {
    // セッションが既に開始されている場合は何もしない
    if (session_status() === PHP_SESSION_NONE) {
        session_start();

        if (!isset($_SESSION['last_session_regenerate_time']) ||
            time() - $_SESSION['last_session_regenerate_time'] > SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true); // 古いセッションIDを削除し、新しいものを生成
            $_SESSION['last_session_regenerate_time'] = time();
        }
    }
    // アクティビティ時間を更新
    $_SESSION['last_activity'] = time();
}

// ユーザーがログインしているか確認する
function isLoggedIn() {
    // セッションが存在し、loggedinフラグがtrue、かつusernameが設定されていることを確認
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['username']);
}

// ユーザー名をセッションから取得し、XSS対策を施す
function getSessionUsername() {
    return isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') : '';
}

// ログイン中のユーザー情報をJSONファイルから読み込む
function getLoggedInUsers() {
    $users = [];
    if (file_exists(LOGGED_IN_USERS_FILE)) {
        $fileContent = file_get_contents(LOGGED_IN_USERS_FILE);
        if ($fileContent !== false) {
            // ファイルが空でないか、有効なJSONであるか確認
            if (trim($fileContent) === '') { // ファイルが空の場合
                return [];
            }
            $decodedContent = json_decode($fileContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                $users = $decodedContent;
            } else {
                error_log('Invalid JSON or not an array in ' . LOGGED_IN_USERS_FILE . '. JSON Error: ' . json_last_error_msg());
            }
        } else {
            error_log('Failed to read ' . LOGGED_IN_USERS_FILE);
        }
    } else {
        // ファイルが存在しない場合は初回作成に備えて空配列を返す
        return [];
    }
    return $users;
}

// ログイン中のユーザー情報をJSONファイルに書き込む
function saveLoggedInUsers($users) {
    // ファイルロックを使用して書き込み中の競合を避ける
    if (file_put_contents(LOGGED_IN_USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        error_log('Failed to write to ' . LOGGED_IN_USERS_FILE . '. Check file permissions.');
        return false;
    }
    return true;
}

// ユーザーをログインユーザーリストに追加
function addLoggedInUser($username) {
    $users = getLoggedInUsers();
    $sessionId = session_id(); // 現在のセッションIDを取得

    // 既存のユーザーエントリを検索し、存在すればセッションIDと最終活動時刻を更新
    $found = false;
    foreach ($users as $key => $userEntry) {
        if ($userEntry['username'] === $username) {
            $users[$key]['session_id'] = $sessionId; // セッションIDを更新
            $users[$key]['last_activity'] = time(); // 最終活動時刻を更新
            $found = true;
            break;
        }
    }

    // 新しいユーザーの場合、追加
    if (!$found) {
        $users[] = [
            'username' => $username,
            'session_id' => $sessionId,
            'login_time' => time(),
            'last_activity' => time()
        ];
    }
    return saveLoggedInUsers($users);
}

// ユーザーをログインユーザーリストから削除
function removeLoggedInUser($usernameToRemove) {
    $users = getLoggedInUsers();
    $newUsers = [];
    $removed = false;
    foreach ($users as $userEntry) {
        // 現在のセッションIDに紐づくユーザーのみを削除
        if ($userEntry['username'] === $usernameToRemove && $userEntry['session_id'] === session_id()) {
            $removed = true;
            continue; // このユーザーエントリはスキップ
        }
        $newUsers[] = $userEntry;
    }
    if ($removed) {
        return saveLoggedInUsers($newUsers);
    }
    return true; // 変更がなければtrue
}

// タイムアウトしたセッションのユーザーをリストから削除
function cleanupExpiredSessions($timeout = SESSION_REGENERATE_INTERVAL) {
    $users = getLoggedInUsers();
    $activeUsers = [];
    $currentTime = time();
    $changed = false;

    foreach ($users as $userEntry) {
        if (isset($userEntry['last_activity']) && ($currentTime - $userEntry['last_activity']) < $timeout) {
            $activeUsers[] = $userEntry;
        } else {
            $changed = true; // 期限切れユーザーが見つかった
        }
    }
    if ($changed) {
        return saveLoggedInUsers($activeUsers);
    }
    return true; // 変更がなければtrue
}

// CSRFトークンを生成し、セッションに保存
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRFトークンを検証
function verifyCsrfToken($token) {
    // トークンが存在し、かつセッションのトークンと一致するか
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}
